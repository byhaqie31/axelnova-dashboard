<?php

namespace App\Services\Quoting;

use App\Models\Quotation;
use App\Models\QuotationRevision;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The quotation revision model's rules, in one place (docs/global/QUOTATION-REVISIONS.md).
 *
 * A quotation FAMILY (`quotations`) keeps its AXNQ reference; its content lives
 * in numbered revisions. The family row MIRRORS the current revision's content
 * (and carries the derived family status), so every reader that predates
 * revisions — lists, emails, reports, invoice mapping — keeps working unchanged.
 * Writes always go revision → row, never the other way:
 *
 *   • content edits land on the family's draft revision (writeDraft), then
 *     refresh() re-mirrors the current revision onto the row;
 *   • status changes land on a revision, then refresh() re-derives the family.
 *
 * Current revision: the accepted one if any (exactly one at a time — older ones
 * are superseded), else the latest revision that wasn't discarded.
 *
 * Safety net: a direct (non-quiet) write to the family row's content, dates or
 * status — tinker, a seeder, legacy code — is absorbed into the current revision
 * (absorbRowWrite), so the two can't drift; a frozen revision refuses it. This
 * class's own row writes are quiet, so the mirror never loops.
 */
class QuotationRevisions
{
    /**
     * The family's current revision, creating R1 from the row's content when the
     * family predates revisions. Every creator calls this once the row (and its
     * add-on rows) are written; readers reach it lazily via Quotation::revision().
     */
    public static function initialise(Quotation $quotation): QuotationRevision
    {
        if ($quotation->revisionView !== null) {
            return $quotation->revisionView;
        }

        if ($quotation->current_revision_id) {
            $current = $quotation->relationLoaded('currentRevision')
                ? $quotation->currentRevision
                : $quotation->currentRevision()->first();
            if ($current) {
                return $current;
            }
        }

        return DB::transaction(function () use ($quotation): QuotationRevision {
            // Serialise on the family row so two concurrent readers can't both mint R1.
            $locked = Quotation::withTrashed()->lockForUpdate()->findOrFail($quotation->getKey());
            if ($locked->revisions()->exists()) {
                return self::refresh($quotation);
            }

            $revision = new QuotationRevision;
            $revision->forceFill(array_merge(self::contentOf($quotation), [
                'quotation_id' => $quotation->getKey(),
                'number' => 1,
                'status' => $quotation->status ?: QuotationRevision::DRAFT,
                'public_token' => $quotation->public_token,
                'addons' => self::addonsOf($quotation),
                'issued_at' => $quotation->issued_at,
                'sent_at' => $quotation->sent_at,
                'expires_at' => $quotation->expires_at,
                'accepted_at' => $quotation->status === 'accepted' ? ($quotation->order?->created_at ?? now()) : null,
                'created_by' => Auth::id(),
            ]));
            // Quietly: copy the row's dates verbatim (the draft hook would re-stamp issued_at).
            $revision->saveQuietly();

            $quotation->forceFill(['current_revision_id' => $revision->id])->saveQuietly();
            $quotation->setRelation('currentRevision', $revision);

            return $revision;
        });
    }

    /**
     * Re-derive the family from its revisions — current pointer, family status —
     * and mirror the current revision's content, dates and add-ons onto the row.
     */
    public static function refresh(Quotation $quotation): QuotationRevision
    {
        $revisions = $quotation->revisions()->get();
        $current = self::pickCurrent($revisions);

        $quotation->forceFill(array_merge(self::contentOf($current), [
            'current_revision_id' => $current->id,
            'status' => self::familyStatus($revisions, $current),
            'issued_at' => $current->issued_at,
            'sent_at' => $current->sent_at,
            'expires_at' => $current->expires_at,
        ]));
        if ($quotation->isDirty()) {
            // Quietly: the row's own draft hook must not re-stamp the mirrored issued_at.
            $quotation->saveQuietly();
        }

        self::mirrorAddons($quotation, $current);
        $quotation->setRelation('currentRevision', $current);
        $quotation->unsetRelation('revisions');

        return $current;
    }

    /**
     * Write content to a DRAFT revision (the only editable state), then re-mirror
     * the family. $attributes may carry anything the writer computed — only the
     * content columns and the custom validity date are taken.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<array{addon_key: string, addon_label: string, amount_myr: float}>|null  $addons  null keeps the stored set
     * @param  Quotation|null  $family  the caller's family instance, refreshed in place (else re-loaded)
     */
    public static function writeDraft(QuotationRevision $draft, array $attributes, ?array $addons = null, ?Quotation $family = null): QuotationRevision
    {
        if (! $draft->isDraft()) {
            throw new RevisionLocked("{$draft->label()} is {$draft->status} — only a draft revision can be edited.");
        }

        $draft->fill(array_intersect_key($attributes, array_flip([...QuotationRevision::CONTENT, 'expires_at'])));
        if ($addons !== null) {
            $draft->addons = $addons;
        }
        $draft->save();

        self::refresh($family ?? $draft->quotation);

        return $draft;
    }

