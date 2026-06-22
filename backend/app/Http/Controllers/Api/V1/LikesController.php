<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EntityLike;
use App\Models\Project;
use App\Models\ServicePackage;
use App\Support\AnalyticsHash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LikesController extends Controller
{
    /** Likeable entity types → their model, for existence checks. */
    private const TYPES = [
        'project' => Project::class,
        'service_package' => ServicePackage::class,
    ];

    /**
     * Toggle an anonymous like for an entity. Deduped per visitor by hashed IP
     * (the table's unique constraint), with an optional client cookie_id for
     * cross-session continuity. Returns the new liked state + fresh count.
     */
    public function toggle(Request $request, string $type, int $id): JsonResponse
    {
        if (! isset(self::TYPES[$type])) {
            abort(404);
        }

        $model = self::TYPES[$type];
        if (! $model::whereKey($id)->exists()) {
            abort(404);
        }

        $request->validate(['cookie_id' => ['nullable', 'string', 'max:64']]);
        $ipHash = AnalyticsHash::forIp($request->ip());

        $existing = EntityLike::where('entity_type', $type)
            ->where('entity_id', $id)
            ->where('ip_hash', $ipHash)
            ->first();

        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            EntityLike::create([
                'entity_type' => $type,
                'entity_id' => $id,
                'ip_hash' => $ipHash,
                'cookie_id' => $request->input('cookie_id'),
            ]);
            $liked = true;
        }

        $count = EntityLike::where('entity_type', $type)->where('entity_id', $id)->count();

        return response()->json(['liked' => $liked, 'count' => $count]);
    }
}
