<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Quoting\PricingEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class QuoteBuilderConfigController extends Controller
{
    public function show(): JsonResponse
    {
        $config = Cache::remember('quote_builder_config_v1', 3600, function () {
            return PricingEngine::active()->configForFrontend();
        });

        return response()->json(['data' => $config]);
    }
}
