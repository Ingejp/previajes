import { EstadoActivo } from '@/components/estado-activo';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, TipoEquipoAdmin } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface Props {
    tiposEquipo: TipoEquipoAdmin[];
    secciones: { id: number; nombre: string }[];
}

const migas: BreadcrumbItem[] = [
    { title: 'Catálogos', href: route('catalogos.index') },
    { title: 'Tipos de equipo', href: route('catalogos.tipos-equipo.index') },
];

export default function TiposEquipoIndex({ tiposEquipo, secciones }: Props) {
    const [editando, setEditando] = useState<TipoEquipoAdmin | null>(null);
    const [creando, setCreando] = useState(false);

    const eliminar = (tipo: TipoEquipoAdmin) => {
        if (!confirm(`¿Eliminar el tipo de equipo "${tipo.nombre}"?`)) return;
        router.delete(route('catalogos.tipos-equipo.destroy', tipo.id), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={migas}>
            <Head title="Tipos de equipo" />

            <div className="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
                <div className="flex items-center gap-2">
                    <h1 className="flex-1 text-xl font-semibold sm:text-2xl">Tipos de equipo</h1>
                    <Button size="sm" onClick={() => setCreando(true)}>
                        <Plus className="size-4" />
                        Nuevo tipo
                    </Button>
                </div>

                {/* RF-16.1 / RN-12: el umbral de días sin previaje vive aquí, por tipo de equipo. */}
                <p className="text-sm text-muted-foreground">
                    El umbral de días sin previaje se define por tipo de equipo, no de forma global.
                </p>

                {tiposEquipo.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-sidebar-border/70 p-10 text-center text-sm text-muted-foreground">
                        No hay tipos de equipo registrados todavía.
                    </p>
                ) : (
                    <ul className="grid gap-3">
                        {tiposEquipo.map((t) => (
                            <li key={t.id} className="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-semibold">{t.nombre}</span>
                                    <EstadoActivo activo={t.activo} />
                                    <span className="text-sm text-muted-foreground">
                                        umbral {t.dias_alerta_sin_previaje} d · {t.equipos_count} equipos
                                    </span>
                                    <div className="ml-auto flex gap-1">
                                        <Button size="sm" variant="ghost" onClick={() => setEditando(t)}>
                                            <Pencil className="size-4" />
                                        </Button>
                                        <Button size="sm" variant="ghost" onClick={() => eliminar(t)}>
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                </div>
                                <p className="mt-2 flex flex-wrap gap-1 text-xs text-muted-foreground">
                                    Secciones:{' '}
                                    {t.secciones.length === 0 ? (
                                        <span className="italic">ninguna</span>
                                    ) : (
                                        t.secciones.map((s) => (
                                            <span key={s.id} className="rounded bg-muted px-1.5 py-0.5">
                                                {s.nombre}
                                            </span>
                                        ))
                                    )}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <FormularioTipoEquipo abierto={creando} onCerrar={() => setCreando(false)} secciones={secciones} />
            <FormularioTipoEquipo abierto={editando !== null} onCerrar={() => setEditando(null)} tipo={editando} secciones={secciones} />
        </AppLayout>
    );
}

function FormularioTipoEquipo({
    abierto,
    onCerrar,
    tipo,
    secciones,
}: {
    abierto: boolean;
    onCerrar: () => void;
    tipo?: TipoEquipoAdmin | null;
    secciones: { id: number; nombre: string }[];
}) {
    const editando = tipo != null;

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        nombre: tipo?.nombre ?? '',
        dias_alerta_sin_previaje: tipo?.dias_alerta_sin_previaje?.toString() ?? '2',
        activo: tipo?.activo ?? true,
        secciones: tipo?.secciones.map((s) => s.id) ?? ([] as number[]),
    });

    const cerrar = () => {
        onCerrar();
        reset();
        clearErrors();
    };

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();
        const opciones = { preserveScroll: true, onSuccess: cerrar };

        if (editando) {
            put(route('catalogos.tipos-equipo.update', tipo.id), opciones);
        } else {
            post(route('catalogos.tipos-equipo.store'), opciones);
        }
    };

    const alternarSeccion = (id: number) => {
        setData('secciones', data.secciones.includes(id) ? data.secciones.filter((s) => s !== id) : [...data.secciones, id]);
    };

    return (
        <Dialog open={abierto} onOpenChange={(v) => !v && cerrar()}>
            <DialogContent>
                <form onSubmit={enviar}>
                    <DialogHeader>
                        <DialogTitle>{editando ? 'Editar tipo de equipo' : 'Nuevo tipo de equipo'}</DialogTitle>
                    </DialogHeader>

                    <div className="mt-4 grid gap-4">
                        <div>
                            <Label htmlFor="nombre">Nombre</Label>
                            <Input
                                id="nombre"
                                value={data.nombre}
                                onChange={(e) => setData('nombre', e.target.value)}
                                autoFocus
                                className="mt-1"
                            />
                            <InputError message={errors.nombre} className="mt-1" />
                        </div>

                        <div>
                            <Label htmlFor="dias_alerta">Días sin previaje para alertar</Label>
                            <Input
                                id="dias_alerta"
                                type="number"
                                min="1"
                                value={data.dias_alerta_sin_previaje}
                                onChange={(e) => setData('dias_alerta_sin_previaje', e.target.value)}
                                className="mt-1 max-w-32"
                            />
                            <InputError message={errors.dias_alerta_sin_previaje} className="mt-1" />
                        </div>

                        <div>
                            <Label>Secciones del checklist que le aplican (RN-07)</Label>
                            <div className="mt-1 grid gap-2 rounded-md border border-input p-3">
                                {secciones.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">No hay secciones creadas todavía.</p>
                                ) : (
                                    secciones.map((s) => (
                                        <label key={s.id} className="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={data.secciones.includes(s.id)}
                                                onCheckedChange={() => alternarSeccion(s.id)}
                                            />
                                            {s.nombre}
                                        </label>
                                    ))
                                )}
                            </div>
                            <InputError message={errors.secciones} className="mt-1" />
                        </div>

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
                            {editando ? 'Guardar cambios' : 'Crear tipo de equipo'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
