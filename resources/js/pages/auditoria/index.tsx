import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Paginado } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Download, ShieldAlert } from 'lucide-react';
import { useState } from 'react';

interface Cambio {
    id: number;
    fecha: string;
    usuario: string;
    rol: string | null;
    entidad: string;
    evento: string;
    registro_id: number | null;
    anterior: Record<string, unknown>;
    nuevo: Record<string, unknown>;
}

interface Acceso {
    id: number;
    fecha: string | null;
    usuario: string | null;
    email_intentado: string | null;
    evento: string;
    exitoso: boolean;
    ip: string | null;
}

interface Props {
    cambios: Paginado<Cambio>;
    accesos: Paginado<Acceso>;
    filtros: Record<string, string | undefined>;
    opciones: {
        usuarios: { id: number; name: string }[];
        eventos: { valor: string; etiqueta: string }[];
        entidades: { valor: string; etiqueta: string }[];
    };
}

const migas: BreadcrumbItem[] = [{ title: 'Auditoría', href: '/auditoria' }];

export default function AuditoriaIndex({ cambios, accesos, filtros, opciones }: Props) {
    const [vista, setVista] = useState<'cambios' | 'accesos'>(
        filtros.vista === 'accesos' ? 'accesos' : 'cambios',
    );

    /** Descarta las claves vacías para no ensuciar la URL con filtros sin usar. */
    const soloConValor = (origen: Record<string, string | undefined>): Record<string, string> =>
        Object.fromEntries(
            Object.entries(origen).filter((par): par is [string, string] => Boolean(par[1])),
        );

    const filtrar = (cambio: Record<string, string | undefined>) => {
        router.get(route('auditoria.index'), soloConValor({ ...filtros, ...cambio, vista }), {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const urlExportar = () => {
        const params = new URLSearchParams(soloConValor({ ...filtros, tipo: vista }));
        return `${route('auditoria.exportar')}?${params.toString()}`;
    };

    return (
        <AppLayout breadcrumbs={migas}>
            <Head title="Auditoría" />

            <div className="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
                <div className="flex flex-wrap items-center gap-2">
                    <div className="flex-1">
                        <h1 className="text-xl font-semibold sm:text-2xl">Auditoría</h1>
                        <p className="text-sm text-muted-foreground">
                            Punto único de monitoreo: qué cambió, quién entró y qué se hizo.
                        </p>
                    </div>

                    <Button asChild variant="outline" size="sm">
                        <a href={urlExportar()}>
                            <Download className="size-4" />
                            Exportar CSV
                        </a>
                    </Button>
                </div>

                <div className="flex gap-1 rounded-lg bg-muted p-1">
                    {(['cambios', 'accesos'] as const).map((v) => (
                        <button
                            key={v}
                            onClick={() => setVista(v)}
                            className={`flex-1 rounded-md px-3 py-2 text-sm font-medium capitalize transition ${
                                vista === v ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            {v === 'cambios' ? 'Cambios de datos' : 'Accesos'}
                        </button>
                    ))}
                </div>

                <div className="grid gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4 sm:grid-cols-2 lg:grid-cols-4 dark:border-sidebar-border">
                    <Campo etiqueta="Usuario">
                        <select
                            value={filtros.usuario_id ?? ''}
                            onChange={(e) => filtrar({ usuario_id: e.target.value })}
                            className="h-11 w-full rounded-md border border-input bg-background px-3 text-base sm:h-10 sm:text-sm"
                        >
                            <option value="">Todos</option>
                            {opciones.usuarios.map((u) => (
                                <option key={u.id} value={u.id}>{u.name}</option>
                            ))}
                        </select>
                    </Campo>

                    {vista === 'cambios' ? (
                        <>
                            <Campo etiqueta="Entidad">
                                <select
                                    value={filtros.log ?? ''}
                                    onChange={(e) => filtrar({ log: e.target.value })}
                                    className="h-11 w-full rounded-md border border-input bg-background px-3 text-base sm:h-10 sm:text-sm"
                                >
                                    <option value="">Todas</option>
                                    {opciones.entidades.map((e) => (
                                        <option key={e.valor} value={e.valor}>{e.etiqueta}</option>
                                    ))}
                                </select>
                            </Campo>

                            <Campo etiqueta="Tipo de evento">
                                <select
                                    value={filtros.tipo_evento ?? ''}
                                    onChange={(e) => filtrar({ tipo_evento: e.target.value })}
                                    className="h-11 w-full rounded-md border border-input bg-background px-3 text-base sm:h-10 sm:text-sm"
                                >
                                    <option value="">Todos</option>
                                    {opciones.eventos.map((e) => (
                                        <option key={e.valor} value={e.valor}>{e.etiqueta}</option>
                                    ))}
                                </select>
                            </Campo>
                        </>
                    ) : (
                        <Campo etiqueta="Resultado">
                            <select
                                value={filtros.solo_fallidos ?? ''}
                                onChange={(e) => filtrar({ solo_fallidos: e.target.value })}
                                className="h-11 w-full rounded-md border border-input bg-background px-3 text-base sm:h-10 sm:text-sm"
                            >
                                <option value="">Todos</option>
                                <option value="1">Sólo intentos fallidos</option>
                            </select>
                        </Campo>
                    )}

                    <div className="grid grid-cols-2 gap-2">
                        <Campo etiqueta="Desde">
                            <input
                                type="date"
                                value={filtros.desde ?? ''}
                                onChange={(e) => filtrar({ desde: e.target.value })}
                                className="h-11 w-full rounded-md border border-input bg-background px-2 text-sm sm:h-10"
                            />
                        </Campo>
                        <Campo etiqueta="Hasta">
                            <input
                                type="date"
                                value={filtros.hasta ?? ''}
                                onChange={(e) => filtrar({ hasta: e.target.value })}
                                className="h-11 w-full rounded-md border border-input bg-background px-2 text-sm sm:h-10"
                            />
                        </Campo>
                    </div>
                </div>

                {vista === 'cambios' ? (
                    <Tabla vacio="No hay cambios registrados con estos filtros.">
                        {cambios.data.map((c) => (
                            <li key={c.id} className="p-4">
                                <div className="flex flex-wrap items-baseline gap-2 text-sm">
                                    <span className="font-medium">{c.usuario}</span>
                                    {c.rol && <span className="text-xs text-muted-foreground">{c.rol}</span>}
                                    <span className="text-muted-foreground">
                                        {etiquetaEvento(c.evento)} · {c.entidad}
                                        {c.registro_id !== null && ` #${c.registro_id}`}
                                    </span>
                                    <span className="ml-auto text-xs text-muted-foreground">
                                        {new Date(c.fecha).toLocaleString('es')}
                                    </span>
                                </div>

                                {Object.keys(c.nuevo).length > 0 && c.evento === 'updated' && (
                                    <ul className="mt-1 space-y-0.5 text-xs text-muted-foreground">
                                        {Object.entries(c.nuevo).map(([campo, valor]) => (
                                            <li key={campo}>
                                                <span className="font-medium">{campo}:</span>{' '}
                                                <span className="line-through">{String(c.anterior[campo] ?? '—')}</span> →{' '}
                                                {String(valor ?? '—')}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </li>
                        ))}
                    </Tabla>
                ) : (
                    <Tabla vacio="No hay accesos registrados con estos filtros.">
                        {accesos.data.map((a) => (
                            <li key={a.id} className="flex flex-wrap items-baseline gap-2 p-4 text-sm">
                                {!a.exitoso && <ShieldAlert className="size-4 shrink-0 text-red-600 dark:text-red-400" />}
                                <span className="font-medium">{a.usuario ?? a.email_intentado ?? 'Desconocido'}</span>
                                <span
                                    className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                                        a.exitoso
                                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
                                            : 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200'
                                    }`}
                                >
                                    {a.evento === 'login' ? 'Ingreso' : a.evento === 'logout' ? 'Salida' : 'Intento fallido'}
                                </span>
                                {a.ip && <span className="text-xs text-muted-foreground">{a.ip}</span>}
                                <span className="ml-auto text-xs text-muted-foreground">
                                    {a.fecha && new Date(a.fecha).toLocaleString('es')}
                                </span>
                            </li>
                        ))}
                    </Tabla>
                )}

                <Paginacion paginado={vista === 'cambios' ? cambios : accesos} />
            </div>
        </AppLayout>
    );
}

function etiquetaEvento(evento: string): string {
    return { created: 'creó', updated: 'modificó', deleted: 'eliminó' }[evento] ?? evento;
}

function Campo({ etiqueta, children }: { etiqueta: string; children: React.ReactNode }) {
    return (
        <label className="flex flex-col gap-1">
            <span className="text-xs font-medium text-muted-foreground">{etiqueta}</span>
            {children}
        </label>
    );
}

function Tabla({ children, vacio }: { children: React.ReactNode[]; vacio: string }) {
    if (children.length === 0) {
        return (
            <p className="rounded-xl border border-dashed border-sidebar-border/70 p-10 text-center text-sm text-muted-foreground">
                {vacio}
            </p>
        );
    }

    return (
        <ul className="divide-y divide-sidebar-border/70 rounded-xl border border-sidebar-border/70 bg-card dark:divide-sidebar-border dark:border-sidebar-border">
            {children}
        </ul>
    );
}

function Paginacion({ paginado }: { paginado: Paginado<unknown> }) {
    if (paginado.last_page <= 1) return null;

    return (
        <nav className="flex flex-wrap justify-center gap-1">
            {paginado.links.map((link, i) => (
                <Button
                    key={i}
                    size="sm"
                    variant={link.active ? 'default' : 'outline'}
                    disabled={!link.url}
                    onClick={() => link.url && router.visit(link.url, { preserveScroll: true })}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ))}
        </nav>
    );
}
