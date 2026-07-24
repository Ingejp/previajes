import { EstadoActivo } from '@/components/estado-activo';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, EquipoAdmin } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface Props {
    equipos: EquipoAdmin[];
    opciones: {
        tiposEquipo: { id: number; nombre: string }[];
        flotas: { id: number; nombre: string }[];
    };
}

const migas: BreadcrumbItem[] = [
    { title: 'Catálogos', href: route('catalogos.index') },
    { title: 'Equipos', href: route('catalogos.equipos.index') },
];

export default function EquiposAdminIndex({ equipos, opciones }: Props) {
    const [editando, setEditando] = useState<EquipoAdmin | null>(null);
    const [creando, setCreando] = useState(false);

    const eliminar = (equipo: EquipoAdmin) => {
        if (!confirm(`¿Eliminar el equipo "${equipo.codigo}"?`)) return;
        router.delete(route('catalogos.equipos.destroy', equipo.id), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={migas}>
            <Head title="Equipos" />

            <div className="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
                <div className="flex items-center gap-2">
                    <h1 className="flex-1 text-xl font-semibold sm:text-2xl">Equipos</h1>
                    <Button size="sm" onClick={() => setCreando(true)}>
                        <Plus className="size-4" />
                        Nuevo equipo
                    </Button>
                </div>

                {equipos.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-sidebar-border/70 p-10 text-center text-sm text-muted-foreground">
                        No hay equipos registrados todavía.
                    </p>
                ) : (
                    <>
                        <ul className="grid gap-3 lg:hidden">
                            {equipos.map((e) => (
                                <li key={e.id} className="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                                    <div className="flex items-center gap-2">
                                        <span className="flex-1 font-semibold">{e.codigo}</span>
                                        <EstadoActivo activo={e.activo} />
                                    </div>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {e.tipo_equipo.nombre} · {e.flota.nombre}
                                        {(e.marca || e.modelo) && ` · ${[e.marca, e.modelo].filter(Boolean).join(' ')}`}
                                    </p>
                                    <div className="mt-3 flex gap-2">
                                        <Button size="sm" variant="outline" className="flex-1" onClick={() => setEditando(e)}>
                                            <Pencil className="size-3.5" />
                                            Editar
                                        </Button>
                                        <Button size="sm" variant="outline" onClick={() => eliminar(e)}>
                                            <Trash2 className="size-3.5" />
                                        </Button>
                                    </div>
                                </li>
                            ))}
                        </ul>

                        <div className="hidden overflow-x-auto rounded-xl border border-sidebar-border/70 bg-card lg:block dark:border-sidebar-border">
                            <table className="w-full text-sm">
                                <thead className="border-b border-sidebar-border/70 text-left text-xs uppercase text-muted-foreground dark:border-sidebar-border">
                                    <tr>
                                        <th className="p-3">Código</th>
                                        <th className="p-3">Tipo</th>
                                        <th className="p-3">Flota</th>
                                        <th className="p-3">Marca / modelo</th>
                                        <th className="p-3">Estado</th>
                                        <th className="p-3" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                    {equipos.map((e) => (
                                        <tr key={e.id}>
                                            <td className="p-3 font-medium">{e.codigo}</td>
                                            <td className="p-3">{e.tipo_equipo.nombre}</td>
                                            <td className="p-3">{e.flota.nombre}</td>
                                            <td className="p-3">{[e.marca, e.modelo].filter(Boolean).join(' ') || '—'}</td>
                                            <td className="p-3">
                                                <EstadoActivo activo={e.activo} />
                                            </td>
                                            <td className="p-3">
                                                <div className="flex justify-end gap-1">
                                                    <Button size="sm" variant="ghost" onClick={() => setEditando(e)}>
                                                        <Pencil className="size-4" />
                                                    </Button>
                                                    <Button size="sm" variant="ghost" onClick={() => eliminar(e)}>
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
            </div>

            <FormularioEquipo abierto={creando} onCerrar={() => setCreando(false)} opciones={opciones} />
            <FormularioEquipo abierto={editando !== null} onCerrar={() => setEditando(null)} equipo={editando} opciones={opciones} />
        </AppLayout>
    );
}

function FormularioEquipo({
    abierto,
    onCerrar,
    equipo,
    opciones,
}: {
    abierto: boolean;
    onCerrar: () => void;
    equipo?: EquipoAdmin | null;
    opciones: { tiposEquipo: { id: number; nombre: string }[]; flotas: { id: number; nombre: string }[] };
}) {
    const editando = equipo != null;

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        codigo: equipo?.codigo ?? '',
        tipo_equipo_id: equipo?.tipo_equipo_id?.toString() ?? '',
        flota_id: equipo?.flota_id?.toString() ?? '',
        marca: equipo?.marca ?? '',
        modelo: equipo?.modelo ?? '',
        activo: equipo?.activo ?? true,
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
            put(route('catalogos.equipos.update', equipo.id), opts);
        } else {
            post(route('catalogos.equipos.store'), opts);
        }
    };

    return (
        <Dialog open={abierto} onOpenChange={(v) => !v && cerrar()}>
            <DialogContent>
                <form onSubmit={enviar}>
                    <DialogHeader>
                        <DialogTitle>{editando ? 'Editar equipo' : 'Nuevo equipo'}</DialogTitle>
                    </DialogHeader>

                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <div className="sm:col-span-2">
                            <Label htmlFor="codigo">Código / placa</Label>
                            <Input
                                id="codigo"
                                value={data.codigo}
                                onChange={(e) => setData('codigo', e.target.value)}
                                autoFocus
                                className="mt-1"
                            />
                            <InputError message={errors.codigo} className="mt-1" />
                        </div>

                        <div>
                            <Label htmlFor="tipo_equipo_id">Tipo de equipo</Label>
                            <select
                                id="tipo_equipo_id"
                                value={data.tipo_equipo_id}
                                onChange={(e) => setData('tipo_equipo_id', e.target.value)}
                                className="mt-1 h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">Seleccione…</option>
                                {opciones.tiposEquipo.map((t) => (
                                    <option key={t.id} value={t.id}>
                                        {t.nombre}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.tipo_equipo_id} className="mt-1" />
                        </div>

                        <div>
                            <Label htmlFor="flota_id">Flota</Label>
                            <select
                                id="flota_id"
                                value={data.flota_id}
                                onChange={(e) => setData('flota_id', e.target.value)}
                                className="mt-1 h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">Seleccione…</option>
                                {opciones.flotas.map((f) => (
                                    <option key={f.id} value={f.id}>
                                        {f.nombre}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.flota_id} className="mt-1" />
                        </div>

                        <div>
                            <Label htmlFor="marca">Marca</Label>
                            <Input id="marca" value={data.marca} onChange={(e) => setData('marca', e.target.value)} className="mt-1" />
                        </div>

                        <div>
                            <Label htmlFor="modelo">Modelo</Label>
                            <Input id="modelo" value={data.modelo} onChange={(e) => setData('modelo', e.target.value)} className="mt-1" />
                        </div>

                        <label className="flex items-center gap-2 text-sm sm:col-span-2">
                            <Checkbox checked={data.activo} onCheckedChange={(v) => setData('activo', v === true)} />
                            Activo
                        </label>
                    </div>

                    <DialogFooter className="mt-6">
                        <Button type="button" variant="ghost" onClick={cerrar}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {editando ? 'Guardar cambios' : 'Crear equipo'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
