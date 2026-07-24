import { EstadoActivo } from '@/components/estado-activo';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, FlotaAdmin } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface Props {
    flotas: FlotaAdmin[];
}

const migas: BreadcrumbItem[] = [
    { title: 'Catálogos', href: route('catalogos.index') },
    { title: 'Flotas', href: route('catalogos.flotas.index') },
];

export default function FlotasIndex({ flotas }: Props) {
    const [editando, setEditando] = useState<FlotaAdmin | null>(null);
    const [creando, setCreando] = useState(false);

    const eliminar = (flota: FlotaAdmin) => {
        if (!confirm(`¿Eliminar la flota "${flota.nombre}"? Esta acción no se puede deshacer.`)) return;
        router.delete(route('catalogos.flotas.destroy', flota.id), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={migas}>
            <Head title="Flotas" />

            <div className="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
                <div className="flex items-center gap-2">
                    <h1 className="flex-1 text-xl font-semibold sm:text-2xl">Flotas</h1>
                    <Button size="sm" onClick={() => setCreando(true)}>
                        <Plus className="size-4" />
                        Nueva flota
                    </Button>
                </div>

                {flotas.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-sidebar-border/70 p-10 text-center text-sm text-muted-foreground">
                        No hay flotas registradas todavía.
                    </p>
                ) : (
                    <>
                        <ul className="grid gap-3 lg:hidden">
                            {flotas.map((f) => (
                                <li key={f.id} className="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                                    <div className="flex items-center gap-2">
                                        <span className="flex-1 font-semibold">{f.nombre}</span>
                                        <EstadoActivo activo={f.activo} />
                                    </div>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {f.pais} · {f.equipos_count} equipos · {f.usuarios_count} usuarios
                                    </p>
                                    <div className="mt-3 flex gap-2">
                                        <Button size="sm" variant="outline" className="flex-1" onClick={() => setEditando(f)}>
                                            <Pencil className="size-3.5" />
                                            Editar
                                        </Button>
                                        <Button size="sm" variant="outline" onClick={() => eliminar(f)}>
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
                                        <th className="p-3">Nombre</th>
                                        <th className="p-3">País</th>
                                        <th className="p-3 text-right">Equipos</th>
                                        <th className="p-3 text-right">Usuarios</th>
                                        <th className="p-3">Estado</th>
                                        <th className="p-3" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                    {flotas.map((f) => (
                                        <tr key={f.id}>
                                            <td className="p-3 font-medium">{f.nombre}</td>
                                            <td className="p-3">{f.pais}</td>
                                            <td className="p-3 text-right tabular-nums">{f.equipos_count}</td>
                                            <td className="p-3 text-right tabular-nums">{f.usuarios_count}</td>
                                            <td className="p-3">
                                                <EstadoActivo activo={f.activo} />
                                            </td>
                                            <td className="p-3">
                                                <div className="flex justify-end gap-1">
                                                    <Button size="sm" variant="ghost" onClick={() => setEditando(f)}>
                                                        <Pencil className="size-4" />
                                                    </Button>
                                                    <Button size="sm" variant="ghost" onClick={() => eliminar(f)}>
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

            <FormularioFlota abierto={creando} onCerrar={() => setCreando(false)} />
            <FormularioFlota abierto={editando !== null} onCerrar={() => setEditando(null)} flota={editando} />
        </AppLayout>
    );
}

function FormularioFlota({ abierto, onCerrar, flota }: { abierto: boolean; onCerrar: () => void; flota?: FlotaAdmin | null }) {
    const editando = flota != null;

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        nombre: flota?.nombre ?? '',
        pais: flota?.pais ?? '',
        activo: flota?.activo ?? true,
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
            put(route('catalogos.flotas.update', flota.id), opciones);
        } else {
            post(route('catalogos.flotas.store'), opciones);
        }
    };

    return (
        <Dialog open={abierto} onOpenChange={(v) => !v && cerrar()}>
            <DialogContent>
                <form onSubmit={enviar}>
                    <DialogHeader>
                        <DialogTitle>{editando ? 'Editar flota' : 'Nueva flota'}</DialogTitle>
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
                            <Label htmlFor="pais">País</Label>
                            <Input id="pais" value={data.pais} onChange={(e) => setData('pais', e.target.value)} className="mt-1" />
                            <InputError message={errors.pais} className="mt-1" />
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
                            {editando ? 'Guardar cambios' : 'Crear flota'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
