<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Sanctum stamps last_used_at on EVERY authenticated request — a write per
 * admin/team/partner GET, purely for the "last used" display. Throttle it:
 * skip the write when last_used_at is the only change and the stored value is
 * recent. Any other dirty attribute (rotation, expiry changes) saves normally.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /** Minutes of last_used_at staleness we accept to avoid a write per request. */
    private const LAST_USED_WRITE_INTERVAL = 5;

    public function save(array $options = []): bool
    {
        $dirty = $this->getDirty();

        if ($this->exists && array_keys($dirty) === ['last_used_at']) {
            $previous = $this->getOriginal('last_used_at');

            if ($previous !== null && $previous->gt(now()->subMinutes(self::LAST_USED_WRITE_INTERVAL))) {
                // Fresh enough — report success without touching the DB.
                $this->syncOriginal();

                return true;
            }
        }

        return parent::save($options);
    }
}
