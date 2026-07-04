<?php

namespace App\Http\Controllers\Api\V1\Team;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Announcements — the workspace read-only feed (Task 6). Published rows only
 * (`published_at` not null AND <= now — a defensive guard against a
 * future-dated `published_at` ever leaking early, even though nothing in the
 * admin controller can currently produce one), audience 'team' or 'all'.
 * 'partners' rows exist in the table for a later phase (the partner portal)
 * but are deliberately excluded here — a team member never sees a
 * partners-only notice. Newest-published first.
 */
class AnnouncementsController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $announcements = Announcement::with('creator')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereIn('audience', ['team', 'all'])
            ->orderByDesc('published_at')
            ->latest('id')
            ->get();

        return AnnouncementResource::collection($announcements);
    }
}
