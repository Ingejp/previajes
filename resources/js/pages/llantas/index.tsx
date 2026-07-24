import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Paginado } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Info } from 'lucide-react';

interface RegistroFila {
    id: number;
    cantidad: number;
    posicion: string | null;
    observaciones: string | null;
    created_at: string;
    equipo: { codigo: string; flota: { nombre: string } };
    usuario: { name: string };
}

interface Props {
    registros: Paginado<RegistroFila>;
    equipos: { id: number; codigo: string }[];
}

const migas: BreadcrumbItem[] = [{ title: 'Cambio de llantas', href: '/llantas' }];

export default function LlantasIndex({ registros, equipos }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        equipo_id: '',
        cantidad: '',
        posicion: '',
        observaciones: '',
    });

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('llantas.store'), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <AppLayout breadcrumbs={migas}>
            <Head title="Cambio de llantas" />

            <div className="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
                <div>
                    <h1 className="text-xl font-semibold sm:text-2xl">Cambio de llantas</h1>
                    {/* RF-17.1: queda explícito que esto es interino. */}
                    <p className="mt-1 flex items-start gap-2 rounded-lg bg-muted p-3 text-sm text-muted-foreground">
                        <Info className="mt-0.5 size-4 shrink-0" />
                        Registro temporal, aparte del previaje, para no perder el dato mientras no exista el módulo de
                        inventario y mantenimiento. Aquí se anota la acción realizada, no el hallazgo detectado.
                    </p>
                </div>

                <form
                    onSubmit={enviar}
                    className="grid gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4 sm:grid-cols-2 lg:grid-cols-4 dark:border-sidebar-border"
                >
                    <div>
                        <Label htmlFor="equipo_id">Equipo</Label>
                        <select
                            id="equipo_id"
                            value={data.equipo_id}
                            onChange={(e) => setData('equipo_id', e.target.value)}
                            className="mt-1 h-11 w-full rounded-md border border-input bg-background px-3 text-base sm:h-10 sm:text-sm"
                        >
                            <option value="">Seleccione…</option>
                            {equipos.map((e) => (
                                <option key={e.id} value={e.id}>{e.codigo}</option>
                            ))}
                        </select>
                        <InputError message={errors.equipo_id} className="mt-1" />
                    </div>

                    <div>
                        <Label htmlFor="cantidad">Llantas cambiadas</Label>
                        <Input
                            id="cantidad"
                            type="number"
                            inputMode="numeric"
                            min="1"
                            value={data.cantidad}
                            onChange={(e) => setData('cantidad', e.target.value)}
                            className="mt-1 h-11 text-base sm:h-10 sm:text-sm"
                        />
                        <InputError message={errors.cantidad} className="mt-1" />
                    </div>

                    <div>
                        <Label htmlFor="posicion">Posición (opcional)</Label>
                        <Input
                            id="posicion"
                            value={data.posicion}
                            onChange={(e) => setData('posicion', e.target.value)}
                            placeholder="Ej. eje delantero izquierdo"
                            className="mt-1 h-11 text-base sm:h-10 sm:text-sm"
                        />
                        <InputError message={errors.posicion} className="mt-1" />
                    </div>

                    <div>
                        <Label htmlFor="observaciones">Observación (opcional)</Label>
                        <Input
                            id="observaciones"
                            value={data.observaciones}
                            onChange={(e) => setData('observaciones', e.target.value)}
                            className="mt-1 h-11 text-base sm:h-10 sm:text-sm"
                        />
                        <InputError message={errors.observaciones} className="mt-1" />
                    </div>

                    <div className="sm:col-span-2 lg:col-span-4">
                        <Button type="submit" disabled={processing} className="w-full sm:w-auto">
                            Registrar cambio
                        </Button>
                        {/* La fecha la pone el sistema, igual que en el previaje. */}
                        <p className="mt-1 text-xs text-muted-foreground">
                            La fecha y hora se registran automáticamente.
                        </p>
                    </div>
                </form>

                {registros.data.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-sidebar-border/70 p-10 text-center text-sm text-muted-foreground">
                        Todavía no hay cambios de llanta registrados.
                    </p>
                ) : (
                    <ul className="divide-y divide-sidebar-border/70 rounded-xl border border-sidebar-border/70 bg-card dark:divide-sidebar-border dark:border-sidebar-border">
                        {registros.data.map((r) => (
                            <li key={r.id} className="flex flex-wrap items-baseline gap-2 p-4 text-sm">
                                <span className="font-medium">{r.equipo.codigo}</span>
                                <span className="text-xs text-muted-foreground">{r.equipo.flota.nombre}</span>
                                <span className="font-medium tabular-nums">
                                    {r.cantidad} {r.cantidad === 1 ? 'llanta' : 'llantas'}
                                </span>
                                {r.posicion && <span className="text-muted-foreground">· {r.posicion}</span>}
                                {r.observaciones && <span className="text-muted-foreground">· {r.observaciones}</span>}
                                <span className="ml-auto text-xs text-muted-foreground">
                                    {r.usuario.name} · {new Date(r.created_at).toLocaleString('es')}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AppLayout>
    );
}
