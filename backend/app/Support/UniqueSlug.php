<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * A URL slug from a name, unique among the rows of $scope: `name`, then
 * `name-2`, `name-3` … `$scope` is a fresh query over the rows the slug must not
 * collide with (e.g. one category's packages), already excluding the row being
 * saved when it keeps its own slug.
 */
final class UniqueSlug
{
    public static function within(Builder $scope, string $base, int $maxLength, string $fallback): string
    {
        // Leave room for a "-NN" suffix inside the column length.
        $root = Str::limit(Str::slug($base), $maxLength - 4, '') ?: $fallback;
        $candidate = $root;

        for ($n = 2; (clone $scope)->where('slug', $candidate)->exists(); $n++) {
            $candidate = "{$root}-{$n}";
        }

        return $candidate;
    }
}
