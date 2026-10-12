<?php

namespace App\Services\Mail;

use App\Mail\PartnerPasscodeChangedMail;
use App\Mail\TeamPasswordChangedMail;
use App\Models\ExternalAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Short notices to the owner's inbox about things worth knowing but not
 * acting on. Never allowed to fail the action that triggered them.
 */
class OwnerHeadsUps
{
    /** Someone set new credentials through a reset link (Phase 2) — not the founder's own account. */
    public function credentialsChanged(Model $account, string $surface): void
    {
        $adminEmail = (string) config('services.admin.email');

        try {
            match (true) {
                $adminEmail === '' => null,
                $account instanceof ExternalAccount => Mail::to($adminEmail)->send(new PartnerPasscodeChangedMail($account)),
                $account instanceof User && ! $account->isCockpit() => Mail::to($adminEmail)->send(new TeamPasswordChangedMail($account)),
                default => null,
            };
        } catch (Throwable $e) {
            report($e);
        }
    }
}
