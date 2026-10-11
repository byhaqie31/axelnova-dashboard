<?php

namespace App\Jobs;

use App\Mail\FeedbackRequestMail;
use App\Models\Feedback;
use App\Services\Mail\EmailLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class RequestFeedbackJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $uniqueFor = 3600;

    public function __construct(private readonly int $feedbackId) {}

    public function uniqueId(): string
    {
        return (string) $this->feedbackId;
    }

    public function handle(): void
    {
        $feedback = Feedback::findOrFail($this->feedbackId);

        if (! $feedback->email) {
            return; // nothing to send to — admin can copy the link from the portal
        }

        Mail::to($feedback->email, $feedback->name)->send(new FeedbackRequestMail($feedback));
    }

    /** Out of retries — the email never left; record it so the owner sees it (spec §5.4). */
    public function failed(Throwable $e): void
    {
        $feedback = Feedback::find($this->feedbackId);
        if ($feedback?->email) {
            app(EmailLogger::class)->recordFailure(
                FeedbackRequestMail::class, $feedback, $feedback->email, $feedback->name, null, $e,
            );
        }
    }
}
