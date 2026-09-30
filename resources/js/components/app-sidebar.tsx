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
            <SidebarHeader className="border-b border-sidebar-border p-4">
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
                    <SidebarGroupLabel className="text-[10px] tracking-widest text-sidebar-foreground/40 uppercase">
                        Main
                    </SidebarGroupLabel>
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
                    <SidebarGroupLabel className="text-[10px] tracking-widest text-sidebar-foreground/40 uppercase">
                        Management
                    </SidebarGroupLabel>
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
                                        className="data-[active=true]:border-l-2 data-[active=true]:border-sidebar-primary data-[active=true]:text-sidebar-primary"
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
                                        <SidebarMenuBadge className="bg-sidebar-foreground/10 text-sidebar-foreground">
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
                <SidebarSeparator className="max-w-[calc(100%_-_1rem)]" />
                <div className="flex items-center gap-2 px-2 py-2 text-[11px] text-sidebar-foreground/50 group-data-[collapsible=icon]:hidden">
                    <ShieldCheck className="size-3.5" />
                    <span>v1.0 — Fire Safety Compliance</span>
                </div>
            </SidebarFooter>
        </Sidebar>
    );
}
