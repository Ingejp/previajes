import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ClipboardList, Disc3, LayoutGrid, Settings2, ShieldCheck, Truck } from 'lucide-react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;

    // El menú se arma según el rol, pero cada pantalla vuelve a autorizar en el
    // backend: esconder un enlace no protege nada por sí solo (§7).
    const navItems: NavItem[] = [];

    // El dashboard va primero: es la vista general de estatus de flota.
    if (auth.permisos?.ver_dashboard) {
        navItems.push({ title: 'Dashboard', url: '/dashboard', icon: LayoutGrid });
    }

    navItems.push(
        { title: 'Previajes', url: '/previajes', icon: ClipboardList },
        // RF-17.1: registro interino, disponible para mecánico y supervisor.
        { title: 'Cambio de llantas', url: '/llantas', icon: Disc3 },
    );

    if (auth.permisos?.ver_dashboard) {
        navItems.push({ title: 'Equipos', url: '/equipos', icon: Truck });
    }

    if (auth.permisos?.ver_auditoria) {
        navItems.push({ title: 'Auditoría', url: '/auditoria', icon: ShieldCheck });
    }

    // RF-18: administración de catálogos (flotas, tipos de equipo, usuarios…).
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
