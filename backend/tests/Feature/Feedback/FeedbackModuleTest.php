<?php

namespace Tests\Feature\Feedback;

use App\Jobs\RequestFeedbackJob;
use App\Models\Feedback;
use App\Models\Order;
use App\Models\User;
use App\Support\DocumentType;
use App\Support\ReferenceCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The Feedback & Reviews module: token-gated client submission (one write per
 * token), the consent-gated publish lifecycle, the cached public testimonial
 * wall, and the two admin create modes. See docs/global/FEEDBACK-MODULE.md.
 */
class FeedbackModuleTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(): array
    {
        $founder = User::factory()->founder()->create();
        $token = $founder->createToken('admin-spa', ['cockpit'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    private function makeFeedback(array $overrides = []): Feedback
    {
        return Feedback::create([
            'reference_code' => ReferenceCodeGenerator::generate(DocumentType::Feedback),
            'public_token' => Feedback::mintToken(),
            'name' => 'Aina Client',
            'email' => 'aina@example.com',
            'project_label' => 'Roofly.my engagement',
            ...$overrides,
        ]);
    }

    // ── Public token page ────────────────────────────────────────────────

    public function test_unknown_token_404s_and_known_token_returns_the_shell(): void
    {
        $this->getJson('/api/v1/feedback/'.str_repeat('x', 48))->assertNotFound();

        $feedback = $this->makeFeedback();

        $this->getJson("/api/v1/feedback/{$feedback->public_token}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Aina Client')
            ->assertJsonPath('data.project_label', 'Roofly.my engagement')
            ->assertJsonPath('data.already_submitted', false);
    }

    public function test_client_submit_stores_scores_and_blocks_a_second_submit(): void
    {
        $feedback = $this->makeFeedback();

        $this->postJson("/api/v1/feedback/{$feedback->public_token}", [
            'overall' => 5,
            'rating_design' => 5,
            'rating_delivery' => 4,
            'nps' => 9,
            'praise' => 'Fast, precise, and the UI is gorgeous.',
            'publish_consent' => true,
            'attribution_name' => 'Aina R.',
            'attribution_role' => 'Founder, Roofly',
        ])->assertCreated();

        $feedback->refresh();
        $this->assertNotNull($feedback->submitted_at);
        $this->assertSame('pending', $feedback->status);        // nothing auto-publishes
        $this->assertSame(5, $feedback->overall);
        $this->assertSame(4.5, $feedback->average_rating);      // mean of the 2 non-null dims
        $this->assertSame('promoter', $feedback->nps_bucket);
        $this->assertTrue($feedback->publish_consent);

        // Second write on the same token is refused.
        $this->postJson("/api/v1/feedback/{$feedback->public_token}", ['overall' => 1])
            ->assertStatus(409);
        $this->assertSame(5, $feedback->fresh()->overall);
    }

    public function test_overall_is_required_and_consent_gates_attribution(): void
    {
        $feedback = $this->makeFeedback();

        $this->postJson("/api/v1/feedback/{$feedback->public_token}", [
            'nps' => 8,
        ])->assertUnprocessable()->assertJsonValidationErrors('overall');

        $this->postJson("/api/v1/feedback/{$feedback->public_token}", [
            'overall' => 4,
            'publish_consent' => true,       // consent without a name to publish under
        ])->assertUnprocessable()->assertJsonValidationErrors('attribution_name');

        // No consent → attribution stays optional.
        $this->postJson("/api/v1/feedback/{$feedback->public_token}", [
            'overall' => 4,
            'publish_consent' => false,
        ])->assertCreated();
    }

    // ── Publish gating ───────────────────────────────────────────────────

    public function test_publishing_without_consent_is_rejected_on_both_paths(): void
    {
        $headers = $this->adminHeaders();
        $feedback = $this->makeFeedback(['overall' => 5, 'publish_consent' => false]);

        $this->postJson("/api/v1/admin/feedback/{$feedback->id}/status", [
            'status' => 'published',
        ], $headers)->assertUnprocessable();

        $this->putJson("/api/v1/admin/feedback/{$feedback->id}", [
            'status' => 'published',
        ], $headers)->assertUnprocessable();

        $this->assertSame('pending', $feedback->fresh()->status);
    }

    public function test_publishing_with_consent_stamps_published_at_once(): void
    {
        $headers = $this->adminHeaders();
        $feedback = $this->makeFeedback([
            'overall' => 5, 'publish_consent' => true, 'attribution_name' => 'Aina R.',
        ]);

        $this->postJson("/api/v1/admin/feedback/{$feedback->id}/status", [
            'status' => 'approved',
        ], $headers)->assertOk();

        $this->postJson("/api/v1/admin/feedback/{$feedback->id}/status", [
            'status' => 'published',
        ], $headers)->assertOk();

        $feedback->refresh();
        $this->assertSame('published', $feedback->status);
        $this->assertNotNull($feedback->published_at);

        // Archive + republish keeps the original publication timestamp.
        $stamp = $feedback->published_at;
        $this->postJson("/api/v1/admin/feedback/{$feedback->id}/status", ['status' => 'archived'], $headers)->assertOk();
        $this->postJson("/api/v1/admin/feedback/{$feedback->id}/status", ['status' => 'published'], $headers)->assertOk();
        $this->assertTrue($stamp->equalTo($feedback->fresh()->published_at));
    }

    // ── Public testimonial wall ──────────────────────────────────────────

    public function test_testimonials_returns_only_published_and_consented_in_wall_order(): void
    {
        // Noise: pending, and published-without-consent (shouldn't be reachable
        // through the API, but the feed must filter it regardless).
        $this->makeFeedback(['overall' => 5]);
        $this->makeFeedback(['overall' => 5, 'status' => 'published', 'publish_consent' => false]);

        $second = $this->makeFeedback([
            'overall' => 4, 'status' => 'published', 'publish_consent' => true,
            'attribution_name' => 'B. Tan', 'sort_order' => 2, 'published_at' => now()->subDay(),
            'praise' => 'Solid work.',
        ]);
        $featured = $this->makeFeedback([
            'overall' => 5, 'status' => 'published', 'publish_consent' => true,
            'attribution_name' => 'Aina R.', 'featured' => true, 'sort_order' => 5,
            'published_at' => now()->subWeek(), 'praise' => 'Gorgeous UI.',
        ]);

        $res = $this->getJson('/api/v1/testimonials')->assertOk()->json('data');

        $this->assertCount(2, $res);
        $this->assertSame('Aina R.', $res[0]['attribution_name']);   // featured pins first
        $this->assertSame('B. Tan', $res[1]['attribution_name']);
        // Wall-safe fields only — no email, token, or improve text.
        $this->assertSame(
            ['attribution_name', 'attribution_role', 'project_label', 'overall', 'praise'],
            array_keys($res[0]),
        );

        // Observer invalidation: unpublishing drops the row on the next read.
        $featured->update(['status' => 'archived']);
        $this->assertCount(1, $this->getJson('/api/v1/testimonials')->json('data'));
        $second->delete();
        $this->assertCount(0, $this->getJson('/api/v1/testimonials')->json('data'));
    }

    // ── Admin create modes ───────────────────────────────────────────────

    public function test_request_mode_snapshots_the_order_client_and_queues_the_email(): void
    {
        Queue::fake();
        $headers = $this->adminHeaders();
        $order = Order::factory()->create();

        $res = $this->postJson('/api/v1/admin/feedback', [
            'mode' => 'request',
            'order_id' => $order->id,
            'project_label' => 'Dashboard build',
        ], $headers)->assertCreated();

        $feedback = Feedback::findOrFail($res->json('data.id'));
        $this->assertMatchesRegularExpression('/^AXNF-\d{4}-\d{4}$/', $feedback->reference_code);
        $this->assertSame(48, strlen($feedback->public_token));
        $this->assertSame($order->client_id, $feedback->client_id);
        $this->assertSame($order->client->email, $feedback->email);
        $this->assertSame('admin', $feedback->source);
        $this->assertSame('pending', $feedback->status);
        $this->assertNull($feedback->submitted_at);              // waits for the client
        Queue::assertPushed(RequestFeedbackJob::class);

        // One feedback per order.
        $this->postJson('/api/v1/admin/feedback', [
            'mode' => 'request', 'order_id' => $order->id,
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors('order_id');

        // Request mode requires an order to anchor to.
        $this->postJson('/api/v1/admin/feedback', ['mode' => 'request'], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('order_id');
    }

    public function test_log_mode_records_offline_feedback_as_submitted(): void
    {
        Queue::fake();
        $headers = $this->adminHeaders();

        $res = $this->postJson('/api/v1/admin/feedback', [
            'mode' => 'log',
            'name' => 'Walk-in Client',
            'overall' => 4,
            'nps' => 7,
            'praise' => 'Told me over WhatsApp the site doubled their leads.',
        ], $headers)->assertCreated();

        $feedback = Feedback::findOrFail($res->json('data.id'));
        $this->assertSame('admin', $feedback->source);
        $this->assertNotNull($feedback->submitted_at);           // born submitted
        $this->assertNull($feedback->order_id);                  // standalone is fine
        $this->assertSame('passive', $feedback->nps_bucket);
        Queue::assertNotPushed(RequestFeedbackJob::class);

        // Log mode without an overall score is refused.
        $this->postJson('/api/v1/admin/feedback', ['mode' => 'log'], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('overall');
    }

    public function test_first_admin_open_stamps_reviewed_at(): void
    {
        $headers = $this->adminHeaders();
        $feedback = $this->makeFeedback(['overall' => 5]);
        $this->assertNull($feedback->reviewed_at);

        $this->getJson("/api/v1/admin/feedback/{$feedback->id}", $headers)->assertOk();
        $stamp = $feedback->fresh()->reviewed_at;
        $this->assertNotNull($stamp);

        // Second open keeps the original stamp.
        $this->getJson("/api/v1/admin/feedback/{$feedback->id}", $headers)->assertOk();
        $this->assertTrue($stamp->equalTo($feedback->fresh()->reviewed_at));
    }

    public function test_reference_codes_are_sequential_per_year(): void
    {
        $a = $this->makeFeedback();
        $b = $this->makeFeedback();

        $year = now()->year;
        $this->assertSame("AXNF-{$year}-0001", $a->reference_code);
        $this->assertSame("AXNF-{$year}-0002", $b->reference_code);
    }
}
