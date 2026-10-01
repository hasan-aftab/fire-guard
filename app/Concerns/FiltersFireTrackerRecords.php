<?php

namespace App\Concerns;

use App\Http\Requests\TrackerFilterRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait FiltersFireTrackerRecords
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function filterRecordState(Builder $query, Request $request): void
    {
        match ($request->string('record_state')->toString()) {
            'all' => null,
            'entered_in_error' => $query->whereNotNull('entered_in_error_at'),
            default => $query->whereNull('entered_in_error_at'),
        };
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function filterDates(Builder $query, TrackerFilterRequest $request, string $column, string $prefix = ''): void
    {
        if ($request->filled($prefix.'from')) {
            $query->where($column, '>=', $request->string($prefix.'from')->toString());
        }

        if ($request->filled($prefix.'to')) {
            $query->where($column, '<=', $request->string($prefix.'to')->toString());
        }
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function filterServiceStatus(Builder $query, TrackerFilterRequest $request, string $column, int $soonDays): void
    {
        $today = today()->toDateString();
        $soon = today()->addDays($soonDays)->toDateString();

        match ($request->string('status')->toString()) {
            'overdue' => $query->where($column, '<', $today),
            'due_soon' => $query->whereBetween($column, [$today, $soon]),
            'ok' => $query->where($column, '>', $soon),
            'unknown' => $query->whereNull($column),
            default => null,
        };
    }

    private function searchPattern(Request $request): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $request->string('search')->toString()).'%';
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  literal-string  $column
     */
    private function whereSearchLike(Builder $query, string $column, string $pattern, string $boolean = 'and'): void
    {
        $query->whereRaw("LOWER({$column}) LIKE LOWER(?) ESCAPE '!'", [$pattern], $boolean);
    }
}
