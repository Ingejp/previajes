import { Button } from '@/components/ui/button';
import { EstatusPreviaje } from '@/components/estatus-previaje';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangle, Ban, History, Pencil } from 'lucide-react';
import { useState } from 'react';

interface RespuestaDetalle {
    item: string;
    es_fluido: boolean;
    respuesta: string;
    es_hallazgo: boolean;
    cantidad_agregada: string | null;
    observaciones: string | null;
    fotos: { id: number; url: string }[];
}

interface Props {
    previaje: {
        id: number;
        equipo: { id: number; codigo: string; marca: string | null; modelo: string | null };
        tipo_equipo: string;
        flota: string;
        mecanico: string;
        kilometraje: number | null;
        horometro: string | null;
        estatus: string;
        estatus_etiqueta: string;
        creado_en: string;
        actualizado_en: string;
        fue_editado: boolean;
        motivo_anulacion: string | null;
        anulado_por: string | null;
        anulado_en: string | null;
        secciones: { id: number; nombre: string; observaciones: string | null; respuestas: RespuestaDetalle[] }[];
        fotos_generales: { id: number; url: string }[];
    };
    auditoria: {
        id: number;
        evento: string;
        usuario: string;
        fecha: string;
        anterior: Record<string, unknown>;
        nuevo: Record<string, unknown>;
    }[];
    permisos: { editar: boolean; anular: boolean };
}

