<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Make Cloudflare's verified client IP the authoritative forwarded-for value.
 *
 * All production traffic reaches this container as:
 *   visitor → Cloudflare edge → cloudflared (loopback) → host nginx → container
 *
 * Cloudflare OVERWRITES `CF-Connecting-IP` on every request it proxies, so a
 * visitor cannot forge it — unlike `X-Forwarded-For`, which Cloudflare merely
 * appends to, leaving any attacker-supplied prefix in place.
 *
 * Trusting the right proxy hops (bootstrap/app.php) already makes Symfony read
 * the RIGHTMOST forwarded entry, which is correct *provided* the host nginx
 * vhost appends with `$proxy_add_x_forwarded_for`. That vhost lives on the VPS,
 * not in this repo, so this middleware removes the dependency on it: when a
 * Cloudflare-verified IP is present, it becomes the whole chain and there is no
 * attacker-controlled prefix left to read.
 *
 * Only honoured from a private socket peer (host nginx / the docker bridge).
 * The container publishes on 127.0.0.1:8003, so nothing off-host can reach it
 * to set this header directly.
 *
 * Runs BEFORE TrustProxies — see the prepend() in bootstrap/app.php.
 */
class ResolveCloudflareClientIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $peer = $request->server->get('REMOTE_ADDR');
        $clientIp = $request->headers->get('CF-Connecting-IP');

        if (
            is_string($peer) && $peer !== ''
            && is_string($clientIp) && $clientIp !== ''
            && IpUtils::isPrivateIp($peer)
            && filter_var($clientIp, FILTER_VALIDATE_IP) !== false
        ) {
            $request->headers->set('X-Forwarded-For', $clientIp);
        }

        return $next($request);
    }
}
