<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remember which admin/team user is acting, so an email their request sends
 * — even one sent later by the queue worker — is logged as theirs. Laravel's
 * Context travels into queued jobs automatically; hidden, so it never reaches
 * log lines.
 */
class RecordMailActor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            Context::addHidden('mail_actor_id', $user->getKey());
        }

        return $next($request);
    }
}
