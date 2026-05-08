<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Tiny helper for "ordered list" semantics on `sort_order` integer columns.
 *
 * `placeNew()` — resolve the sort_order for a new row. Treats <= 0 OR >= next-available
 * as "auto-append". Otherwise inserts at the requested position and shifts colliding
 * rows up by 1.
 *
 * `move()` — reposition an existing row inside the same scope. Closes the gap at the
 * old position and shifts neighbours into the new position. Clamps the requested
 * position to the valid range so admins can't strand rows at sort_order=99.
 *
 * `removeFromScope()` — close the gap when a row leaves a scope (e.g. a package
 * moved to a different category).
 *
 * Caller is responsible for wrapping in DB::transaction().
 */
class SortOrder
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $scope  column => value pairs that scope the ordering
     */
    public static function placeNew(string $modelClass, array $scope, int $requested): int
    {
        $maxOrder = $modelClass::query()->where($scope)->max('sort_order');
        $appendAt = ($maxOrder === null) ? 0 : $maxOrder + 1;

        if ($requested <= 0 || $requested >= $appendAt) {
            return $appendAt;
        }

        $modelClass::query()
            ->where($scope)
            ->where('sort_order', '>=', $requested)
            ->increment('sort_order');

        return $requested;
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    public static function move(Model $model, int $newOrder, array $scope): int
    {
        $modelClass = $model::class;
        $oldOrder = (int) $model->getOriginal('sort_order');

        $siblingMax = $modelClass::query()
            ->where($scope)
            ->where('id', '!=', $model->id)
            ->max('sort_order');
        $maxValid = ($siblingMax === null) ? 0 : $siblingMax + 1;
        $clamped = max(0, min($newOrder, $maxValid));

        if ($clamped === $oldOrder) {
            return $oldOrder;
        }

        $query = fn () => $modelClass::query()
            ->where($scope)
            ->where('id', '!=', $model->id);

        if ($clamped > $oldOrder) {
            $query()->whereBetween('sort_order', [$oldOrder + 1, $clamped])->decrement('sort_order');
        } else {
            $query()->whereBetween('sort_order', [$clamped, $oldOrder - 1])->increment('sort_order');
        }

        return $clamped;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $scope
     */
    public static function removeFromScope(string $modelClass, array $scope, int $removedOrder): void
    {
        $modelClass::query()
            ->where($scope)
            ->where('sort_order', '>', $removedOrder)
            ->decrement('sort_order');
    }
}
