<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Email 27 — heads-up to the founder AFTER a teammate set a new password
 * through a reset link (Phase 2: team resets are self-service). If it wasn't
 * them, the founder deactivates the account from the Users screen.
 */
class TeamPasswordChangedMail extends KitMailable implements ShouldQueue
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
