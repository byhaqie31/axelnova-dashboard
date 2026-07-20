<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicFeedbackRequest;
use App\Models\Feedback;
use Illuminate\Http\JsonResponse;

/**
 * The token-gated client surface behind /feedback/{token}. The unguessable
 * 48-char token is the only credential — no login. A row is created by the
 * admin (request or log mode); this controller only lets the client read the
 * shell and fill their scores in ONCE.
 */
class FeedbackController extends Controller
{
    /** The page shell — enough to greet the client, never the admin fields. */
    public function showByToken(string $token): JsonResponse
    {
        $feedback = Feedback::where('public_token', $token)->firstOrFail();

        return response()->json([
            'data' => [
                'name' => $feedback->name,
                'project_label' => $feedback->project_label,
                'already_submitted' => $feedback->submitted_at !== null,
            ],
        ]);
    }

    public function submit(PublicFeedbackRequest $request, string $token): JsonResponse
    {
        $feedback = Feedback::where('public_token', $token)->firstOrFail();

        // One submission per token — the page shows a thank-you state instead.
        if ($feedback->submitted_at !== null) {
            return response()->json(['message' => 'This feedback has already been submitted.'], 409);
        }

        $data = $request->validated();

        $feedback->update([
            ...$data,
            'publish_consent' => $request->boolean('publish_consent'),
            'submitted_at' => now(),
            // status stays 'pending' — an admin reviews before anything publishes.
        ]);

        return response()->json(['message' => 'Feedback received. Thank you!'], 201);
    }
}
