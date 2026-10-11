<?php

namespace App\Http\Resources;

use App\Models\QuotationRevision;
use App\Services\Quoting\ScopeSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // The list endpoint stays lean; everything else (detail/store/update/send)
        // carries the full document + scope payload the builder needs.
        $listRoute = $request->routeIs('admin.quotations.index');

        return [
            'id' => $this->id,
            'reference_code' => $this->reference_code,
            'source' => $this->source,
            'client_id' => $this->client_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'package_key' => $this->package_key,
            // Non-catalog quotes (bespoke / detailed) surface a derived "Custom"
            // descriptor so the list shows a real label + provenance, not "—".
            'custom_package' => $listRoute ? $this->customPackageLean() : $this->customPackage(),
            'estimate_min_myr' => $this->estimate_min_myr,
            'estimate_max_myr' => $this->estimate_max_myr,
            'estimate_eta_value' => $this->estimate_eta_value,
            'estimate_eta_unit' => $this->estimate_eta_unit,
            'status' => $this->status,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'viewed_at' => $this->viewed_at?->toISOString(),
            'sent_at' => $this->sent_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'updated_by' => $this->whenLoaded('updatedBy', fn () => $this->updatedBy ? [
                'id' => $this->updatedBy->id,
                'name' => $this->updatedBy->name,
            ] : null),
            'order_id' => $this->whenLoaded('order', fn () => $this->order?->id),
            'order_number' => $this->whenLoaded('order', fn () => $this->order?->order_number),
            'referral_partner_id' => $this->referral_partner_id,
            'referrer' => $this->whenLoaded('referrer', fn () => $this->referrer ? [
                'name' => $this->referrer->name,
                'relationship_tier' => $this->referrer->relationship_tier,
                'commission_pct' => $this->referrer->commission_pct,
            ] : null),
            'public_token' => $this->when(! $listRoute, $this->public_token),
            // Raw payload stays for the builder's hydration; the spec grid reads
            // scope_display (normalizer-curated, scalars only) instead.
            'form_payload' => $this->when(! $listRoute, $this->form_payload),
            'scope_display' => $this->when(! $listRoute, fn () => ScopeSummary::forQuotation($this->resource)),
            'document' => $this->when(! $listRoute, $this->document),
            // PDF template language (en | bm) — chrome only, never the content.
            'locale' => $this->locale ?? 'en',
            // Derived deposit + plan figures (fixed-wins, rounding, schedule, variance)
            // — what the builder, detail page and order flow display; never re-derived in the UI.
            'payment_plan' => $this->when(! $listRoute, fn () => $this->paymentPlan()->toArray()),
            'addons' => $this->whenLoaded('addons', fn () => $this->addons->map(fn ($a) => [
                'key' => $a->addon_key,
                'label' => $a->addon_label,
                'amount_myr' => $a->amount_myr,
            ])),
            ...($listRoute ? $this->listRevisionFields() : $this->revisionFields()),
        ];
    }

    /**
     * The list row's revision scalars (selected by the index query — no extra
     * queries): the current revision's label and any open revision in progress.
     *
     * @return array<string, mixed>
     */
    private function listRevisionFields(): array
    {
        $number = (int) ($this->getAttribute('current_revision_number') ?? 1);
        $open = $this->getAttribute('open_revision');
        [$openNumber, $openStatus] = $open ? explode(':', (string) $open, 2) : [null, null];

        return [
            'display_reference' => QuotationRevision::displayReference((string) $this->reference_code, $number),
            'revision_number' => $number,
            'open_revision' => $openNumber !== null && (int) $openNumber !== $number
                ? ['number' => (int) $openNumber, 'status' => $openStatus]
                : null,
        ];
    }

    /**
     * Which revision this payload shows, and the family's revision tabs. Content
     * and `status` above are the SHOWN revision's (a view built by
     * QuotationRevision::asQuotation); `family_status` is the family's derived one.
     *
     * @return array<string, mixed>
     */
    private function revisionFields(): array
    {
        $revision = $this->resource->revisionView ?? $this->resource->revision();
        $family = $revision->quotation;
        $revisions = $family->revisions()->with('creator:id,name')->get();
        $numberOf = $revisions->pluck('number', 'id');

        return [
            'family_status' => $family->status,
            'display_reference' => QuotationRevision::displayReference($family->reference_code, $revision->number),
            'revision' => [
                'id' => $revision->id,
                'number' => $revision->number,
                'label' => $revision->label(),
                'status' => $revision->status,
                'change_reason' => $revision->change_reason,
                'replaces' => $numberOf[$revision->replaces_revision_id] ?? null,
                'is_current' => $revision->id === $family->current_revision_id,
                'editable' => $revision->isDraft(),
                'issued_at' => $revision->issued_at?->toISOString(),
                'sent_at' => $revision->sent_at?->toISOString(),
                'accepted_at' => $revision->accepted_at?->toISOString(),
                'superseded_at' => $revision->superseded_at?->toISOString(),
                'pdf_path' => $revision->pdfPath(),
                'pdf_filename' => QuotationRevision::fileStem($family->reference_code, $revision->number).'.pdf',
            ],
            'revisions' => $revisions->map(fn (QuotationRevision $r) => [
                'number' => $r->number,
                'label' => $r->label(),
                'status' => $r->status,
                'is_current' => $r->id === $family->current_revision_id,
                'estimate_myr' => $r->asQuotation($family)->finalAmount(),
                'change_reason' => $r->change_reason,
                'replaces' => $numberOf[$r->replaces_revision_id] ?? null,
                'created_by' => $r->creator?->name,
                'created_at' => $r->created_at?->toISOString(),
                'sent_at' => $r->sent_at?->toISOString(),
                'accepted_at' => $r->accepted_at?->toISOString(),
                'superseded_at' => $r->superseded_at?->toISOString(),
            ])->values()->all(),
            'open_revision' => $revisions->first(fn (QuotationRevision $r) => $r->isOpen())?->number,
        ];
    }
}
