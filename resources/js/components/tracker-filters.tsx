import { router } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { DateRangePicker } from '@/components/date-range-picker';
import type { TrackerFilters as FilterValues } from '@/types/fire-tracker';
import type { RouteDefinition } from '@/wayfinder';

export function TrackerFilters({
    filters,
    route,
    searchPlaceholder,
    statusOptions,
    statusKey = 'status',
    dateLabel,
    issueDates = false,
    total,
    noun,
}: {
    filters: FilterValues;
    route: RouteDefinition<'get'>;
    searchPlaceholder: string;
    statusOptions?: { value: string; label: string }[];
    statusKey?: 'status' | 'result';
    dateLabel?: string;
    issueDates?: boolean;
    total: number;
    noun: string;
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [filtering, setFiltering] = useState(false);
    const searchTimer = useRef<ReturnType<typeof setTimeout> | undefined>(
        undefined,
    );
    useEffect(() => () => clearTimeout(searchTimer.current), []);
    useEffect(() => setSearch(filters.search ?? ''), [filters.search]);
    const apply = (changes: FilterValues) => {
        clearTimeout(searchTimer.current);
        router.get(
            route.url,
            { ...filters, search, ...changes, page: 1 },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setFiltering(true),
                onFinish: () => setFiltering(false),
            },
        );
    };
    return (
        <div
            className="mb-4 flex flex-wrap items-center gap-3"
            aria-busy={filtering}
        >
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    clearTimeout(searchTimer.current);
                    apply({ search });
                }}
                className="flex w-full items-center gap-2 sm:w-auto"
            >
                <Input
                    value={search}
                    onChange={(event) => {
                        const value = event.target.value;
                        setSearch(value);
                        clearTimeout(searchTimer.current);
                        searchTimer.current = setTimeout(
                            () => apply({ search: value }),
                            300,
                        );
                    }}
                    placeholder={searchPlaceholder}
                    aria-label={searchPlaceholder}
                    type="search"
                    className="w-full sm:w-72"
                />
            </form>
            {issueDates && (
                <DateRangePicker
                    from={filters.issue_from}
                    to={filters.issue_to}
                    onSelect={(range) =>
                        apply({ issue_from: range.from, issue_to: range.to })
                    }
                    placeholder="Issue date range"
                />
            )}
            {dateLabel && (
                <DateRangePicker
                    from={filters.from}
                    to={filters.to}
                    onSelect={apply}
                    placeholder={dateLabel}
                />
            )}
            {statusOptions && (
                <Select
                    value={filters[statusKey] || 'all'}
                    onValueChange={(value) => apply({ [statusKey]: value })}
                >
                    <SelectTrigger
                        aria-label={
                            statusKey === 'result'
                                ? 'Filter by result'
                                : 'Filter by status'
                        }
                        className="w-36"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem value="all">
                                {statusKey === 'result'
                                    ? 'All results'
                                    : 'All statuses'}
                            </SelectItem>
                            {statusOptions.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    </SelectContent>
                </Select>
            )}
            <span className="text-xs text-muted-foreground">
                {total} {noun}
                {total === 1 ? '' : 's'}
            </span>
            {filtering && (
                <span role="status" className="text-xs text-muted-foreground">
                    Loading {noun}s…
                </span>
            )}
            {Object.values(filters).some(
                (value) => value && value !== 'all',
            ) && (
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                        clearTimeout(searchTimer.current);
                        setSearch('');
                        router.get(
                            route,
                            {},
                            {
                                preserveState: true,
                                preserveScroll: true,
                                replace: true,
                            },
                        );
                    }}
                >
                    <RotateCcw data-icon="inline-start" />
                    Reset
                </Button>
            )}
        </div>
    );
}
