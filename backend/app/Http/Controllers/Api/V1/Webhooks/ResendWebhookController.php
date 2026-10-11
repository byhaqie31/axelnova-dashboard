<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Mail\DeliveryAlerts;
use App\Services\Mail\ResendEventMatcher;
use App\Services\Mail\ResendSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/v1/webhooks/resend — Resend's delivery reports (spec §5.5).
 * Verifies the Svix signature, finds the email's log row and advances its
 * status forward-only. Reports we can't place (a test sent from Resend's
 * dashboard, an event type we don't track) are accepted and ignored, so
 * Resend doesn't retry them.
 */
class ResendWebhookController extends Controller
{
    /** Resend event → email_logs status (null = nothing to change). */
    private const STATUS = [
        'email.sent' => null,
        'email.delivery_delayed' => 'delayed',
        'email.delivered' => 'delivered',
        'email.bounced' => 'bounced',
        'email.suppressed' => 'bounced',
        'email.failed' => 'failed',
        'email.complained' => 'complained',
    ];

    public function __invoke(Request $request, ResendEventMatcher $matcher, DeliveryAlerts $alerts): JsonResponse
    {
        $verified = ResendSignature::verify(
            (string) $request->header('svix-id'),
            (string) $request->header('svix-timestamp'),
            (string) $request->header('svix-signature'),
            $request->getContent(),
            (string) config('services.resend.webhook_secret'),
        );

        if (! $verified) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $type = (string) $request->json('type');
        $data = $request->json('data');

        if (! array_key_exists($type, self::STATUS) || ! is_array($data)) {
            return response()->json(['ignored' => 'event type not tracked']);
        }

        $log = $matcher->match($data);

        if ($log === null) {
            Log::warning('Resend webhook for an email we have no record of.', [
                'type' => $type,
                'email_id' => $data['email_id'] ?? null,
            ]);

            return response()->json(['ignored' => 'no matching email']);
        }

        if ($log->provider_id === null && filled($data['email_id'] ?? null)) {
            $log->forceFill(['provider_id' => (string) $data['email_id']])->save();
        }

        $status = self::STATUS[$type];
        if ($status !== null && $log->advanceTo($status, $this->detail($type, $data))) {
            $alerts->maybeAlert($log);
        }

        return response()->json(['ok' => true]);
    }

    /** @param  array<string, mixed>  $data */
    private function detail(string $type, array $data): ?string
    {
        return match ($type) {
            'email.bounced' => $data['bounce']['message'] ?? $data['bounce']['subType'] ?? null,
            'email.suppressed' => 'Suppressed by Resend — this address bounced or complained before.',
            'email.complained' => 'Marked as spam by the recipient.',
            'email.failed' => $data['failed']['reason'] ?? $data['reason'] ?? 'Resend could not send this email.',
            'email.delivery_delayed' => 'Delivery delayed — Resend is still retrying.',
            default => null,
        };
    }
}
