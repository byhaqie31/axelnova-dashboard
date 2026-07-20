<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminFeedbackStoreRequest;
use App\Http\Requests\Admin\AdminFeedbackUpdateRequest;
use App\Http\Resources\FeedbackResource;
use App\Jobs\RequestFeedbackJob;
use App\Models\Feedback;
use App\Models\Order;
use App\Support\DocumentType;
use App\Support\ReferenceCodeGenerator;
use App\Support\SortOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class FeedbackController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Feedback::with('order')->latest('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Stat tiles ride along so the index is one round-trip.
        $stats = [
            'total' => Feedback::count(),
            'pending' => Feedback::where('status', 'pending')->count(),
            'published' => Feedback::where('status', 'published')->count(),
            'avg_overall' => round((float) Feedback::whereNotNull('overall')->avg('overall'), 1) ?: null,
        ];

        return FeedbackResource::collection($query->paginate(20))
            ->additional(['stats' => $stats]);
    }

    /**
     * Two create modes:
     * - `request` — anchor to an order, mint token + AXNF code, email the
     *   client their /feedback/{token} link (queued). Scores arrive later.
     * - `log`     — feedback already received offline; admin enters the
     *   scores directly and the row is born submitted.
     */
    public function store(AdminFeedbackStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $isRequestMode = $data['mode'] === 'request';

        $order = isset($data['order_id']) ? Order::with('client')->find($data['order_id']) : null;

        $feedback = DB::transaction(function () use ($data, $order, $isRequestMode) {
            $placed = SortOrder::placeNew(Feedback::class, [], (int) ($data['sort_order'] ?? 0));

            return Feedback::create([
                'reference_code' => ReferenceCodeGenerator::generate(DocumentType::Feedback),
                'public_token' => Feedback::mintToken(),
                'order_id' => $order?->id,
                'client_id' => $order?->client_id,
                // Snapshot for display — explicit input wins, order's client fills the gap.
                'name' => $data['name'] ?? $order?->client?->name,
                'email' => $data['email'] ?? $order?->client?->email,
                'project_label' => $data['project_label'] ?? null,
                'overall' => $data['overall'] ?? null,
                'rating_design' => $data['rating_design'] ?? null,
                'rating_communication' => $data['rating_communication'] ?? null,
                'rating_delivery' => $data['rating_delivery'] ?? null,
                'rating_value' => $data['rating_value'] ?? null,
                'nps' => $data['nps'] ?? null,
                'praise' => $data['praise'] ?? null,
                'improve' => $data['improve'] ?? null,
                'publish_consent' => (bool) ($data['publish_consent'] ?? false),
                'attribution_name' => $data['attribution_name'] ?? null,
                'attribution_role' => $data['attribution_role'] ?? null,
                'status' => 'pending',
                'source' => 'admin',
                'featured' => (bool) ($data['featured'] ?? false),
                'sort_order' => $placed,
                'submitted_at' => $isRequestMode ? null : now(),
            ]);
        });

        if ($isRequestMode) {
            RequestFeedbackJob::dispatch($feedback->id);
        }

        return (new FeedbackResource($feedback->load('order')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Feedback $feedback): FeedbackResource
    {
        // First admin open of a pending review stamps the audit timestamp.
        if ($feedback->status === 'pending' && $feedback->reviewed_at === null) {
            $feedback->update(['reviewed_at' => now()]);
        }

        return new FeedbackResource($feedback->load('order'));
    }

    public function update(AdminFeedbackUpdateRequest $request, Feedback $feedback): FeedbackResource|JsonResponse
    {
        $data = $request->validated();

        // Status changes obey the same consent gate as the /status endpoint.
        if (isset($data['status'])) {
            if ($error = $this->guardTransition($feedback, $data['status'])) {
                return $error;
            }
            if ($data['status'] === 'published' && $feedback->published_at === null) {
                $data['published_at'] = now();
            }
        }

        if (array_key_exists('sort_order', $data) && $data['sort_order'] !== null) {
            $data['sort_order'] = DB::transaction(
                fn () => SortOrder::move($feedback, (int) $data['sort_order'], []),
            );
        }

        $feedback->update($data);

        return new FeedbackResource($feedback->fresh()->load('order'));
    }

    /** pending → approved → published (or archived). Nothing auto-publishes. */
    public function updateStatus(Request $request, Feedback $feedback): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:pending,approved,published,archived'],
        ]);

        if ($error = $this->guardTransition($feedback, $request->status)) {
            return $error;
        }

        $updates = ['status' => $request->status];
        if ($request->status === 'published' && $feedback->published_at === null) {
            $updates['published_at'] = now();
        }

        $feedback->update($updates);

        return response()->json(['message' => 'Status updated.', 'status' => $feedback->status]);
    }

    public function destroy(Feedback $feedback): JsonResponse
    {
        $feedback->delete();

        return response()->json(['message' => 'Feedback deleted.']);
    }

    /** The one publish rule: no consent, no wall — on every path. */
    private function guardTransition(Feedback $feedback, string $status): ?JsonResponse
    {
        if ($status === 'published' && ! $feedback->publish_consent) {
            return response()->json([
                'message' => 'Cannot publish: the client has not consented to publication.',
            ], 422);
        }

        return null;
    }
}
