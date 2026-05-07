<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicProjectsController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $projects = Project::where('active', true)
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        return ProjectResource::collection($projects);
    }

    public function show(string $slug): ProjectResource
    {
        $project = Project::where('slug', $slug)
            ->where('active', true)
            ->firstOrFail();

        return new ProjectResource($project);
    }
}
