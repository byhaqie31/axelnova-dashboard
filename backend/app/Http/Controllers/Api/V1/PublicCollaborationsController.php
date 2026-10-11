<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CollaborationPartnerResource;
use App\Models\CollaborationPartner;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Public, read-only: the studio partners behind /collaborations. */
class PublicCollaborationsController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $partners = CollaborationPartner::active()
            ->withCount(['publicProjects as projects_count'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return CollaborationPartnerResource::collection($partners);
    }

    public function show(string $slug): CollaborationPartnerResource
    {
        $partner = CollaborationPartner::active()
            ->where('slug', $slug)
            ->with(['publicProjects' => fn ($q) => $q->withCount('likes')->orderBy('sort_order')->orderByDesc('projects.id')])
            ->firstOrFail();

        return new CollaborationPartnerResource($partner);
    }
}
