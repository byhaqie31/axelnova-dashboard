<?php

namespace App\Jobs;

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendInvoiceEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(
        private readonly int $invoiceId,
        private readonly string $email,
        private readonly ?string $name = null,
    ) {}

    public function handle(): void
    {
        $invoice = Invoice::with('order.quotation')->findOrFail($this->invoiceId);

        Mail::to($this->email, $this->name)
            ->send(new InvoiceMail($invoice, $this->fetchPdf($invoice)));

        // Quiet save — a send stamp is bookkeeping, not an audited edit.
        $invoice->forceFill(['emailed_at' => now(), 'emailed_to' => $this->email])->saveQuietly();
        $invoice->logActivity('invoice.emailed', ['to' => $this->email]);
    }

    /**
     * Render the PDF through the frontend's Nitro route (docker-internal
     * origin — the same renderer clients hit). Best-effort: any failure
     * returns null and the email still goes out with the link only; a slow
     * Chromium must never block the invoice reaching the client.
     */
    private function fetchPdf(Invoice $invoice): ?string
    {
        $base = rtrim((string) config('services.frontend.url'), '/');

        try {
            $response = Http::timeout(30)->get($base.$invoice->pdf_path);

            if ($response->successful()
                && str_starts_with((string) $response->header('Content-Type'), 'application/pdf')) {
                return $response->body();
            }
        } catch (\Throwable) {
            // fall through to the warning below
        }

        Log::warning('Invoice PDF attachment fetch failed; sending link-only.', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
        ]);

        return null;
    }
}
