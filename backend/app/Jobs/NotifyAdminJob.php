<?php

namespace App\Jobs;

use App\Mail\AdminNotificationMail;
use App\Models\Quotation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class NotifyAdminJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private readonly int $quoteRequestId) {}

    public function handle(): void
    {
        $quote = Quotation::findOrFail($this->quoteRequestId);
        $adminEmail = config('services.admin.email', env('ADMIN_NOTIFICATION_EMAIL'));

        Mail::to($adminEmail)->send(new AdminNotificationMail($quote));
    }
}
