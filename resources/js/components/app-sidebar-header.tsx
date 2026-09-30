import { usePage } from '@inertiajs/react';
import { Moon, Sun } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { NotificationBell } from '@/components/notification-bell';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useAppearance } from '@/hooks/use-appearance';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItem[];
}) {
    const { today } = usePage().props;
    const currentPath = usePage().url.split('?')[0];
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const titles: Record<string, string> = {
        dashboard: 'Dashboard',
        clients: 'Clients & Sites',
        assets: 'Assets',
        inspections: 'Inspections',
        compliance: 'Compliance',
        technicians: 'Technicians',
    };
    const title = titles[currentPath.split('/')[1]] ?? 'Settings';
    return (
        <header className="sticky top-0 z-10 flex h-12 shrink-0 items-center gap-3 border-b bg-background/95 px-4 backdrop-blur">
            <SidebarTrigger />
            <Separator orientation="vertical" className="h-4" />
            <Breadcrumbs
                breadcrumbs={
                    breadcrumbs.length
                        ? breadcrumbs
                        : [
                              { title: 'FireTrack', href: dashboard() },
                              { title, href: currentPath },
                          ]
                }
            />
            <div className="ml-auto flex items-center gap-3">
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
                <span className="hidden text-xs text-muted-foreground tabular-nums lg:block">
                    {new Date(`${today}T12:00:00`).toLocaleDateString('en-US', {
                        weekday: 'short',
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric',
                    })}
                </span>
            </div>
        </header>
    );
}
