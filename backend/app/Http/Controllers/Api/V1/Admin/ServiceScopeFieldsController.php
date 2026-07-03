<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceScopeFieldRequest;
use App\Http\Resources\ServiceScopeFieldResource;
use App\Models\ServiceScopeField;
use App\Support\SortOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ServiceScopeFieldsController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ServiceScopeField::orderBy('sort_order');

        if ($request->filled('service_category_id')) {
            $query->where('service_category_id', $request->service_category_id);
        }

        return ServiceScopeFieldResource::collection($query->get());
    }

    public function show(ServiceScopeField $serviceScopeField): ServiceScopeFieldResource
    {
        return new ServiceScopeFieldResource($serviceScopeField);
    }

    public function store(ServiceScopeFieldRequest $request): ServiceScopeFieldResource
    {
        $data = $request->validated();
        $data['config'] = $this->normalizeConfig($data['type'], $data['config'] ?? []);
        $data['applies_to'] = array_values($data['applies_to'] ?? []);

        $field = DB::transaction(function () use ($data) {
            $data['sort_order'] = SortOrder::placeNew(
                ServiceScopeField::class,
                ['service_category_id' => $data['service_category_id']],
                (int) ($data['sort_order'] ?? 0),
            );

            return ServiceScopeField::create($data);
        });

        return new ServiceScopeFieldResource($field);
    }

    public function update(ServiceScopeFieldRequest $request, ServiceScopeField $serviceScopeField): ServiceScopeFieldResource
    {
        $data = $request->validated();
        $data['config'] = $this->normalizeConfig($data['type'], $data['config'] ?? []);
        $data['applies_to'] = array_values($data['applies_to'] ?? []);

        DB::transaction(function () use ($serviceScopeField, $data) {
            $scope = ['service_category_id' => (int) $data['service_category_id']];
            $newOrder = (int) ($data['sort_order'] ?? $serviceScopeField->sort_order);
            if ($newOrder !== (int) $serviceScopeField->sort_order) {
                $data['sort_order'] = SortOrder::move($serviceScopeField, $newOrder, $scope);
            }
            $serviceScopeField->update($data);
        });

        return new ServiceScopeFieldResource($serviceScopeField);
    }

    public function destroy(ServiceScopeField $serviceScopeField): JsonResponse
    {
        Gate::authorize('hard-delete');

        DB::transaction(function () use ($serviceScopeField) {
            $scope = ['service_category_id' => (int) $serviceScopeField->service_category_id];
            $oldOrder = (int) $serviceScopeField->sort_order;
            $serviceScopeField->delete();
            SortOrder::removeFromScope(ServiceScopeField::class, $scope, $oldOrder);
        });

        return response()->json(['message' => 'Scope field deleted.']);
    }

    /** Keep only the keys that belong to the chosen type, with correct casts. */
    private function normalizeConfig(string $type, array $config): array
    {
        return match ($type) {
            'slider' => [
                'min' => (int) ($config['min'] ?? 1),
                'max' => (int) ($config['max'] ?? 10),
                'default' => (int) ($config['default'] ?? ($config['min'] ?? 1)),
                'unit' => (string) ($config['unit'] ?? ''),
                'free_threshold' => (int) ($config['free_threshold'] ?? 0),
                'price_per_unit' => (float) ($config['price_per_unit'] ?? 0),
            ],
            'toggle' => [
                'amount' => (float) ($config['amount'] ?? 0),
                'default' => (bool) ($config['default'] ?? false),
            ],
            'select' => [
                'default' => (string) ($config['default'] ?? ($config['options'][0]['value'] ?? '')),
                'options' => array_values(array_map(fn ($o) => [
                    'value' => (string) ($o['value'] ?? ''),
                    'label' => (string) ($o['label'] ?? ''),
                    'amount' => (float) ($o['amount'] ?? 0),
                ], $config['options'] ?? [])),
            ],
            default => $config,
        };
    }
}
