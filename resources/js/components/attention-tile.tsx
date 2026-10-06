import type { LucideIcon } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

type Tone = 'default' | 'destructive' | 'warning' | 'success';

/**
 * One number worth acting on. Deliberately not `StatCard`: that component is a
 * readout, and this one is a control — clicking it changes what the schedule
 * below shows, so it has to say so and to say when it is already the active
 * scope.
 *
 * Selection is a neutral fill and ring. The page's brand colour is not used
 * anywhere here: a colour that means "brand" on one card and "selected" on
 * another teaches the reader nothing.
 */
export function AttentionTile({
    label,
    value,
    icon: Icon,
    tone = 'default',
    detail,
    action,
    active = false,
    onClick,
}: {
    label: string;
    value: number | string;
    icon: LucideIcon;
    tone?: Tone;
    /** The breakdown under the number, e.g. "3 inspections · 2 certificates". */
    detail?: string;
    /** Names what clicking does, so the tile is never an unexplained button. */
    action?: string;
    active?: boolean;
    onClick?: () => void;
}) {
    const interactive = onClick !== undefined;

    return (
        <button
            type="button"
            onClick={onClick}
            disabled={!interactive}
            aria-pressed={interactive ? active : undefined}
            data-tile
            data-active={interactive ? active : undefined}
            data-status={tone}
            className={cn(
                'group block h-full w-full rounded-xl text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none',
                'disabled:cursor-default',
            )}
        >
            <Card
                className={cn(
                    'h-full gap-1 py-3 transition-colors',
                    active && 'bg-accent/60 ring-1 ring-foreground/20',
                    interactive && !active && 'hover:bg-accent/40',
                )}
            >
                <CardHeader className="flex flex-row items-start justify-between space-y-0 pb-0">
                    <CardTitle className="text-xs font-medium text-muted-foreground sm:text-sm">
                        {label}
                    </CardTitle>

                    <div className="flex items-center gap-1.5">
                        {/*
                            The active scope is announced by the ring and by this
                            word, not by colour alone. The ring is on the Card,
                            which sits inside the button, so the button itself
                            carries the focus ring and the Card carries selection.
                        */}
                        {active && (
                            <span className="text-xs font-medium text-foreground">
                                Showing
                            </span>
                        )}

                        <span
                            className={cn(
                                'flex size-7 shrink-0 items-center justify-center rounded-lg',
                                tone === 'destructive' && 'bg-destructive/10',
                                tone === 'warning' && 'bg-warning/10',
                                tone === 'success' && 'bg-success/10',
                                tone === 'default' && 'bg-muted',
                            )}
                        >
                            <Icon
                                className={cn(
                                    'size-3.5',
                                    tone === 'destructive' &&
                                        'text-destructive',
                                    tone === 'warning' && 'text-warning',
                                    tone === 'success' && 'text-success',
                                    tone === 'default' &&
                                        'text-muted-foreground',
                                )}
                                aria-hidden="true"
                            />
                        </span>
                    </div>
                </CardHeader>

                <CardContent>
                    <div
                        className={cn(
                            'text-2xl font-bold tracking-tight tabular-nums sm:text-3xl',
                            tone === 'destructive' && 'text-destructive',
                            tone === 'default' && 'text-foreground',
                        )}
                    >
                        {value}
                    </div>

                    {detail && (
                        <p className="mt-0.5 text-[11px] leading-snug text-muted-foreground sm:text-xs">
                            {detail}
                        </p>
                    )}

                    {interactive && (
                        <span className="sr-only">
                            {active
                                ? 'Clear schedule filter'
                                : (action ?? 'Show matching work')}
                        </span>
                    )}
                </CardContent>
            </Card>
        </button>
    );
}
