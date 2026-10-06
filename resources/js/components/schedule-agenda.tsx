import { useState } from 'react';
import { CheckCircle2 } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { ScheduleRow } from '@/components/schedule-row';
import { kindBreakdown, sinceSentence } from '@/lib/schedule';
import { cn } from '@/lib/utils';
import type {
    ScheduleGroup,
    ScheduleGroupId,
    ScheduleItem,
    ScheduleOverdueMode,
} from '@/types/fire-tracker';

/**
 * Rows shown before a group folds. The agenda is the page's main work list, so it
 * gets more room than a sidebar summary, but a backlog holding twenty records
 * should still not bury the two groups under it.
 */
const COLLAPSE_AFTER = 8;

/**
 * The work, as three fixed sections.
 *
 * This replaces a version that repeated a heading per calendar day. That made
 * "5 overdue" appear as five separate headings and inflated the count on screen
 * past the number the tile reported, because every late date became its own
 * section. Late work is now one section, and the two forward sections split at
 * the server's own boundary so a row's section can never disagree with the tile
 * that measured it.
 *
 * All three sections are always present in this order. A section holding nothing
 * is dropped from the render, but the array slots stay fixed, so nothing has to
 * index around a missing group.
 */
export function ScheduleAgenda({
    groups,
    returnedCount,
    totalCount,
    truncated,
    maxItems,
    overdueMode,
    overdueCount,
    windowItemCount,
    asOfDate,
    onShowOverdue,
    onShowWindow,
}: {
    groups: ScheduleGroup[];
    returnedCount: number;
    totalCount: number;
    truncated: boolean;
    maxItems: number;
    overdueMode: ScheduleOverdueMode;
    /** Total late records in scope, whether or not the mode is hiding them. */
    overdueCount: number;
    /** Total in-window records in scope, whether or not the mode is hiding them. */
    windowItemCount: number;
    asOfDate: string;
    /** Offers the scope a mode has hidden, so its count stays reachable. */
    onShowOverdue?: () => void;
    onShowWindow?: () => void;
}) {
    const filled = groups.filter((group) => group.itemCount > 0);

    if (filled.length === 0) {
        return (
            <EmptyState
                icon={CheckCircle2}
                title={
                    overdueMode === 'only'
                        ? 'Nothing overdue'
                        : 'Nothing due in this range'
                }
                description={
                    overdueMode === 'only'
                        ? 'Everything is in date.'
                        : 'Try a wider range, or include the overdue backlog.'
                }
            />
        );
    }

    const hidden = hiddenScope(overdueMode, overdueCount, windowItemCount);

    return (
        <div className="flex flex-col" data-agenda>
            {filled.map((group) => (
                <GroupSection
                    key={group.id}
                    group={group}
                    asOfDate={asOfDate}
                />
            ))}

            {truncated && (
                <div
                    className="border-t px-4 py-3 text-xs text-muted-foreground"
                    role="status"
                >
                    Showing {returnedCount} of {totalCount} records. The view is
                    capped at {maxItems} — widen the range or filter by type to
                    see the rest.
                </div>
            )}

            {hidden !== null && (
                <div className="border-t px-4 py-2">
                    <button
                        type="button"
                        /*
                         * The padding is for the target, not the looks: the
                         * label is text-xs on its own line, so at 16px tall it
                         * fell under WCAG 2.2 AA's 24px minimum. The negative
                         * margin gives that height back, keeping the row
                         * spacing the text size would otherwise have had.
                         */
                        className="-mx-1 min-h-6 px-1 py-1 text-xs font-medium text-foreground underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                        onClick={
                            hidden === 'overdue' ? onShowOverdue : onShowWindow
                        }
                    >
                        {hidden === 'overdue'
                            ? 'Show the overdue backlog'
                            : 'Show everything due in this range'}
                    </button>
                </div>
            )}
        </div>
    );
}

/**
 * Which scope the current mode is hiding.
 *
 * This reads the server's scope totals rather than the visible groups, because a
 * hidden scope has no groups to look at: in overdue-only mode the window groups
 * come back empty, and in exclude mode the overdue group does. Inferring it from
 * what is on screen would therefore never offer to reveal the half that is
 * missing, and the count on the matching tile could not be reached from here.
 */
function hiddenScope(
    mode: ScheduleOverdueMode,
    overdueCount: number,
    windowItemCount: number,
): ScheduleGroupId | null {
    if (mode === 'only') {
        return windowItemCount > 0 ? 'next' : null;
    }

    if (mode === 'exclude') {
        return overdueCount > 0 ? 'overdue' : null;
    }

    return null;
}

function GroupSection({
    group,
    asOfDate,
}: {
    group: ScheduleGroup;
    asOfDate: string;
}) {
    const [expanded, setExpanded] = useState(false);
    const collapsible = group.items.length > COLLAPSE_AFTER;
    const visible =
        collapsible && !expanded ? COLLAPSE_AFTER : group.items.length;
    const hidden = group.items.length - visible;
    const isOverdue = group.id === 'overdue';

    return (
        <section className="border-t first:border-t-0" data-group={group.id}>
            <h3
                className={cn(
                    'flex flex-wrap items-baseline gap-x-2 gap-y-0.5 bg-muted/40 px-4 py-2 text-xs font-medium text-muted-foreground',
                    isOverdue && 'font-semibold text-destructive',
                )}
            >
                <span>{group.label}</span>

                <span className="tabular-nums">
                    {group.itemCount} record{group.itemCount === 1 ? '' : 's'}
                </span>

                {/*
                    The breakdown and the oldest date are what make the count
                    believable: "5 overdue" alone cannot be checked against
                    anything, and "3 inspections · 2 certificates, oldest 20 Aug"
                    can.
                */}
                <span className="font-normal">
                    {kindBreakdown(group.byKind, isOverdue ? 'overdue' : 'due')}
                </span>

                {isOverdue && group.firstDate && (
                    <span className="font-normal">
                        {sinceSentence(group.firstDate)}
                    </span>
                )}
            </h3>

            <ul className="divide-y">
                {group.items.slice(0, visible).map((item: ScheduleItem) => (
                    <ScheduleRow
                        key={item.key}
                        item={item}
                        asOfDate={asOfDate}
                    />
                ))}
            </ul>

            {/* Rendered whenever the group can fold, including once expanded:
                otherwise there is no way back down to the folded view. */}
            {collapsible && (
                <div className="border-t">
                    <button
                        type="button"
                        className="w-full px-4 py-2 text-xs font-medium text-foreground underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                        onClick={() => setExpanded((value) => !value)}
                        aria-expanded={expanded}
                    >
                        {expanded
                            ? 'Show fewer'
                            : `Show ${hidden} more in this group`}
                    </button>
                </div>
            )}
        </section>
    );
}
