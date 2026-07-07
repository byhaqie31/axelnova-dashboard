<?php

namespace App\Http\Requests\Connector;

use App\Services\Connector\ConnectorCatalog;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the MCP connector's draft-quotation contract. Two shapes share one
 * request:
 *   • priced   — package_key set → modifiers/addon_keys/rush are catalog keys.
 *   • bespoke  — package_key null → line_items[] carry the whole quote.
 *
 * Beyond the structural rules, the after() hook enforces the catalog-aware rules
 * (unknown package/modifier/add-on keys, bespoke needs line_items) with
 * INSTRUCTIVE messages that list the valid keys — the MCP surfaces these verbatim
 * to Claude, which self-corrects, so terse errors would waste a round-trip.
 */
class DraftQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Client — name + email required; upserted by email downstream.
            'client' => ['required', 'array'],
            'client.name' => ['required', 'string', 'min:2', 'max:150'],
            'client.email' => ['required', 'email:rfc', 'max:200'],
            'client.phone' => ['nullable', 'string', 'max:30'],
            'client.company' => ['nullable', 'string', 'max:200'],

            // Pricing basis. null = fully bespoke (validated in after()).
            'package_key' => ['nullable', 'string'],
            'modifiers' => ['nullable', 'array'],
            'addon_keys' => ['nullable', 'array'],
            'addon_keys.*' => ['string'],
            'rush' => ['nullable', 'boolean'],

            // Bespoke line items (also allowed as extras on a priced quote —
            // stored, never added to the engine estimate).
            'line_items' => ['nullable', 'array'],
            'line_items.*.label' => ['required_with:line_items', 'string', 'max:200'],
            'line_items.*.description' => ['nullable', 'string', 'max:1000'],
            'line_items.*.amount_myr' => ['required_with:line_items', 'numeric', 'min:0'],

            // AI's review aids — stored on the draft for the admin.
            'assumptions' => ['nullable', 'array'],
            'assumptions.*' => ['string', 'max:500'],
            'open_questions' => ['nullable', 'array'],
            'open_questions.*' => ['string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // Only run catalog-aware checks once the structural rules hold, so we
            // never read malformed input (e.g. a non-array `modifiers`).
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $catalog = new ConnectorCatalog;
            $packageKey = $this->input('package_key');
            $packageKey = ($packageKey === '' ? null : $packageKey);
            $modifiers = (array) $this->input('modifiers', []);
            $addonKeys = (array) $this->input('addon_keys', []);
            $lineItems = (array) $this->input('line_items', []);

            if ($packageKey === null) {
                // Fully bespoke — line items are the whole quote.
                if ($lineItems === []) {
                    $validator->errors()->add(
                        'line_items',
                        'line_items is required and must contain at least one item for a bespoke quote (package_key: null). Each item needs a label and amount_myr.',
                    );
                }
                if ($modifiers !== []) {
                    $validator->errors()->add(
                        'modifiers',
                        'modifiers only apply when package_key is set. Omit modifiers for a bespoke quote (package_key: null).',
                    );
                }
                if ($addonKeys !== []) {
                    $validator->errors()->add(
                        'addon_keys',
                        'addon_keys only apply when package_key is set. Put bespoke extras in line_items instead.',
                    );
                }

                return;
            }

            // Priced path — the package must exist and be quotable.
            if (! $catalog->isQuotable($packageKey)) {
                $validator->errors()->add(
                    'package_key',
                    "Unknown package_key '{$packageKey}'. Call list_catalog for the current keys. Valid quotable package keys: "
                        .implode(', ', $catalog->packageKeys())
                        .'. Pass package_key: null for a fully bespoke quote.',
                );

                return;
            }

            if ($modifiers !== []) {
                $validKeys = $catalog->validModifierKeys($packageKey);
                $unknown = array_values(array_diff(array_keys($modifiers), $validKeys));
                if ($unknown !== []) {
                    $validator->errors()->add(
                        'modifiers',
                        'Unknown modifier key(s) ['.implode(', ', $unknown)."] for package '{$packageKey}'. Valid modifier keys for this package: "
                            .($validKeys === [] ? '(none)' : implode(', ', $validKeys)).'.',
                    );
                }
            }

            if ($addonKeys !== []) {
                $validKeys = $catalog->validAddonKeys();
                $unknown = array_values(array_diff($addonKeys, $validKeys));
                if ($unknown !== []) {
                    $validator->errors()->add(
                        'addon_keys',
                        'Unknown add-on key(s) ['.implode(', ', $unknown).']. Valid add-on keys: '
                            .($validKeys === [] ? '(none)' : implode(', ', $validKeys)).'.',
                    );
                }
            }
        });
    }
}
