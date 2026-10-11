<?php

namespace App\Mail;

use App\Models\ExternalAccount;
use Illuminate\Queue\SerializesModels;

/**
 * Email 18. The ONLY place a partner passcode is ever surfaced. Sent when staff
 * approve a referrer (first passcode), reset one, or a partner self-serves
 * "forgot passcode" — for BOTH partner kinds (referrer + investor) since the
 * Task 9 unification onto external_accounts. The plaintext passcode lives only
 * in this in-flight message — it is never rendered on a staff screen, returned
 * by an API, or written to a log. Sent synchronously (no ShouldQueue) so the
 * acting staffer gets immediate success/failure and there's no queue-worker
 * dependency for a credential delivery.
 */
class PartnerPasscodeMail extends KitMailable
{
    use SerializesModels;

    public function __construct(
        public readonly ExternalAccount $account,
        public readonly string $passcode,
    ) {}

    public static function emailType(): string
    {
        return 'partner.passcode';
    }

    public static function relatedProperty(): ?string
    {
        return 'account';
    }

    public function viewData(): array
    {
        $name = $this->account->displayName();

        return [
            'name' => $name !== '' ? $name : 'there',
            'email' => $this->account->email,
            'passcode' => $this->passcode,
            'loginUrl' => rtrim((string) config('services.frontend.public_url'), '/').'/partners/login',
        ];
    }
}
