<?php

namespace App\Services\Auth;

use RuntimeException;

/** An access link that is unknown, expired, already used, or for an account that can't sign in. */
class InvalidAccessLink extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This link has expired or has already been used.');
    }
}