    /**
     * A direct write landed on the family row (Quotation `updated` event): carry
     * the changed content / dates / status onto the current revision, so the
     * row and its revision never drift. Refuses content edits on a frozen
     * (accepted / superseded) revision.
     */
    public static function absorbRowWrite(Quotation $quotation): void
    {
        $tracked = [...QuotationRevision::CONTENT, 'status', 'issued_at', 'sent_at', 'expires_at'];
        $changed = array_intersect(array_keys($quotation->getChanges()), $tracked);
        if ($changed === [] || ! $quotation->current_revision_id) {
            return;
        }

        $current = QuotationRevision::find($quotation->current_revision_id);
        if (! $current) {
            return;
        }

        $content = array_intersect($changed, QuotationRevision::CONTENT);
        if ($content !== [] && $current->isFrozen()) {
            throw new RevisionLocked("{$current->label()} of {$quotation->reference_code} is {$current->status} — its content is frozen. Use Revise to change the terms.");
        }

        foreach ($changed as $column) {
            $current->setAttribute($column, $quotation->getAttribute($column));
        }
        if ($current->status === QuotationRevision::ACCEPTED && $current->accepted_at === null) {
            $current->accepted_at = now();
        }
        // Quietly: the row's dates are copied as written (no draft re-stamp).
        $current->saveQuietly();
        $quotation->setRelation('currentRevision', $current);
    }

    /**
     * Mark a draft (or re-send a sent) revision as delivered: token, sent_at and
     * the default validity (sent_at + valid_for_days unless a custom date is set).
     */
    public static function markSent(QuotationRevision $revision): QuotationRevision
    {
        if (! $revision->isOpen()) {
            throw new RevisionLocked("{$revision->label()} is {$revision->status} — only a draft or sent revision can be sent.");
        }

        $revision->public_token ??= Str::random(48);
        $revision->status = QuotationRevision::SENT;
        $revision->sent_at = now();
        if (! $revision->expires_at) {
            $revision->expires_at = now()->addDays($revision->asQuotation()->validForDays());
        }
        $revision->save();

        self::refresh($revision->quotation);

        return $revision;
    }

    /** Lazy expiry of one sent revision past its validity date. */
    public static function expire(QuotationRevision $revision): void
    {
        if ($revision->status !== QuotationRevision::SENT) {
            return;
        }

        $revision->forceFill(['status' => QuotationRevision::EXPIRED])->save();
        self::refresh($revision->quotation);
    }

    /**
     * Lazy expiry sweep: every sent revision past its date flips to expired, and
     * each affected family is re-derived. A no-op (one indexed SELECT) when
     * nothing is overdue.
     */
    public static function expireOverdue(): void
    {
        $overdue = QuotationRevision::query()
            ->where('status', QuotationRevision::SENT)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->pluck('quotation_id', 'id');
        if ($overdue->isEmpty()) {
            return;
        }

        QuotationRevision::whereIn('id', $overdue->keys())->update(['status' => QuotationRevision::EXPIRED]);
        Quotation::withTrashed()->whereIn('id', $overdue->unique()->values())->get()
            ->each(fn (Quotation $quotation) => self::refresh($quotation));
    }

    /**
     * The add-on snapshot a revision stores, from catalog add-on keys.
     *
     * @param  list<string>  $addonKeys
     * @return list<array{addon_key: string, addon_label: string, amount_myr: float}>
     */
    public static function addonSnapshot(array $addonKeys, PricingEngine $engine): array
    {
        $defs = $engine->addons();

        return array_values(array_map(fn (string $key) => [
            'addon_key' => $key,
            'addon_label' => (string) $defs[$key]['label'],
            'amount_myr' => (float) $defs[$key]['amount'],
        ], array_filter($addonKeys, fn ($key) => isset($defs[$key]))));
    }

    // ── Derivation ───────────────────────────────────────────────────────────

    /** The accepted revision, else the latest one not discarded (else simply the latest). */
    public static function pickCurrent(Collection $revisions): QuotationRevision
    {
        return $revisions->firstWhere('status', QuotationRevision::ACCEPTED)
            ?? $revisions->where('status', '!=', QuotationRevision::DISCARDED)->sortByDesc('number')->first()
            ?? $revisions->sortByDesc('number')->firstOrFail();
    }

    /**
     * Family status from its revisions: accepted / rejected / expired follow the
     * current revision; an open current revision reads `sent` once any revision
     * has gone out, else `draft`. A family never shows `superseded`.
     */
    public static function familyStatus(Collection $revisions, QuotationRevision $current): string
    {
        return match ($current->status) {
            QuotationRevision::ACCEPTED, QuotationRevision::SUPERSEDED => 'accepted',
            QuotationRevision::REJECTED => 'rejected',
            QuotationRevision::EXPIRED => 'expired',
            QuotationRevision::SENT => 'sent',
            default => $revisions->contains(fn (QuotationRevision $r) => $r->isNot($current)
                && ($r->sent_at !== null || in_array($r->status, [QuotationRevision::SENT, QuotationRevision::EXPIRED], true)))
                ? 'sent'
                : 'draft',
        };
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** @return array<string, mixed> The content columns of a quotation row or revision. */
    public static function contentOf(Quotation|QuotationRevision $model): array
    {
        $content = [];
        foreach (QuotationRevision::CONTENT as $column) {
            $content[$column] = $model->getAttribute($column);
        }
        $content['locale'] ??= 'en';
        $content['form_payload'] ??= [];

        return $content;
    }

    /** @return list<array{addon_key: string, addon_label: string, amount_myr: float}> */
    private static function addonsOf(Quotation $quotation): array
    {
        return $quotation->addons()->orderBy('id')->get()->map(fn ($a) => [
            'addon_key' => (string) $a->addon_key,
            'addon_label' => (string) $a->addon_label,
            'amount_myr' => (float) $a->amount_myr,
        ])->values()->all();
    }

    /** Re-write the family's quotation_addons rows when the current revision's set differs. */
    private static function mirrorAddons(Quotation $quotation, QuotationRevision $current): void
    {
        $wanted = array_values($current->addons ?? []);
        if (self::addonsOf($quotation) == $wanted) {
            return;
        }

        $quotation->addons()->delete();
        foreach ($wanted as $addon) {
            $quotation->addons()->create($addon);
        }
        $quotation->unsetRelation('addons');
    }
}
