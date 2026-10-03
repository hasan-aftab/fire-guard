import { Link, usePage } from '@inertiajs/react';
import { Mail, Moon, Sun } from 'lucide-react';
import { index as reminders } from '@/actions/App/Http/Controllers/ReminderHistoryController';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { NotificationBell } from '@/components/notification-bell';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useAppearance } from '@/hooks/use-appearance';
import type { BreadcrumbItem } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItem[];
}) {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const reminderCount = usePage().props.counts?.reminders ?? 0;

    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="ml-auto flex items-center gap-1">
                <Button variant="ghost" size="sm" asChild>
                    <Link
                        href={reminders()}
                        aria-label={`Reminder activity, ${reminderCount} deliveries`}
                        className="gap-2"
                    >
                        <Mail className="size-4" aria-hidden="true" />
                        <span className="hidden sm:inline">Reminders</span>
                        <span className="flex min-w-5 items-center justify-center rounded-full bg-sidebar-primary px-1.5 text-xs font-semibold text-sidebar-primary-foreground">
                            {reminderCount}
                        </span>
                    </Link>
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Toggle theme"
                    onClick={() =>
                        updateAppearance(
                            resolvedAppearance === 'dark' ? 'light' : 'dark',
                        )
                    }
                >
                    <Sun className="hidden dark:block" />
                    <Moon className="dark:hidden" />
                </Button>
                <NotificationBell />
            </div>
        </header>
    );
}
