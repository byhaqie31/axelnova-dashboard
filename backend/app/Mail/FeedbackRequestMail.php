<?php

namespace App\Mail;

use App\Models\Feedback;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

/**
 * Email 4 — the feedback request, sent only when the founder asks for it
 * (the "project completed" email carries its own feedback button).
 */
class FeedbackRequestMail extends KitMailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Feedback $feedback) {}

    public static function emailType(): string
    {
        return 'feedback.request';
    }

    public static function relatedProperty(): ?string
    {
        return 'feedback';
    }

    public function viewData(): array
    {
        $feedback = $this->feedback;

        return [
            'name' => $feedback->name ?: null,
            'variant' => match (true) {
                $feedback->order_id && filled($feedback->project_label) => 'project_label',
                (bool) $feedback->order_id => 'project',
                default => 'general',
            },
            'projectLabel' => $feedback->project_label,
            'feedbackUrl' => rtrim((string) config('services.frontend.public_url'), '/')."/feedback/{$feedback->public_token}",
        ];
    }
}
