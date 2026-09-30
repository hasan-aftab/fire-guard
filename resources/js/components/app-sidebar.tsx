import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    ClipboardCheck,
    Flame,
    LayoutDashboard,
    ShieldCheck,
    Users,
} from 'lucide-react';
import { index as assets } from '@/actions/App/Http/Controllers/AssetController';
import { index as certificates } from '@/actions/App/Http/Controllers/CertificateController';
import { index as clients } from '@/actions/App/Http/Controllers/ClientController';
import { index as inspections } from '@/actions/App/Http/Controllers/InspectionController';
import { index as technicians } from '@/actions/App/Http/Controllers/TechnicianController';
import AppLogo from '@/components/app-logo';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
    useSidebar,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
const management = [
    {
        href: clients,
        icon: Building2,
        label: 'Clients & Sites',
        count: 'clients',
    },
    { href: assets, icon: Flame, label: 'Assets', count: 'assets' },
    {
        href: inspections,
        icon: ClipboardCheck,
        label: 'Inspections',
        count: 'inspections',
    },
    {
        href: certificates,
        icon: ShieldCheck,
        label: 'Compliance',
        count: 'certificates',
    },
    {
        href: technicians,
        icon: Users,
        label: 'Technicians',
        count: 'technicians',
    },
] as const;
export function AppSidebar() {
    const { counts } = usePage().props;
    const currentPath = usePage().url.split('?')[0];
    const { isMobile, setOpenMobile } = useSidebar();
    const closeMobileSidebar = () => {
        if (isMobile) {
            setOpenMobile(false);
        }
    };
    return (
        <Sidebar collapsible="icon">
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
                <SidebarGroup>
                    <SidebarGroupLabel>Main</SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    asChild
                                    tooltip="Dashboard"
                                    isActive={currentPath === dashboard.url()}
                                >
                                    <Link
                                        href={dashboard()}
                                        prefetch
                                        onClick={closeMobileSidebar}
                                    >
                                        <LayoutDashboard />
                                        <span>Dashboard</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
                <SidebarSeparator className="max-w-[calc(100%_-_1rem)]" />
                <SidebarGroup>
                    <SidebarGroupLabel>Management</SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            {management.map((item) => (
                                <SidebarMenuItem key={item.count}>
                                    <SidebarMenuButton
                                        asChild
                                        tooltip={item.label}
                                        isActive={
                                            currentPath === item.href.url()
                                        }
                                    >
                                        <Link
                                            href={item.href()}
                                            prefetch
                                            onClick={closeMobileSidebar}
                                        >
                                            <item.icon />
                                            <span>{item.label}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                    {!!counts?.[item.count] && (
                                        <SidebarMenuBadge className="bg-sidebar-accent text-sidebar-accent-foreground">
                                            {counts[item.count]}
                                        </SidebarMenuBadge>
                                    )}
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            </SidebarContent>
            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
