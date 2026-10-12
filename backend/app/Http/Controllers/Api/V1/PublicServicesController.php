<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceCategoryResource;
use App\Models\ServiceCategory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicServicesController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $categories = ServiceCategory::where('active', true)
            ->with(['packages' => fn ($q) => $q->where('active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return ServiceCategoryResource::collection($categories);
    }
}
