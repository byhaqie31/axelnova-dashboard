<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * The public testimonial wall feed. Only reviews that are BOTH published by an
 * admin AND consented by the client appear, and only the wall-safe fields are
 * exposed. Cached 1h; FeedbackObserver clears the key on every save/delete.
 */
class PublicTestimonialsController extends Controller
{
    public const CACHE_KEY = 'public_testimonials_v1';

    public function index(): JsonResponse
    {
        $testimonials = Cache::remember(self::CACHE_KEY, 3600, function () {
            return Feedback::query()
                ->where('status', 'published')
                ->where('publish_consent', true)
                ->orderByDesc('featured')
                ->orderBy('sort_order')
                ->orderByDesc('published_at')
                ->get()
                ->map(fn (Feedback $f) => [
                    'attribution_name' => $f->attribution_name,
                    'attribution_role' => $f->attribution_role,
                    'project_label' => $f->project_label,
                    'overall' => $f->overall,
                    'praise' => $f->praise,
                ])
                ->values()
                ->all();
        });

        return response()->json(['data' => $testimonials]);
    }
}
