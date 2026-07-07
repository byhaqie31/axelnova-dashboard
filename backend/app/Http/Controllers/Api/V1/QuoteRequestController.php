<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuoteRequestRequest;
use App\Jobs\NotifyAdminJob;
use App\Jobs\SendClientQuoteEmail;
use App\Models\Client;
use App\Models\Quotation;
use App\Services\Quoting\PricingEngine;
use App\Services\Quoting\QuoteRequestInput;
use App\Support\DocumentType;
use App\Support\ReferenceCodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class QuoteRequestController extends Controller
{
    public function store(StoreQuoteRequestRequest $request): JsonResponse
    {
        $engine = PricingEngine::active();

        $input = new QuoteRequestInput(
            name: $request->input('name'),
            email: $request->input('email'),
            phone: $request->input('phone'),
            company: $request->input('company'),
            packageKey: $request->input('package_key'),
            modifiers: $request->input('modifiers', []),
            addonKeys: $request->input('addon_keys', []),
            rush: (bool) $request->input('rush', false),
            scopeValues: $request->input('scope_values', []),
        );

        $estimate = $engine->calculate($input);
        $refCode = ReferenceCodeGenerator::generate(DocumentType::Quotation);

        $quotation = DB::transaction(function () use ($request, $input, $engine, $estimate, $refCode) {
            // Upsert Client by email so repeat customers stay deduplicated.
            $client = Client::firstOrCreate(
                ['email' => $input->email],
                [
                    'name' => $input->name,
                    'phone' => $input->phone,
                    'company' => $input->company,
                ],
            );

            // The public funnel is single-package: wrap it into the canonical
            // packages[] shape (see FormPayloadNormalizer) so the funnel, the admin
            // builder, and the MCP connector all store ONE shape. The estimate math,
            // the HTTP response, and the customer email are unchanged — the grouped
            // breakdown flattens back to the same tuples the email renders.
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
                'form_payload' => array_merge($request->input('form_payload', []), [
                    'packages' => [$package],
                    'rush' => $input->rush,
                    'breakdown' => $breakdownGroup,
                    'source_meta' => ['created_via' => 'quote_funnel'],
                ]),
                'estimate_min_myr' => $estimate->minMyr,
                'estimate_max_myr' => $estimate->maxMyr,
                'estimate_eta_value' => $estimate->etaValue,
                'estimate_eta_unit' => $estimate->etaUnit,
                // Lands as a draft in the admin's lap for review (self-serve lead
                // statuses retired; the simplified lifecycle is draft→sent→accepted).
                'status' => 'draft',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'submitted_at' => now(),
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

            return $quotation;
        });

        SendClientQuoteEmail::dispatch($quotation->id);
        // Mailtrap free caps at 1 email/sec, and the customer email itself takes ~4s
        // to send. Delay the admin email enough that the customer one has fully finished
        // by the time the worker picks this up. Cheap to wait — admin doesn't care.
        NotifyAdminJob::dispatch($quotation->id)->delay(now()->addSeconds(10));

        $validUntil = now()
            ->addDays($engine->getConfig()->config['valid_for_days'] ?? 30)
            ->toDateString();

        return response()->json([
            'data' => [
                'reference_code' => $refCode,
                'estimate_min_myr' => number_format($estimate->minMyr, 2),
                'estimate_max_myr' => number_format($estimate->maxMyr, 2),
                'estimate_eta_value' => $estimate->etaValue,
                'estimate_eta_unit' => $estimate->etaUnit,
                'breakdown' => $estimate->breakdown,
                'valid_until' => $validUntil,
            ],
            'message' => 'Quote saved. Your estimate has been emailed to you and I\'ll be in touch shortly.',
        ], 201);
    }
}
