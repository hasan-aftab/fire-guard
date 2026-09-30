import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export function serviceStatus(
    date: string | null,
    soonDays = 14,
    asOfDate?: string,
): 'unknown' | 'overdue' | 'due_soon' | 'ok' {
    if (!date) return 'unknown';
    const today = new Date();
    const todayKey =
        asOfDate ??
        `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
    const [todayYear, todayMonth, todayDate] = todayKey.split('-').map(Number);
    const todayDay = Date.UTC(todayYear, todayMonth - 1, todayDate);
    const [year, month, day] = date.split('-').map(Number);
    const days = Math.round(
        (Date.UTC(year, month - 1, day) - todayDay) / 86400000,
    );
    return days < 0 ? 'overdue' : days <= soonDays ? 'due_soon' : 'ok';
}

export function StatusBadge({
    date,
    showDate = true,
    soonDays = 14,
    asOfDate,
}: {
    date: string | null;
    showDate?: boolean;
    soonDays?: number;
    asOfDate?: string;
}) {
    const status = serviceStatus(date, soonDays, asOfDate);
    const labels = {
        overdue: 'Overdue',
        due_soon: 'Due soon',
        ok: 'OK',
        unknown: 'No due date',
    };
    return (
        <Badge
            variant={status === 'overdue' ? 'destructive' : 'outline'}
            className={cn(
                'gap-1.5 text-xs',
                status === 'due_soon' &&
                    'border-warning/40 bg-warning/10 text-warning',
                status === 'ok' &&
                    'border-success/40 bg-success/10 text-success',
            )}
        >
            <span className="size-1.5 rounded-full bg-current" />
            {labels[status]}
            {showDate && date && <span className="opacity-70">({date})</span>}
        </Badge>
    );
}

export function ResultBadge({ result }: { result: string }) {
    return (
        <Badge
            variant={result === 'fail' ? 'destructive' : 'outline'}
            className={cn(
                'text-xs',
                result === 'pass' &&
                    'border-success/40 bg-success/10 text-success',
                result === 'needs_repair' &&
                    'border-warning/40 bg-warning/10 text-warning',
            )}
        >
            {result === 'needs_repair'
                ? 'Needs repair'
                : result === 'pass'
                  ? 'Pass'
                  : 'Fail'}
        </Badge>
    );
}
