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

            // Pricing basis. null / omitted = fully bespoke (validated in after()).
            // Single-package sugar — a convenience for a one-entry packages[].
            'package_key' => ['nullable', 'string'],
            'modifiers' => ['nullable', 'array'],
            'addon_keys' => ['nullable', 'array'],
            'addon_keys.*' => ['string'],
            'rush' => ['nullable', 'boolean'],

            // Canonical multi-package shape. Each entry is a package + its own
            // modifiers/add-ons; mutually exclusive with the top-level sugar.
            'packages' => ['nullable', 'array'],
            'packages.*.package_key' => ['required_with:packages', 'string'],
            'packages.*.modifiers' => ['nullable', 'array'],
            'packages.*.addon_keys' => ['nullable', 'array'],
            'packages.*.addon_keys.*' => ['string'],

            // Document presentation fields written onto document.project / .intro
            // (the quotation's title + lead-in shown on the PDF). Optional — the
            // mapper falls back to a sensible default project when omitted.
            'project' => ['nullable', 'string', 'max:200'],
            'intro' => ['nullable', 'string', 'max:2000'],

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
            $packages = $this->input('packages');
            $topKey = $this->input('package_key');
            $topKey = ($topKey === '' ? null : $topKey);
            $lineItems = (array) $this->input('line_items', []);

            // Multi-package: validate each entry; the top-level sugar is off-limits.
            if (! empty($packages) && is_array($packages)) {
                if ($topKey !== null || $this->input('modifiers') || $this->input('addon_keys')) {
                    $validator->errors()->add(
                        'packages',
                        'Provide EITHER packages[] (multi-package) OR the top-level package_key/modifiers/addon_keys (single package), not both.',
                    );

                    return;
                }
                foreach ($packages as $i => $p) {
                    $key = is_array($p) ? ($p['package_key'] ?? null) : null;
                    if (! is_string($key) || ! $catalog->isQuotable($key)) {
                        $validator->errors()->add("packages.{$i}.package_key", $this->unknownPackageMessage((string) $key, $catalog));

                        continue;
                    }
                    $this->checkModifiers($validator, $catalog, $key, (array) ($p['modifiers'] ?? []), "packages.{$i}.modifiers");
                    $this->checkAddons($validator, $catalog, (array) ($p['addon_keys'] ?? []), "packages.{$i}.addon_keys");
                }

                return;
            }

            $modifiers = (array) $this->input('modifiers', []);
            $addonKeys = (array) $this->input('addon_keys', []);

            if ($topKey === null) {
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

            // Priced path (single-package sugar) — the package must be quotable.
            if (! $catalog->isQuotable($topKey)) {
                $validator->errors()->add('package_key', $this->unknownPackageMessage($topKey, $catalog));

                return;
            }

            $this->checkModifiers($validator, $catalog, $topKey, $modifiers, 'modifiers');
            $this->checkAddons($validator, $catalog, $addonKeys, 'addon_keys');
        });
    }

    private function unknownPackageMessage(string $key, ConnectorCatalog $catalog): string
    {
        return "Unknown package_key '{$key}'. Call list_catalog for the current keys. Valid quotable package keys: "
            .implode(', ', $catalog->packageKeys())
            .'. Pass package_key: null (single, no packages[]) for a fully bespoke quote.';
    }

    /** @param  array<string, mixed>  $modifiers */
    private function checkModifiers(Validator $validator, ConnectorCatalog $catalog, string $packageKey, array $modifiers, string $path): void
    {
        if ($modifiers === []) {
            return;
        }
        $validKeys = $catalog->validModifierKeys($packageKey);
        $unknown = array_values(array_diff(array_keys($modifiers), $validKeys));
        if ($unknown !== []) {
            $validator->errors()->add(
                $path,
                'Unknown modifier key(s) ['.implode(', ', $unknown)."] for package '{$packageKey}'. Valid modifier keys for this package: "
                    .($validKeys === [] ? '(none)' : implode(', ', $validKeys)).'.',
            );
        }
    }

    /** @param  list<string>  $addonKeys */
    private function checkAddons(Validator $validator, ConnectorCatalog $catalog, array $addonKeys, string $path): void
    {
        if ($addonKeys === []) {
            return;
        }
        $validKeys = $catalog->validAddonKeys();
        $unknown = array_values(array_diff($addonKeys, $validKeys));
        if ($unknown !== []) {
            $validator->errors()->add(
                $path,
                'Unknown add-on key(s) ['.implode(', ', $unknown).']. Valid add-on keys: '
                    .($validKeys === [] ? '(none)' : implode(', ', $validKeys)).'.',
            );
        }
    }
}
