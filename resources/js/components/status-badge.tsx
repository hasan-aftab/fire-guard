import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export type DateStatus =
    | 'unknown'
    | 'overdue'
    | 'due_soon'
    | 'ok'
    | 'expired'
    | 'expiring_soon'
    | 'valid';

export function StatusBadge({
    status,
    date,
    showDate = true,
}: {
    status: DateStatus;
    date: string | null;
    showDate?: boolean;
}) {
    const labels = {
        overdue: 'Overdue',
        due_soon: 'Due soon',
        ok: 'OK',
        unknown: 'No due date',
        expired: 'Expired',
        expiring_soon: 'Expiring soon',
        valid: 'Valid',
    };
    return (
        <Badge
            variant={
                status === 'overdue' || status === 'expired'
                    ? 'destructive'
                    : 'outline'
            }
            className={cn(
                'gap-1.5 text-xs',
                (status === 'due_soon' || status === 'expiring_soon') &&
                    'border-warning/40 bg-warning/10 text-warning',
                (status === 'ok' || status === 'valid') &&
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
