import { EstadoActivo } from '@/components/estado-activo';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, UsuarioAdmin } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { useState } from 'react';

interface Props {
    usuarios: UsuarioAdmin[];
    opciones: {
        flotas: { id: number; nombre: string }[];
        roles: { valor: string; etiqueta: string }[];
    };
}

const migas: BreadcrumbItem[] = [
    { title: 'Catálogos', href: route('catalogos.index') },
    { title: 'Usuarios', href: route('catalogos.usuarios.index') },
];

/**
 * La flota principal puede repetirse dentro de `flotas` (el supervisor de
 * ejemplo la trae en ambos campos, para poder probar el filtrado
 * multi-flota); se deduplica aquí para no mostrarla dos veces en pantalla.
 */
function flotasTexto(u: UsuarioAdmin): string {
    const todas = [u.flota, ...u.flotas].filter((f): f is { id: number; nombre: string } => f !== null);
    const unicas = [...new Map(todas.map((f) => [f.id, f])).values()];

    return unicas.map((f) => f.nombre).join(', ') || '—';
}

export default function UsuariosIndex({ usuarios, opciones }: Props) {
    const [editando, setEditando] = useState<UsuarioAdmin | null>(null);
    const [creando, setCreando] = useState(false);

    return (
        <AppLayout breadcrumbs={migas}>
            <Head title="Usuarios" />

            <div className="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
                <div className="flex items-center gap-2">
                    <h1 className="flex-1 text-xl font-semibold sm:text-2xl">Usuarios</h1>
                    <Button size="sm" onClick={() => setCreando(true)}>
                        <Plus className="size-4" />
                        Nuevo usuario
                    </Button>
                </div>

                {/* RF-18: no hay borrado de usuarios, sólo desactivación — preserva su historial de previajes. */}
                <p className="text-sm text-muted-foreground">Para retirar acceso, desactive al usuario en vez de eliminarlo.</p>

                <ul className="grid gap-3 lg:hidden">
                    {usuarios.map((u) => (
                        <li key={u.id} className="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                            <div className="flex items-center gap-2">
                                <span className="flex-1 font-semibold">{u.name}</span>
                                <EstadoActivo activo={u.activo} />
                            </div>
                            <p className="mt-1 text-sm text-muted-foreground">{u.email}</p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {opciones.roles.find((r) => r.valor === u.rol)?.etiqueta ?? u.rol} · {flotasTexto(u)}
                            </p>
                            <Button size="sm" variant="outline" className="mt-3 w-full" onClick={() => setEditando(u)}>
                                <Pencil className="size-3.5" />
                                Editar
                            </Button>
                        </li>
                    ))}
                </ul>

                <div className="hidden overflow-x-auto rounded-xl border border-sidebar-border/70 bg-card lg:block dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="border-b border-sidebar-border/70 text-left text-xs uppercase text-muted-foreground dark:border-sidebar-border">
                            <tr>
                                <th className="p-3">Nombre</th>
                                <th className="p-3">Correo</th>
                                <th className="p-3">Rol</th>
                                <th className="p-3">Flota(s)</th>
                                <th className="p-3">Estado</th>
                                <th className="p-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                            {usuarios.map((u) => (
                                <tr key={u.id}>
                                    <td className="p-3 font-medium">{u.name}</td>
                                    <td className="p-3">{u.email}</td>
                                    <td className="p-3">{opciones.roles.find((r) => r.valor === u.rol)?.etiqueta ?? u.rol}</td>
                                    <td className="p-3">{flotasTexto(u)}</td>
                                    <td className="p-3">
                                        <EstadoActivo activo={u.activo} />
                                    </td>
                                    <td className="p-3">
                                        <div className="flex justify-end">
                                            <Button size="sm" variant="ghost" onClick={() => setEditando(u)}>
                                                <Pencil className="size-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <FormularioUsuario abierto={creando} onCerrar={() => setCreando(false)} opciones={opciones} />
            <FormularioUsuario abierto={editando !== null} onCerrar={() => setEditando(null)} usuario={editando} opciones={opciones} />
        </AppLayout>
    );
}

