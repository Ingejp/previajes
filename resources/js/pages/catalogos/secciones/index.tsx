import { EstadoActivo } from '@/components/estado-activo';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, ChecklistItemAdmin, ChecklistOpcionAdmin, ChecklistSeccionAdmin } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { AlertTriangle, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface Props {
    secciones: ChecklistSeccionAdmin[];
    tiposEquipo: { id: number; nombre: string }[];
}

const migas: BreadcrumbItem[] = [
    { title: 'Catálogos', href: route('catalogos.index') },
    { title: 'Secciones de checklist', href: route('catalogos.secciones.index') },
];

export default function SeccionesIndex({ secciones, tiposEquipo }: Props) {
    const [editando, setEditando] = useState<ChecklistSeccionAdmin | null>(null);
    const [creando, setCreando] = useState(false);

    const eliminarSeccion = (seccion: ChecklistSeccionAdmin) => {
        if (!confirm(`¿Eliminar la sección "${seccion.nombre}" junto con sus ítems y opciones?`)) return;
        router.delete(route('catalogos.secciones.destroy', seccion.id), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={migas}>
            <Head title="Secciones de checklist" />

            <div className="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
                <div className="flex items-center gap-2">
                    <h1 className="flex-1 text-xl font-semibold sm:text-2xl">Secciones de checklist</h1>
                    <Button size="sm" onClick={() => setCreando(true)}>
                        <Plus className="size-4" />
                        Nueva sección
                    </Button>
                </div>

                {/* RF-05: precargadas por seeder (MOTOR, CHASIS, CABINA Y ACCESORIOS); estas pantallas administran el catálogo completo. */}
                {secciones.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-sidebar-border/70 p-10 text-center text-sm text-muted-foreground">
                        No hay secciones registradas todavía.
                    </p>
                ) : (
                    <div className="grid gap-4">
                        {secciones.map((s) => (
                            <SeccionCard
                                key={s.id}
                                seccion={s}
                                onEditar={() => setEditando(s)}
                                onEliminar={() => eliminarSeccion(s)}
                            />
                        ))}
                    </div>
                )}
            </div>

            <FormularioSeccion abierto={creando} onCerrar={() => setCreando(false)} tiposEquipo={tiposEquipo} />
            <FormularioSeccion abierto={editando !== null} onCerrar={() => setEditando(null)} seccion={editando} tiposEquipo={tiposEquipo} />
        </AppLayout>
    );
}

function SeccionCard({
    seccion,
    onEditar,
    onEliminar,
}: {
    seccion: ChecklistSeccionAdmin;
    onEditar: () => void;
    onEliminar: () => void;
}) {
    const [itemEnEdicion, setItemEnEdicion] = useState<ChecklistItemAdmin | null>(null);
    const [creandoItem, setCreandoItem] = useState(false);
    const [opcionEnEdicion, setOpcionEnEdicion] = useState<ChecklistOpcionAdmin | null>(null);
    const [creandoOpcion, setCreandoOpcion] = useState(false);

    const eliminarItem = (item: ChecklistItemAdmin) => {
        if (!confirm(`¿Eliminar el ítem "${item.descripcion}"?`)) return;
        router.delete(route('catalogos.secciones.items.destroy', [seccion.id, item.id]), { preserveScroll: true });
    };

    const eliminarOpcion = (opcion: ChecklistOpcionAdmin) => {
        if (!confirm(`¿Eliminar la opción "${opcion.etiqueta}"?`)) return;
        router.delete(route('catalogos.secciones.opciones.destroy', [seccion.id, opcion.id]), { preserveScroll: true });
    };

    return (
        <section className="rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
            <div className="flex flex-wrap items-center gap-2 border-b border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <span className="font-semibold">{seccion.nombre}</span>
                <EstadoActivo activo={seccion.activo} />
                <span className="text-xs text-muted-foreground">orden {seccion.orden}</span>
                <div className="ml-auto flex gap-1">
                    <Button size="sm" variant="ghost" onClick={onEditar}>
                        <Pencil className="size-4" />
                    </Button>
                    <Button size="sm" variant="ghost" onClick={onEliminar}>
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            </div>

            <div className="flex flex-wrap gap-1 px-4 pt-3 text-xs text-muted-foreground">
                Tipos de equipo:{' '}
                {seccion.tipos_equipo.length === 0 ? (
                    <span className="italic">ninguno</span>
                ) : (
                    seccion.tipos_equipo.map((t) => (
                        <span key={t.id} className="rounded bg-muted px-1.5 py-0.5">
                            {t.nombre}
                        </span>
                    ))
                )}
            </div>

            <div className="grid gap-4 p-4 sm:grid-cols-2">
                <div>
                    <div className="mb-2 flex items-center gap-2">
                        <h3 className="text-sm font-semibold">Ítems (RF-06)</h3>
                        <Button size="sm" variant="outline" className="ml-auto h-7 px-2" onClick={() => setCreandoItem(true)}>
                            <Plus className="size-3.5" />
                        </Button>
                    </div>
                    <ul className="divide-y divide-sidebar-border/70 rounded-md border border-sidebar-border/70 dark:divide-sidebar-border dark:border-sidebar-border">
                        {seccion.items.length === 0 && <li className="p-3 text-sm text-muted-foreground">Sin ítems.</li>}
                        {seccion.items.map((item) => (
                            <li key={item.id} className="flex items-center gap-2 p-3 text-sm">
                                <span className="flex-1">
                                    {item.descripcion}
                                    {item.es_fluido && (
                                        <span className="ml-1.5 rounded bg-muted px-1 py-0.5 text-xs text-muted-foreground">fluido</span>
                                    )}
                                    {!item.activo && <span className="ml-1.5 text-xs text-muted-foreground">(inactivo)</span>}
                                </span>
                                <button type="button" onClick={() => setItemEnEdicion(item)} className="text-muted-foreground hover:text-foreground">
                                    <Pencil className="size-3.5" />
                                </button>
                                <button type="button" onClick={() => eliminarItem(item)} className="text-muted-foreground hover:text-destructive">
                                    <Trash2 className="size-3.5" />
                                </button>
                            </li>
                        ))}
                    </ul>
                </div>

                <div>
                    <div className="mb-2 flex items-center gap-2">
                        <h3 className="text-sm font-semibold">Opciones de respuesta (RF-07)</h3>
                        <Button size="sm" variant="outline" className="ml-auto h-7 px-2" onClick={() => setCreandoOpcion(true)}>
                            <Plus className="size-3.5" />
                        </Button>
                    </div>
                    <ul className="divide-y divide-sidebar-border/70 rounded-md border border-sidebar-border/70 dark:divide-sidebar-border dark:border-sidebar-border">
                        {seccion.opciones.length === 0 && <li className="p-3 text-sm text-muted-foreground">Sin opciones.</li>}
                        {seccion.opciones.map((opcion) => (
                            <li key={opcion.id} className="flex items-center gap-2 p-3 text-sm">
                                <span className="flex-1">{opcion.etiqueta}</span>
                                {!opcion.es_optima && (
                                    <span className="flex items-center gap-1 text-xs text-amber-700 dark:text-amber-400">
                                        <AlertTriangle className="size-3" />
                                        hallazgo
                                    </span>
                                )}
                                <button
                                    type="button"
                                    onClick={() => setOpcionEnEdicion(opcion)}
                                    className="text-muted-foreground hover:text-foreground"
                                >
                                    <Pencil className="size-3.5" />
                                </button>
                                <button type="button" onClick={() => eliminarOpcion(opcion)} className="text-muted-foreground hover:text-destructive">
                                    <Trash2 className="size-3.5" />
                                </button>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>

            <FormularioItem seccionId={seccion.id} abierto={creandoItem} onCerrar={() => setCreandoItem(false)} />
            <FormularioItem seccionId={seccion.id} abierto={itemEnEdicion !== null} onCerrar={() => setItemEnEdicion(null)} item={itemEnEdicion} />

            <FormularioOpcion seccionId={seccion.id} abierto={creandoOpcion} onCerrar={() => setCreandoOpcion(false)} />
            <FormularioOpcion
                seccionId={seccion.id}
                abierto={opcionEnEdicion !== null}
                onCerrar={() => setOpcionEnEdicion(null)}
                opcion={opcionEnEdicion}
            />
        </section>
    );
}

function FormularioSeccion({
    abierto,
    onCerrar,
    seccion,
    tiposEquipo,
}: {
    abierto: boolean;
    onCerrar: () => void;
    seccion?: ChecklistSeccionAdmin | null;
    tiposEquipo: { id: number; nombre: string }[];
}) {
    const editando = seccion != null;

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        nombre: seccion?.nombre ?? '',
        orden: seccion?.orden?.toString() ?? '0',
        activo: seccion?.activo ?? true,
        tipos_equipo: seccion?.tipos_equipo.map((t) => t.id) ?? ([] as number[]),
    });

    const cerrar = () => {
        onCerrar();
        reset();
        clearErrors();
    };

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: cerrar };

        if (editando) {
            put(route('catalogos.secciones.update', seccion.id), opts);
        } else {
            post(route('catalogos.secciones.store'), opts);
        }
    };

    const alternar = (id: number) => {
        setData('tipos_equipo', data.tipos_equipo.includes(id) ? data.tipos_equipo.filter((t) => t !== id) : [...data.tipos_equipo, id]);
    };

    return (
        <Dialog open={abierto} onOpenChange={(v) => !v && cerrar()}>
            <DialogContent>
                <form onSubmit={enviar}>
                    <DialogHeader>
                        <DialogTitle>{editando ? 'Editar sección' : 'Nueva sección'}</DialogTitle>
                    </DialogHeader>

                    <div className="mt-4 grid gap-4">
                        <div>
                            <Label htmlFor="nombre">Nombre</Label>
                            <Input
                                id="nombre"
                                value={data.nombre}
                                onChange={(e) => setData('nombre', e.target.value.toUpperCase())}
                                autoFocus
                                className="mt-1"
                            />
                            <InputError message={errors.nombre} className="mt-1" />
                        </div>

                        <div>
                            <Label htmlFor="orden">Orden</Label>
                            <Input
                                id="orden"
                                type="number"
                                min="0"
                                value={data.orden}
                                onChange={(e) => setData('orden', e.target.value)}
                                className="mt-1 max-w-32"
                            />
                            <InputError message={errors.orden} className="mt-1" />
                        </div>

                        <div>
                            <Label>Tipos de equipo que la usan (RN-07)</Label>
                            <div className="mt-1 grid gap-2 rounded-md border border-input p-3">
                                {tiposEquipo.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">No hay tipos de equipo creados todavía.</p>
                                ) : (
                                    tiposEquipo.map((t) => (
                                        <label key={t.id} className="flex items-center gap-2 text-sm">
                                            <Checkbox checked={data.tipos_equipo.includes(t.id)} onCheckedChange={() => alternar(t.id)} />
                                            {t.nombre}
                                        </label>
                                    ))
                                )}
                            </div>
                        </div>

                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox checked={data.activo} onCheckedChange={(v) => setData('activo', v === true)} />
                            Activa
                        </label>
                    </div>

                    <DialogFooter className="mt-6">
                        <Button type="button" variant="ghost" onClick={cerrar}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {editando ? 'Guardar cambios' : 'Crear sección'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function FormularioItem({
    seccionId,
    abierto,
    onCerrar,
    item,
}: {
    seccionId: number;
    abierto: boolean;
    onCerrar: () => void;
    item?: ChecklistItemAdmin | null;
}) {
    const editando = item != null;

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        descripcion: item?.descripcion ?? '',
        es_fluido: item?.es_fluido ?? false,
        orden: item?.orden?.toString() ?? '0',
        activo: item?.activo ?? true,
    });

    const cerrar = () => {
        onCerrar();
        reset();
        clearErrors();
    };

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: cerrar };

        if (editando) {
            put(route('catalogos.secciones.items.update', [seccionId, item.id]), opts);
        } else {
            post(route('catalogos.secciones.items.store', seccionId), opts);
        }
    };

    return (
        <Dialog open={abierto} onOpenChange={(v) => !v && cerrar()}>
            <DialogContent>
                <form onSubmit={enviar}>
                    <DialogHeader>
                        <DialogTitle>{editando ? 'Editar ítem' : 'Nuevo ítem'}</DialogTitle>
                    </DialogHeader>

                    <div className="mt-4 grid gap-4">
                        <div>
                            <Label htmlFor="descripcion">Descripción</Label>
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                autoFocus
                                className="mt-1"
                            />
                            <InputError message={errors.descripcion} className="mt-1" />
                        </div>

                        <div>
                            <Label htmlFor="orden-item">Orden</Label>
                            <Input
                                id="orden-item"
                                type="number"
                                min="0"
                                value={data.orden}
                                onChange={(e) => setData('orden', e.target.value)}
                                className="mt-1 max-w-32"
                            />
                            <InputError message={errors.orden} className="mt-1" />
                        </div>

                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox checked={data.es_fluido} onCheckedChange={(v) => setData('es_fluido', v === true)} />
                            Es fluido (habilita galones agregados en hallazgo — RF-08)
                        </label>

                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox checked={data.activo} onCheckedChange={(v) => setData('activo', v === true)} />
                            Activo
                        </label>
                    </div>

                    <DialogFooter className="mt-6">
                        <Button type="button" variant="ghost" onClick={cerrar}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {editando ? 'Guardar cambios' : 'Crear ítem'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function FormularioOpcion({
    seccionId,
    abierto,
    onCerrar,
    opcion,
}: {
    seccionId: number;
    abierto: boolean;
    onCerrar: () => void;
    opcion?: ChecklistOpcionAdmin | null;
}) {
    const editando = opcion != null;

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        etiqueta: opcion?.etiqueta ?? '',
        es_optima: opcion?.es_optima ?? true,
        orden: opcion?.orden?.toString() ?? '0',
    });

    const cerrar = () => {
        onCerrar();
        reset();
        clearErrors();
    };

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: cerrar };

        if (editando) {
            put(route('catalogos.secciones.opciones.update', [seccionId, opcion.id]), opts);
        } else {
            post(route('catalogos.secciones.opciones.store', seccionId), opts);
        }
    };

    return (
        <Dialog open={abierto} onOpenChange={(v) => !v && cerrar()}>
            <DialogContent>
                <form onSubmit={enviar}>
                    <DialogHeader>
                        <DialogTitle>{editando ? 'Editar opción' : 'Nueva opción'}</DialogTitle>
                    </DialogHeader>

                    <div className="mt-4 grid gap-4">
                        <div>
                            <Label htmlFor="etiqueta">Etiqueta</Label>
                            <Input
                                id="etiqueta"
                                value={data.etiqueta}
                                onChange={(e) => setData('etiqueta', e.target.value)}
                                autoFocus
                                className="mt-1"
                            />
                            <InputError message={errors.etiqueta} className="mt-1" />
                        </div>

                        <div>
                            <Label htmlFor="orden-opcion">Orden</Label>
                            <Input
                                id="orden-opcion"
                                type="number"
                                min="0"
                                value={data.orden}
                                onChange={(e) => setData('orden', e.target.value)}
                                className="mt-1 max-w-32"
                            />
                            <InputError message={errors.orden} className="mt-1" />
                        </div>

                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox checked={data.es_optima} onCheckedChange={(v) => setData('es_optima', v === true)} />
                            Es óptima (sin ella, la respuesta constituye hallazgo — RN-04)
                        </label>
                    </div>

                    <DialogFooter className="mt-6">
                        <Button type="button" variant="ghost" onClick={cerrar}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {editando ? 'Guardar cambios' : 'Crear opción'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
