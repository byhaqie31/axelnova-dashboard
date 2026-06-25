<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceAddonResource;
use App\Models\ServiceAddon;
use App\Support\SortOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ServiceAddonsController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ServiceAddonResource::collection(
            ServiceAddon::orderBy('sort_order')->get(),
        );
    }

    public function show(ServiceAddon $serviceAddon): ServiceAddonResource
    {
        return new ServiceAddonResource($serviceAddon);
    }

    public function store(Request $request): ServiceAddonResource
    {
        $data = $request->validate($this->rules());

        $addon = DB::transaction(function () use ($data) {
            // Add-ons share one global list (no per-category scope yet).
            $data['sort_order'] = SortOrder::placeNew(
                ServiceAddon::class,
                [],
                (int) ($data['sort_order'] ?? 0),
            );
            return ServiceAddon::create($data);
        });

        return new ServiceAddonResource($addon);
    }

    public function update(Request $request, ServiceAddon $serviceAddon): ServiceAddonResource
    {
        $data = $request->validate($this->rules($serviceAddon));

        DB::transaction(function () use ($serviceAddon, $data) {
            $newOrder = (int) ($data['sort_order'] ?? $serviceAddon->sort_order);
            if ($newOrder !== (int) $serviceAddon->sort_order) {
                $data['sort_order'] = SortOrder::move($serviceAddon, $newOrder, []);
            }
            $serviceAddon->update($data);
        });

        return new ServiceAddonResource($serviceAddon);
    }

    public function destroy(ServiceAddon $serviceAddon): JsonResponse
    {
        DB::transaction(function () use ($serviceAddon) {
            $oldOrder = (int) $serviceAddon->sort_order;
            $serviceAddon->delete();
            SortOrder::removeFromScope(ServiceAddon::class, [], $oldOrder);
        });

        return response()->json(['message' => 'Add-on deleted.']);
    }

    private function rules(?ServiceAddon $existing = null): array
    {
        $keyRule = Rule::unique('service_addons', 'addon_key');
        if ($existing) {
            $keyRule->ignore($existing->id);
        }

        return [
            // Stable identifier stored on quotes (quotation_addons.addon_key); snake_case.
            'addon_key' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/', $keyRule],
            'label' => ['required', 'string', 'max:150'],
            'amount_myr' => ['required', 'numeric', 'min:0'],
            'sort_order' => ['integer', 'min:0'],
            'active' => ['boolean'],
        ];
    }
}
