<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServicePackageResource;
use App\Models\ServicePackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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

        return new ServicePackageResource(ServicePackage::create($data));
    }

    public function update(Request $request, ServicePackage $servicePackage): ServicePackageResource
    {
        $data = $request->validate($this->rules($servicePackage));

        $servicePackage->update($data);

        return new ServicePackageResource($servicePackage);
    }

    public function destroy(ServicePackage $servicePackage): JsonResponse
    {
        $servicePackage->delete();

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
