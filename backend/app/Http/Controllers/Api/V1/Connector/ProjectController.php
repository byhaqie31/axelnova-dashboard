<?php

namespace App\Http\Controllers\Api\V1\Connector;

use App\Http\Controllers\Api\V1\Connector\Concerns\EditsHiddenDrafts;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\Connector\CatalogRecords;
use App\Support\UniqueSlug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The MCP connector's portfolio-projects surface (connector v6; see
 * docs/global/MCP-CONNECTOR.md). Same access model as the blog, with Live /
 * Hidden (`active`) standing in for published / draft:
 *   • READ everything  — a slim list and the full record of ANY project.
 *   • WRITE hidden only — create always lands Hidden, unfeatured, at the end of
 *     the order; update is PARTIAL and refused while the project is Live.
 *   • NEVER go Live, feature, reorder, link partners, or delete — admin only.
 *
 * `status` is the project's BUILD STAGE (live / soon / wip / planning) — shown
 * as a badge on the card — not its visibility; `visibility` says whether the
 * public site shows it at all.
 */
class ProjectController extends Controller
{
    use EditsHiddenDrafts;

    private const STAGES = ['live', 'soon', 'wip', 'planning'];

    /** Slim rows in the public order. visibility / q (name or slug) filters; per_page default 10, capped 25. */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'visibility' => ['nullable', 'in:live,hidden'],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ]);
        $perPage = min(25, max(1, (int) ($data['per_page'] ?? 10)));

        $query = Project::query()->orderBy('sort_order')->orderByDesc('id');
        if (! empty($data['visibility'])) {
            $query->where('active', $data['visibility'] === 'live');
        }
        if (! empty($data['q'])) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$data['q'].'%')->orWhere('slug', 'like', '%'.$data['q'].'%'));
        }

        $page = $query->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn (Project $project) => CatalogRecords::projectRow($project))->all(),
            'meta' => $this->pageMeta($page),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => CatalogRecords::project($this->find($id))]);
    }

    /** Always Hidden — active / featured / sort_order aren't in the rule set, so sent values are ignored. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $maxOrder = Project::max('sort_order');
        $project = Project::create([
            ...$data,
            'slug' => filled($data['slug'] ?? null) ? $data['slug'] : UniqueSlug::within(Project::query(), $data['name'], 80, 'project'),
            'tags' => array_values($data['tags'] ?? []),
            'stack' => array_values($data['stack'] ?? []),
            'featured' => false,
            'active' => false,
            'sort_order' => $maxOrder === null ? 0 : $maxOrder + 1,
        ]);
        $project->logActivity('project.created', ['name' => $project->name, 'via' => 'mcp_connector']);

        return response()->json(['data' => CatalogRecords::project($project->fresh())], 201);
    }

    /** Partial update of a HIDDEN project. The slug only changes when a slug is sent. */
    public function update(Request $request, int $id): JsonResponse
    {
        $project = $this->find($id);
        $this->assertHidden($project->active, 'project', 'go public on /projects');

        $data = $request->validate($this->rules($project));
        if (blank($data['slug'] ?? null)) {
            unset($data['slug']);
        }
        foreach (['tags', 'stack'] as $list) {
            if (array_key_exists($list, $data)) {
                $data[$list] = array_values($data[$list] ?? []);
            }
        }

        $project->update($data);
        $project->logActivity('project.updated', ['name' => $project->name, 'via' => 'mcp_connector']);

        return response()->json(['data' => CatalogRecords::project($project->fresh())]);
    }

    /** Create requires the content fields; an update (existing project) is partial. */
    private function rules(?Project $existing = null): array
    {
        $required = $existing ? ['sometimes', 'required'] : ['required'];

        return [
            'name' => [...$required, 'string', 'max:100'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('projects', 'slug')->ignore($existing?->id)],
            'description' => [...$required, 'string', 'max:500'],
            'long_description' => [...$required, 'string', 'max:20000'],
            'status' => [...$required, Rule::in(self::STAGES)],
            'url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'repo' => ['sometimes', 'nullable', 'url', 'max:500'],
            'tags' => ['sometimes', 'nullable', 'array', 'max:12'],
            'tags.*' => ['string', 'max:50'],
            'stack' => ['sometimes', 'nullable', 'array', 'max:20'],
            'stack.*' => ['string', 'max:50'],
            // Same guard as the admin: an absolute URL or a root-relative path
            // (/previews/<slug>.webp), never javascript: / data: in an <img src>.
            'cover_image_url' => ['sometimes', 'nullable', 'string', 'max:500', 'regex:#^(https?://|/[^/])#'],
        ];
    }

    private function find(int $id): Project
    {
        return Project::find($id)
            ?? abort(404, "No project with id {$id}. Use list_projects to find the right one.");
    }
}
