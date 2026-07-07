<?php

namespace App\Http\Controllers\Api\V1\Connector;

use App\Http\Controllers\Controller;
use App\Services\Connector\ConnectorCatalog;
use Illuminate\Http\JsonResponse;

/**
 * Read-only catalog for the MCP connector (ability: connector:read). Serves the
 * same merged quote-builder config the public funnel reads, reshaped so the AI
 * has every valid package / modifier / add-on key before it drafts a quotation.
 */
class CatalogController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json((new ConnectorCatalog)->toArray());
    }
}
