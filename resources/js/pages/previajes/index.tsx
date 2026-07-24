import { Button } from '@/components/ui/button';
import { EstatusPreviaje } from '@/components/estatus-previaje';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Paginado } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Camera, Plus, SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';

interface PreviajeFila {
    id: number;
    kilometraje: number | null;
    horometro: string | null;
    estatus: 'sin_hallazgos' | 'con_hallazgos' | 'anulado';
    created_at: string;
    fotos_count: number;
    equipo: { id: number; codigo: string; tipo_equipo: { nombre: string } };
    mecanico: { name: string };
    flota: { nombre: string };
}

interface Props {
    previajes: Paginado<PreviajeFila>;
    filtros: Record<string, string | number | undefined>;
    opciones: {
        flotas: { id: number; nombre: string }[];
        tiposEquipo: { id: number; nombre: string }[];
        equipos: { id: number; codigo: string }[];
        mecanicos: { id: number; name: string }[];
        estatus: { valor: string; etiqueta: string }[];
    };
}

const migas: BreadcrumbItem[] = [{ title: 'Previajes', href: '/previajes' }];

export default function PreviajesIndex({ previajes, filtros, opciones }: Props) {
    // En móvil los filtros arrancan plegados para no empujar la lista fuera de
    // la pantalla; en escritorio hay espacio de sobra (RNF-00).
    const [filtrosVisibles, setFiltrosVisibles] = useState(false);
    const [borrador, setBorrador] = useState(filtros);

    const aplicar = () => {
        const limpios = Object.fromEntries(Object.entries(borrador).filter(([, v]) => v !== '' && v != null));
        router.get(route('previajes.index'), limpios, { preserveScroll: true, preserveState: true });
    };

    const limpiar = () => {
        setBorrador({});
        router.get(route('previajes.index'));
    };

    return (
        <AppLayout breadcrumbs={migas}>
            <Head title="Previajes" />

            <div className="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
                <div className="flex items-center gap-2">
                    <h1 className="flex-1 text-xl font-semibold sm:text-2xl">Previajes</h1>

                    <Button variant="outline" size="sm" onClick={() => setFiltrosVisibles((v) => !v)} className="lg:hidden">
                        <SlidersHorizontal className="size-4" />
                        Filtros
                    </Button>

                    <Button asChild size="sm">
                        <Link href={route('previajes.create')}>
                            <Plus className="size-4" />
                            <span className="hidden sm:inline">Nuevo previaje</span>
                            <span className="sm:hidden">Nuevo</span>
                        </Link>
                    </Button>
                </div>

                {/* RF-15: filtros por flota, tipo, equipo, mecánico, fechas y estatus. */}
                <div
                    className={`${filtrosVisibles ? 'grid' : 'hidden'} gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4 sm:grid-cols-2 lg:grid lg:grid-cols-4 dark:border-sidebar-border`}
                >
                    <Filtro etiqueta="Flota">
                        <Select valor={borrador.flota_id} onChange={(v) => setBorrador({ ...borrador, flota_id: v })}>
                            {opciones.flotas.map((f) => (
                                <option key={f.id} value={f.id}>{f.nombre}</option>
                            ))}
                        </Select>
                    </Filtro>

                    <Filtro etiqueta="Tipo de equipo">
                        <Select valor={borrador.tipo_equipo_id} onChange={(v) => setBorrador({ ...borrador, tipo_equipo_id: v })}>
                            {opciones.tiposEquipo.map((t) => (
                                <option key={t.id} value={t.id}>{t.nombre}</option>
                            ))}
                        </Select>
                    </Filtro>

                    <Filtro etiqueta="Equipo">
                        <Select valor={borrador.equipo_id} onChange={(v) => setBorrador({ ...borrador, equipo_id: v })}>
                            {opciones.equipos.map((e) => (
                                <option key={e.id} value={e.id}>{e.codigo}</option>
                            ))}
                        </Select>
                    </Filtro>

                    <Filtro etiqueta="Estatus">
                        <Select valor={borrador.estatus} onChange={(v) => setBorrador({ ...borrador, estatus: v })}>
                            {opciones.estatus.map((e) => (
                                <option key={e.valor} value={e.valor}>{e.etiqueta}</option>
                            ))}
                        </Select>
                    </Filtro>

                    {opciones.mecanicos.length > 0 && (
                        <Filtro etiqueta="Mecánico">
                            <Select valor={borrador.mecanico_id} onChange={(v) => setBorrador({ ...borrador, mecanico_id: v })}>
                                {opciones.mecanicos.map((m) => (
                                    <option key={m.id} value={m.id}>{m.name}</option>
                                ))}
                            </Select>
                        </Filtro>
                    )}

                    <Filtro etiqueta="Desde">
                        <input
                            type="date"
                            value={(borrador.desde as string) ?? ''}
                            onChange={(e) => setBorrador({ ...borrador, desde: e.target.value })}
                            className="h-11 w-full rounded-md border border-input bg-background px-3 text-base sm:h-10 sm:text-sm"
                        />
                    </Filtro>

                    <Filtro etiqueta="Hasta">
                        <input
                            type="date"
                            value={(borrador.hasta as string) ?? ''}
                            onChange={(e) => setBorrador({ ...borrador, hasta: e.target.value })}
                            className="h-11 w-full rounded-md border border-input bg-background px-3 text-base sm:h-10 sm:text-sm"
                        />
                    </Filtro>

                    <div className="flex items-end gap-2">
                        <Button onClick={aplicar} className="flex-1">Aplicar</Button>
                        <Button variant="ghost" onClick={limpiar}>Limpiar</Button>
                    </div>
                </div>

                {previajes.data.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-sidebar-border/70 p-10 text-center text-sm text-muted-foreground">
                        No hay previajes que coincidan con los filtros.
                    </p>
                ) : (
                    <>
                        {/* Tarjetas en móvil, tabla en escritorio: la misma información, apropiada a cada ancho. */}
                        <ul className="grid gap-3 lg:hidden">
                            {previajes.data.map((p) => (
                                <li key={p.id}>
                                    <Link
                                        href={route('previajes.show', p.id)}
                                        className="flex flex-col gap-2 rounded-xl border border-sidebar-border/70 bg-card p-4 active:bg-accent dark:border-sidebar-border"
                                    >
                                        <div className="flex items-center gap-2">
                                            <span className="font-semibold">{p.equipo.codigo}</span>
                                            <span className="text-xs text-muted-foreground">{p.equipo.tipo_equipo.nombre}</span>
                                            <EstatusPreviaje estatus={p.estatus} className="ml-auto" />
                                        </div>
                                        <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                            <span>{new Date(p.created_at).toLocaleString('es')}</span>
                                            <span>{p.mecanico.name}</span>
                                            <span>{p.kilometraje?.toLocaleString('es') ?? '—'} km</span>
                                            <span>{p.horometro ?? '—'} h</span>
                                            {p.fotos_count > 0 && (
                                                <span className="flex items-center gap-1">
                                                    <Camera className="size-3" />
                                                    {p.fotos_count}
                                                </span>
                                            )}
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ul>

                        <div className="hidden overflow-x-auto rounded-xl border border-sidebar-border/70 bg-card lg:block dark:border-sidebar-border">
                            <table className="w-full text-sm">
                                <thead className="border-b border-sidebar-border/70 text-left text-xs uppercase text-muted-foreground dark:border-sidebar-border">
                                    <tr>
                                        <th className="p-3">Fecha</th>
                                        <th className="p-3">Equipo</th>
                                        <th className="p-3">Tipo</th>
                                        <th className="p-3">Flota</th>
                                        <th className="p-3">Mecánico</th>
                                        <th className="p-3 text-right">Km</th>
                                        <th className="p-3 text-right">Horómetro</th>
                                        <th className="p-3">Estatus</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                    {previajes.data.map((p) => (
                                        <tr
                                            key={p.id}
                                            onClick={() => router.visit(route('previajes.show', p.id))}
                                            className="cursor-pointer hover:bg-accent"
                                        >
                                            <td className="p-3 whitespace-nowrap">{new Date(p.created_at).toLocaleString('es')}</td>
                                            <td className="p-3 font-medium">{p.equipo.codigo}</td>
                                            <td className="p-3">{p.equipo.tipo_equipo.nombre}</td>
                                            <td className="p-3">{p.flota.nombre}</td>
                                            <td className="p-3">{p.mecanico.name}</td>
                                            <td className="p-3 text-right tabular-nums">{p.kilometraje?.toLocaleString('es') ?? '—'}</td>
                                            <td className="p-3 text-right tabular-nums">{p.horometro ?? '—'}</td>
                                            <td className="p-3"><EstatusPreviaje estatus={p.estatus} /></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {previajes.last_page > 1 && (
                            <nav className="flex flex-wrap justify-center gap-1">
                                {previajes.links.map((link, i) => (
                                    <Button
                                        key={i}
                                        size="sm"
                                        variant={link.active ? 'default' : 'outline'}
                                        disabled={!link.url}
                                        onClick={() => link.url && router.visit(link.url)}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </nav>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    );
}

function Filtro({ etiqueta, children }: { etiqueta: string; children: React.ReactNode }) {
    return (
        <label className="flex flex-col gap-1 text-sm">
            <span className="text-xs font-medium text-muted-foreground">{etiqueta}</span>
            {children}
        </label>
    );
}

function Select({
    valor,
    onChange,
    children,
}: {
    valor: string | number | undefined;
    onChange: (valor: string) => void;
    children: React.ReactNode;
}) {
    return (
        <select
            value={valor ?? ''}
            onChange={(e) => onChange(e.target.value)}
            className="h-11 w-full rounded-md border border-input bg-background px-3 text-base sm:h-10 sm:text-sm"
        >
            <option value="">Todos</option>
            {children}
        </select>
    );
}
