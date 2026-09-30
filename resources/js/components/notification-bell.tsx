import { router, useHttp, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Bell,
    ClipboardCheck,
    Clock,
    ShieldAlert,
    ShieldOff,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { index as assetsIndex } from '@/actions/App/Http/Controllers/AssetController';
import { index as certificatesIndex } from '@/actions/App/Http/Controllers/CertificateController';
import {
    markAllRead,
    store,
    update,
} from '@/actions/App/Http/Controllers/FireNotificationController';
import { index as inspectionsIndex } from '@/actions/App/Http/Controllers/InspectionController';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import type { FireNotification, NotificationData } from '@/types/fire-tracker';

const icons = {
    overdue_asset: AlertTriangle,
    due_soon_asset: Clock,
    expiring_cert: ShieldAlert,
    expired_cert: ShieldOff,
    failed_inspection: ClipboardCheck,
};

const iconColors = {
    overdue_asset:
        'bg-red-100 text-red-700 ring-1 ring-red-200 dark:bg-red-950 dark:text-red-300 dark:ring-red-800',
    due_soon_asset:
        'bg-amber-100 text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:ring-amber-800',
    expiring_cert:
        'bg-orange-100 text-orange-700 ring-1 ring-orange-200 dark:bg-orange-950 dark:text-orange-300 dark:ring-orange-800',
    expired_cert:
        'bg-red-200 text-red-900 ring-1 ring-red-300 dark:bg-red-900 dark:text-red-100 dark:ring-red-700',
    failed_inspection:
        'bg-rose-100 text-rose-800 ring-1 ring-rose-200 dark:bg-rose-950 dark:text-rose-300 dark:ring-rose-800',
};

const entityRoutes = {
    asset: assetsIndex,
    certificate: certificatesIndex,
    inspection: inspectionsIndex,
};

const relativeTime = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

function timeAgo(createdAt: string): string {
    const seconds = Math.round((Date.parse(createdAt) - Date.now()) / 1000);
    if (!Number.isFinite(seconds)) {
        return '';
    }
    if (Math.abs(seconds) < 60) {
        return relativeTime.format(seconds, 'second');
    }
    const minutes = Math.round(seconds / 60);
    if (Math.abs(minutes) < 60) {
        return relativeTime.format(minutes, 'minute');
    }
    const hours = Math.round(minutes / 60);
    if (Math.abs(hours) < 24) {
        return relativeTime.format(hours, 'hour');
    }
    const days = Math.round(hours / 24);
    if (Math.abs(days) < 30) {
        return relativeTime.format(days, 'day');
    }
    const months = Math.round(days / 30);
    if (Math.abs(months) < 12) {
        return relativeTime.format(months, 'month');
    }
    return relativeTime.format(Math.round(months / 12), 'year');
}

export function NotificationBell() {
    const { fireNotifications } = usePage().props;
    const { post } = useHttp<Record<string, never>, NotificationData>({});
    const { patch } = useHttp<Record<string, never>, NotificationData>({});
    const [notifications, setNotifications] = useState<NotificationData | null>(
        fireNotifications,
    );
    const [open, setOpen] = useState(false);
    const [refreshing, setRefreshing] = useState(false);
    const [pendingAction, setPendingAction] = useState<number | 'all' | null>(
        null,
    );
    const [error, setError] = useState<string | null>(null);
    const active = useRef(false);
    const refreshingRef = useRef(false);
    const mutationRevision = useRef(0);
    const previousCount = useRef(notifications?.unreadCount ?? 0);
    const count = notifications?.unreadCount ?? 0;

    const refresh = useCallback(async () => {
        if (refreshingRef.current || document.visibilityState === 'hidden') {
            return;
        }
        refreshingRef.current = true;
        const startedAtRevision = mutationRevision.current;
        setRefreshing(true);
        try {
            const data = await post(store.url());
            if (
                active.current &&
                startedAtRevision === mutationRevision.current
            ) {
                setNotifications(data);
                setError(null);
            }
        } catch {
            if (
                active.current &&
                startedAtRevision === mutationRevision.current
            ) {
                setError('Could not refresh notifications.');
            }
        } finally {
            refreshingRef.current = false;
            if (active.current) {
                setRefreshing(false);
            }
        }
    }, [post]);

    useEffect(() => {
        active.current = true;
        const initial = window.setTimeout(() => void refresh(), 500);
        const interval = window.setInterval(() => void refresh(), 120000);
        const onVisibilityChange = () => {
            if (document.visibilityState === 'visible') {
                void refresh();
            }
        };
        document.addEventListener('visibilitychange', onVisibilityChange);
        const removeSuccessListener = router.on('success', (event) => {
            if (event.detail.page.props.fireNotifications) {
                setNotifications(event.detail.page.props.fireNotifications);
            }
        });
        return () => {
            active.current = false;
            window.clearTimeout(initial);
            window.clearInterval(interval);
            document.removeEventListener(
                'visibilitychange',
                onVisibilityChange,
            );
            removeSuccessListener();
        };
    }, [refresh]);

    useEffect(() => {
        if (
            count > previousCount.current &&
            navigator.userActivation?.hasBeenActive
        ) {
            try {
                const context = new AudioContext();
                const oscillator = context.createOscillator();
                const gain = context.createGain();
                oscillator.connect(gain);
                gain.connect(context.destination);
                oscillator.frequency.setValueAtTime(800, context.currentTime);
                oscillator.frequency.setValueAtTime(
                    600,
                    context.currentTime + 0.1,
                );
                gain.gain.setValueAtTime(0.1, context.currentTime);
                gain.gain.exponentialRampToValueAtTime(
                    0.01,
                    context.currentTime + 0.3,
                );
                oscillator.onended = () => void context.close();
                oscillator.start();
                oscillator.stop(context.currentTime + 0.3);
            } catch {
                // Audio can be unavailable even after user activation.
            }
        }
        previousCount.current = count;
    }, [count]);

    const markAllAsRead = async () => {
        if (pendingAction !== null) {
            return;
        }
        mutationRevision.current += 1;
        setPendingAction('all');
        try {
            setNotifications(await patch(markAllRead.url()));
            setError(null);
        } catch {
            setError('Could not mark notifications as read.');
        } finally {
            setPendingAction(null);
        }
    };

    const markAsRead = async (notification: FireNotification) => {
        if (pendingAction !== null) {
            return;
        }
        mutationRevision.current += 1;
        setPendingAction(notification.id);
        try {
            setNotifications(await patch(update.url(notification.id)));
            setError(null);
            setOpen(false);
            router.visit(entityRoutes[notification.entity_type]());
        } catch {
            setError('Could not open this notification. Try again.');
        } finally {
            setPendingAction(null);
        }
    };

    return (
        <DropdownMenu open={open} onOpenChange={setOpen}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Notifications, ${count} unread`}
                    className="relative rounded-full hover:bg-muted/80"
                >
                    <Bell
                        key={count}
                        className={cn(
                            'size-[18px]',
                            count > 0 && 'animate-bell',
                        )}
                    />
                    {count > 0 && (
                        <span className="animate-badge-bounce pointer-events-none absolute -top-0.5 -right-0.5 z-10 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-red-600 px-1 text-[10px] leading-none font-semibold text-white ring-2 ring-background">
                            {count > 99 ? '99+' : count}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                sideOffset={8}
                aria-label="Notifications"
                className="w-80 max-w-[calc(100vw-1rem)] p-0"
            >
                <div className="flex min-h-12 items-center justify-between gap-2 p-3">
                    <h2 className="text-sm font-semibold">Notifications</h2>
                    {count > 0 && (
                        <DropdownMenuItem
                            disabled={pendingAction !== null}
                            onSelect={(event) => {
                                event.preventDefault();
                                void markAllAsRead();
                            }}
                            className="h-7 px-2 text-xs text-muted-foreground hover:text-foreground"
                        >
                            {pendingAction === 'all'
                                ? 'Marking...'
                                : 'Mark all read'}
                        </DropdownMenuItem>
                    )}
                </div>
                <DropdownMenuSeparator className="m-0" />
                {error && (
                    <div
                        className="flex items-center justify-between gap-2 border-b px-3 py-2 text-xs text-destructive"
                        role="alert"
                    >
                        <span>{error}</span>
                        <DropdownMenuItem
                            onSelect={(event) => {
                                event.preventDefault();
                                void refresh();
                            }}
                            className="h-7 px-2 text-xs"
                        >
                            Refresh
                        </DropdownMenuItem>
                    </div>
                )}
                <DropdownMenuGroup className="custom-scrollbar max-h-[min(400px,calc(100dvh-8rem))] overflow-y-auto p-1">
                    {!notifications ? (
                        <div
                            className="flex items-center justify-center p-8 text-sm text-muted-foreground"
                            role="status"
                        >
                            Loading notifications...
                        </div>
                    ) : notifications.items.length === 0 ? (
                        <div className="flex flex-col items-center justify-center p-8 text-center">
                            <Bell className="mb-2 size-8 text-muted-foreground/40" />
                            <p className="text-sm text-muted-foreground">
                                No notifications
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                You’re all caught up!
                            </p>
                        </div>
                    ) : (
                        notifications.items.map((notification) => {
                            const Icon = icons[notification.type];
                            return (
                                <DropdownMenuItem
                                    key={notification.id}
                                    disabled={pendingAction !== null}
                                    onSelect={(event) => {
                                        event.preventDefault();
                                        void markAsRead(notification);
                                    }}
                                    className={cn(
                                        'items-start gap-3 rounded-md p-2.5 text-left hover:bg-muted/50',
                                        !notification.is_read && 'bg-muted/30',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'flex size-8 shrink-0 items-center justify-center rounded-full',
                                            iconColors[notification.type],
                                        )}
                                    >
                                        <Icon
                                            className="size-4 text-current"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="flex items-center gap-2">
                                            {!notification.is_read && (
                                                <span className="sr-only">
                                                    Unread notification:{' '}
                                                </span>
                                            )}
                                            <span className="truncate text-sm leading-none font-medium">
                                                {notification.title}
                                            </span>
                                            {!notification.is_read && (
                                                <span
                                                    className="size-2 shrink-0 rounded-full bg-blue-500"
                                                    aria-hidden="true"
                                                />
                                            )}
                                        </span>
                                        <span className="mt-1 line-clamp-2 text-xs text-muted-foreground">
                                            {notification.message}
                                        </span>
                                        <time
                                            className="mt-1 block text-[11px] text-muted-foreground"
                                            dateTime={notification.created_at}
                                            title={new Date(
                                                notification.created_at,
                                            ).toLocaleString()}
                                        >
                                            {timeAgo(notification.created_at)}
                                        </time>
                                    </span>
                                </DropdownMenuItem>
                            );
                        })
                    )}
                </DropdownMenuGroup>
                {refreshing && notifications && (
                    <p
                        className="border-t px-3 py-1 text-[11px] text-muted-foreground"
                        role="status"
                    >
                        Refreshing notifications...
                    </p>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
