import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, CircleDot, Clock, Droplets } from 'lucide-react';

interface Props {
    periodo: { desde: string; hasta: string };
    inspeccionHoy: { flota_id: number; flota: string; total: number; inspeccionados: number; pendientes: number }[];
    hallazgosAbiertos: { equipo_id: number; codigo: string; tipo: string; flota: string; previaje_id: number; fecha: string }[];
    sinPreviajeReciente: {
        equipo_id: number;
        codigo: string;
        tipo: string;
        flota: string;
        umbral_dias: number;
        dias_sin_previaje: number | null;
        ultimo_previaje_en: string | null;
    }[];
    consumoFluidos: {
        total: number;
        porFluido: { fluido: string; galones: number; eventos: number }[];
        porEquipo: { equipo_id: number; equipo: string; flota: string; galones: number }[];
        porFlota: { flota_id: number; flota: string; galones: number }[];
    };
    consumoLlantas: {
        total: number;
        porEquipo: { equipo_id: number; equipo: string; flota: string; llantas: number }[];
    };
}

const migas: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

/** "Hoy" en la zona horaria del navegador, para que la tarjeta y el enlace coincidan con lo que el usuario ve en pantalla. */
function hoyISO(): string {
    const hoy = new Date();
    return new Date(hoy.getTime() - hoy.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
}

export default function Dashboard({
    periodo,
    inspeccionHoy,
    hallazgosAbiertos,
    sinPreviajeReciente,
    consumoFluidos,
    consumoLlantas,
}: Props) {
    const cambiarPeriodo = (campo: 'desde' | 'hasta', valor: string) => {
        router.get(route('dashboard'), { ...periodo, [campo]: valor }, { preserveScroll: true, preserveState: true });
    };

    const totalInspeccionados = inspeccionHoy.reduce((s, f) => s + f.inspeccionados, 0);
    const totalPendientes = inspeccionHoy.reduce((s, f) => s + f.pendientes, 0);
    const hoy = hoyISO();

    return (
        <AppLayout breadcrumbs={migas}>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
                <div className="flex flex-wrap items-end gap-3">
                    <h1 className="flex-1 text-xl font-semibold sm:text-2xl">Estatus de flota</h1>

                    <label className="flex flex-col gap-1 text-xs">
                        <span className="font-medium text-muted-foreground">Desde</span>
                        <input
                            type="date"
                            value={periodo.desde}
                            onChange={(e) => cambiarPeriodo('desde', e.target.value)}
                            className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                        />
                    </label>
                    <label className="flex flex-col gap-1 text-xs">
                        <span className="font-medium text-muted-foreground">Hasta</span>
                        <input
                            type="date"
                            value={periodo.hasta}
                            onChange={(e) => cambiarPeriodo('hasta', e.target.value)}
                            className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                        />
                    </label>
                </div>

                {/* Cada tarjeta lleva a la lista que agrupa: previajes o equipos filtrados. */}
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Indicador
                        etiqueta="Inspeccionados hoy"
                        valor={totalInspeccionados}
                        icono={<CircleDot className="size-4" />}
                        href={route('previajes.index', { desde: hoy, hasta: hoy })}
                    />
                    <Indicador
                        etiqueta="Pendientes hoy"
                        valor={totalPendientes}
                        icono={<Clock className="size-4" />}
                        href={route('equipos.index')}
                    />
                    <Indicador
                        etiqueta="Con hallazgos abiertos"
                        valor={hallazgosAbiertos.length}
                        icono={<AlertTriangle className="size-4" />}
                        destacado={hallazgosAbiertos.length > 0}
                        href={route('previajes.index', { estatus: 'con_hallazgos' })}
                    />
                    <Indicador
                        etiqueta="Galones agregados"
                        valor={consumoFluidos.total}
                        icono={<Droplets className="size-4" />}
                        sufijo="gal"
                        href="#consumo-fluidos"
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Panel titulo="Inspección de hoy por flota">
                        {inspeccionHoy.length === 0 ? (
                            <Vacio>No hay flotas asignadas.</Vacio>
                        ) : (
                            <ul className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                {inspeccionHoy.map((f) => (
                                    <li key={f.flota_id}>
                                        <Link
                                            href={route('previajes.index', { flota_id: f.flota_id, desde: hoy, hasta: hoy })}
                                            className="flex items-center gap-3 p-4 hover:bg-accent"
                                        >
                                            <span className="flex-1 font-medium">{f.flota}</span>
                                            <span className="text-sm text-muted-foreground">
                                                {f.inspeccionados} de {f.total}
                                            </span>
                                            <div className="h-2 w-24 overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className="h-full bg-emerald-600"
                                                    style={{ width: `${f.total ? (f.inspeccionados / f.total) * 100 : 0}%` }}
                                                />
                                            </div>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>

                    {/* RF-14: informativo, el previaje no bloquea la salida del equipo. */}
                    <Panel titulo="Equipos con hallazgos abiertos">
                        {hallazgosAbiertos.length === 0 ? (
                            <Vacio>Ningún equipo tiene hallazgos en su último previaje.</Vacio>
                        ) : (
                            <ul className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                {hallazgosAbiertos.map((h) => (
                                    <li key={h.equipo_id}>
                                        <Link
                                            href={route('previajes.show', h.previaje_id)}
                                            className="flex items-center gap-2 p-4 hover:bg-accent"
                                        >
                                            <span className="font-medium">{h.codigo}</span>
                                            <span className="text-xs text-muted-foreground">
                                                {h.tipo} · {h.flota}
                                            </span>
                                            <span className="ml-auto text-xs text-muted-foreground">
                                                {new Date(h.fecha).toLocaleDateString('es')}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>

                    {/* RF-16.1 / RN-12: el umbral es el del tipo de equipo, no uno global. */}
                    <Panel titulo="Más días sin previaje">
                        {sinPreviajeReciente.length === 0 ? (
                            <Vacio>Toda la flota está al día.</Vacio>
                        ) : (
                            <ul className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                {sinPreviajeReciente.map((e) => (
                                    <li key={e.equipo_id}>
                                        <Link
                                            href={route('previajes.index', { equipo_id: e.equipo_id })}
                                            className="flex items-center gap-2 p-4 hover:bg-accent"
                                        >
                                            <span className="font-medium">{e.codigo}</span>
                                            <span className="text-xs text-muted-foreground">
                                                {e.tipo} · {e.flota}
                                            </span>
                                            <span className="ml-auto text-sm font-medium text-red-700 dark:text-red-300">
                                                {e.dias_sin_previaje === null ? 'Sin previajes' : `${e.dias_sin_previaje} d`}
                                            </span>
                                            <span className="text-xs text-muted-foreground">(umbral {e.umbral_dias} d)</span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>

                    {/* RF-17: consumo de fluidos, insumo para detectar fugas. Sin
                        enlace propio: el previaje no se puede filtrar por tipo de
                        fluido, así que no hay una lista a la que llevar aquí. */}
                    <Panel titulo="Consumo de fluidos por tipo">
                        {consumoFluidos.porFluido.length === 0 ? (
                            <Vacio>No se registraron galones agregados en el período.</Vacio>
                        ) : (
                            <ul className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                {consumoFluidos.porFluido.map((f) => (
                                    <li key={f.fluido} className="flex items-center gap-3 p-4">
                                        <span className="flex-1 text-sm">{f.fluido}</span>
                                        <span className="text-xs text-muted-foreground">{f.eventos} veces</span>
                                        <span className="font-medium tabular-nums">{f.galones} gal</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>

                    <Panel titulo="Equipos con mayor consumo" id="consumo-fluidos">
                        {consumoFluidos.porEquipo.length === 0 ? (
                            <Vacio>Sin consumo registrado en el período.</Vacio>
                        ) : (
                            <ul className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                {consumoFluidos.porEquipo.map((e) => (
                                    <li key={e.equipo_id}>
                                        <Link
                                            href={route('previajes.index', { equipo_id: e.equipo_id })}
                                            className="flex items-center gap-3 p-4 hover:bg-accent"
                                        >
                                            <span className="flex-1 font-medium">{e.equipo}</span>
                                            <span className="text-xs text-muted-foreground">{e.flota}</span>
                                            <span className="font-medium tabular-nums">{e.galones} gal</span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>

                    {/* RF-17.1: dato interino, hasta que exista el módulo de inventario. */}
                    <Panel titulo={`Llantas cambiadas (${consumoLlantas.total} en el período)`}>
                        {consumoLlantas.porEquipo.length === 0 ? (
                            <Vacio>No se registraron cambios de llanta en el período.</Vacio>
                        ) : (
                            <ul className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                {consumoLlantas.porEquipo.map((e) => (
                                    <li key={e.equipo_id}>
                                        <Link
                                            href={route('llantas.index', { equipo_id: e.equipo_id })}
                                            className="flex items-center gap-3 p-4 hover:bg-accent"
                                        >
                                            <span className="flex-1 font-medium">{e.equipo}</span>
                                            <span className="text-xs text-muted-foreground">{e.flota}</span>
                                            <span className="font-medium tabular-nums">{e.llantas}</span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                        <p className="border-t border-sidebar-border/70 p-3 text-xs text-muted-foreground dark:border-sidebar-border">
                            Registro interino (RF-17.1): se reemplazará cuando exista el módulo de inventario.
                        </p>
                    </Panel>
                </div>
            </div>
        </AppLayout>
    );
}

function Indicador({
    etiqueta,
    valor,
    icono,
    sufijo,
    destacado = false,
    href,
}: {
    etiqueta: string;
    valor: number;
    icono: React.ReactNode;
    sufijo?: string;
    destacado?: boolean;
    /** Ruta de Inertia o ancla (`#id`) a los datos que agrupa esta tarjeta. */
    href?: string;
}) {
    const clases = `block rounded-xl border p-4 transition ${
        destacado
            ? 'border-amber-400 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/40'
            : 'border-sidebar-border/70 bg-card dark:border-sidebar-border'
    } ${href ? 'hover:border-primary/50 hover:shadow-sm' : ''}`;

    const contenido = (
        <>
            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                {icono}
                {etiqueta}
            </div>
            <p className="mt-1 text-2xl font-semibold tabular-nums">
                {valor}
                {sufijo && <span className="ml-1 text-sm font-normal text-muted-foreground">{sufijo}</span>}
            </p>
        </>
    );

    // Un ancla (#consumo-fluidos) es navegación de página, no una visita de
    // Inertia: se usa <a> nativo para que el navegador la resuelva solo.
    if (href?.startsWith('#')) {
        return (
            <a href={href} className={clases}>
                {contenido}
            </a>
        );
    }

    if (href) {
        return (
            <Link href={href} className={clases}>
                {contenido}
            </Link>
        );
    }

    return <div className={clases}>{contenido}</div>;
}

function Panel({ titulo, children, id }: { titulo: string; children: React.ReactNode; id?: string }) {
    return (
        <section id={id} className="scroll-mt-4 rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
            <h2 className="border-b border-sidebar-border/70 px-4 py-3 font-semibold dark:border-sidebar-border">{titulo}</h2>
            {children}
        </section>
    );
}

function Vacio({ children }: { children: React.ReactNode }) {
    return <p className="p-6 text-center text-sm text-muted-foreground">{children}</p>;
}
