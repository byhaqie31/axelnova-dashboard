<?php

namespace App\Http\Requests;

use App\Services\Quoting\PricingEngine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuoteRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Validate against the merged builder config (pricing JSON + admin-managed
        // service_packages) — the exact catalog the builder offered — so DB-managed
        // packages aren't rejected as "invalid".
        $config = PricingEngine::cachedFrontendConfig();
        $validAddonKeys = array_keys($config['addons'] ?? []);
        $validPackageKeys = array_keys($config['base_packages'] ?? []);

        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:200'],
            'phone' => ['required', 'string', 'max:30'],
            'company' => ['nullable', 'string', 'max:200'],
            'package_key' => ['required', 'string', Rule::in($validPackageKeys)],
            'modifiers' => ['nullable', 'array'],
            'addon_keys' => ['nullable', 'array'],
            'addon_keys.*' => ['string', Rule::in($validAddonKeys)],
            'rush' => ['boolean'],
            'form_payload' => ['required', 'array'],
            'form_payload.source' => ['nullable', 'string', 'max:100'],
            'form_payload.notes' => ['nullable', 'string', 'max:2000'],
            'website_url' => ['nullable', 'max:0'], // honeypot — must be empty
        ];
    }
}
