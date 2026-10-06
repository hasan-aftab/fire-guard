import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    ClipboardCheck,
    Flame,
    LayoutDashboard,
    FileText,
    Mail,
    Shapes,
    Files,
    ShieldCheck,
    Users,
    MessageCircle,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { index as assets } from '@/actions/App/Http/Controllers/AssetController';
import { index as assetTypes } from '@/actions/App/Http/Controllers/AssetTypeController';
import { index as certificates } from '@/actions/App/Http/Controllers/CertificateController';
import { index as certificateTypes } from '@/actions/App/Http/Controllers/CertificateTypeController';
import { index as clients } from '@/actions/App/Http/Controllers/ClientController';
import { index as inspections } from '@/actions/App/Http/Controllers/InspectionController';
import { index as technicians } from '@/actions/App/Http/Controllers/TechnicianController';
import { index as reports } from '@/actions/App/Http/Controllers/ReportController';
import { index as reminders } from '@/actions/App/Http/Controllers/ReminderHistoryController';
import { index as whatsapp } from '@/actions/App/Http/Controllers/WhatsAppController';
import AppLogo from '@/components/app-logo';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { Counts } from '@/types/fire-tracker';
import type { NavItem } from '@/types';

type PlatformNavItem = Omit<NavItem, 'icon'> & {
    icon: LucideIcon;
    count?: keyof Counts;
};

type NavGroup = {
    label?: string;
    adminOnly?: boolean;
    items: PlatformNavItem[];
};

const navGroups: NavGroup[] = [
    {
        items: [
            { title: 'Dashboard', href: dashboard(), icon: LayoutDashboard },
        ],
    },
    {
        label: 'Records',
        items: [
            {
                title: 'Clients & Sites',
                href: clients(),
                icon: Building2,
                count: 'clients',
            },
            { title: 'Assets', href: assets(), icon: Flame, count: 'assets' },
            {
                title: 'Inspections',
                href: inspections(),
                icon: ClipboardCheck,
                count: 'inspections',
            },
            {
                title: 'Compliance',
                href: certificates(),
                icon: ShieldCheck,
                count: 'certificates',
            },
            {
                title: 'Technicians',
                href: technicians(),
                icon: Users,
                count: 'technicians',
            },
        ],
    },
    {
        label: 'Outputs',
        items: [
            {
                title: 'Reports',
                href: reports(),
                icon: FileText,
                count: 'reports',
            },
            {
                title: 'Reminders',
                href: reminders(),
                icon: Mail,
                count: 'reminders',
            },
        ],
    },
    {
        label: 'Configuration',
        adminOnly: true,
        items: [
            {
                title: 'WhatsApp',
                href: whatsapp(),
                icon: MessageCircle,
                count: 'whatsapp',
            },
            {
                title: 'Asset Types',
                href: assetTypes(),
                icon: Shapes,
                count: 'assetTypes',
            },
            {
                title: 'Certificate Types',
                href: certificateTypes(),
                icon: Files,
                count: 'certificateTypes',
            },
        ],
    },
];

export function AppSidebar() {
    const { counts, auth } = usePage().props;
    const { isCurrentUrl } = useCurrentUrl();
    const { isMobile, setOpenMobile } = useSidebar();

    const closeMobileSidebar = () => {
        if (isMobile) {
            setOpenMobile(false);
        }
    };

    const activeItemClass =
        'relative border border-sidebar-border bg-sidebar-accent font-semibold text-sidebar-accent-foreground shadow-xs before:absolute before:inset-y-1.5 before:left-0 before:w-0.5 before:rounded-full before:bg-brand group-data-[collapsible=icon]:before:hidden';

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link
                                href={dashboard()}
                                prefetch
                                onClick={closeMobileSidebar}
                            >
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent>
                {navGroups.map((group) => {
                    if (group.adminOnly && auth.user.role !== 'admin') {
                        return null;
                    }

                    return (
                        <SidebarGroup
                            key={group.label ?? group.items[0].title}
                            className="px-2 py-0"
                        >
                            {group.label && (
                                <SidebarGroupLabel>
                                    {group.label}
                                </SidebarGroupLabel>
                            )}
                            <SidebarMenu>
                                {group.items.map((item) => (
                                    <SidebarMenuItem key={item.title}>
                                        <SidebarMenuButton
                                            asChild
                                            tooltip={item.title}
                                            isActive={isCurrentUrl(item.href)}
                                            className={cn(
                                                isCurrentUrl(item.href) &&
                                                    activeItemClass,
                                            )}
                                        >
                                            <Link
                                                href={item.href}
                                                prefetch
                                                onClick={closeMobileSidebar}
                                            >
                                                <item.icon />
                                                <span>{item.title}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                        {item.count &&
                                            counts?.[item.count] !==
                                                undefined && (
                                                <SidebarMenuBadge className="bg-sidebar-accent text-sidebar-accent-foreground">
                                                    {counts[item.count]}
                                                </SidebarMenuBadge>
                                            )}
                                    </SidebarMenuItem>
                                ))}
                            </SidebarMenu>
                        </SidebarGroup>
                    );
                })}
            </SidebarContent>
            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
