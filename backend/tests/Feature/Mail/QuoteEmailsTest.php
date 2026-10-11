<?php

namespace Tests\Feature\Mail;

use App\Jobs\SendClientQuoteEmail;
use App\Mail\ClientEstimateMail;
use App\Mail\ClientQuotationMail;
use App\Models\Quotation;
use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use App\Support\Mail\EmailFormat;
use App\Support\Mail\EmailSamples;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Support\KitHtml;
use Tests\TestCase;

/**
 * Email 1 is split (spec §5.13): the quote-builder submit sends the estimate
 * (01a), the admin "Send" sends the formal quotation (01b).
 */
class QuoteEmailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-11 08:46');
    }

    public function test_estimate_matches_the_kit(): void
    {
        KitHtml::assertMatchesKit('01a-estimate.html', view('emails.quotation.estimate', EmailSamples::for('quotation.estimate'))->render());
    }

    public function test_quotation_matches_the_kit(): void
    {
        KitHtml::assertMatchesKit('01b-quotation.html', view('emails.quotation.sent', EmailSamples::for('quotation.sent'))->render());
    }

    public function test_client_text_is_escaped_once(): void
    {
        $data = ['name' => "O'Brien & Co <Ltd>"] + EmailSamples::for('quotation.estimate');

        $html = view('emails.quotation.estimate', $data)->render();

        $this->assertStringContainsString('O&#039;Brien &amp; Co &lt;Ltd&gt;', $html);
        $this->assertStringNotContainsString('<Ltd>', $html);
        $this->assertStringNotContainsString('&amp;#039;', $html);
    }

    public function test_optional_blocks_hide_when_their_data_is_missing(): void
    {
        $data = ['pdfUrl' => null, 'whatsappUrl' => null, 'addons' => [], 'included' => [], 'packageName' => null]
            + EmailSamples::for('quotation.estimate');

        $html = view('emails.quotation.estimate', $data)->render();

        $this->assertStringNotContainsString('Download PDF', $html);
        $this->assertStringNotContainsString('Chat on WhatsApp', $html);
        $this->assertStringNotContainsString('Add-ons', $html);
        $this->assertStringNotContainsString("What's included", html_entity_decode($html, ENT_QUOTES));
        $this->assertStringNotContainsString('>Package<', $html);
    }

    public function test_quotation_without_line_items_still_shows_the_total(): void
    {
        $data = ['items' => [], 'pdfUrl' => null] + EmailSamples::for('quotation.sent');

        $html = view('emails.quotation.sent', $data)->render();

        $this->assertStringContainsString('RM 6,500', $html);
        $this->assertStringNotContainsString("What's included", html_entity_decode($html, ENT_QUOTES));
        $this->assertStringNotContainsString('The PDF has the full scope', $html);
    }

    public function test_funnel_send_is_the_estimate_and_admin_send_is_the_quotation(): void
    {
        Mail::fake();
        $quotation = Quotation::factory()->create(['email' => 'client@example.com', 'public_token' => 'tok-1']);

        (new SendClientQuoteEmail($quotation->id))->handle();
        Mail::assertSent(ClientEstimateMail::class, fn ($m) => $m->hasTo('client@example.com'));
        Mail::assertNotSent(ClientQuotationMail::class);

        (new SendClientQuoteEmail($quotation->id, $quotation->revision()->id))->handle();
        Mail::assertSent(ClientQuotationMail::class, fn ($m) => $m->hasTo('client@example.com'));
    }

    public function test_estimate_data_lists_every_package_with_its_features(): void
    {
        $category = ServiceCategory::forceCreate(['slug' => 'mail-web', 'name' => 'Web', 'icon' => 'i-lucide-globe', 'description' => 'x', 'active' => true]);
        $site = ServicePackage::forceCreate([
            'service_category_id' => $category->id, 'slug' => 'mail-site', 'name' => 'Business Site', 'tagline' => 't',
            'price_min_myr' => 1, 'price_max_myr' => 2, 'unit' => 'project', 'duration_text' => '3 weeks',
            'features' => ['5 pages', 'CMS ready'], 'quote_key' => ['package' => 'mail_site'], 'eta_value' => 3, 'eta_unit' => 'week', 'active' => true,
        ]);
        $logo = ServicePackage::forceCreate([
            'service_category_id' => $category->id, 'slug' => 'mail-logo', 'name' => 'Logo Design', 'tagline' => 't',
            'price_min_myr' => 1, 'price_max_myr' => 2, 'unit' => 'project', 'duration_text' => '1 week',
            'features' => ['3 concepts'], 'quote_key' => ['package' => 'mail_logo'], 'eta_value' => 1, 'eta_unit' => 'week', 'active' => true,
        ]);
        $quotation = Quotation::factory()->create([
            'estimate_min_myr' => 5000, 'estimate_max_myr' => 8000,
            'form_payload' => ['packages' => [
                ['package_key' => 'mail_site', 'service_package_id' => $site->id, 'scope_values' => [], 'modifiers' => [], 'addon_keys' => []],
                ['package_key' => 'mail_logo', 'service_package_id' => $logo->id, 'scope_values' => [], 'modifiers' => [], 'addon_keys' => []],
            ]],
        ]);

        $data = (new ClientEstimateMail($quotation))->viewData();

        $this->assertSame('Business Site + Logo Design', $data['packageName']);
        $this->assertSame(['Business Site: 5 pages', 'Business Site: CMS ready', 'Logo Design: 3 concepts'], $data['included']);
        $this->assertSame('RM 5,000 to RM 8,000', $data['range']);
    }

    public function test_single_package_features_are_not_prefixed(): void
    {
        $category = ServiceCategory::forceCreate(['slug' => 'mail-web2', 'name' => 'Web', 'icon' => 'i-lucide-globe', 'description' => 'x', 'active' => true]);
        $site = ServicePackage::forceCreate([
            'service_category_id' => $category->id, 'slug' => 'mail-site2', 'name' => 'Business Site', 'tagline' => 't',
            'price_min_myr' => 1, 'price_max_myr' => 2, 'unit' => 'project', 'duration_text' => '3 weeks',
            'features' => ['5 pages'], 'quote_key' => ['package' => 'mail_site2'], 'eta_value' => 3, 'eta_unit' => 'week', 'active' => true,
        ]);
        $quotation = Quotation::factory()->create(['form_payload' => ['packages' => [
            ['package_key' => 'mail_site2', 'service_package_id' => $site->id, 'scope_values' => [], 'modifiers' => [], 'addon_keys' => []],
        ]]]);

        $this->assertSame(['5 pages'], (new ClientEstimateMail($quotation))->viewData()['included']);
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
