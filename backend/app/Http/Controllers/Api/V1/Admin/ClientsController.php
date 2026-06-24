<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ClientsController extends Controller
{
    /**
     * Two callers share this: the builder's typeahead (short slice) and the
     * Customers page (paginated, with activity counts via ?paginate=1).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Client::query()->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('paginate')) {
            return ClientResource::collection(
                $query->withCount(['inquiries', 'quotations', 'orders'])->paginate(20),
            );
        }

        return ClientResource::collection($query->limit(10)->get());
    }

    /** Customer detail — the spine: their inquiries, quotations, and orders. */
    public function show(Client $client): ClientResource
    {
        $client->load([
            'inquiries' => fn ($q) => $q->latest(),
            'quotations' => fn ($q) => $q->latest('submitted_at'),
            'orders' => fn ($q) => $q->latest(),
        ])->loadCount(['inquiries', 'quotations', 'orders']);

        return new ClientResource($client);
    }

    public function store(Request $request): ClientResource
    {
        return new ClientResource(Client::create($this->validated($request)));
    }

    public function update(Request $request, Client $client): ClientResource
    {
        $client->update($this->validated($request, $client));

        return new ClientResource($client);
    }

    private function validated(Request $request, ?Client $client = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:200', Rule::unique('clients', 'email')->ignore($client?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'company' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:40'],
        ]);
    }
}
