<?php

namespace App\Jobs;

use App\Mail\ClientQuotationMail;
use App\Models\Quotation;
use App\Services\Mail\EmailLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendClientQuoteEmail implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $uniqueFor = 3600;

    /**
     * @param  int|null  $revisionId  the revision being sent (R2's own token + "· R2" reference);
     *                                null = the family's current revision
     */
    public function __construct(private readonly int $quoteRequestId, private readonly ?int $revisionId = null) {}

    /** Per revision — sending R2 within the hour of R1 must not be swallowed as a duplicate. */
    public function uniqueId(): string
    {
        return $this->revisionId ? "{$this->quoteRequestId}:{$this->revisionId}" : (string) $this->quoteRequestId;
    }

    public function handle(): void
    {
        $family = Quotation::with('addons', 'pricingConfig')->findOrFail($this->quoteRequestId);
        $revision = $this->revisionId
            ? $family->revisions()->whereKey($this->revisionId)->firstOrFail()
            : $family->revision();

        // Always the formal quotation (email 1b). The instant estimate (1a) went
        // with the retired public quote funnel.
        Mail::to($family->email, $family->name)->send(new ClientQuotationMail($revision->asQuotation($family)));
    }

    /** Out of retries — the email never left; record it so the owner sees it (spec §5.4). */
    public function failed(Throwable $e): void
    {
        $family = Quotation::find($this->quoteRequestId);
        if ($family?->email) {
            app(EmailLogger::class)->recordFailure(ClientQuotationMail::class, $family, $family->email, $family->name, null, $e);
        }
    }
}
