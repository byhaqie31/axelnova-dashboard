<?php

namespace App\Http\Controllers\Api\V1\Connector\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * The connector's rule for the public catalogue (projects, services, packages),
 * where Live / Hidden (`active`) plays the part a blog post's draft status does:
 * Claude creates rows Hidden and edits them only while they are Hidden. A Live
 * row's edit would reach the public site (and, for a package, the quote builder)
 * without the founder's preview, so it is refused — the founder hides it first
 * or edits it in the admin.
 */
trait EditsHiddenDrafts
{
    /** @throws ValidationException when the row is Live */
    private function assertHidden(bool $active, string $what, string $consequence): void
    {
        if ($active) {
            throw ValidationException::withMessages([
                'visibility' => ["This {$what} is Live, so changes would {$consequence} immediately. Ask the founder to hide it in the admin first, or to edit it there."],
            ]);
        }
    }

    /** @return array{current_page: int, per_page: int, last_page: int, total: int} */
    private function pageMeta(LengthAwarePaginator $page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
        ];
    }
}
