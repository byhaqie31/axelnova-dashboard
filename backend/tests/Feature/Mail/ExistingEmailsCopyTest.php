<?php

namespace Tests\Feature\Mail;

use App\Mail\AdminNotificationMail;
use App\Mail\FeedbackRequestMail;
use App\Mail\InquiryAdminNotificationMail;
use App\Mail\ReferralCommissionMail;
use App\Mail\ReferralReceivedMail;
use App\Models\Feedback;
use App\Models\Inquiry;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\Referral;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The existing emails the owner didn't mock are re-skinned onto the kit
 * layouts with their wording intact (the credential emails were rewritten
 * for Phase 2 — see CredentialEmailsTest): every sentence of the old markdown
 * template must survive (spec §5.13 — copy changes only where a mockup made
 * them). New text is limited to eyebrows, preheaders and labels.
 */
class ExistingEmailsCopyTest extends TestCase
{
    use RefreshDatabase;

    private function text(Mailable $mail): string
    {
        $html = $mail->render();
        $this->assertStringContainsString('<!DOCTYPE html>', $html);

        return (string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5));
    }

    private function assertKeeps(Mailable $mail, array $sentences): void
    {
        $text = $this->text($mail);
        foreach ($sentences as $sentence) {
            $this->assertStringContainsString($sentence, $text);
        }
    }

    public function test_feedback_request_keeps_its_copy_in_all_three_variants(): void
    {
        $order = Order::factory()->create();
        $base = ['reference_code' => 'AXNF-2026-0001', 'public_token' => Str::random(48), 'name' => 'Aisyah', 'email' => 'a@example.com'];

        $labelled = Feedback::create($base + ['order_id' => $order->id, 'project_label' => 'Business Site']);
        $this->assertKeeps(new FeedbackRequestMail($labelled), [
            'How did we do, Aisyah?',
            "Your project — Business Site — has wrapped, and I'd love to hear how the experience was for you.",
            "It's a short form — a couple of quick ratings and two open questions. Two minutes, tops.",
            'Share your feedback',
            "Your answers come straight to me. If you're happy for your words to appear on the Axel Nova site, there's an optional permission toggle on the form — nothing is ever published without it.",
            'Thanks for building with me,',
        ]);
        $this->assertStringContainsString("/feedback/{$labelled->public_token}", (new FeedbackRequestMail($labelled))->render());

        $unlabelled = Feedback::create(['reference_code' => 'AXNF-2026-0002', 'public_token' => Str::random(48), 'order_id' => Order::factory()->create()->id] + $base);
        $this->assertKeeps(new FeedbackRequestMail($unlabelled), [
            "Your project with Axel Nova Ventures has wrapped, and I'd love to hear how the experience was for you.",
        ]);

        $general = Feedback::create(['reference_code' => 'AXNF-2026-0003', 'public_token' => Str::random(48), 'name' => null] + $base);
        $this->assertKeeps(new FeedbackRequestMail($general), [
            'How did we do?',
            "I'd love to hear your honest take on Axel Nova Ventures — how we come across, and how the experience of dealing with us has been.",
        ]);
    }

    public function test_referral_emails_keep_their_copy(): void
    {
        $referral = Referral::create([
            'referrer_name' => 'Ref Erra', 'referrer_email' => 'ref@example.com', 'business_name' => 'Big Whale Sdn Bhd',
            'relationship_tier' => 'closed', 'commission_tier_pct' => 15, 'status' => 'new',
        ]);

        $this->assertKeeps(new ReferralReceivedMail($referral), [
            'Thanks for the referral, Ref Erra',
            "I've received your referral of Big Whale Sdn Bhd and made a note of it. I'll reach out to them (usually within 3 business days) and keep you posted on how it goes.",
            'If it becomes a signed, paid project, your commission follows per the Partner Program terms (your current tier is 15% of the final project value, capped at RM 1,500 per referral).',
            'Thanks for thinking of me. It genuinely means a lot.',
            'Best,',
        ]);

        $this->assertKeeps(new ReferralCommissionMail($referral, 1234.5), [
            'Good news, Ref Erra — your referral converted',
            'Your referral of Big Whale Sdn Bhd has turned into a signed project. Thank you — this is exactly what the Partner Program is for.',
            "Based on your {$referral->effectivePct()}% rate on the project value (capped at RM 1,500 per referral), your estimated commission is:",
            'RM 1,234.50',
            'This is an estimate against the agreed project value and may settle slightly once the project is finalised.',
            'To get you paid, just reply to this email with your bank details:',
            'Bank name', 'Account number', 'Account holder name',
            "Once I have those, I'll arrange the transfer and send you a confirmation.",
            'Thanks again for the introduction.',
        ]);
    }

    public function test_owner_alerts_keep_their_copy_and_use_the_internal_layout(): void
    {
        $quote = Quotation::factory()->create(['name' => 'Lead Person', 'email' => 'lead@example.com', 'company' => 'Lead Co']);
        $leadText = $this->text(new AdminNotificationMail($quote));
        foreach (['New Quote Request', 'A new lead has been submitted via the quote builder.', $quote->reference_code, 'Lead Person', 'lead@example.com', 'Lead Co', 'View Lead in Admin', 'Internal'] as $s) {
            $this->assertStringContainsString($s, $leadText);
        }

        $inquiry = Inquiry::create(['origin' => 'contact', 'subject' => 'General question', 'name' => 'Aina', 'email' => 'aina@example.com', 'message' => "Line one\nLine two", 'status' => 'new']);
        $this->assertKeeps(new InquiryAdminNotificationMail($inquiry), [
            'New inquiry',
            'A new message came in through the contact form.',
            'General question',
            'Line one',
            'Open in Admin',
            'Reply to this email to answer Aina directly.',
        ]);

    }

    public function test_the_markdown_mail_templates_are_gone(): void
    {
        $this->assertDirectoryDoesNotExist(resource_path('views/mail'));
    }
}
