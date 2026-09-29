<?php

namespace Tests\Feature\Inquiries;

use App\Mail\InquiryAdminNotificationMail;
use App\Mail\InquiryReceivedMail;
use App\Models\Client;
use App\Models\Inquiry;
use App\Models\PricingConfig;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * /quote and /contact both post to POST /v1/inquiries — one pipeline, told apart
 * by `origin`. Every submit queues an acknowledgement to the sender plus a
 * heads-up to the admin inbox. No client row is created until the admin quotes
 * the inquiry; the quotation's client is then stamped back onto it.
 */
class PublicInquiryIntakeTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(): array
    {
        $token = User::factory()->founder()->create()->createToken('admin-spa', ['cockpit'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    private function contactPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Aina Rahman',
            'email' => 'aina@example.com',
            'origin' => 'contact',
            'subject' => 'General question',
            'message' => 'Do you take on maintenance work for existing sites?',
        ], $overrides);
    }

    public function test_quote_form_inquiry_defaults_to_quote_origin_and_creates_no_client(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/inquiries', [
            'name' => 'Acme Sdn Bhd',
            'email' => 'acme@example.com',
            'project_type' => 'Website',
            'message' => 'We need a new marketing site by Q4.',
        ])->assertCreated();

        $this->assertDatabaseHas('inquiries', [
            'email' => 'acme@example.com', 'origin' => 'quote', 'subject' => null, 'client_id' => null,
        ]);
        $this->assertSame(0, Client::count());
    }

    public function test_contact_form_inquiry_stores_origin_and_subject(): void
    {
        Mail::fake();

        $res = $this->postJson('/api/v1/inquiries', $this->contactPayload())->assertCreated();

        $inquiry = Inquiry::findOrFail($res->json('data.id'));
        $this->assertSame('contact', $inquiry->origin);
        $this->assertSame('General question', $inquiry->subject);
        $this->assertSame('new', $inquiry->status);
        $this->assertNull($inquiry->client_id);
        $this->assertSame(0, Client::count());
    }

    public function test_every_submit_queues_an_auto_reply_and_an_admin_notification(): void
    {
        Mail::fake();
        config(['services.admin.email' => 'baihaqie@axelnova.tech']);

        $this->postJson('/api/v1/inquiries', $this->contactPayload())->assertCreated();

        Mail::assertQueued(InquiryReceivedMail::class, fn ($m) => $m->hasTo('aina@example.com'));
        Mail::assertQueued(InquiryAdminNotificationMail::class, function ($m) {
            return $m->hasTo('baihaqie@axelnova.tech') && $m->hasReplyTo('aina@example.com');
        });
    }

    public function test_unknown_origin_is_rejected(): void
    {
        $this->postJson('/api/v1/inquiries', $this->contactPayload(['origin' => 'popup']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('origin');
    }

    public function test_auto_reply_only_promises_a_quote_for_project_intent(): void
    {
        $general = Inquiry::create($this->contactPayload() + ['status' => 'new']);
        $project = Inquiry::create($this->contactPayload(['subject' => 'Project inquiry']) + ['status' => 'new']);

        $this->assertStringNotContainsString('tailored quote', (new InquiryReceivedMail($general))->render());
        $this->assertStringContainsString('tailored quote', (new InquiryReceivedMail($project))->render());
    }

    public function test_admin_notification_renders_the_message_and_subject(): void
    {
        $inquiry = Inquiry::create($this->contactPayload() + ['status' => 'new']);

        $html = (new InquiryAdminNotificationMail($inquiry))->render();

        $this->assertStringContainsString('General question', $html);
        $this->assertStringContainsString('maintenance work', $html);
        $this->assertStringContainsString("/admin/inquiries/{$inquiry->id}", $html);
    }

    public function test_building_a_quotation_from_an_inquiry_creates_and_links_the_client(): void
    {
        PricingConfig::factory()->create([
            'config' => ['currency' => 'MYR', 'valid_for_days' => 30, 'rush_multiplier' => 1.2,
                'base_packages' => [], 'modifiers' => [], 'addons' => []],
        ]);
        Cache::flush();
        $inquiry = Inquiry::create($this->contactPayload(['subject' => 'Project inquiry']) + ['status' => 'reviewing']);

        $res = $this->postJson('/api/v1/admin/quotations', [
            'name' => $inquiry->name,
            'email' => $inquiry->email,
            'inquiry_id' => $inquiry->id,
            'document' => ['layout' => 'detailed'], // package-free, keeps the fixture minimal
        ], $this->adminHeaders())->assertCreated();

        $client = Client::where('email', 'aina@example.com')->firstOrFail();
        $quotation = Quotation::where('reference_code', $res->json('data.reference_code'))->firstOrFail();

        $this->assertDatabaseHas('inquiries', [
            'id' => $inquiry->id, 'status' => 'quoted',
            'quotation_id' => $quotation->id, 'client_id' => $client->id,
        ]);
    }

    public function test_linking_an_existing_quotation_adopts_its_client(): void
    {
        $client = Client::factory()->create();
        $quotation = Quotation::factory()->create(['client_id' => $client->id]);
        $inquiry = Inquiry::create($this->contactPayload() + ['status' => 'reviewing']);

        $this->postJson("/api/v1/admin/inquiries/{$inquiry->id}/quotation", [
            'quotation_id' => $quotation->id,
        ], $this->adminHeaders())->assertOk();

        $this->assertDatabaseHas('inquiries', [
            'id' => $inquiry->id, 'status' => 'quoted', 'client_id' => $client->id,
        ]);
    }

    public function test_admin_list_filters_by_origin(): void
    {
        Inquiry::create($this->contactPayload() + ['status' => 'new']);
        Inquiry::create([
            'name' => 'Quote Lead', 'email' => 'lead@example.com',
            'message' => 'Need a dashboard built.', 'status' => 'new',
        ]);

        $res = $this->getJson('/api/v1/admin/inquiries?origin=contact', $this->adminHeaders())->assertOk();

        $this->assertCount(1, $res->json('data'));
        $this->assertSame('contact', $res->json('data.0.origin'));
        $this->assertSame('General question', $res->json('data.0.subject'));
    }
}
