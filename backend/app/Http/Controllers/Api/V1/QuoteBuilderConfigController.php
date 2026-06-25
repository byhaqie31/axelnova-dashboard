<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Quoting\PricingEngine;
use Illuminate\Http\JsonResponse;

class QuoteBuilderConfigController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => PricingEngine::cachedFrontendConfig()]);
    }
}
