<?php

namespace App\Services\Clients;

use App\Http\Controllers\Api\V1\PublicTestimonialsController;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Feedback;
use App\Models\Inquiry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Quotation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Permanently deletes a client. Anything that ties it to money or a quote
 * (quotations, orders, payments — soft-deleted rows included) must first move to
 * a replacement client; inquiries and feedback follow along. The row is then
 * force-deleted so its unique email is free again. The orders/payments FKs are
 * RESTRICT, so a tie this class ever misses aborts the delete instead of
 * orphaning a money record. Issued invoices/receipts keep their frozen snapshots.
 */
class ClientDeleter
{
    /**
     * What blocks a replacement-less delete, counted across soft-deleted rows.
     *
     * @return array{quotations: int, orders: int, payments: int}
     */
    public function blockers(Client $client): array
    {
        return [
            'quotations' => Quotation::withTrashed()->where('client_id', $client->id)->count(),
            'orders' => Order::withTrashed()->where('client_id', $client->id)->count(),
            'payments' => Payment::withTrashed()->where('client_id', $client->id)->count(),
        ];
    }

    public function hasBlockers(Client $client): bool
    {
        return array_sum($this->blockers($client)) > 0;
    }

    /**
     * Move every tie to the replacement (when given), then delete the client —
     * all in one transaction. The replacement is resolved via Client::resolveForRelink
     * INSIDE the transaction, so a "create new" is rolled back with everything else.
     *
     * @param  array{client_id?: int|null, client?: array<string, mixed>|null}|null  $replacementInput
     * @return array{replacement_id: ?int, moved: array<string, int>}
     */
    public function delete(Client $client, ?array $replacementInput): array
    {
        return DB::transaction(function () use ($client, $replacementInput) {
            $replacement = null;
            $moved = [];

            if ($replacementInput) {
                [$replacement] = Client::resolveForRelink($replacementInput);

                if ($replacement->id === $client->id) {
                    throw ValidationException::withMessages([
                        'client_id' => 'Choose a different client — this is the one being deleted.',
                    ]);
                }

                $moved = $this->moveTies($client, $replacement);
            }

            $client->forceDelete();

            ActivityLog::create([
                'actor_id' => Auth::id(),
                'action' => 'client.deleted',
                'subject_type' => 'Client',
                'subject_id' => $client->id,
                'changes' => [
                    'name' => $client->name,
                    'email' => $client->email,
                    'replacement_id' => $replacement?->id,
                    'moved' => $moved,
                ],
            ]);
            // Already audited — keep LogAdminActivity from adding a generic row.
            app()->instance('activity.recorded', true);

            return ['replacement_id' => $replacement?->id, 'moved' => $moved];
        });
    }

    /**
     * Bulk re-point, one query per table. Quotations also refresh their contact
     * snapshot (what Quotation::relinkToClient does per row). Bulk updates skip
     * model events, so the testimonials cache FeedbackObserver would clear is
     * cleared here instead.
     *
     * @return array<string, int>
     */
    private function moveTies(Client $from, Client $to): array
    {
        $moved = [
            'quotations' => Quotation::withTrashed()->where('client_id', $from->id)->update([
                'client_id' => $to->id,
                'name' => $to->name,
                'email' => $to->email,
                'phone' => $to->phone,
                'company' => $to->company,
            ]),
            'orders' => Order::withTrashed()->where('client_id', $from->id)->update(['client_id' => $to->id]),
            'payments' => Payment::withTrashed()->where('client_id', $from->id)->update(['client_id' => $to->id]),
            'inquiries' => Inquiry::withTrashed()->where('client_id', $from->id)->update(['client_id' => $to->id]),
            'feedback' => Feedback::withTrashed()->where('client_id', $from->id)->update(['client_id' => $to->id]),
        ];

        if ($moved['feedback'] > 0) {
            Cache::forget(PublicTestimonialsController::CACHE_KEY);
        }

        return $moved;
    }
}
