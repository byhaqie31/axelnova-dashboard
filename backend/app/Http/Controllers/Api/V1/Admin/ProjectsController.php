<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ProjectsController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Project::query()->orderBy('sort_order')->orderByDesc('id');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return ProjectResource::collection($query->get());
    }

    public function show(Project $project): ProjectResource
    {
        return new ProjectResource($project);
    }

    public function store(Request $request): ProjectResource
    {
        $data = $request->validate($this->rules());

        return new ProjectResource(Project::create($data));
    }

    public function update(Request $request, Project $project): ProjectResource
    {
        $data = $request->validate($this->rules($project->id));

        $project->update($data);

        return new ProjectResource($project);
    }

    public function destroy(Project $project): JsonResponse
    {
        $project->delete();

        return response()->json(['message' => 'Project deleted.']);
    }

    private function rules(?int $ignoreId = null): array
    {
        return [
            'slug' => ['required', 'string', 'max:80', Rule::unique('projects', 'slug')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:500'],
            'long_description' => ['required', 'string'],
            'status' => ['required', 'in:live,soon,wip,planning'],
            'url' => ['nullable', 'url', 'max:500'],
            'repo' => ['nullable', 'url', 'max:500'],
            'tags' => ['array'],
            'tags.*' => ['string', 'max:50'],
            'stack' => ['array'],
            'stack.*' => ['string', 'max:50'],
            'featured' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'cover_image_url' => ['nullable', 'url', 'max:500'],
            'active' => ['boolean'],
        ];
    }
}
