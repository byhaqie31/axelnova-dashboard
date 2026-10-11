<?php

namespace Tests\Feature\Quotations;

use App\Jobs\SendClientQuoteEmail;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\PricingConfig;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Quoting\RevisionLocked;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * §2–§3 of the revisions spec: "Revise" opens a new draft revision (change
 * reason, one open revision at a time), and accepting it SUPERSEDES the earlier
 * accepted revision atomically — the order keeps its id and re-points
 * (acceptance tests 2 and 3, statuses half; money lives in the ledger tests).
 */
class QuotationRevisionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;

    protected function setUp(): void
    {
        parent::setUp();

        PricingConfig::factory()->create([
            'config' => ['currency' => 'MYR', 'valid_for_days' => 30, 'rush_multiplier' => 1.20,
                'base_packages' => [], 'modifiers' => [], 'addons' => []],
        ]);
        $this->founder = User::factory()->founder()->create();
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.$this->founder->createToken('admin-spa', ['cockpit'])->plainTextToken];
    }

    private function document(float $total, array $plan = []): array
    {
        return array_merge([
            'layout' => 'detailed',
            'payload' => ['project' => 'Sistem Bengkel', 'sections' => [[
                'title' => 'Scope', 'rows' => [['title' => 'All', 'price' => $total]], 'total' => $total,
            ]]],
        ], $plan);
    }

    /** AXNQ-2026-0017's R1: RM 14,340 instalment plan, accepted into an order. */
    private function acceptedFamily(): Quotation
    {
        $id = $this->postJson('/api/v1/admin/quotations', [
            'name' => 'M Automobile', 'email' => 'm-auto@example.com',
            'document' => $this->document(14340, [
                'deposit_amount_myr' => 2700, 'payment_plan' => 'instalment',
                'instalment_months' => 12, 'instalment_amount_myr' => 970, 'includes_care_plan' => true,
            ]),
        ], $this->headers())->assertCreated()->json('data.id');
        $this->postJson("/api/v1/admin/quotations/{$id}/accept", [], $this->headers())->assertOk();

        return Quotation::findOrFail($id);
    }

    private function revise(Quotation $quotation, string $reason = 'Client switched to the 40/40/20 plan'): TestResponse
    {
        return $this->postJson("/api/v1/admin/quotations/{$quotation->id}/revisions", ['change_reason' => $reason], $this->headers());
    }

    public function test_revise_opens_a_draft_r2_cloned_from_the_accepted_r1(): void
    {
        $quotation = $this->acceptedFamily();
        $r1 = $quotation->revision();

        $this->revise($quotation)
            ->assertCreated()
            ->assertJsonPath('data.revision.number', 2)
            ->assertJsonPath('data.revision.status', 'draft')
            ->assertJsonPath('data.revision.replaces', 1)
            ->assertJsonPath('data.revision.change_reason', 'Client switched to the 40/40/20 plan')
            ->assertJsonPath('data.display_reference', $quotation->reference_code.' · R2')
            ->assertJsonPath('data.family_status', 'accepted');

        $r2 = $quotation->revisions()->where('number', 2)->firstOrFail();
        $this->assertEquals($r1->document, $r2->document);
        $this->assertNotSame($r1->public_token, $r2->public_token);
        $this->assertNull($r2->sent_at);

        // R1 stays the accepted, current revision until R2 is accepted.
        $quotation->refresh();
        $this->assertSame('accepted', $quotation->status);
        $this->assertSame($r1->id, $quotation->current_revision_id);
        $this->assertSame('accepted', $r1->fresh()->status);
    }

    public function test_a_change_reason_is_required(): void
    {
        $quotation = $this->acceptedFamily();

        $this->postJson("/api/v1/admin/quotations/{$quotation->id}/revisions", [], $this->headers())
            ->assertStatus(422)->assertJsonValidationErrors(['change_reason']);
        $this->revise($quotation, '   ')->assertStatus(422);
    }

    public function test_only_one_open_revision_at_a_time(): void
    {
        $quotation = $this->acceptedFamily();
        $this->revise($quotation)->assertCreated();

        $this->revise($quotation, 'Another change')
            ->assertStatus(422)
            ->assertJsonPath('message', "R2 of {$quotation->reference_code} is still draft — edit, send or discard it before opening another revision.");
        $this->assertSame(2, $quotation->revisions()->count());
    }

    public function test_an_accepted_revision_cannot_be_edited(): void
    {
        $quotation = $this->acceptedFamily();

        $this->putJson("/api/v1/admin/quotations/{$quotation->id}", [
            'name' => 'M Automobile', 'email' => 'm-auto@example.com', 'document' => $this->document(10800),
        ], $this->headers())->assertStatus(422)->assertJsonPath('message', 'Only a draft revision can be edited. Use Revise to open a new revision.');

        $this->expectException(RevisionLocked::class);
        $quotation->update(['estimate_max_myr' => 10800]);
    }

    public function test_editing_the_draft_r2_leaves_the_accepted_r1_and_the_family_untouched(): void
    {
        $quotation = $this->acceptedFamily();
        $this->revise($quotation)->assertCreated();

        $this->putJson("/api/v1/admin/quotations/{$quotation->id}", [
            'name' => 'M Automobile', 'email' => 'm-auto@example.com', 'document' => $this->document(10800),
        ], $this->headers())->assertOk()
            ->assertJsonPath('data.revision.number', 2)
            ->assertJsonPath('data.estimate_max_myr', '10800.00');

        $quotation->refresh();
        $this->assertSame(14340.0, $quotation->finalAmount(), 'the family still mirrors the accepted R1');
        $this->assertSame(14340.0, $quotation->revision()->asQuotation()->finalAmount());
        $this->assertSame(10800.0, $quotation->draftRevision()->asQuotation()->finalAmount());

        $this->getJson("/api/v1/admin/quotations/{$quotation->id}?revision=1", $this->headers())
            ->assertOk()->assertJsonPath('data.estimate_max_myr', '14340.00')->assertJsonPath('data.status', 'accepted');
        $this->getJson("/api/v1/admin/quotations/{$quotation->id}", $this->headers())
            ->assertOk()->assertJsonPath('data.revision.number', 2)->assertJsonCount(2, 'data.revisions');
    }

    public function test_accepting_r2_supersedes_r1_and_the_order_keeps_its_id(): void
    {
        $quotation = $this->acceptedFamily();
        $order = $quotation->order;
        $this->revise($quotation)->assertCreated();
        $this->putJson("/api/v1/admin/quotations/{$quotation->id}", [
            'name' => 'M Automobile', 'email' => 'm-auto@example.com', 'document' => $this->document(10800),
        ], $this->headers())->assertOk();

        $this->postJson("/api/v1/admin/quotations/{$quotation->id}/accept", [], $this->headers())
            ->assertOk()
            ->assertJsonPath('order_id', $order->id)
            ->assertJsonPath('order_number', $order->order_number)
            ->assertJsonPath('revision', 2);

        [$r1, $r2] = $quotation->revisions()->get()->all();
        $this->assertSame('superseded', $r1->status);
        $this->assertNotNull($r1->superseded_at);
        $this->assertSame('accepted', $r2->status);
        $this->assertNotNull($r2->accepted_at);

        $quotation->refresh();
        $this->assertSame('accepted', $quotation->status);
        $this->assertSame($r2->id, $quotation->current_revision_id);
        $this->assertSame(10800.0, $quotation->finalAmount());

        $this->assertSame(1, Order::count());
        $order->refresh();
        $this->assertSame($r2->id, $order->current_revision_id);
        $this->assertSame(10800.0, (float) $order->final_amount_myr);
        $this->assertNull($order->payment_plan, 'R2 is a lump sum — no instalment snapshot');
        $this->assertNotNull($order->revised_at);

        $log = ActivityLog::where('action', 'order.revised')->where('subject_id', $order->id)->firstOrFail();
        $this->assertEquals(['from_revision' => 1, 'to_revision' => 2, 'change_reason' => 'Client switched to the 40/40/20 plan'], $log->changes);
        $this->assertSame($this->founder->id, $log->actor_id);
    }

    public function test_the_supersede_is_atomic(): void
    {
        $quotation = $this->acceptedFamily();
        $this->revise($quotation)->assertCreated();

        Order::updating(fn () => throw new \RuntimeException('order write failed'));
        try {
            $this->withoutExceptionHandling()->postJson("/api/v1/admin/quotations/{$quotation->id}/accept", [], $this->headers());
            $this->fail('the accept should have thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('order write failed', $e->getMessage());
        }

        [$r1, $r2] = $quotation->revisions()->get()->all();
        $this->assertSame('accepted', $r1->status);
        $this->assertNull($r1->superseded_at);
        $this->assertSame('draft', $r2->status);
        $this->assertSame($r1->id, $quotation->fresh()->current_revision_id);
    }

    public function test_revising_a_sent_revision_discards_it_and_discarding_the_draft_restores_it(): void
    {
        $quotation = Quotation::factory()->create(['public_token' => 'tok']);
        $this->postJson("/api/v1/admin/quotations/{$quotation->id}/send", ['email' => false], $this->headers())->assertOk();

        $this->revise($quotation, 'Typo in the scope')->assertCreated();
        $this->assertSame('discarded', $quotation->revisions()->where('number', 1)->value('status'));
        $quotation->refresh();
        $this->assertSame('sent', $quotation->status, 'a revision already went out');
        $this->assertSame(2, $quotation->revision()->number);

        $this->postJson("/api/v1/admin/quotations/{$quotation->id}/revisions/2/discard", [], $this->headers())
            ->assertOk()->assertJsonPath('data.revision.number', 1);
        $this->assertSame('sent', $quotation->revisions()->where('number', 1)->value('status'));
        $this->assertSame('discarded', $quotation->revisions()->where('number', 2)->value('status'));
        $this->assertSame(1, $quotation->fresh()->revision()->number);
    }

    public function test_r1_cannot_be_discarded_and_a_stale_revision_cannot_be_accepted(): void
    {
        $quotation = Quotation::factory()->create();
        $this->postJson("/api/v1/admin/quotations/{$quotation->id}/revisions/1/discard", [], $this->headers())->assertStatus(422);

        $accepted = $this->acceptedFamily();
        $this->revise($accepted)->assertCreated();
        $this->postJson("/api/v1/admin/quotations/{$accepted->id}/accept", ['revision' => 1], $this->headers())->assertStatus(422);
    }

    public function test_no_revision_once_the_order_is_completed_or_cancelled(): void
    {
        $quotation = $this->acceptedFamily();
        $quotation->order->update(['status' => 'completed']);

        $this->revise($quotation)->assertStatus(422)
            ->assertJsonPath('message', "Order {$quotation->order->order_number} is completed — it can't be revised. Start a new quotation instead.");
    }

    public function test_status_endpoint_only_rejects_and_never_reopens_in_place(): void
    {
        $quotation = Quotation::factory()->create();
        $this->postJson("/api/v1/admin/quotations/{$quotation->id}/send", ['email' => false], $this->headers());

        $this->postJson("/api/v1/admin/quotations/{$quotation->id}/status", ['status' => 'draft'], $this->headers())
            ->assertStatus(422);
        $this->postJson("/api/v1/admin/quotations/{$quotation->id}/status", ['status' => 'rejected'], $this->headers())
            ->assertOk();
        $this->assertSame('rejected', $quotation->fresh()->status);
        $this->assertSame('rejected', $quotation->fresh()->revision()->status);

        $accepted = $this->acceptedFamily();
        $this->postJson("/api/v1/admin/quotations/{$accepted->id}/status", ['status' => 'rejected'], $this->headers())
            ->assertStatus(422);
    }

    public function test_sending_r2_emails_its_own_revision(): void
    {
        Queue::fake();
        $quotation = $this->acceptedFamily();
        $this->revise($quotation)->assertCreated();

        $this->postJson("/api/v1/admin/quotations/{$quotation->id}/send", [], $this->headers())->assertOk()
            ->assertJsonPath('data.revision.status', 'sent');

        $r2 = $quotation->revisions()->where('number', 2)->firstOrFail();
        Queue::assertPushed(SendClientQuoteEmail::class, fn ($job) => $job->uniqueId() === "{$quotation->id}:{$r2->id}");
        $this->getJson("/api/v1/documents/{$r2->public_token}")->assertOk()->assertJsonPath('number', $quotation->reference_code);
    }
}
