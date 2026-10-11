<?php

namespace Tests\Feature\Mail;

use App\Mail\InquiryReceivedMail;
use App\Mail\InvoiceMail;
use App\Models\Inquiry;
use App\Models\Order;
use App\Services\Quoting\DocumentIssuer;
use App\Support\Mail\EmailSamples;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\KitHtml;
use Tests\TestCase;

/** Emails 2 (inquiry received, four variants) and 3 (invoice) — kit mockups 02 + 03. */
class InquiryAndInvoiceEmailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-11 08:46');
    }

    public function test_inquiry_received_matches_the_kit(): void
    {
        KitHtml::assertMatchesKit('02-inquiry-received.html', view('emails.inquiry.received', EmailSamples::for('inquiry.received'))->render());
    }

    public function test_invoice_matches_the_kit_with_its_bracketed_placeholders(): void
    {
        // The kit leaves account name / number / payment link in [brackets];
        // passing them through literally must reproduce the file exactly.
        $data = [
            'bank' => ['name' => 'OCBC', 'holder' => '[account name]', 'account' => '[account number]'],
            'onlinePaymentLink' => '[online payment link]',
        ] + EmailSamples::for('invoice.sent');

        KitHtml::assertMatchesKit('03-invoice.html', view('emails.invoice.sent', $data)->render());
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string, 2: string}> */
    public static function variants(): array
    {
        return [
            'contact' => [['origin' => 'contact', 'subject' => 'General question'], 'usually within one working day', 'Usually within one working day.'],
            'project' => [['origin' => 'contact', 'subject' => 'Project inquiry'], 'tailored quote', 'Usually within 1–2 business days.'],
            'collaboration' => [['origin' => 'collaboration', 'subject' => null], 'collaborating', 'Usually within a few working days.'],
        ];
    }

    #[DataProvider('variants')]
    public function test_each_inquiry_variant_keeps_its_own_promise(array $attrs, string $lead, string $step): void
    {
        $inquiry = Inquiry::create($attrs + [
            'name' => 'Aina', 'email' => 'aina@example.com', 'message' => 'Hello', 'status' => 'new',
        ]);

        $html = html_entity_decode((new InquiryReceivedMail($inquiry))->render(), ENT_QUOTES);

        $this->assertStringContainsString($lead, $html);
        $this->assertStringContainsString($step, $html);
        $this->assertStringContainsString('Thanks for reaching out, Aina', $html);
    }

    public function test_invoice_hides_rows_it_has_no_value_for(): void
    {
        $data = ['dueDate' => null, 'quoteRef' => null, 'onlinePaymentLink' => null, 'projectName' => null]
            + EmailSamples::for('invoice.sent');

        $html = html_entity_decode(view('emails.invoice.sent', $data)->render(), ENT_QUOTES);

        $this->assertStringNotContainsString('>Due<', $html);
        $this->assertStringNotContainsString('Due 25 Oct', $html);
        $this->assertStringNotContainsString('>Quote ref<', $html);
        $this->assertStringNotContainsString('Card or FPX', $html);
        $this->assertStringContainsString("Hi Aisyah, here's your invoice from Axel Nova Ventures.", $html);
    }

    public function test_invoice_lead_only_promises_an_attachment_when_there_is_one(): void
    {
        $html = html_entity_decode(view('emails.invoice.sent', ['attached' => false] + EmailSamples::for('invoice.sent'))->render(), ENT_QUOTES);

        $this->assertStringContainsString('The PDF is linked below.', $html);
        $this->assertStringNotContainsString('attached', $html);
    }

    public function test_invoice_mail_maps_the_invoice_and_the_studio_bank(): void
    {
        $order = Order::factory()->create();
        $invoice = DocumentIssuer::issueInvoice($order, ['amount' => 1300]);

        $data = (new InvoiceMail($invoice->load('order.quotation'), '%PDF'))->viewData();

        $this->assertSame($invoice->invoice_number, $data['invoiceNumber']);
        $this->assertSame('RM 1,300', $data['amountDue']);
        $this->assertSame(['name' => 'OCBC Bank', 'holder' => 'Axel Nova Ventures', 'account' => '7051415701'], $data['bank']);
        $this->assertNull($data['onlinePaymentLink']);
        $this->assertTrue($data['attached']);
        $this->assertFalse((new InvoiceMail($invoice))->viewData()['attached']);
    }
}
