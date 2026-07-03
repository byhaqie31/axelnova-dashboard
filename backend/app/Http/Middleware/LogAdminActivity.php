<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Audit safety-net. Auto-logs every successful mutating admin request
 * (POST/PUT/PATCH/DELETE) so no write is ever missed, even on endpoints that
 * don't call logActivity() themselves. Reads (GET) are intentionally NOT logged
 * — they'd bury the trail in noise.
 *
 * De-duped against the rich semantic entries: RecordsActivity::logActivity()
 * flags the request as already audited (container binding 'activity.recorded'),
 * and this middleware skips those — so a status change logs once, semantically,
 * not twice.
 */
class LogAdminActivity
{
    private const MUTATING = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        // Clear any stale flag before the action runs (Octane-safe).
        app()->forgetInstance('activity.recorded');

        $response = $next($request);

        $isMutation = in_array($request->method(), self::MUTATING, true);
        $succeeded = $response->getStatusCode() < 400;

        if ($isMutation && $succeeded && ! app()->bound('activity.recorded')) {
            [$subjectType, $subjectId] = $this->resolveSubject($request);

            ActivityLog::create([
                'actor_id' => $request->user()?->id,
                'action' => $this->action($request),
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'changes' => [
                    'method' => $request->method(),
                    'path' => '/'.ltrim($request->path(), '/'),
                    'status' => $response->getStatusCode(),
                ],
            ]);
        }

        return $response;
    }

    /** Prefer a route-model-bound subject so the entry links back; else generic. */
    private function resolveSubject(Request $request): array
    {
        foreach ($request->route()?->parameters() ?? [] as $param) {
            if ($param instanceof Model) {
                return [class_basename($param), (int) $param->getKey()];
            }
        }

        return ['Request', 0];
    }

    /** Route name without the `admin.` prefix (e.g. clients.update); else http.{method}. */
    private function action(Request $request): string
    {
        $name = $request->route()?->getName();

        if ($name) {
            return str_starts_with($name, 'admin.') ? substr($name, 6) : $name;
        }

        return 'http.'.strtolower($request->method());
    }
}
