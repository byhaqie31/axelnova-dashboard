<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServicePackageResource;
use App\Models\ServicePackage;
use App\Support\SortOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ServicePackagesController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ServicePackage::with('category')->orderBy('sort_order');

        if ($request->filled('service_category_id')) {
            $query->where('service_category_id', $request->service_category_id);
        }

        return ServicePackageResource::collection($query->get());
    }

    public function show(ServicePackage $servicePackage): ServicePackageResource
    {
        $servicePackage->load('category');

        return new ServicePackageResource($servicePackage);
    }

    public function store(Request $request): ServicePackageResource
    {
        $data = $request->validate($this->rules());

        $package = DB::transaction(function () use ($data) {
            $data['sort_order'] = SortOrder::placeNew(
                ServicePackage::class,
                ['service_category_id' => $data['service_category_id']],
                (int) ($data['sort_order'] ?? 0),
            );
            return ServicePackage::create($data);
        });

        return new ServicePackageResource($package);
    }

    public function update(Request $request, ServicePackage $servicePackage): ServicePackageResource
    {
        $data = $request->validate($this->rules($servicePackage));

        DB::transaction(function () use ($servicePackage, $data) {
            $oldCategory = (int) $servicePackage->service_category_id;
            $newCategory = (int) $data['service_category_id'];
            $oldOrder = (int) $servicePackage->sort_order;
            $newOrder = (int) ($data['sort_order'] ?? $oldOrder);

            if ($newCategory !== $oldCategory) {
                // Moved to a new category: close gap in old, then place in new.
                // We update the category first so SortOrder::placeNew sees the row in the new scope.
                $servicePackage->update(['service_category_id' => $newCategory]);
                SortOrder::removeFromScope(ServicePackage::class, ['service_category_id' => $oldCategory], $oldOrder);
                $data['sort_order'] = SortOrder::placeNew(
                    ServicePackage::class,
                    ['service_category_id' => $newCategory],
                    $newOrder,
                );
            } elseif ($newOrder !== $oldOrder) {
                $data['sort_order'] = SortOrder::move(
                    $servicePackage,
                    $newOrder,
                    ['service_category_id' => $newCategory],
                );
            }

            $servicePackage->update($data);
        });

        return new ServicePackageResource($servicePackage);
    }

    public function destroy(ServicePackage $servicePackage): JsonResponse
    {
        DB::transaction(function () use ($servicePackage) {
            $oldCategory = (int) $servicePackage->service_category_id;
            $oldOrder = (int) $servicePackage->sort_order;
            $servicePackage->delete();
            SortOrder::removeFromScope(ServicePackage::class, ['service_category_id' => $oldCategory], $oldOrder);
        });

        return response()->json(['message' => 'Package deleted.']);
    }

    private function rules(?ServicePackage $existing = null): array
    {
        // Slug must be unique within its category. If updating, scope the unique check to the new (or unchanged) category_id.
        $categoryId = request()->input('service_category_id', $existing?->service_category_id);

        $slugRule = Rule::unique('service_packages', 'slug')
            ->where('service_category_id', $categoryId);

        if ($existing) {
            $slugRule->ignore($existing->id);
        }

        return [
            'service_category_id' => ['required', 'exists:service_categories,id'],
            'slug' => ['required', 'string', 'max:80', $slugRule],
            'name' => ['required', 'string', 'max:100'],
            'tagline' => ['required', 'string', 'max:200'],
            'price_min_myr' => ['required', 'numeric', 'min:0'],
            'price_max_myr' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:50'],
            'duration_text' => ['required', 'string', 'max:50'],
            'eta_value' => ['required', 'integer', 'min:1', 'max:999'],
            'eta_unit' => ['required', 'string', 'in:hour,day,week,month'],
            'revisions' => ['nullable', 'string', 'max:50'],
            'featured' => ['boolean'],
            'features' => ['required', 'array'],
            'features.*' => ['string', 'max:300'],
            'cta' => ['nullable', 'string', 'max:100'],
            'quote_key' => ['nullable', 'array'],
            'quote_key.category' => ['required_with:quote_key', 'string', 'max:60'],
            'quote_key.package' => ['required_with:quote_key', 'string', 'max:80'],
            'sort_order' => ['integer', 'min:0'],
            'active' => ['boolean'],
        ];
    }
}
