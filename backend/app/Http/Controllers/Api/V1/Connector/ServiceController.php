<?php

namespace App\Http\Controllers\Api\V1\Connector;

use App\Http\Controllers\Api\V1\Connector\Concerns\EditsHiddenDrafts;
use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use App\Services\Connector\CatalogRecords;
use App\Support\SortOrder;
use App\Support\UniqueSlug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The MCP connector's SERVICES surface (connector v6) — in connector language a
 * "service" is a service_categories row (Web, Dashboard …) and its "packages"
 * are the cards inside it (ServicePackageController). Same rule as projects:
 *   • READ everything  — every service (Live or Hidden) with its packages.
 *   • WRITE hidden only — create lands Hidden, never the default, at the end of
 *     the order; update is PARTIAL and refused while the service is Live.
 *   • NEVER go Live, set the default, reorder, or delete — admin only.
 */
class ServiceController extends Controller
{
    use EditsHiddenDrafts;

    /** Every service in the public order, each with its packages (slim rows). */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => ServiceCategory::orderBy('sort_order')->get()
                ->map(fn (ServiceCategory $service) => CatalogRecords::service($service))
                ->all(),
        ]);
    }

    /** One service with its packages' full records. */
    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => CatalogRecords::service($this->find($id), fullPackages: true)]);
    }

    /** Always Hidden and never the default — active / is_default / sort_order aren't in the rule set. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $service = DB::transaction(fn () => ServiceCategory::create([
            ...$data,
            'slug' => filled($data['slug'] ?? null) ? $data['slug'] : UniqueSlug::within(ServiceCategory::query(), $data['name'], 60, 'service'),
            'active' => false,
            'is_default' => false,
            'sort_order' => SortOrder::placeNew(ServiceCategory::class, [], 0),
        ]));
        $service->logActivity('service_category.created', ['name' => $service->name, 'via' => 'mcp_connector']);

        return response()->json(['data' => CatalogRecords::service($service->fresh(), fullPackages: true)], 201);
    }

    /** Partial update of a HIDDEN service. The slug only changes when a slug is sent. */
    public function update(Request $request, int $id): JsonResponse
    {
        $service = $this->find($id);
        $this->assertHidden($service->active, 'service', 'go public on /services');

        $data = $request->validate($this->rules($service));
        if (blank($data['slug'] ?? null)) {
            unset($data['slug']);
        }

        DB::transaction(function () use ($service, $data) {
            $service->update($data);

            // A package's quote_key.category is its service's slug (the /services
            // CTA deep-links with it) — keep the wired packages in step.
            if ($service->wasChanged('slug')) {
                $service->packages()->whereNotNull('quote_key')->get()->each(
                    fn (ServicePackage $package) => $package->update(['quote_key' => [...$package->quote_key, 'category' => $service->slug]]),
                );
            }
        });
        $service->logActivity('service_category.updated', ['name' => $service->name, 'via' => 'mcp_connector']);

        return response()->json(['data' => CatalogRecords::service($service->fresh(), fullPackages: true)]);
    }

    /** Create requires every field but the slug; an update (existing service) is partial. */
    private function rules(?ServiceCategory $existing = null): array
    {
        $required = $existing ? ['sometimes', 'required'] : ['required'];

        return [
            'name' => [...$required, 'string', 'max:100'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('service_categories', 'slug')->ignore($existing?->id)],
            // Lucide icons only (the house icon set; never emojis).
            'icon' => [...$required, 'string', 'max:80', 'regex:/^i-lucide-[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => [...$required, 'string', 'max:2000'],
        ];
    }

    private function find(int $id): ServiceCategory
    {
        return ServiceCategory::find($id)
            ?? abort(404, "No service with id {$id}. Use list_services to find the right one.");
    }
}
