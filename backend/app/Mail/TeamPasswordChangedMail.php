<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Email 27 — heads-up to the founder that a team member used "forgot
 * password" on the workspace sign-in. Unlike the partner flow there is NO
 * auto-reset — team passwords are only ever reset by the founder from the
 * Users screen, so this mail is the whole flow. Queued like every email
 * (spec §5.7).
 */
class TeamPasswordResetRequestedMail extends KitMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $user) {}

    public static function emailType(): string
    {
        return 'team.reset_alert';
    }

    public static function relatedProperty(): ?string
    {
        return 'user';
    }

    public function viewData(): array
    {
        return [
            'userName' => $this->user->name,
            'rows' => [
                ['Name', $this->user->name],
                ['Email', $this->user->email],
                ['Role', $this->user->role],
            ],
            'usersUrl' => rtrim((string) config('services.frontend.url'), '/').'/admin/users',
        ];
    }
}
