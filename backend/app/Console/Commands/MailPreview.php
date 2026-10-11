<?php

namespace App\Console\Commands;

use App\Mail\PreviewMail;
use App\Support\Mail\EmailCatalog;
use App\Support\Mail\EmailSamples;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Send email templates filled with sample data to the configured mailer —
 * in dev that's Mailtrap, where each one can be checked at desktop width and
 * at 390px. Sample data never touches the database.
 */
class MailPreview extends Command
{
    protected $signature = 'mail:preview
        {type? : An email type from the catalog, e.g. invoice.sent}
        {--all : Send every built template}
        {--to= : Recipient (defaults to the admin notification address)}
        {--pause=1200 : Milliseconds between sends (Mailtrap free allows ~1 email/second)}
        {--retry-wait=10000 : Milliseconds to wait before retrying a rate-limited send}';

    protected $description = 'Send email templates with sample data, for visual review';

    public function handle(): int
    {
        $types = $this->option('all') ? EmailSamples::types() : array_filter([$this->argument('type')]);

        if ($types === []) {
            $this->error('Name an email type, or pass --all. Built types: '.implode(', ', EmailSamples::types()));

            return self::FAILURE;
        }

        foreach ($types as $type) {
            if (! EmailCatalog::has($type)) {
                $this->error("Unknown email type [{$type}].");

                return self::FAILURE;
            }
            if (! in_array($type, EmailSamples::types(), true)) {
                $this->error("The [{$type}] template isn't built yet.");

                return self::FAILURE;
            }
        }

        $to = $this->option('to') ?: config('services.admin.email');
        $pause = max(0, (int) $this->option('pause'));
        $failed = [];

        foreach (array_values($types) as $i => $type) {
            if ($i > 0 && $pause > 0) {
                usleep($pause * 1000);
            }

            $error = $this->sendWithRetry($to, $type);
            if ($error === null) {
                $this->line("  sent    {$type}");
            } else {
                $failed[] = $type;
                $this->line("  <error>failed</error>  {$type} — {$error}");
            }
        }

        $sent = count($types) - count($failed);
        $this->info("{$sent} preview(s) sent to {$to}.");

        if ($failed !== []) {
            $this->error(count($failed).' failed: '.implode(', ', $failed));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Send one preview. A sandbox inbox's rate limit ("Too many emails per
     * second") is waited out and retried; anything else is reported and the
     * run moves on to the next template.
     */
    private function sendWithRetry(string $to, string $type): ?string
    {
        $wait = max(0, (int) $this->option('retry-wait'));

        for ($attempt = 1; ; $attempt++) {
            try {
                Mail::to($to)->send(new PreviewMail($type));

                return null;
            } catch (Throwable $e) {
                $rateLimited = str_contains($e->getMessage(), 'Too many emails');
                if (! $rateLimited || $attempt >= 4) {
                    return Str::limit($e->getMessage(), 160);
                }

                $this->line("  <comment>rate-limited</comment> {$type} — retrying in ".($wait / 1000).'s');
                usleep($wait * 1000);
            }
        }
    }
}
