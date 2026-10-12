<?php

namespace Tests\Feature\Mail;

use App\Jobs\SendClientQuoteEmail;
use App\Mail\ClientQuotationMail;
use App\Models\Quotation;
use App\Support\Mail\EmailFormat;
use App\Support\Mail\EmailSamples;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Support\KitHtml;
use Tests\TestCase;

/**
 * The client quote email is the formal quotation (kit 01b), sent by the admin
 * "Send". The instant estimate (01a) went with the retired public quote funnel.
 */
class QuoteEmailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-11 08:46');
    }

    public function test_quotation_matches_the_kit(): void
    {
        KitHtml::assertMatchesKit('01b-quotation.html', view('emails.quotation.sent', EmailSamples::for('quotation.sent'))->render());
    }

    public function test_client_text_is_escaped_once(): void
    {
        $data = ['name' => "O'Brien & Co <Ltd>"] + EmailSamples::for('quotation.sent');

        $html = view('emails.quotation.sent', $data)->render();

        $this->assertStringContainsString('O&#039;Brien &amp; Co &lt;Ltd&gt;', $html);
        $this->assertStringNotContainsString('<Ltd>', $html);
        $this->assertStringNotContainsString('&amp;#039;', $html);
    }

    public function test_quotation_without_line_items_still_shows_the_total(): void
    {
        $data = ['items' => [], 'pdfUrl' => null] + EmailSamples::for('quotation.sent');

        $html = view('emails.quotation.sent', $data)->render();

        $this->assertStringContainsString('RM 6,500', $html);
        $this->assertStringNotContainsString("What's included", html_entity_decode($html, ENT_QUOTES));
        $this->assertStringNotContainsString('The PDF has the full scope', $html);
    }

    public function test_every_send_is_the_formal_quotation(): void
    {
        Mail::fake();
        $quotation = Quotation::factory()->create(['email' => 'client@example.com', 'public_token' => 'tok-1']);

        // The admin Send names the revision; no revision = the current one. Both
        // are the quotation — the instant estimate went with the public funnel.
        (new SendClientQuoteEmail($quotation->id, $quotation->revision()->id))->handle();
        (new SendClientQuoteEmail($quotation->id))->handle();

        Mail::assertSent(ClientQuotationMail::class, 2);
        Mail::assertSent(ClientQuotationMail::class, fn ($m) => $m->hasTo('client@example.com'));
    }

    public function test_with_tax_the_email_total_is_the_agreed_final_amount(): void
    {
        $quotation = Quotation::factory()->create(['document' => [
            'items' => [['title' => 'Business Site', 'qty' => 1, 'rate' => 5000]],
            'tax_rate' => 0.06,
            'tax_label' => 'SST',
        ]]);

        $data = (new ClientQuotationMail($quotation))->viewData();

        $this->assertSame('RM 5,300', $data['total']);
        $this->assertSame(EmailFormat::money($quotation->finalAmount()), $data['total']);
        $this->assertSame(['label' => 'SST', 'detail' => null, 'amount' => 'RM 300'], end($data['items']));
    }

    public function test_quotation_data_mirrors_the_pdf_line_items_and_totals(): void
    {
        $quotation = Quotation::factory()->create([
            'document' => [
                'items' => [
                    ['title' => 'Business Site', 'desc' => '5 pages, enquiry forms', 'qty' => 1, 'rate' => 5000],
                    ['title' => 'CMS', 'qty' => 2, 'rate' => 600],
                ],
                'discount' => 200,
            ],
        ]);

        $data = (new ClientQuotationMail($quotation))->viewData();

        $this->assertSame(['label' => 'Business Site', 'detail' => '5 pages, enquiry forms', 'amount' => 'RM 5,000'], $data['items'][0]);
        $this->assertSame('CMS', $data['items'][1]['label']);
        $this->assertSame('2 × RM 600', $data['items'][1]['detail']);
        $this->assertSame('RM 1,200', $data['items'][1]['amount']);
        $this->assertSame(['label' => 'Discount', 'detail' => null, 'amount' => '− RM 200'], $data['items'][2]);
        $this->assertSame('RM 6,000', $data['total']);
    }
}
