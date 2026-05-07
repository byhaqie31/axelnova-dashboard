<?php

namespace App\Jobs;

use App\Mail\ClientQuoteMail;
use App\Models\QuoteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendClientQuoteEmail implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $uniqueFor = 3600;

    public function __construct(private readonly int $quoteRequestId) {}

    public function uniqueId(): string
    {
        return (string) $this->quoteRequestId;
    }

    public function handle(): void
    {
        $quote = QuoteRequest::with('addons', 'pricingConfig')->findOrFail($this->quoteRequestId);

        Mail::to($quote->email, $quote->name)->send(new ClientQuoteMail($quote));
    }
}
