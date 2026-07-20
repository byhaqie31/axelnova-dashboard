<?php

namespace App\Observers;

use App\Http\Controllers\Api\V1\PublicTestimonialsController;
use App\Models\Feedback;
use Illuminate\Support\Facades\Cache;

class FeedbackObserver
{
    public function saved(Feedback $feedback): void
    {
        Cache::forget(PublicTestimonialsController::CACHE_KEY);
    }

    public function deleted(Feedback $feedback): void
    {
        Cache::forget(PublicTestimonialsController::CACHE_KEY);
    }
}
