<?php

namespace Tests\Feature\Mail;

use App\Support\Mail\EmailSamples;
use Illuminate\Support\Carbon;
use Tests\Support\KitHtml;
use Tests\TestCase;

/**
 * Emails 6 (receipt), 9 (project delivered) and 28 (delivery alert) — kit
 * mockups 06, 09, 28. Their mailables arrive in Phase 3 (6, 9) and Task B5
 * (28); the templates are built and verified now.
 */
class KitTemplatesFidelityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-11 08:46');
    }

    private function render(string $type, array $overrides = []): string
    {
        return view("emails.{$this->viewName($type)}", $overrides + EmailSamples::for($type))->render();
    }

    private function viewName(string $type): string
    {
        return ['payment.receipt' => 'payment.receipt', 'order.delivered' => 'order.delivered', 'system.delivery_alert' => 'admin.delivery-alert'][$type];
    }

    private function text(string $type, array $overrides = []): string
    {
        return html_entity_decode($this->render($type, $overrides), ENT_QUOTES);
    }

    public function test_receipt_matches_the_kit(): void
    {
        KitHtml::assertMatchesKit('06-payment-receipt.html', $this->render('payment.receipt', [
            'receiptNumber' => '[receipt no]',
            'paymentReference' => '[payment reference]',
        ]));
    }

    public function test_delivered_matches_the_kit(): void
    {
        KitHtml::assertMatchesKit('09-project-delivered.html', $this->render('order.delivered', ['reviewWindow' => '[review window]']));
    }

    public function test_delivery_alert_matches_the_kit(): void
    {
        KitHtml::assertMatchesKit('28-delivery-alert.html', $this->render('system.delivery_alert'), KitHtml::normalize(KitHtml::kit('28-delivery-alert.html')));
    }

    public function test_receipt_hides_a_blank_payment_reference(): void
    {
        $this->assertStringNotContainsString('Payment ref', $this->text('payment.receipt', ['paymentReference' => null]));
    }

    public function test_delivered_without_a_balance_drops_the_panel_and_the_button(): void
    {
        $html = $this->text('order.delivered', ['balance' => null, 'invoiceUrl' => null]);

        $this->assertStringNotContainsString('Balance due', $html);
        $this->assertStringNotContainsString('View invoice', $html);
    }

    public function test_delivered_without_handover_items_or_review_window(): void
    {
        $html = $this->text('order.delivered', ['handoverItems' => [], 'reviewWindow' => null]);

        $this->assertStringNotContainsString('What was handed over', $html);
        $this->assertStringNotContainsString('You have', $html);
        $this->assertStringContainsString("Reply to this email with any changes and I'll take care of them.", $html);
    }

    public function test_delivery_alert_wording_follows_the_problem(): void
    {
        $spam = $this->text('system.delivery_alert', ['problem' => 'complained']);
        $this->assertStringContainsString('An invoice email was marked as spam', $spam);
        $this->assertStringContainsString('Marked as spam', $spam);

        $failed = $this->text('system.delivery_alert', ['problem' => 'failed', 'emailLabel' => 'Quotation', 'recordLabel' => 'Quotation AXNQ-2026-0042']);
        $this->assertStringContainsString('A quotation email failed to send', $failed);
        $this->assertStringContainsString('>Failed<', $failed);
    }
}
