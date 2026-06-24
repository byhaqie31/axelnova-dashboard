<?php

namespace App\Http\Requests\Admin;

use App\Services\Quoting\PricingEngine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Validate against the merged builder config (pricing JSON + admin-managed
        // service_packages) — the exact catalog the builder offered — so DB-managed
        // packages (e.g. the Admin portal tiers) aren't rejected as "invalid".
        $config = PricingEngine::cachedFrontendConfig();
        $validAddonKeys = array_keys($config['addons'] ?? []);
        $validPackageKeys = array_keys($config['base_packages'] ?? []);

        return [
            // Client — either an existing id, or enough to upsert a new one.
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'name' => ['required_without:client_id', 'nullable', 'string', 'min:2', 'max:150'],
            'email' => ['required_without:client_id', 'nullable', 'email:rfc', 'max:200'],
            'phone' => ['nullable', 'string', 'max:30'],
            'company' => ['nullable', 'string', 'max:200'],

            // Pricing inputs — re-priced server-side with the same engine as the funnel.
            // A detailed quote is priced by its own composed sections, so the internal
            // pricing-basis package is optional there; standard quotes still require one.
            'package_key' => ['required_unless:document.layout,detailed', 'nullable', 'string', Rule::in($validPackageKeys)],
            'modifiers' => ['nullable', 'array'],
            'addon_keys' => ['nullable', 'array'],
            'addon_keys.*' => ['string', Rule::in($validAddonKeys)],
            'rush' => ['boolean'],
            'form_payload' => ['nullable', 'array'],

            // Optional custom validity date. When unset, send() defaults it to
            // sent_at + valid_for_days; when set, that custom date is kept.
            'expires_at' => ['nullable', 'date'],

            // Presentable document (line items + terms) for the PDF.
            'document' => ['nullable', 'array'],
            'document.project' => ['nullable', 'string', 'max:200'],
            'document.intro' => ['nullable', 'string', 'max:2000'],
            'document.items' => ['nullable', 'array'],
            'document.items.*.title' => ['required_with:document.items', 'string', 'max:200'],
            'document.items.*.desc' => ['nullable', 'string', 'max:500'],
            'document.items.*.qty' => ['nullable', 'numeric', 'min:0'],
            'document.items.*.unit' => ['nullable', 'string', 'max:40'],
            'document.items.*.rate' => ['nullable', 'numeric', 'min:0'],
            'document.terms' => ['nullable', 'array'],
            'document.terms.*' => ['string', 'max:300'],
            'document.deposit_pct' => ['nullable', 'integer', 'min:0', 'max:100'],
            'document.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],

            // Layout selector + the detailed/customized presentation blob. The
            // detailed builder stores its full content (sections, options, care,
            // summary, panels…) under document.payload; it's passed straight
            // through to the PDF by DocumentMapper, so it's validated loosely.
            'document.layout' => ['nullable', 'string', Rule::in(['standard', 'detailed'])],
            'document.payload' => ['nullable', 'array'],

            // Link back to the inquiry this was built from.
            'inquiry_id' => ['nullable', 'integer', 'exists:inquiries,id'],
        ];
    }
}
