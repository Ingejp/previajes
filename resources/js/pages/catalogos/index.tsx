import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ClipboardCheck, Settings2, Sliders, Truck, Users, Waypoints } from 'lucide-react';

interface Props {
    resumen: {
        flotas: number;
        tiposEquipo: number;
        equipos: number;
        usuarios: number;
        secciones: number;
        configuraciones: number;
    };
}

const migas: BreadcrumbItem[] = [{ title: 'Catálogos', href: '/catalogos' }];

export default function CatalogosIndex({ resumen }: Props) {
    const tarjetas = [
        { titulo: 'Flotas', total: resumen.flotas, href: route('catalogos.flotas.index'), icono: Waypoints },
        { titulo: 'Tipos de equipo', total: resumen.tiposEquipo, href: route('catalogos.tipos-equipo.index'), icono: Sliders },
        { titulo: 'Equipos', total: resumen.equipos, href: route('catalogos.equipos.index'), icono: Truck },
        { titulo: 'Usuarios', total: resumen.usuarios, href: route('catalogos.usuarios.index'), icono: Users },
        { titulo: 'Secciones de checklist', total: resumen.secciones, href: route('catalogos.secciones.index'), icono: ClipboardCheck },
        { titulo: 'Configuraciones', total: resumen.configuraciones, href: route('catalogos.configuraciones.index'), icono: Settings2 },
    ];

    return (
        <AppLayout breadcrumbs={migas}>
            <Head title="Catálogos" />

            <div className="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
                <div>
                    <h1 className="text-xl font-semibold sm:text-2xl">Catálogos</h1>
                    <p className="text-sm text-muted-foreground">
                        Flotas, tipos de equipo, equipos, usuarios y el checklist de previaje (RF-18).
                    </p>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {tarjetas.map(({ titulo, total, href, icono: Icono }) => (
                        <Link
                            key={titulo}
                            href={href}
                            className="flex items-center gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4 transition hover:border-primary/50 hover:shadow-sm dark:border-sidebar-border"
                        >
                            <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted">
                                <Icono className="size-5" />
                            </div>
                            <div className="flex-1">
                                <p className="font-medium">{titulo}</p>
                                <p className="text-sm text-muted-foreground">{total} registrados</p>
                            </div>
                        </Link>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
