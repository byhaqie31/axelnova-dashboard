<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceCategoryResource;
use App\Models\ServiceCategory;
use App\Support\SortOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ServiceCategoriesController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ServiceCategoryResource::collection(
            ServiceCategory::with(['packages' => fn ($q) => $q->orderBy('sort_order')])
                ->orderBy('sort_order')
                ->get(),
        );
    }

    public function show(ServiceCategory $serviceCategory): ServiceCategoryResource
    {
        $serviceCategory->load(['packages' => fn ($q) => $q->orderBy('sort_order')]);

        return new ServiceCategoryResource($serviceCategory);
    }

    public function store(Request $request): ServiceCategoryResource
    {
        $data = $request->validate($this->rules());

        $category = DB::transaction(function () use ($data) {
            $data['sort_order'] = SortOrder::placeNew(ServiceCategory::class, [], (int) ($data['sort_order'] ?? 0));
            $category = ServiceCategory::create($data);
            if ($category->is_default) {
                ServiceCategory::where('id', '!=', $category->id)->update(['is_default' => false]);
            }
            return $category;
        });

        return new ServiceCategoryResource($category);
    }

    public function update(Request $request, ServiceCategory $serviceCategory): ServiceCategoryResource
    {
        $data = $request->validate($this->rules($serviceCategory->id));

        DB::transaction(function () use ($serviceCategory, $data) {
            if (isset($data['sort_order']) && (int) $data['sort_order'] !== (int) $serviceCategory->sort_order) {
                $data['sort_order'] = SortOrder::move($serviceCategory, (int) $data['sort_order'], []);
            }
            $serviceCategory->update($data);
            if ($serviceCategory->is_default) {
                ServiceCategory::where('id', '!=', $serviceCategory->id)->update(['is_default' => false]);
            }
        });

        return new ServiceCategoryResource($serviceCategory);
    }

    public function destroy(ServiceCategory $serviceCategory): JsonResponse
    {
        DB::transaction(function () use ($serviceCategory) {
            $oldOrder = (int) $serviceCategory->sort_order;
            $serviceCategory->delete();
            SortOrder::removeFromScope(ServiceCategory::class, [], $oldOrder);
        });

        return response()->json(['message' => 'Category deleted.']);
    }

    private function rules(?int $ignoreId = null): array
    {
        return [
            'slug' => ['required', 'string', 'max:60', Rule::unique('service_categories', 'slug')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:100'],
            'icon' => ['required', 'string', 'max:80'],
            'description' => ['required', 'string'],
            'sort_order' => ['integer', 'min:0'],
            'active' => ['boolean'],
            'is_default' => ['boolean'],
        ];
    }
}