export default function PreviajeShow({ previaje, auditoria, permisos }: Props) {
    const [anulando, setAnulando] = useState(false);
    const { data, setData, post, processing, errors } = useForm({ motivo_anulacion: '' });

    const migas: BreadcrumbItem[] = [
        { title: 'Previajes', href: route('previajes.index') },
        { title: `Previaje #${previaje.id}`, href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={migas}>
            <Head title={`Previaje #${previaje.id}`} />

            <div className="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
                <header className="flex flex-wrap items-start gap-3">
                    <div className="flex-1">
                        <h1 className="text-xl font-semibold sm:text-2xl">
                            {previaje.equipo.codigo}
                            <span className="ml-2 text-sm font-normal text-muted-foreground">{previaje.tipo_equipo}</span>
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {new Date(previaje.creado_en).toLocaleString('es')} · {previaje.mecanico} · {previaje.flota}
                        </p>
                    </div>

                    <EstatusPreviaje estatus={previaje.estatus} />

                    {permisos.editar && (
                        <Button asChild variant="outline" size="sm">
                            <Link href={route('previajes.edit', previaje.id)}>
                                <Pencil className="size-4" />
                                Editar
                            </Link>
                        </Button>
                    )}
                    {permisos.anular && (
                        <Button variant="outline" size="sm" onClick={() => setAnulando((v) => !v)}>
                            <Ban className="size-4" />
                            Anular
                        </Button>
                    )}
                </header>

                {/* RF-12: anular sustituye al borrado, preservando el historial. */}
                {anulando && (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            post(route('previajes.anular', previaje.id));
                        }}
                        className="rounded-xl border border-destructive/50 bg-destructive/5 p-4"
                    >
                        <label className="text-sm font-medium">Motivo de la anulación</label>
                        <textarea
                            rows={2}
                            value={data.motivo_anulacion}
                            onChange={(e) => setData('motivo_anulacion', e.target.value)}
                            className="mt-1 w-full rounded-md border border-input bg-background p-3 text-base sm:text-sm"
                            placeholder="Explique por qué este previaje deja de ser válido (mínimo 10 caracteres)"
                        />
                        {errors.motivo_anulacion && <p className="mt-1 text-sm text-destructive">{errors.motivo_anulacion}</p>}
                        <div className="mt-3 flex gap-2">
                            <Button type="submit" variant="destructive" size="sm" disabled={processing}>
                                Confirmar anulación
                            </Button>
                            <Button type="button" variant="ghost" size="sm" onClick={() => setAnulando(false)}>
                                Cancelar
                            </Button>
                        </div>
                    </form>
                )}

                {previaje.motivo_anulacion && (
                    <div className="rounded-xl border border-sidebar-border/70 bg-muted p-4 text-sm dark:border-sidebar-border">
                        <p className="font-medium">Previaje anulado</p>
                        <p className="text-muted-foreground">
                            {previaje.anulado_por} · {previaje.anulado_en && new Date(previaje.anulado_en).toLocaleString('es')}
                        </p>
                        <p className="mt-1">{previaje.motivo_anulacion}</p>
                    </div>
                )}

                <dl className="grid grid-cols-2 gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4 sm:grid-cols-4 dark:border-sidebar-border">
                    <Dato etiqueta="Kilometraje" valor={previaje.kilometraje ? `${previaje.kilometraje.toLocaleString('es')} km` : '—'} />
                    <Dato etiqueta="Horómetro" valor={previaje.horometro ? `${previaje.horometro} h` : '—'} />
                    <Dato etiqueta="Marca / modelo" valor={[previaje.equipo.marca, previaje.equipo.modelo].filter(Boolean).join(' ') || '—'} />
                    <Dato
                        etiqueta="Última edición"
                        valor={previaje.fue_editado ? new Date(previaje.actualizado_en).toLocaleString('es') : 'Sin ediciones'}
                    />
                </dl>

                {previaje.secciones.map((seccion) => (
                    <section key={seccion.id} className="rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                        <h2 className="border-b border-sidebar-border/70 px-4 py-3 font-semibold sm:px-6 dark:border-sidebar-border">
                            {seccion.nombre}
                        </h2>

                        <ul className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                            {seccion.respuestas.map((r, i) => (
                                <li key={i} className="p-4 sm:px-6">
                                    <div className="flex flex-wrap items-baseline gap-2">
                                        <span className="flex-1 text-sm font-medium">{r.item}</span>
                                        <span
                                            className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${
                                                r.es_hallazgo
                                                    ? 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200'
                                                    : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
                                            }`}
                                        >
                                            {r.es_hallazgo && <AlertTriangle className="size-3" />}
                                            {r.respuesta}
                                        </span>
                                    </div>

                                    {r.cantidad_agregada && (
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Se agregaron {r.cantidad_agregada} galones
                                        </p>
                                    )}
                                    {r.observaciones && <p className="mt-1 text-sm text-muted-foreground">{r.observaciones}</p>}

                                    {r.fotos.length > 0 && (
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            {r.fotos.map((foto) => (
                                                <a key={foto.id} href={foto.url} target="_blank" rel="noreferrer">
                                                    <img
                                                        src={foto.url}
                                                        alt={`Evidencia de ${r.item}`}
                                                        className="size-20 rounded-lg border border-input object-cover"
                                                    />
                                                </a>
                                            ))}
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ul>

                        {seccion.observaciones && (
                            <p className="border-t border-sidebar-border/70 p-4 text-sm sm:px-6 dark:border-sidebar-border">
                                <span className="font-medium">Observaciones: </span>
                                <span className="text-muted-foreground">{seccion.observaciones}</span>
                            </p>
                        )}
                    </section>
                ))}

                {previaje.fotos_generales.length > 0 && (
                    <section className="rounded-xl border border-sidebar-border/70 bg-card p-4 sm:p-6 dark:border-sidebar-border">
                        <h2 className="font-semibold">Fotos del previaje</h2>
                        <div className="mt-3 flex flex-wrap gap-2">
                            {previaje.fotos_generales.map((foto) => (
                                <a key={foto.id} href={foto.url} target="_blank" rel="noreferrer">
                                    <img src={foto.url} alt="Foto del previaje" className="size-20 rounded-lg border border-input object-cover" />
                                </a>
                            ))}
                        </div>
                    </section>
                )}

                {/* RF-15: historial de ediciones del previaje. */}
                {auditoria.length > 0 && (
                    <section className="rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                        <h2 className="flex items-center gap-2 border-b border-sidebar-border/70 px-4 py-3 font-semibold sm:px-6 dark:border-sidebar-border">
                            <History className="size-4" />
                            Historial de cambios
                        </h2>
                        <ul className="divide-y divide-sidebar-border/70 text-sm dark:divide-sidebar-border">
                            {auditoria.map((entrada) => (
                                <li key={entrada.id} className="p-4 sm:px-6">
                                    <p>
                                        <span className="font-medium">{entrada.usuario}</span>{' '}
                                        <span className="text-muted-foreground">
                                            {entrada.evento === 'created' ? 'registró el previaje' : 'modificó el previaje'} ·{' '}
                                            {new Date(entrada.fecha).toLocaleString('es')}
                                        </span>
                                    </p>
                                    {Object.keys(entrada.nuevo).length > 0 && entrada.evento !== 'created' && (
                                        <ul className="mt-1 space-y-0.5 text-xs text-muted-foreground">
                                            {Object.entries(entrada.nuevo).map(([campo, valor]) => (
                                                <li key={campo}>
                                                    <span className="font-medium">{campo}:</span>{' '}
                                                    <span className="line-through">{String(entrada.anterior[campo] ?? '—')}</span> →{' '}
                                                    {String(valor ?? '—')}
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}

function Dato({ etiqueta, valor }: { etiqueta: string; valor: string }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{etiqueta}</dt>
            <dd className="text-sm font-medium">{valor}</dd>
        </div>
    );
}
