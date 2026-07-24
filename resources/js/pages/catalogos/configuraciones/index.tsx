import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, ConfiguracionAdmin } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface Props {
    configuraciones: ConfiguracionAdmin[];
}

const migas: BreadcrumbItem[] = [
    { title: 'Catálogos', href: route('catalogos.index') },
    { title: 'Configuraciones', href: route('catalogos.configuraciones.index') },
];

export default function ConfiguracionesIndex({ configuraciones }: Props) {
    const [editando, setEditando] = useState<ConfiguracionAdmin | null>(null);
    const [creando, setCreando] = useState(false);

    const eliminar = (config: ConfiguracionAdmin) => {
        if (!confirm(`¿Eliminar "${config.clave}"? El código volverá a usar su valor por defecto.`)) return;
        router.delete(route('catalogos.configuraciones.destroy', config.id), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={migas}>
            <Head title="Configuraciones" />

            <div className="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
                <div className="flex items-center gap-2">
                    <h1 className="flex-1 text-xl font-semibold sm:text-2xl">Configuraciones</h1>
                    <Button size="sm" onClick={() => setCreando(true)}>
                        <Plus className="size-4" />
                        Nueva
                    </Button>
                </div>

                {/* RF-16.1: el umbral de días sin previaje NO vive aquí, es un campo de cada tipo de equipo. */}
                <p className="text-sm text-muted-foreground">
                    Parámetros globales del sistema (ej. tamaño máximo de foto). El umbral de días sin previaje se
                    configura por tipo de equipo, no aquí.
                </p>

                {configuraciones.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-sidebar-border/70 p-10 text-center text-sm text-muted-foreground">
                        No hay configuraciones registradas todavía.
                    </p>
                ) : (
                    <ul className="divide-y divide-sidebar-border/70 rounded-xl border border-sidebar-border/70 bg-card dark:divide-sidebar-border dark:border-sidebar-border">
                        {configuraciones.map((c) => (
                            <li key={c.id} className="flex flex-wrap items-center gap-3 p-4">
                                <div className="min-w-0 flex-1">
                                    <p className="font-mono text-sm font-medium">{c.clave}</p>
                                    {c.descripcion && <p className="text-xs text-muted-foreground">{c.descripcion}</p>}
                                </div>
                                <span className="rounded bg-muted px-2 py-1 font-mono text-sm">{c.valor}</span>
                                <Button size="sm" variant="ghost" onClick={() => setEditando(c)}>
                                    <Pencil className="size-4" />
                                </Button>
                                <Button size="sm" variant="ghost" onClick={() => eliminar(c)}>
                                    <Trash2 className="size-4" />
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <FormularioConfiguracion abierto={creando} onCerrar={() => setCreando(false)} />
            <FormularioConfiguracion abierto={editando !== null} onCerrar={() => setEditando(null)} configuracion={editando} />
        </AppLayout>
    );
}

function FormularioConfiguracion({
    abierto,
    onCerrar,
    configuracion,
}: {
    abierto: boolean;
    onCerrar: () => void;
    configuracion?: ConfiguracionAdmin | null;
}) {
    const editando = configuracion != null;

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        clave: configuracion?.clave ?? '',
        valor: configuracion?.valor ?? '',
        descripcion: configuracion?.descripcion ?? '',
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
            put(route('catalogos.configuraciones.update', configuracion.id), opts);
        } else {
            post(route('catalogos.configuraciones.store'), opts);
        }
    };

    return (
        <Dialog open={abierto} onOpenChange={(v) => !v && cerrar()}>
            <DialogContent>
                <form onSubmit={enviar}>
                    <DialogHeader>
                        <DialogTitle>{editando ? 'Editar configuración' : 'Nueva configuración'}</DialogTitle>
                    </DialogHeader>

                    <div className="mt-4 grid gap-4">
                        <div>
                            <Label htmlFor="clave">Clave</Label>
                            <Input
                                id="clave"
                                value={data.clave}
                                onChange={(e) => setData('clave', e.target.value)}
                                disabled={editando}
                                autoFocus={!editando}
                                className="mt-1 font-mono"
                                placeholder="ej. tamano_maximo_foto_kb"
                            />
                            <InputError message={errors.clave} className="mt-1" />
                            {editando && <p className="mt-1 text-xs text-muted-foreground">La clave no se puede cambiar una vez creada.</p>}
                        </div>

                        <div>
                            <Label htmlFor="valor">Valor</Label>
                            <Input
                                id="valor"
                                value={data.valor}
                                onChange={(e) => setData('valor', e.target.value)}
                                autoFocus={editando}
                                className="mt-1"
                            />
                            <InputError message={errors.valor} className="mt-1" />
                        </div>

                        <div>
                            <Label htmlFor="descripcion">Descripción</Label>
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                className="mt-1"
                            />
                            <InputError message={errors.descripcion} className="mt-1" />
                        </div>
                    </div>

                    <DialogFooter className="mt-6">
                        <Button type="button" variant="ghost" onClick={cerrar}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {editando ? 'Guardar cambios' : 'Crear'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
