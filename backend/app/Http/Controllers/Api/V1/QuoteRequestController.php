<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuoteRequestRequest;
use App\Http\Resources\QuoteRequestResource;
use App\Jobs\NotifyAdminJob;
use App\Jobs\SendClientQuoteEmail;
use App\Models\QuoteRequest;
use App\Services\Quoting\PricingEngine;
use App\Services\Quoting\QuoteRequestInput;
use App\Support\ReferenceCodeGenerator;
use Illuminate\Http\JsonResponse;

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
        );

        $estimate = $engine->calculate($input);
        $refCode = ReferenceCodeGenerator::generate();

        $quoteRequest = QuoteRequest::create([
            'reference_code' => $refCode,
            'name' => $input->name,
            'email' => $input->email,
            'phone' => $input->phone,
            'company' => $input->company,
            'service_category_id' => $request->input('service_category_id'),
            'service_package_id' => $request->input('service_package_id'),
            'pricing_config_id' => $engine->getConfig()->id,
            'form_payload' => array_merge($request->input('form_payload', []), [
                'package_key' => $input->packageKey,
                'modifiers' => $input->modifiers,
                'addon_keys' => $input->addonKeys,
                'rush' => $input->rush,
                'breakdown' => $estimate->breakdown,
            ]),
            'estimate_min_myr' => $estimate->minMyr,
            'estimate_max_myr' => $estimate->maxMyr,
            'estimate_weeks' => $estimate->weeks,
            'status' => 'new',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'submitted_at' => now(),
        ]);

        $addonDefs = $engine->getConfig()->config['addons'] ?? [];
        foreach ($input->addonKeys as $key) {
            if (isset($addonDefs[$key])) {
                $quoteRequest->addons()->create([
                    'addon_key' => $key,
                    'addon_label' => $addonDefs[$key]['label'],
                    'amount_myr' => $addonDefs[$key]['amount'],
                ]);
            }
        }

        SendClientQuoteEmail::dispatch($quoteRequest->id);
        NotifyAdminJob::dispatch($quoteRequest->id);

        $validUntil = now()
            ->addDays($engine->getConfig()->config['valid_for_days'] ?? 30)
            ->toDateString();

        return response()->json([
            'data' => [
                'reference_code' => $refCode,
                'estimate_min_myr' => number_format($estimate->minMyr, 2),
                'estimate_max_myr' => number_format($estimate->maxMyr, 2),
                'estimate_weeks' => $estimate->weeks,
                'breakdown' => $estimate->breakdown,
                'valid_until' => $validUntil,
            ],
            'message' => 'Quote saved. Your estimate has been emailed to you and I\'ll be in touch shortly.',
        ], 201);
    }
}
