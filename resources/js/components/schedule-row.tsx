import { Link } from '@inertiajs/react';
import { Flame, ShieldCheck } from 'lucide-react';
import { StatusBadge } from '@/components/status-badge';
import { formatLongDate, relativeDue, shortDate } from '@/lib/dates';
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

    return (
        <li data-row data-kind={item.kind}>
            {/*
                The row is the link. The destination is already filtered to this
                record on the destination page, so the row opens the list view
                rather than a dead-end detail page.
            */}
            <Link
                href={item.href}
                className="flex items-start gap-3 px-4 py-3 transition-colors hover:bg-accent/40 focus-visible:bg-accent/40 focus-visible:outline-none"
            >
                <span
                    className={cn(
                        'mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-md',
                        item.isOverdue
                            ? 'bg-destructive/10 text-destructive'
                            : 'bg-muted text-muted-foreground',
                    )}
                    aria-hidden="true"
                >
                    <Icon className="size-4" />
                </span>

                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                        <span className="truncate text-sm font-medium text-foreground">
                            {item.typeName}
                        </span>
                        {item.reference && (
                            <span className="font-mono text-xs text-muted-foreground">
                                {item.reference}
                            </span>
                        )}
                        <span className="text-xs text-muted-foreground">
                            {label}
                        </span>
                    </div>

                    <p className="mt-0.5 truncate text-xs text-muted-foreground">
                        {location}
                    </p>

                    <p className="mt-1 text-xs">
                        <span
                            className={cn(
                                item.isOverdue
                                    ? 'font-medium text-destructive'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {relativeDue(item.dueDate, asOfDate)}
                        </span>
                        {/*
                            The full date earns its weekday and year only on the
                            overdue backlog, where a record can be a year late and
                            two "20 Aug" rows can be different years. In the
                            forward sections the range is days away, so the year on
                            every row is noise.
                        */}
                        <span className="text-muted-foreground">
                            {' · '}
                            {item.isOverdue
                                ? formatLongDate(item.dueDate)
                                : shortDate(item.dueDate)}
                        </span>
                    </p>
                </div>

                <div className="flex shrink-0 items-center">
                    <StatusBadge
                        status={item.status}
                        date={item.dueDate}
                        showDate={false}
                    />
                </div>
            </Link>
        </li>
    );
}