function FormularioUsuario({
    abierto,
    onCerrar,
    usuario,
    opciones,
}: {
    abierto: boolean;
    onCerrar: () => void;
    usuario?: UsuarioAdmin | null;
    opciones: { flotas: { id: number; nombre: string }[]; roles: { valor: string; etiqueta: string }[] };
}) {
    const editando = usuario != null;

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        name: usuario?.name ?? '',
        email: usuario?.email ?? '',
        password: '',
        rol: usuario?.rol ?? (opciones.roles[0]?.valor as UsuarioAdmin['rol']) ?? 'mecanico',
        flota_id: usuario?.flota_id?.toString() ?? '',
        flotas: usuario?.flotas.map((f) => f.id) ?? ([] as number[]),
        activo: usuario?.activo ?? true,
    });

    const esSuperAdmin = data.rol === 'super_administrador';

    const cerrar = () => {
        onCerrar();
        reset();
        clearErrors();
    };

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: cerrar };

        if (editando) {
            put(route('catalogos.usuarios.update', usuario.id), opts);
        } else {
            post(route('catalogos.usuarios.store'), opts);
        }
    };

    const alternarFlota = (id: number) => {
        setData('flotas', data.flotas.includes(id) ? data.flotas.filter((f) => f !== id) : [...data.flotas, id]);
    };

    return (
        <Dialog open={abierto} onOpenChange={(v) => !v && cerrar()}>
            <DialogContent>
                <form onSubmit={enviar}>
                    <DialogHeader>
                        <DialogTitle>{editando ? 'Editar usuario' : 'Nuevo usuario'}</DialogTitle>
                    </DialogHeader>

                    <div className="mt-4 grid gap-4">
                        <div>
                            <Label htmlFor="name">Nombre</Label>
                            <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} autoFocus className="mt-1" />
                            <InputError message={errors.name} className="mt-1" />
                        </div>

                        <div>
                            <Label htmlFor="email">Correo</Label>
                            <Input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className="mt-1"
                            />
                            <InputError message={errors.email} className="mt-1" />
                        </div>

                        <div>
                            <Label htmlFor="password">{editando ? 'Nueva contraseña (opcional)' : 'Contraseña'}</Label>
                            <Input
                                id="password"
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                autoComplete="new-password"
                                className="mt-1"
                                placeholder={editando ? 'Dejar en blanco para no cambiarla' : undefined}
                            />
                            <InputError message={errors.password} className="mt-1" />
                        </div>

                        <div>
                            <Label htmlFor="rol">Rol</Label>
                            <select
                                id="rol"
                                value={data.rol}
                                onChange={(e) => setData('rol', e.target.value as UsuarioAdmin['rol'])}
                                className="mt-1 h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                {opciones.roles.map((r) => (
                                    <option key={r.valor} value={r.valor}>
                                        {r.etiqueta}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.rol} className="mt-1" />
                        </div>

                        {/* RF-01: el super administrador opera a nivel global, sin flota asignada. */}
                        {!esSuperAdmin && (
                            <div>
                                <Label htmlFor="flota_id">Flota principal</Label>
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
                        )}

                        {/* RF-20: un supervisor puede cubrir varias flotas además de la principal. */}
                        {data.rol === 'supervisor' && (
                            <div>
                                <Label>Flotas adicionales</Label>
                                <div className="mt-1 grid gap-2 rounded-md border border-input p-3">
                                    {opciones.flotas.map((f) => (
                                        <label key={f.id} className="flex items-center gap-2 text-sm">
                                            <Checkbox checked={data.flotas.includes(f.id)} onCheckedChange={() => alternarFlota(f.id)} />
                                            {f.nombre}
                                        </label>
                                    ))}
                                </div>
                            </div>
                        )}

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
                            {editando ? 'Guardar cambios' : 'Crear usuario'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
