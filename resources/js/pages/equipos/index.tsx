import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Clock } from 'lucide-react';

interface EquipoFila {
    id: number;
    codigo: string;
    tipo: string;
    flota: string;
    marca: string | null;
    modelo: string | null;
    ultimo_kilometraje: number | null;
    ultimo_horometro: string | null;
    ultimo_previaje_id: number | null;
    ultimo_previaje_en: string | null;
    dias_sin_previaje: number | null;
    umbral_dias: number;
    atrasado: boolean;
    tiene_hallazgos: boolean;
}

interface Props {
    equipos: EquipoFila[];
    filtros: { flota_id?: string; tipo_equipo_id?: string; solo_atrasados?: string };
    opciones: {
        flotas: { id: number; nombre: string }[];
        tiposEquipo: { id: number; nombre: string }[];
    };
}

const migas: BreadcrumbItem[] = [{ title: 'Equipos', href: '/equipos' }];

export default function EquiposIndex({ equipos, filtros, opciones }: Props) {
    const filtrar = (cambio: Record<string, string | undefined>) => {
        const nuevos = { ...filtros, ...cambio };
        const limpios = Object.fromEntries(Object.entries(nuevos).filter(([, v]) => v !== '' && v != null));
        router.get(route('equipos.index'), limpios, { preserveScroll: true, preserveState: true });
    };

    const atrasados = equipos.filter((e) => e.atrasado).length;

    return (
        <AppLayout breadcrumbs={migas}>
            <Head title="Equipos" />

            <div className="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
                <div>
                    <h1 className="text-xl font-semibold sm:text-2xl">Equipos</h1>
                    {/* RF-16.1: el atraso se mide contra el umbral de cada tipo de equipo. */}
                    <p className="text-sm text-muted-foreground">
                        {equipos.length} equipos · {atrasados} sin previaje reciente
                    </p>
                </div>

                <div className="grid gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4 sm:grid-cols-3 dark:border-sidebar-border">
                    <label className="flex flex-col gap-1 text-sm">
                        <span className="text-xs font-medium text-muted-foreground">Flota</span>
                        <select
                            value={filtros.flota_id ?? ''}
                            onChange={(e) => filtrar({ flota_id: e.target.value })}
                            className="h-11 rounded-md border border-input bg-background px-3 text-base sm:h-10 sm:text-sm"
                        >
                            <option value="">Todas</option>
                            {opciones.flotas.map((f) => (
                                <option key={f.id} value={f.id}>{f.nombre}</option>
                            ))}
                        </select>
                    </label>

                    <label className="flex flex-col gap-1 text-sm">
                        <span className="text-xs font-medium text-muted-foreground">Tipo de equipo</span>
                        <select
                            value={filtros.tipo_equipo_id ?? ''}
                            onChange={(e) => filtrar({ tipo_equipo_id: e.target.value })}
                            className="h-11 rounded-md border border-input bg-background px-3 text-base sm:h-10 sm:text-sm"
                        >
                            <option value="">Todos</option>
                            {opciones.tiposEquipo.map((t) => (
                                <option key={t.id} value={t.id}>{t.nombre}</option>
                            ))}
                        </select>
                    </label>

                    <label className="flex items-end gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={Boolean(filtros.solo_atrasados)}
                            onChange={(e) => filtrar({ solo_atrasados: e.target.checked ? '1' : undefined })}
                            className="size-5 rounded border-input"
                        />
                        <span className="pb-2.5">Sólo sin previaje reciente</span>
                    </label>
                </div>

                {equipos.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-sidebar-border/70 p-10 text-center text-sm text-muted-foreground">
                        No hay equipos que coincidan con los filtros.
                    </p>
                ) : (
                    <>
                        <ul className="grid gap-3 lg:hidden">
                            {equipos.map((e) => (
                                <li key={e.id} className="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                                    <div className="flex items-center gap-2">
                                        <span className="font-semibold">{e.codigo}</span>
                                        <span className="text-xs text-muted-foreground">{e.tipo}</span>
                                        <Indicadores equipo={e} className="ml-auto" />
                                    </div>
                                    <dl className="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                                        <Campo etiqueta="Kilometraje" valor={e.ultimo_kilometraje?.toLocaleString('es') ?? '—'} />
                                        <Campo etiqueta="Horómetro" valor={e.ultimo_horometro ?? '—'} />
                                        <Campo
                                            etiqueta="Último previaje"
                                            valor={e.ultimo_previaje_en ? new Date(e.ultimo_previaje_en).toLocaleDateString('es') : 'Nunca'}
                                        />
                                        <Campo etiqueta="Días" valor={textoDias(e)} />
                                    </dl>
                                    {e.ultimo_previaje_id && (
                                        <Button asChild size="sm" variant="outline" className="mt-3 w-full">
                                            <Link href={route('previajes.show', e.ultimo_previaje_id)}>Ver último previaje</Link>
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>

                        <div className="hidden overflow-x-auto rounded-xl border border-sidebar-border/70 bg-card lg:block dark:border-sidebar-border">
                            <table className="w-full text-sm">
                                <thead className="border-b border-sidebar-border/70 text-left text-xs uppercase text-muted-foreground dark:border-sidebar-border">
                                    <tr>
                                        <th className="p-3">Equipo</th>
                                        <th className="p-3">Tipo</th>
                                        <th className="p-3">Flota</th>
                                        <th className="p-3 text-right">Último km</th>
                                        <th className="p-3 text-right">Último horómetro</th>
                                        <th className="p-3">Último previaje</th>
                                        <th className="p-3">Días</th>
                                        <th className="p-3">Estado</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                    {equipos.map((e) => (
                                        <tr key={e.id} className="hover:bg-accent">
                                            <td className="p-3 font-medium">
                                                {e.ultimo_previaje_id ? (
                                                    <Link href={route('previajes.show', e.ultimo_previaje_id)} className="hover:underline">
                                                        {e.codigo}
                                                    </Link>
                                                ) : (
                                                    e.codigo
                                                )}
                                            </td>
                                            <td className="p-3">{e.tipo}</td>
                                            <td className="p-3">{e.flota}</td>
                                            <td className="p-3 text-right tabular-nums">{e.ultimo_kilometraje?.toLocaleString('es') ?? '—'}</td>
                                            <td className="p-3 text-right tabular-nums">{e.ultimo_horometro ?? '—'}</td>
                                            <td className="p-3 whitespace-nowrap">
                                                {e.ultimo_previaje_en ? new Date(e.ultimo_previaje_en).toLocaleDateString('es') : 'Nunca'}
                                            </td>
                                            <td className="p-3 whitespace-nowrap">{textoDias(e)}</td>
                                            <td className="p-3"><Indicadores equipo={e} /></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}

function textoDias(equipo: EquipoFila): string {
    if (equipo.dias_sin_previaje === null) return 'Sin previajes';
    if (equipo.dias_sin_previaje === 0) return 'Hoy';
    return `hace ${equipo.dias_sin_previaje} ${equipo.dias_sin_previaje === 1 ? 'día' : 'días'}`;
}

function Campo({ etiqueta, valor }: { etiqueta: string; valor: string }) {
    return (
        <div>
            <dt className="text-muted-foreground">{etiqueta}</dt>
            <dd className="font-medium">{valor}</dd>
        </div>
    );
}

function Indicadores({ equipo, className = '' }: { equipo: EquipoFila; className?: string }) {
    return (
        <span className={`flex flex-wrap items-center gap-1 ${className}`}>
            {equipo.atrasado && (
                <span
                    title={`Umbral del tipo ${equipo.tipo}: ${equipo.umbral_dias} días`}
                    className="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800 dark:bg-red-950 dark:text-red-200"
                >
                    <Clock className="size-3" />
                    Sin previaje reciente
                </span>
            )}
            {equipo.tiene_hallazgos && (
                <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900 dark:bg-amber-950 dark:text-amber-200">
                    <AlertTriangle className="size-3" />
                    Hallazgos
                </span>
            )}
        </span>
    );
}
