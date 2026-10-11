<?php

namespace Tests\Feature\Inquiries;

use App\Mail\InquiryAdminNotificationMail;
use App\Mail\InquiryReceivedMail;
use App\Models\CollaborationPartner;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The /collaborations pages post to the same POST /v1/inquiries pipeline:
 * `origin=partner` is a client hiring a partner studio THROUGH Axel Nova (the
 * lead is logged and passed on), `origin=collaboration` is a studio applying
 * to partner with Axel Nova.
 */
class PartnerInquiryIntakeTest extends TestCase
{
    use RefreshDatabase;

    private CollaborationPartner $studio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->studio = CollaborationPartner::factory()->create([
            'slug' => 'studio-x', 'name' => 'Studio X', 'founder_name' => 'Sam Lee',
        ]);
    }

    private function partnerPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nadia Karim',
            'email' => 'nadia@example.com',
            'origin' => 'partner',
            'partner' => 'studio-x',
            'project_type' => 'Mobile app',
            'message' => 'We need a booking app and a website to match.',
        ], $overrides);
    }

    private function applicationPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Farid Osman',
            'email' => 'farid@example.com',
            'origin' => 'collaboration',
            'company' => 'Osman Design Co',
            'subject' => 'Design studio',
            'portfolio_url' => 'https://osman.design',
            'message' => 'We design brands and would like to co-deliver web projects.',
        ], $overrides);
    }

    private function storedEnquiry(): Inquiry
    {
        return Inquiry::create(collect($this->partnerPayload())->except('partner')->all() + [
            'collaboration_partner_id' => $this->studio->id, 'status' => 'new',
        ]);
    }

    private function storedApplication(): Inquiry
    {
        return Inquiry::create(collect($this->applicationPayload())->except('portfolio_url')->all() + ['status' => 'new']);
    }

    public function test_a_partner_enquiry_is_tagged_with_the_partner(): void
    {
        Mail::fake();

        $res = $this->postJson('/api/v1/inquiries', $this->partnerPayload())->assertCreated();

        $inquiry = Inquiry::findOrFail($res->json('data.id'));
        $this->assertSame('partner', $inquiry->origin);
        $this->assertSame($this->studio->id, $inquiry->collaboration_partner_id);
        $this->assertSame('Mobile app', $inquiry->project_type);
    }

    public function test_a_partner_enquiry_needs_a_partner(): void
    {
        $this->postJson('/api/v1/inquiries', $this->partnerPayload(['partner' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('partner');
    }

    public function test_a_partner_enquiry_refuses_an_inactive_or_unknown_partner(): void
    {
        CollaborationPartner::factory()->create(['slug' => 'dormant', 'active' => false]);

        $this->postJson('/api/v1/inquiries', $this->partnerPayload(['partner' => 'dormant']))
            ->assertUnprocessable()->assertJsonValidationErrors('partner');
        $this->postJson('/api/v1/inquiries', $this->partnerPayload(['partner' => 'nobody']))
            ->assertUnprocessable()->assertJsonValidationErrors('partner');
    }

    public function test_other_origins_ignore_a_stray_partner(): void
    {
        Mail::fake();

        $res = $this->postJson('/api/v1/inquiries', $this->applicationPayload(['partner' => 'studio-x']))
            ->assertCreated();

        $this->assertNull(Inquiry::findOrFail($res->json('data.id'))->collaboration_partner_id);
    }

    public function test_a_partnership_application_keeps_the_portfolio_link_in_the_message(): void
    {
        Mail::fake();

        $res = $this->postJson('/api/v1/inquiries', $this->applicationPayload())->assertCreated();

        $inquiry = Inquiry::findOrFail($res->json('data.id'));
        $this->assertSame('collaboration', $inquiry->origin);
        $this->assertSame('Osman Design Co', $inquiry->company);
        $this->assertSame('Design studio', $inquiry->subject);
        $this->assertStringContainsString('co-deliver web projects', $inquiry->message);
        $this->assertStringContainsString('Portfolio: https://osman.design', $inquiry->message);
    }

    public function test_a_portfolio_link_must_be_a_url(): void
    {
        $this->postJson('/api/v1/inquiries', $this->applicationPayload(['portfolio_url' => 'not a link']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('portfolio_url');
    }

    public function test_admin_notification_names_the_partner_and_the_application(): void
    {
        $enquiry = $this->storedEnquiry();
        $application = $this->storedApplication();

        $partnerMail = new InquiryAdminNotificationMail($enquiry);
        $this->assertStringContainsString('Axel Nova × Studio X', $partnerMail->envelope()->subject);
        $this->assertStringContainsString('Studio X', $partnerMail->render());

        $this->assertStringContainsString('Partnership application', (new InquiryAdminNotificationMail($application))->envelope()->subject);
    }

    public function test_auto_replies_fit_each_origin(): void
    {
        $enquiry = $this->storedEnquiry();
        $application = $this->storedApplication();

        $enquiryHtml = (new InquiryReceivedMail($enquiry))->render();
        $this->assertStringContainsString('Axel Nova × Studio X', $enquiryHtml);
        $this->assertStringContainsString('Sam Lee', $enquiryHtml);

        $applicationHtml = (new InquiryReceivedMail($application))->render();
        $this->assertStringContainsString('collaborating', $applicationHtml);
        $this->assertStringNotContainsString('tailored quote', $applicationHtml);
    }

    public function test_admin_list_shows_the_partner_and_filters_by_the_new_origins(): void
    {
        $token = User::factory()->founder()->create()->createToken('admin-spa', ['cockpit'])->plainTextToken;
        $this->storedEnquiry();
        $this->storedApplication();

        $res = $this->getJson('/api/v1/admin/inquiries?origin=partner', ['Authorization' => "Bearer {$token}"])
            ->assertOk();

        $this->assertCount(1, $res->json('data'));
        $res->assertJsonPath('data.0.origin', 'partner')
            ->assertJsonPath('data.0.collaboration_partner.slug', 'studio-x')
            ->assertJsonPath('data.0.collaboration_partner.name', 'Studio X');
    }
}
