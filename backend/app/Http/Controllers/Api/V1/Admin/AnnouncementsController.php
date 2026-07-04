<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Announcements — the founder's post/edit surface (Task 6). Every row is
 * authored here; the team reads a filtered feed at /v1/team/announcements
 * (published + audience team|all). 'partners' audience is a forward hook for
 * a later phase (the partner portal doesn't read announcements yet) — this
 * controller just stores the value, no partner-facing behaviour lives here.
 *
 * Publishing is a boolean intent (`published` in the request), translated to
 * the `published_at` timestamp:
 *   - true  → sets `published_at` to now() ONCE — only if the row is
 *             currently a draft (null). Re-sending `published: true` on an
 *             already-published row leaves its original timestamp alone.
 *   - false → clears `published_at` back to null (revert to draft).
 * There's no delete endpoint — "unpublish" IS the retraction action, which is
 * why publish/unpublish live entirely in `update()`.
 */
class AnnouncementsController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AnnouncementResource::collection(
            Announcement::with('creator')->latest()->latest('id')->get()
        );
    }

    public function store(Request $request): AnnouncementResource
    {
        $data = $this->validatePayload($request, creating: true);
        $publish = $data['published'] ?? false;
        unset($data['published']);

        $announcement = Announcement::create([
            ...$data,
            'created_by' => $request->user()->id,
            'published_at' => $publish ? now() : null,
        ]);

        return new AnnouncementResource($announcement->load('creator'));
    }

    /**
     * Edit title/body/audience and/or toggle publish state. `published` is
     * never written verbatim — it's translated against the row's CURRENT
     * `published_at` so a repeated "publish" is a no-op on the timestamp
     * (see the class docblock for the exact semantics).
     */
    public function update(Request $request, Announcement $announcement): AnnouncementResource
    {
        $data = $this->validatePayload($request, creating: false);

        if (array_key_exists('published', $data)) {
            $data['published_at'] = $data['published']
                ? ($announcement->published_at ?? now())
                : null;
        }
        unset($data['published']);

        $announcement->update($data);

        return new AnnouncementResource($announcement->fresh()->load('creator'));
    }

    /**
     * Shared create/update validation. On create, title/body/audience are all
     * required-and-valid; on update every field is `sometimes` so a PATCH can
     * touch just one (e.g. only flipping `published`).
     */
    private function validatePayload(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'title' => [$required, 'string', 'max:200'],
            'body' => [$required, 'string', 'max:10000'],
            'audience' => [$required, Rule::in(['team', 'partners', 'all'])],
            'published' => ['sometimes', 'boolean'],
        ]);
    }
}
