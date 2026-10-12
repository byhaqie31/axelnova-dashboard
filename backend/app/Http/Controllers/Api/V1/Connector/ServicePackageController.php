<?php

namespace App\Http\Controllers\Api\V1\Connector;

use App\Http\Controllers\Api\V1\Connector\Concerns\EditsHiddenDrafts;
use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use App\Services\Connector\CatalogRecords;
use App\Services\Quoting\QuotePackageKey;
use App\Support\SortOrder;
use App\Support\UniqueSlug;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The MCP connector's PACKAGES surface (connector v6) — the cards inside a
 * service (ServiceController). Same rule as projects and services:
 *   • READ any package (Live or Hidden).
 *   • WRITE hidden only — create lands Hidden, unfeatured, at the end of its
 *     service; update is PARTIAL and refused while the package is EFFECTIVELY
 *     Live (its own flag AND its service Live — ServicePackage::isEffectivelyLive;
 *     a Live wired package also prices quotes, so an edit could re-price them).
 *     A Live-flagged package inside a Hidden service is editable, but can't be
 *     moved into a Live service (that would publish it).
 *   • NEVER go Live, feature, reorder, or delete — admin only; nor flip the
 *     package's own flag.
 *
 * `quote_package_key` wires the package to the quote builder (quote_key.package;
 * quote_key.category is always its service's slug). The key must be NEW — not
 * held by any other package row, Live or Hidden, nor priced by the legacy
 * pricing JSON (QuotePackageKey) — and a wired package needs price_max_myr,
 * since the engine prices a min–max range.
 */
class ServicePackageController extends Controller
{
    use EditsHiddenDrafts;

    private const ETA_UNITS = ['hour', 'day', 'week', 'month'];

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => CatalogRecords::package($this->find($id))]);
    }

    /** Always Hidden — active / featured / sort_order aren't in the rule set, so sent values are ignored. */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $service = ServiceCategory::findOrFail($data['service_id']);

        $package = DB::transaction(fn () => ServicePackage::create([
            ...$this->columns($data),
            'service_category_id' => $service->id,
            'slug' => filled($data['slug'] ?? null)
                ? $data['slug']
                : UniqueSlug::within(ServicePackage::where('service_category_id', $service->id), $data['name'], 80, 'package'),
            'quote_key' => $this->quoteKey($data['quote_package_key'] ?? null, $service),
            'featured' => false,
            'active' => false,
            'sort_order' => SortOrder::placeNew(ServicePackage::class, ['service_category_id' => $service->id], 0),
        ]));
        $package->logActivity('service_package.created', ['name' => $package->name, 'via' => 'mcp_connector']);

        return response()->json(['data' => CatalogRecords::package($package->fresh())], 201);
    }

    /**
     * Partial update of an effectively HIDDEN package (it, or its service, is
     * Hidden). The slug only changes when a slug is sent; a move to another
     * service appends it there (re-suffixing its slug only if it would collide)
     * and re-points its quote_key.category — refused when the move itself would
     * make the package effectively Live.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $package = $this->find($id);
        $this->assertHidden($package->isEffectivelyLive(), 'package', 'go public on /services (and re-price quotes, if it is wired to the quote builder)');

        $data = $this->validated($request, $package);

        $target = isset($data['service_id']) && (int) $data['service_id'] !== (int) $package->service_category_id
            ? ServiceCategory::findOrFail($data['service_id'])
            : null;
        if ($target && $package->isEffectivelyLive(in: $target)) {
            throw ValidationException::withMessages([
                'service_id' => ['Moving this package into a Live service would publish it. Ask the founder to hide the package in the admin first.'],
            ]);
        }

        DB::transaction(function () use ($package, $data, $target) {
            $attrs = $this->columns($data);
            $service = $package->category;

            if (filled($data['slug'] ?? null)) {
                $attrs['slug'] = $data['slug'];
            }

            if ($target) {
                $service = $target;
                $attrs['sort_order'] = SortOrder::placeNew(ServicePackage::class, ['service_category_id' => $service->id], 0);
                SortOrder::removeFromScope(ServicePackage::class, ['service_category_id' => $package->service_category_id], (int) $package->sort_order);
                $attrs['service_category_id'] = $service->id;
                $attrs['slug'] ??= UniqueSlug::within(ServicePackage::where('service_category_id', $service->id), $package->slug, 80, 'package');
            }

            if (array_key_exists('quote_package_key', $data)) {
                $attrs['quote_key'] = $this->quoteKey($data['quote_package_key'], $service);
            } elseif ($package->quote_key && $service->id !== $package->service_category_id) {
                $attrs['quote_key'] = $this->quoteKey($package->quote_key['package'] ?? null, $service);
            }

            $package->update($attrs);
        });
        $package->logActivity('service_package.updated', ['name' => $package->name, 'via' => 'mcp_connector']);

        return response()->json(['data' => CatalogRecords::package($package->fresh())]);
    }

    /**
     * Validate a create (no $existing — every card field required) or a partial
     * update. The cross-field checks run against the package as it will be after
     * this write, so a partial update can't break an unsent field's invariant.
     */
    private function validated(Request $request, ?ServicePackage $existing = null): array
    {
        $required = $existing ? ['sometimes', 'required'] : ['required'];
        $serviceId = $request->input('service_id', $existing?->service_category_id);
        $after = fn (string $field, mixed $current) => $request->has($field) ? $request->input($field) : $current;

        $validator = Validator::make($request->all(), [
            'service_id' => [...$required, 'integer', 'exists:service_categories,id'],
            'name' => [...$required, 'string', 'max:100'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('service_packages', 'slug')->where('service_category_id', $serviceId)->ignore($existing?->id)],
            'tagline' => [...$required, 'string', 'max:200'],
            'price_min_myr' => [...$required, 'numeric', 'min:0'],
            'price_max_myr' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'unit' => [...$required, 'string', 'max:50'],
            'duration_text' => [...$required, 'string', 'max:50'],
            'eta_value' => [...$required, 'integer', 'min:1', 'max:999'],
            'eta_unit' => [...$required, Rule::in(self::ETA_UNITS)],
            'revisions' => ['sometimes', 'nullable', 'string', 'max:50'],
            'features' => [...$required, 'array', 'min:1', 'max:20'],
            'features.*' => ['string', 'max:300'],
            'cta' => ['sometimes', 'nullable', 'string', 'max:100'],
            'quote_package_key' => ['sometimes', 'nullable', 'string', 'max:80', 'regex:/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', $this->newKeyRule($existing)],
        ], [
            'quote_package_key.regex' => 'The quote package key must be snake_case, e.g. web_landing_sprint.',
        ]);

        $validator->after(function ($v) use ($after, $existing) {
            $min = $after('price_min_myr', $existing?->price_min_myr);
            $max = $after('price_max_myr', $existing?->price_max_myr);
            $key = $after('quote_package_key', $existing?->quote_key['package'] ?? null);

            if (is_numeric($min) && is_numeric($max) && (float) $max < (float) $min) {
                $v->errors()->add('price_max_myr', 'price_max_myr must be at least price_min_myr.');
            }
            if (filled($key) && blank($max)) {
                $v->errors()->add('price_max_myr', 'A package wired to the quote builder (quote_package_key) needs price_max_myr — the engine prices a min–max range. Set it, or send quote_package_key: null to unwire it.');
            }
        });

        return $validator->validate();
    }

    /** The key must be new: not another row's (Live or Hidden), not a legacy pricing-JSON key. Keeping its own key is fine. */
    private function newKeyRule(?ServicePackage $existing): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($existing) {
            if (! is_string($value) || $value === ($existing?->quote_key['package'] ?? null)) {
                return;
            }
            if ($holder = QuotePackageKey::heldBy($value, $existing?->id)) {
                $fail(QuotePackageKey::takenMessage($value, $holder));
            } elseif (QuotePackageKey::inPricingConfig($value)) {
                $fail("The quote package key \"{$value}\" is already priced by the legacy pricing config. Pick a new key.");
            }
        };
    }

    /** The validated card fields that map 1:1 onto columns. */
    private function columns(array $data): array
    {
        $columns = Arr::except($data, ['service_id', 'slug', 'quote_package_key']);
        if (isset($columns['features'])) {
            $columns['features'] = array_values($columns['features']);
        }

        return $columns;
    }

    /** @return array{category: string, package: string}|null */
    private function quoteKey(?string $key, ServiceCategory $service): ?array
    {
        return filled($key) ? ['category' => $service->slug, 'package' => $key] : null;
    }

    private function find(int $id): ServicePackage
    {
        return ServicePackage::with('category')->find($id)
            ?? abort(404, "No package with id {$id}. Use list_services to find the right one.");
    }
}
