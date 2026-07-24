import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ClipboardList, LayoutGrid, Settings2, ShieldCheck, Truck } from 'lucide-react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;

    // El menú se arma según el rol, pero cada pantalla vuelve a autorizar en el
    // backend: esconder un enlace no protege nada por sí solo (§7).
    const navItems: NavItem[] = [{ title: 'Previajes', url: '/previajes', icon: ClipboardList }];

    if (auth.permisos?.ver_dashboard) {
        navItems.push({ title: 'Equipos', url: '/equipos', icon: Truck });
        navItems.push({ title: 'Dashboard', url: '/dashboard', icon: LayoutGrid });
    }

    if (auth.permisos?.ver_auditoria) {
        navItems.push({ title: 'Auditoría', url: '/auditoria', icon: ShieldCheck });
    }

    if (auth.permisos?.administrar) {
        navItems.push({ title: 'Catálogos', url: '/catalogos', icon: Settings2 });
    }

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/previajes" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
