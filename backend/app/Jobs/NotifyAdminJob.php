<?php

namespace App\Jobs;

use App\Mail\AdminNotificationMail;
use App\Models\Quotation;
use App\Services\Mail\EmailLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotifyAdminJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private readonly int $quoteRequestId) {}

    /**
     * Queue the owner's new-lead alert. Outside production it waits 10s: the
     * dev inbox (Mailtrap free) accepts ~1 email/second and the client's
     * estimate goes out first. Real providers need no such gap.
     */
    public static function dispatchForNewLead(int $quoteRequestId): void
    {
        $pending = static::dispatch($quoteRequestId);

        if (! app()->isProduction()) {
            $pending->delay(now()->addSeconds(10));
        }
    }

    public function handle(): void
    {
        $quote = Quotation::findOrFail($this->quoteRequestId);
        $adminEmail = config('services.admin.email', env('ADMIN_NOTIFICATION_EMAIL'));

        Mail::to($adminEmail)->send(new AdminNotificationMail($quote));
    }

    /** Out of retries — the email never left; record it so the owner sees it (spec §5.4). */
    public function failed(Throwable $e): void
    {
        $adminEmail = (string) config('services.admin.email');
        if ($adminEmail !== '') {
            app(EmailLogger::class)->recordFailure(
                AdminNotificationMail::class, Quotation::find($this->quoteRequestId), $adminEmail, null, null, $e,
            );
        }
    }
}
