import { Link } from '@inertiajs/react';
import { Flame, ShieldCheck } from 'lucide-react';
import { relativeDue, shortDate } from '@/lib/dates';
import { kindLabel } from '@/lib/schedule';
import { cn } from '@/lib/utils';
import type { ScheduleItem } from '@/types/fire-tracker';

const KIND_META = {
    asset: { icon: Flame },
    certificate: { icon: ShieldCheck },
} as const;

export function ScheduleRow({
    item,
    asOfDate,
}: {
    item: ScheduleItem;
    asOfDate: string;
}) {
    const { icon: Icon } = KIND_META[item.kind];
    const label = kindLabel(item.kind);
    const location = [item.siteName, item.clientName]
        .filter(Boolean)
        .join(' · ');
    const mobileLocation = [item.reference, item.locationDesc, item.siteName]
        .filter(Boolean)
        .join(' · ');

    return (
        <li data-row data-kind={item.kind}>
            {/*
                The row is the link. The destination is already filtered to this
                record on the destination page, so the row opens the list view
                rather than a dead-end detail page.
            */}
            <Link
                href={item.href}
                className="grid min-w-0 grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-0.5 px-4 py-2.5 transition-colors hover:bg-accent/40 focus-visible:bg-accent/40 focus-visible:outline-none sm:grid-cols-[minmax(0,1fr)_minmax(9rem,auto)_7rem]"
            >
                <div className="flex min-w-0 items-center gap-2">
                    <Icon
                        className={cn(
                            'size-4 shrink-0',
                            item.status === 'overdue'
                                ? 'text-destructive'
                                : item.status === 'due_soon'
                                  ? 'text-warning'
                                  : 'text-muted-foreground',
                        )}
                        aria-hidden="true"
                    />
                    <span className="min-w-0 truncate text-sm font-medium text-foreground">
                        {item.typeName}
                    </span>
                    {item.reference && (
                        <span className="hidden shrink-0 font-mono text-xs text-muted-foreground lg:inline">
                            {item.reference}
                        </span>
                    )}
                </div>
                <span className="row-start-2 truncate pl-6 text-xs text-muted-foreground sm:col-start-2 sm:row-start-1 sm:pl-0">
                    <span className="block truncate sm:hidden">
                        {mobileLocation}
                    </span>
                    <span className="hidden truncate sm:block">{location}</span>
                </span>
                <span
                    className={cn(
                        'col-start-2 row-start-1 text-right text-xs font-medium tabular-nums sm:col-start-3',
                        item.status === 'overdue'
                            ? 'text-destructive'
                            : item.status === 'due_soon'
                              ? 'text-warning'
                              : 'text-foreground',
                    )}
                >
                    {relativeDue(item.dueDate, asOfDate)}
                    <span className="block font-normal text-muted-foreground">
                        {item.isOverdue
                            ? `${shortDate(item.dueDate)} ${item.dueDate.slice(0, 4)}`
                            : shortDate(item.dueDate)}
                    </span>
                </span>
                <span className="sr-only">{label}</span>
            </Link>
        </li>
    );
}
