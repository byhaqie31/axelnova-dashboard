<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Inquiry;
use App\Models\Quotation;
use App\Models\Order;
use App\Services\Payments\PaymentService;
use App\Services\Quoting\DocumentIssuer;
use App\Services\Quoting\PricingEngine;
use App\Services\Quoting\QuoteRequestInput;
use App\Support\DocumentType;
use App\Support\ReferenceCodeGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Realistic demo data for local dev: inquiries → quotations → orders, with
 * invoices/payments on some, created through the SAME domain paths production
 * uses — canonical form_payload via the funnel shape, AXN codes via
 * ReferenceCodeGenerator, orders via the accept() recipe, invoices via
 * DocumentIssuer, money via PaymentService (PaymentObserver derives caches).
 *
 * STRICTLY ADDITIVE. This seeder never deletes, truncates, or updates data it
 * didn't create. Idempotent per persona: an existing demo client email is
 * skipped, so re-running adds nothing. Safe to run any time:
 *
 *   docker compose -f docker-compose.dev.yml exec backend \
 *     php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $engine = PricingEngine::active();

        foreach ($this->personas() as $p) {
            if (Client::where('email', $p['email'])->exists()) {
                $this->command?->warn("skip {$p['email']} — demo client already exists");

                continue;
            }

            $inquiry = $this->makeInquiry($p);
            $quotation = $this->makeQuotation($engine, $p);

            // Inquiries that got quoted link to their quotation (admin flow does
            // this when a quote is sent).
            if (in_array($p['quotation_status'], ['sent', 'accepted'], true)) {
                $inquiry->update(['quotation_id' => $quotation->id, 'status' => 'quoted']);
            }

            if ($p['quotation_status'] === 'accepted') {
                $order = $this->acceptIntoOrder($quotation, $p);
                if ($p['billing'] !== 'none') {
                    $this->billAndPay($order, $p);
                }
            }

            $this->command?->info("seeded {$p['name']} → {$quotation->reference_code} [{$p['quotation_status']}]");
        }
    }

    /**
     * Five personas spanning the lifecycle: fresh inquiry-only, draft quote,
     * sent quote, and two accepted orders (one deposit-paid, one fully paid).
     * Dates spread over the last three weeks so dashboards look lived-in.
     */
    private function personas(): array
    {
        return [
            [
                'name' => 'Nurul Aisyah', 'email' => 'nurul.aisyah@kopilokal.demo', 'phone' => '+60123901122',
                'company' => 'Kopi Lokal Enterprise', 'project_type' => 'E-commerce website',
                'budget_hint' => 'RM 8k–12k', 'timeline_hint' => 'Before Merdeka campaign',
                'message' => 'We roast and sell coffee beans online via WhatsApp now — need a proper store with FPX payment and order tracking.',
                'package_key' => 'web_premium', 'modifiers' => ['cms' => true, 'fpx_integration' => true], 'addons' => ['seo'],
                'rush' => false, 'scope' => ['pages' => '8 core pages + product catalogue', 'content_ready' => 'Photos ready, copy needs help'],
                'inquiry_status' => 'quoted', 'quotation_status' => 'accepted', 'billing' => 'deposit_paid', 'days_ago' => 21,
            ],
            [
                'name' => 'Marcus Tan', 'email' => 'marcus@fittrack.demo', 'phone' => '+60167738845',
                'company' => 'FitTrack Studio', 'project_type' => 'SaaS MVP',
                'budget_hint' => 'RM 20k+', 'timeline_hint' => '2 months',
                'message' => 'Gym class booking + membership platform. Members book classes, trainers manage schedules, owner sees revenue dashboard.',
                'package_key' => 'saas_mvp_sprint', 'modifiers' => [], 'addons' => ['logo', 'analytics'],
                'rush' => true, 'scope' => ['core_flows' => 'Booking, membership billing, trainer roster', 'users' => '~400 members'],
                'inquiry_status' => 'quoted', 'quotation_status' => 'accepted', 'billing' => 'fully_paid', 'days_ago' => 18,
            ],
            [
                'name' => 'Priya Raman', 'email' => 'priya@klinikserene.demo', 'phone' => '+60193342210',
                'company' => 'Klinik Serene', 'project_type' => 'Business website',
                'budget_hint' => 'RM 5k', 'timeline_hint' => 'Flexible',
                'message' => 'Clinic website with doctor profiles, service list and an appointment enquiry form. Must look trustworthy.',
                'package_key' => 'web_business', 'modifiers' => ['booking_flow' => true], 'addons' => ['copywriting'],
                'rush' => false, 'scope' => ['pages' => '6 pages', 'content_ready' => 'Nothing yet'],
                'inquiry_status' => 'quoted', 'quotation_status' => 'accepted', 'billing' => 'none', 'days_ago' => 12,
            ],
            [
                'name' => 'Hafiz Roslan', 'email' => 'hafiz@dapurmak.demo', 'phone' => '+60112287739',
                'company' => 'Dapur Mak Catering', 'project_type' => 'Landing page + ordering',
                'budget_hint' => 'RM 3k–5k', 'timeline_hint' => 'Raya season',
                'message' => 'Catering menu site where corporate customers can browse packages and request quotations for events.',
                'package_key' => 'web_essential', 'modifiers' => [], 'addons' => [],
                'rush' => false, 'scope' => ['pages' => '4 pages', 'content_ready' => 'Menu PDF exists'],
                'inquiry_status' => 'reviewing', 'quotation_status' => 'sent', 'billing' => 'none', 'days_ago' => 6,
            ],
            [
                'name' => 'Chen Wei Ling', 'email' => 'weiling@artisangold.demo', 'phone' => '+60175560914',
                'company' => 'Artisan Gold Jewellery', 'project_type' => 'Brand + dashboard',
                'budget_hint' => 'Not sure yet', 'timeline_hint' => 'Exploring',
                'message' => 'We hand-make jewellery and want an internal dashboard to track custom orders, deposits and goldsmith assignments.',
                'package_key' => 'dash_starter', 'modifiers' => [], 'addons' => [],
                'rush' => false, 'scope' => ['modules' => 'Orders, deposits, assignments', 'users' => '5 staff'],
                'inquiry_status' => 'new', 'quotation_status' => 'draft', 'billing' => 'none', 'days_ago' => 2,
            ],
        ];
    }

    private function makeInquiry(array $p): Inquiry
    {
        $inquiry = Inquiry::create([
            'name' => $p['name'],
            'email' => $p['email'],
            'phone' => $p['phone'],
            'company' => $p['company'],
            'project_type' => $p['project_type'],
            'budget_hint' => $p['budget_hint'],
            'timeline_hint' => $p['timeline_hint'],
            'message' => $p['message'],
            'source' => 'web',
            'status' => $p['inquiry_status'],
        ]);

        $created = now()->subDays($p['days_ago'])->setTime(rand(9, 18), rand(0, 59));
        $inquiry->forceFill(['created_at' => $created, 'updated_at' => $created])->saveQuietly();

        return $inquiry;
    }

    /** Mirrors QuoteRequestController::store — the canonical funnel writer. */
    private function makeQuotation(PricingEngine $engine, array $p): Quotation
    {
        $input = new QuoteRequestInput(
            name: $p['name'],
            email: $p['email'],
            phone: $p['phone'],
            company: $p['company'],
            packageKey: $p['package_key'],
            modifiers: $p['modifiers'],
            addonKeys: $p['addons'],
            rush: $p['rush'],
            scopeValues: $p['scope'],
        );

        $estimate = $engine->calculate($input);
        $refCode = ReferenceCodeGenerator::generate(DocumentType::Quotation);
        $submitted = now()->subDays($p['days_ago'])->setTime(rand(9, 18), rand(0, 59));

        return DB::transaction(function () use ($engine, $input, $estimate, $refCode, $submitted, $p) {
            $client = Client::firstOrCreate(
                ['email' => $input->email],
                ['name' => $input->name, 'phone' => $input->phone, 'company' => $input->company],
            );

            $package = [
                'package_key' => $input->packageKey,
                'service_package_id' => $engine->packageId($input->packageKey),
                'scope_values' => $input->scopeValues,
                'modifiers' => $input->modifiers,
                'addon_keys' => $input->addonKeys,
            ];
            $breakdownGroup = [[
                'package_key' => $input->packageKey,
                'name' => $engine->packageName($input->packageKey),
                'min' => $estimate->minMyr,
                'max' => $estimate->maxMyr,
                'eta_value' => $estimate->etaValue,
                'eta_unit' => $estimate->etaUnit,
                'lines' => $estimate->breakdown,
            ]];

            $sent = in_array($p['quotation_status'], ['sent', 'accepted'], true);

            $quotation = Quotation::create([
                'reference_code' => $refCode,
                'client_id' => $client->id,
                'name' => $input->name,
                'email' => $input->email,
                'phone' => $input->phone,
                'company' => $input->company,
                'package_key' => $input->packageKey,
                'service_package_id' => $package['service_package_id'],
                'pricing_config_id' => $engine->getConfig()->id,
                'form_payload' => [
                    'packages' => [$package],
                    'rush' => $input->rush,
                    'breakdown' => $breakdownGroup,
                    'source_meta' => ['created_via' => 'quote_funnel'],
                ],
                'estimate_min_myr' => $estimate->minMyr,
                'estimate_max_myr' => $estimate->maxMyr,
                'estimate_eta_value' => $estimate->etaValue,
                'estimate_eta_unit' => $estimate->etaUnit,
                'status' => $p['quotation_status'] === 'draft' ? 'draft' : 'sent',
                'public_token' => $sent ? Str::random(48) : null,
                'sent_at' => $sent ? $submitted->copy()->addDay() : null,
                'expires_at' => $sent ? $submitted->copy()->addDay()->addDays(30) : null,
                'submitted_at' => $submitted,
            ]);

            $addonDefs = $engine->addons();
            foreach ($input->addonKeys as $key) {
                if (isset($addonDefs[$key])) {
                    $quotation->addons()->create([
                        'addon_key' => $key,
                        'addon_label' => $addonDefs[$key]['label'],
                        'amount_myr' => $addonDefs[$key]['amount'],
                    ]);
                }
            }

            $quotation->forceFill(['created_at' => $submitted, 'updated_at' => $submitted])->saveQuietly();

            return $quotation;
        });
    }

    /** Mirrors QuotationsController::accept — quotation → order. */
    private function acceptIntoOrder(Quotation $quotation, array $p): Order
    {
        return DB::transaction(function () use ($quotation, $p) {
            $quotation->update(['status' => 'accepted']);

            $order = Order::create([
                'order_number' => ReferenceCodeGenerator::generate(DocumentType::Order),
                'quotation_id' => $quotation->id,
                'client_id' => $quotation->client_id,
                'value_min_myr' => $quotation->estimate_min_myr,
                'value_max_myr' => $quotation->estimate_max_myr,
                'final_amount_myr' => $quotation->finalAmount(),
                'deposit_pct' => $quotation->depositPct(),
                'amount_paid_myr' => 0,
                'due_at' => $quotation->dueDateFrom(),
                'status' => $p['billing'] === 'fully_paid' ? 'in_progress' : 'pending',
            ]);

            $accepted = now()->subDays(max($p['days_ago'] - 3, 1));
            $order->forceFill(['created_at' => $accepted, 'updated_at' => $accepted])->saveQuietly();

            return $order;
        });
    }

    /**
     * Issue invoices via DocumentIssuer and record money via PaymentService —
     * PaymentObserver derives all paid caches; nothing here writes them.
     */
    private function billAndPay(Order $order, array $p): void
    {
        $final = (float) $order->final_amount_myr;
        $depositAmt = round($final * $order->deposit_pct / 100, 2);

        $deposit = DocumentIssuer::issueInvoice($order, [
            'invoiceType' => 'deposit',
            'amount' => $depositAmt,
            'notes' => "Deposit ({$order->deposit_pct}%) to commence work",
        ]);

        PaymentService::record($order, [
            'invoice_id' => $deposit->id,
            'amount_myr' => $depositAmt,
            'method' => 'bank_transfer',
            'reference' => 'DEMO-'.strtoupper(Str::random(8)),
            'paid_at' => now()->subDays(max($p['days_ago'] - 4, 1)),
            'notes' => 'Demo seed payment',
        ]);

        if ($p['billing'] === 'fully_paid') {
            $balance = round($final - $depositAmt, 2);
            $finalInvoice = DocumentIssuer::issueInvoice($order, [
                'invoiceType' => 'final',
                'amount' => $balance,
                'notes' => 'Balance payment on delivery',
            ]);

            PaymentService::record($order, [
                'invoice_id' => $finalInvoice->id,
                'amount_myr' => $balance,
                'method' => 'fpx',
                'reference' => 'DEMO-'.strtoupper(Str::random(8)),
                'paid_at' => now()->subDays(1),
                'notes' => 'Demo seed payment',
            ]);
        }
    }
}
