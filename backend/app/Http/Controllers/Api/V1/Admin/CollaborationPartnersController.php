<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CollaborationPartner;
use App\Models\Inquiry;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Partners › Collaborations (docs/global/COLLABORATIONS.md §Admin): the studio
 * partners, their profile and the projects built with them. The slug never
 * changes here — the hand-built public page and the partner's colour scope hang
 * off it. Their leads come from InquiriesController (origin partner /
 * collaboration); adding a brand-new partner is still a data migration.
 */
class CollaborationPartnersController extends Controller
{
    /** An enquiry still needing attention. */
    private const OPEN_STATUSES = ['new', 'reviewing'];

    /** A site path ("/logos/x.svg") — never an outside or protocol-relative URL. */
    private const SITE_PATH = ['regex:#^/(?!/)#'];

    public function index(): JsonResponse
    {
        $partners = CollaborationPartner::query()
            ->withCount($this->counts())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $partners->map(fn (CollaborationPartner $partner) => $this->row($partner))->all(),
            // Studios asking to partner — the Applications tab's badge.
            'applications_new' => Inquiry::where('origin', 'collaboration')->where('status', 'new')->count(),
        ]);
    }

    public function show(CollaborationPartner $collaborationPartner): JsonResponse
    {
        return response()->json(['data' => $this->detail($collaborationPartner)]);
    }

    public function update(Request $request, CollaborationPartner $collaborationPartner): JsonResponse
    {
        $data = $request->validate([
            'slug' => ['prohibited'],
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:150'],
            'summary' => ['sometimes', 'required', 'string', 'max:200'],
            'story' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'logo_url' => ['sometimes', 'nullable', 'string', 'max:500', ...self::SITE_PATH],
            'symbol_url' => ['sometimes', 'nullable', 'string', 'max:500', ...self::SITE_PATH],
            'website_url' => ['sometimes', 'nullable', 'url:http,https', 'max:500'],
            'founder_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'founder_role' => ['sometimes', 'nullable', 'string', 'max:100'],
            'location' => ['sometimes', 'nullable', 'string', 'max:100'],
            'services' => ['sometimes', 'array', 'max:20'],
            'services.*' => ['required', 'string', 'max:60', 'distinct'],
            'partner_since' => ['sometimes', 'nullable', 'date'],
            'active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ]);

        $wasActive = $collaborationPartner->active;
        $collaborationPartner->fill($data);
        $fields = array_keys($collaborationPartner->getDirty());

        if ($fields !== []) {
            $collaborationPartner->save();
            // Flat strings — the Activity feed prints changes as "key: value".
            $collaborationPartner->logActivity('collaboration_partner.updated', ['fields' => implode(', ', $fields)]
                + ($wasActive !== $collaborationPartner->active ? ['visibility' => $collaborationPartner->active ? 'live' : 'hidden'] : []));
        }

        return response()->json(['data' => $this->detail($collaborationPartner->fresh())]);
    }

    /** Link an existing project. "Show publicly" defaults on only for co-builds (linkProject). */
    public function linkProject(Request $request, CollaborationPartner $collaborationPartner): JsonResponse
    {
        $data = $request->validate([
            'project_id' => [
                'required', 'integer', 'exists:projects,id',
                Rule::unique('collaboration_partner_project')->where('collaboration_partner_id', $collaborationPartner->id),
            ],
            'mode' => ['required', Rule::in(CollaborationPartner::MODES)],
            'role_note' => ['nullable', 'string', 'max:200'],
            'credit_public' => ['nullable', 'boolean'],
        ], [
            'project_id.unique' => 'That project is already linked to this partner.',
        ]);

        $project = Project::findOrFail($data['project_id']);
        $collaborationPartner->linkProject($project, $data['mode'], $data['role_note'] ?? null, $data['credit_public'] ?? null);
        $collaborationPartner->logActivity('collaboration_partner.project_linked', [
            'project' => $project->slug, 'mode' => $data['mode'],
        ]);

        return response()->json(['data' => $this->detail($collaborationPartner)], 201);
    }

    public function updateProject(Request $request, CollaborationPartner $collaborationPartner, Project $project): JsonResponse
    {
        $this->ensureLinked($collaborationPartner, $project);

        $data = $request->validate([
            'mode' => ['sometimes', 'required', Rule::in(CollaborationPartner::MODES)],
            'role_note' => ['sometimes', 'nullable', 'string', 'max:200'],
            'credit_public' => ['sometimes', 'boolean'],
        ]);

        if ($data !== []) {
            $collaborationPartner->projects()->updateExistingPivot($project->id, $data);
            $collaborationPartner->logActivity('collaboration_partner.project_updated', [
                'project' => $project->slug, 'fields' => implode(', ', array_keys($data)),
            ]);
        }

        return response()->json(['data' => $this->detail($collaborationPartner)]);
    }

    /** Remove the link only — the project itself is untouched. */
    public function unlinkProject(CollaborationPartner $collaborationPartner, Project $project): JsonResponse
    {
        $this->ensureLinked($collaborationPartner, $project);

        $collaborationPartner->projects()->detach($project->id);
        $collaborationPartner->logActivity('collaboration_partner.project_unlinked', ['project' => $project->slug]);

        return response()->json(['data' => $this->detail($collaborationPartner)]);
    }

    private function ensureLinked(CollaborationPartner $partner, Project $project): void
    {
        abort_unless($partner->projects()->whereKey($project->id)->exists(), 404);
    }

    private function counts(): array
    {
        return [
            'projects as projects_total',
            'publicProjects as projects_public',
            'enquiries as enquiries_open' => fn ($query) => $query->whereIn('status', self::OPEN_STATUSES),
            'enquiries as enquiries_new' => fn ($query) => $query->where('status', 'new'),
        ];
    }

    /** The list row (also the head of the detail payload). */
    private function row(CollaborationPartner $partner): array
    {
        return [
            'id' => $partner->id,
            'slug' => $partner->slug,
            'name' => $partner->name,
            'tagline' => $partner->tagline,
            'logo_url' => $partner->logo_url,
            'symbol_url' => $partner->symbol_url,
            'active' => (bool) $partner->active,
            'partner_since' => $partner->partner_since?->toDateString(),
            'sort_order' => (int) $partner->sort_order,
            'projects_total' => (int) $partner->projects_total,
            // A hidden partner shows nothing publicly, whatever its links say.
            'projects_public' => $partner->active ? (int) $partner->projects_public : 0,
            'enquiries_open' => (int) $partner->enquiries_open,
            'enquiries_new' => (int) $partner->enquiries_new,
        ];
    }

    private function detail(CollaborationPartner $partner): array
    {
        $partner->loadCount($this->counts());

        $projects = $partner->projects()
            ->orderBy('collaboration_partner_project.created_at')
            ->orderBy('projects.id')
            ->get();

        return $this->row($partner) + [
            'summary' => $partner->summary,
            'story' => $partner->story,
            'website_url' => $partner->website_url,
            'founder_name' => $partner->founder_name,
            'founder_role' => $partner->founder_role,
            'location' => $partner->location,
            'services' => $partner->services ?? [],
            'updated_at' => $partner->updated_at?->toISOString(),
            'projects' => $projects->map(function (Project $project) use ($partner) {
                $reason = $this->hiddenReason($partner, $project);

                return [
                    'id' => $project->id,
                    'slug' => $project->slug,
                    'name' => $project->name,
                    'active' => (bool) $project->active,
                    'mode' => $project->pivot->mode,
                    'role_note' => $project->pivot->role_note,
                    'credit_public' => (bool) $project->pivot->credit_public,
                    'public' => $reason === null,
                    'hidden_reason' => $reason,
                ];
            })->values()->all(),
        ];
    }

    /** Why the public site won't show this shared project — the server-side visibility rule, spelled out. */
    private function hiddenReason(CollaborationPartner $partner, Project $project): ?string
    {
        return match (true) {
            ! $partner->active => 'partner_hidden',
            ! $project->pivot->credit_public => 'credit_off',
            ! $project->active => 'project_inactive',
            default => null,
        };
    }
}
