import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type {
    BreadcrumbItem,
    ChecklistItem,
    ChecklistSeccion,
    EquipoOpcion,
    FotoExistente,
    UltimasLecturas,
} from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { AlertTriangle, Camera, Check, Loader2, X } from 'lucide-react';
import { useMemo, useState } from 'react';

/**
 * Se declara como `type` y no como `interface` a propósito: TypeScript sólo
 * infiere firma de índice implícita para los alias de tipo, y `useForm` la
 * exige para poder anidar este objeto dentro de los datos del formulario.
 */
type RespuestaForm = {
    checklist_opcion_id: number | '';
    cantidad_agregada: string;
    observaciones: string;
};

interface PreviajeEditable {
    id: number;
    equipo_id: number;
    kilometraje: number | null;
    horometro: string | number | null;
    creado_en: string;
    respuestas: Record<string, Partial<RespuestaForm>>;
    observaciones_seccion: Record<string, string>;
    fotos: FotoExistente[];
}

interface Props {
    previaje: PreviajeEditable | null;
    equipos: EquipoOpcion[];
    equipoSeleccionado: { id: number; codigo: string; marca: string | null; modelo: string | null } | null;
    checklist: ChecklistSeccion[];
    ultimasLecturas: UltimasLecturas | null;
    maxFotosPorItem: number;
}

export default function PreviajeForm({
    previaje,
    equipos,
    equipoSeleccionado,
    checklist,
    ultimasLecturas,
    maxFotosPorItem,
}: Props) {
    const editando = previaje !== null;

    const { data, setData, errors, processing, progress, post, transform } = useForm<{
        equipo_id: number | '';
        kilometraje: string;
        horometro: string;
        observaciones_seccion: Record<string, string>;
        respuestas: Record<string, RespuestaForm>;
        fotos: Record<string, File[]>;
        fotos_eliminadas: number[];
    }>({
        equipo_id: equipoSeleccionado?.id ?? '',
        kilometraje: previaje?.kilometraje?.toString() ?? '',
        horometro: previaje?.horometro?.toString() ?? '',
        observaciones_seccion: previaje?.observaciones_seccion ?? {},
        respuestas: inicializarRespuestas(checklist, previaje),
        fotos: {},
        fotos_eliminadas: [],
    });

    // Índice rápido opción -> es_optima, para saber si un ítem es hallazgo
    // sin recorrer el checklist en cada render.
    const opcionEsOptima = useMemo(() => {
        const mapa = new Map<number, boolean>();
        checklist.forEach((s) => s.opciones.forEach((o) => mapa.set(o.id, o.es_optima)));
        return mapa;
    }, [checklist]);

    const esHallazgo = (itemId: number) => {
        const opcionId = data.respuestas[itemId]?.checklist_opcion_id;
        return opcionId !== '' && opcionId !== undefined && opcionEsOptima.get(Number(opcionId)) === false;
    };

    const fotosExistentesDe = (itemId: number) =>
        (previaje?.fotos ?? []).filter(
            (f) => f.checklist_item_id === itemId && !data.fotos_eliminadas.includes(f.id),
        );

    const totalHallazgos = checklist
        .flatMap((s) => s.items)
        .filter((i) => esHallazgo(i.id)).length;

    const cambiarEquipo = (id: string) => {
        // El checklist depende del tipo de equipo (RN-07), así que se pide al
        // servidor en vez de adivinarlo en el cliente.
        router.get(route('previajes.create'), { equipo_id: id }, { preserveScroll: true });
    };

    const actualizarRespuesta = (itemId: number, campo: keyof RespuestaForm, valor: string | number) => {
        setData('respuestas', {
            ...data.respuestas,
            [itemId]: { ...data.respuestas[itemId], [campo]: valor },
        });
    };

    const agregarFotos = (itemId: number, archivos: FileList | null) => {
        if (!archivos) return;
        const actuales = data.fotos[itemId] ?? [];
        setData('fotos', {
            ...data.fotos,
            [itemId]: [...actuales, ...Array.from(archivos)].slice(0, maxFotosPorItem),
        });
    };

    const quitarFotoNueva = (itemId: number, indice: number) => {
        setData('fotos', {
            ...data.fotos,
            [itemId]: (data.fotos[itemId] ?? []).filter((_, i) => i !== indice),
        });
    };

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();

        // Las fotos obligan a multipart, y multipart no admite PUT: Laravel lo
        // resuelve con el campo `_method`.
        transform((datos) => (editando ? { ...datos, _method: 'put' } : datos));

        post(editando ? route('previajes.update', previaje.id) : route('previajes.store'), {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    const migas: BreadcrumbItem[] = [
        { title: 'Previajes', href: route('previajes.index') },
        { title: editando ? `Editar previaje #${previaje.id}` : 'Nuevo previaje', href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={migas}>
            <Head title={editando ? 'Editar previaje' : 'Nuevo previaje'} />

            <form
                onSubmit={enviar}
                className="flex flex-col gap-4 p-4 pb-[calc(7rem+env(safe-area-inset-bottom))] sm:gap-6 sm:p-6 lg:pb-6"
            >
                <section className="rounded-xl border border-sidebar-border/70 bg-card p-4 sm:p-6 dark:border-sidebar-border">
                    <h2 className="text-base font-semibold sm:text-lg">Datos generales</h2>

                    <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div className="sm:col-span-2 lg:col-span-1">
                            <Label htmlFor="equipo_id">Equipo</Label>
                            <select
                                id="equipo_id"
                                value={data.equipo_id}
                                disabled={editando}
                                onChange={(e) => cambiarEquipo(e.target.value)}
                                className="mt-1 h-11 w-full rounded-md border border-input bg-background px-3 text-base disabled:opacity-60 sm:h-10 sm:text-sm"
                            >
                                <option value="">Seleccione un equipo…</option>
                                {equipos.map((equipo) => (
                                    <option key={equipo.id} value={equipo.id}>
                                        {equipo.codigo} — {equipo.tipo} ({equipo.flota})
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.equipo_id} className="mt-1" />
                            {editando && (
                                <p className="mt-1 text-xs text-muted-foreground">
                                    El equipo no se puede cambiar en un previaje ya registrado.
                                </p>
                            )}
                        </div>

                        {/* RF-09.1: ambos campos en todo previaje, sin importar el tipo de equipo. */}
                        <LecturaNumerica
                            id="kilometraje"
                            etiqueta="Kilómetros"
                            sufijo="km"
                            valor={data.kilometraje}
                            ultimo={ultimasLecturas?.kilometraje ?? null}
                            error={errors.kilometraje}
                            onChange={(v) => setData('kilometraje', v)}
                        />
                        <LecturaNumerica
                            id="horometro"
                            etiqueta="Horómetro"
                            sufijo="h"
                            paso="0.01"
                            valor={data.horometro}
                            ultimo={ultimasLecturas?.horometro ?? null}
                            error={errors.horometro}
                            onChange={(v) => setData('horometro', v)}
                        />
                    </div>

                    {/* RF-10: la fecha la pone el servidor, no el usuario. */}
                    <p className="mt-4 text-xs text-muted-foreground">
                        {editando
                            ? `Fecha original del previaje: ${new Date(previaje.creado_en).toLocaleString('es')}. No cambia al editar.`
                            : 'La fecha y hora se registran automáticamente al guardar.'}
                    </p>
                </section>

                {data.equipo_id === '' ? (
                    <p className="rounded-xl border border-dashed border-sidebar-border/70 p-8 text-center text-sm text-muted-foreground">
                        Seleccione un equipo para cargar su checklist.
                    </p>
                ) : checklist.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-destructive/50 p-8 text-center text-sm text-destructive">
                        Este tipo de equipo no tiene secciones de checklist configuradas. Avise al administrador.
                    </p>
                ) : (
                    checklist.map((seccion) => (
                        <SeccionChecklist
                            key={seccion.id}
                            seccion={seccion}
                            data={data}
                            errors={errors}
                            esHallazgo={esHallazgo}
                            maxFotosPorItem={maxFotosPorItem}
                            fotosExistentesDe={fotosExistentesDe}
                            onRespuesta={actualizarRespuesta}
                            onObservacionSeccion={(valor) =>
                                setData('observaciones_seccion', {
                                    ...data.observaciones_seccion,
                                    [seccion.id]: valor,
                                })
                            }
                            onAgregarFotos={agregarFotos}
                            onQuitarFotoNueva={quitarFotoNueva}
                            onQuitarFotoExistente={(id) =>
                                setData('fotos_eliminadas', [...data.fotos_eliminadas, id])
                            }
                        />
                    ))
                )}

                {/*
                    En móvil la barra de envío queda fija abajo, al alcance del
                    pulgar; en escritorio vuelve al flujo normal (RNF-00).

                    Fondo sólido en vez de `backdrop-blur`: el desenfoque exige
                    recomponer esta capa en cada frame de scroll, y en hardware
                    móvil modesto eso es una causa conocida de scroll trabado o
                    con parones — un costo que un GPU de escritorio no revela.

                    El padding inferior sigue `env(safe-area-inset-bottom)` para
                    no quedar pegado a la barra de gestos/home del teléfono.
                */}
                <div
                    className="fixed inset-x-0 bottom-0 z-10 flex items-center gap-3 border-t border-sidebar-border/70 bg-background p-4 lg:static lg:rounded-xl lg:border lg:bg-card"
                    style={{ paddingBottom: 'max(1rem, env(safe-area-inset-bottom))' }}
                >
                    <div className="flex-1 text-sm">
                        {totalHallazgos > 0 ? (
                            <span className="flex items-center gap-1.5 font-medium text-amber-600 dark:text-amber-500">
                                <AlertTriangle className="size-4 shrink-0" />
                                {totalHallazgos} {totalHallazgos === 1 ? 'hallazgo' : 'hallazgos'}
                            </span>
                        ) : (
                            <span className="text-muted-foreground">Sin hallazgos</span>
                        )}
                        {/* RF-14: el previaje nunca bloquea la salida del equipo. */}
                        <p className="text-xs text-muted-foreground">Los hallazgos no bloquean la salida.</p>
                    </div>

                    <Button type="submit" disabled={processing} size="lg" className="min-w-36">
                        {processing && <Loader2 className="size-4 animate-spin" />}
                        {editando ? 'Guardar cambios' : 'Registrar previaje'}
                    </Button>
                </div>

                {progress && (
                    <div className="fixed inset-x-0 bottom-0 z-20 h-1 bg-primary/20">
                        <div className="h-full bg-primary transition-all" style={{ width: `${progress.percentage}%` }} />
                    </div>
                )}
            </form>
        </AppLayout>
    );
}

function inicializarRespuestas(
    checklist: ChecklistSeccion[],
    previaje: PreviajeEditable | null,
): Record<string, RespuestaForm> {
    const respuestas: Record<string, RespuestaForm> = {};

    checklist.forEach((seccion) =>
        seccion.items.forEach((item) => {
            const guardada = previaje?.respuestas?.[item.id];
            respuestas[item.id] = {
                checklist_opcion_id: guardada?.checklist_opcion_id ?? '',
                cantidad_agregada: guardada?.cantidad_agregada?.toString() ?? '',
                observaciones: guardada?.observaciones ?? '',
            };
        }),
    );

    return respuestas;
}

function LecturaNumerica({
    id,
    etiqueta,
    sufijo,
    valor,
    ultimo,
    error,
    paso = '1',
    onChange,
}: {
    id: string;
    etiqueta: string;
    sufijo: string;
    valor: string;
    ultimo: number | string | null;
    error?: string;
    paso?: string;
    onChange: (valor: string) => void;
}) {
    return (
        <div>
            <Label htmlFor={id}>
                {etiqueta} <span className="text-muted-foreground">({sufijo})</span>
            </Label>
            <Input
                id={id}
                type="number"
                inputMode="decimal"
                step={paso}
                min="0"
                value={valor}
                onChange={(e) => onChange(e.target.value)}
                className="mt-1 h-11 text-base sm:h-10 sm:text-sm"
            />
            <InputError message={error} className="mt-1" />
            {/* RN-02: la lectura no puede retroceder; se muestra la referencia. */}
            {ultimo !== null && (
                <p className="mt-1 text-xs text-muted-foreground">
                    Último registrado: {ultimo} {sufijo}
                </p>
            )}
        </div>
    );
}

function SeccionChecklist({
    seccion,
    data,
    errors,
    esHallazgo,
    maxFotosPorItem,
    fotosExistentesDe,
    onRespuesta,
    onObservacionSeccion,
    onAgregarFotos,
    onQuitarFotoNueva,
    onQuitarFotoExistente,
}: {
    seccion: ChecklistSeccion;
    data: { respuestas: Record<string, RespuestaForm>; observaciones_seccion: Record<string, string>; fotos: Record<string, File[]> };
    errors: Record<string, string>;
    esHallazgo: (itemId: number) => boolean;
    maxFotosPorItem: number;
    fotosExistentesDe: (itemId: number) => FotoExistente[];
    onRespuesta: (itemId: number, campo: keyof RespuestaForm, valor: string | number) => void;
    onObservacionSeccion: (valor: string) => void;
    onAgregarFotos: (itemId: number, archivos: FileList | null) => void;
    onQuitarFotoNueva: (itemId: number, indice: number) => void;
    onQuitarFotoExistente: (id: number) => void;
}) {
    return (
        <section className="rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
            <h2 className="border-b border-sidebar-border/70 px-4 py-3 text-base font-semibold sm:px-6 sm:text-lg dark:border-sidebar-border">
                {seccion.nombre}
            </h2>

            <div className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                {seccion.items.map((item) => (
                    <ItemChecklist
                        key={item.id}
                        item={item}
                        opciones={seccion.opciones}
                        respuesta={data.respuestas[item.id]}
                        hallazgo={esHallazgo(item.id)}
                        errors={errors}
                        fotosNuevas={data.fotos[item.id] ?? []}
                        fotosExistentes={fotosExistentesDe(item.id)}
                        maxFotos={maxFotosPorItem}
                        onRespuesta={onRespuesta}
                        onAgregarFotos={onAgregarFotos}
                        onQuitarFotoNueva={onQuitarFotoNueva}
                        onQuitarFotoExistente={onQuitarFotoExistente}
                    />
                ))}
            </div>

            {/* RF-09: la observación general de la sección siempre es obligatoria. */}
            <div className="border-t border-sidebar-border/70 p-4 sm:p-6 dark:border-sidebar-border">
                <Label htmlFor={`obs-${seccion.id}`}>Observaciones de {seccion.nombre}</Label>
                <textarea
                    id={`obs-${seccion.id}`}
                    rows={2}
                    value={data.observaciones_seccion[seccion.id] ?? ''}
                    onChange={(e) => onObservacionSeccion(e.target.value)}
                    className="mt-1 w-full rounded-md border border-input bg-background p-3 text-base sm:text-sm"
                    placeholder="Comentario general de la sección (obligatorio)"
                />
                <InputError message={errors[`observaciones_seccion.${seccion.id}`]} className="mt-1" />
            </div>
        </section>
    );
}

function ItemChecklist({
    item,
    opciones,
    respuesta,
    hallazgo,
    errors,
    fotosNuevas,
    fotosExistentes,
    maxFotos,
    onRespuesta,
    onAgregarFotos,
    onQuitarFotoNueva,
    onQuitarFotoExistente,
}: {
    item: ChecklistItem;
    opciones: { id: number; etiqueta: string; es_optima: boolean }[];
    respuesta: RespuestaForm | undefined;
    hallazgo: boolean;
    errors: Record<string, string>;
    fotosNuevas: File[];
    fotosExistentes: FotoExistente[];
    maxFotos: number;
    onRespuesta: (itemId: number, campo: keyof RespuestaForm, valor: string | number) => void;
    onAgregarFotos: (itemId: number, archivos: FileList | null) => void;
    onQuitarFotoNueva: (itemId: number, indice: number) => void;
    onQuitarFotoExistente: (id: number) => void;
}) {
    const totalFotos = fotosNuevas.length + fotosExistentes.length;

    return (
        <div className="p-4 sm:p-6">
            <p className="text-sm font-medium sm:text-base">
                {item.descripcion}
                {item.es_fluido && (
                    <span className="ml-2 rounded bg-muted px-1.5 py-0.5 align-middle text-xs font-normal text-muted-foreground">
                        fluido
                    </span>
                )}
            </p>

            {/*
                Botones grandes en vez de radios nativos: el mecánico responde
                desde el teléfono, en el patio y a veces con guantes (RNF-00).
            */}
            <div className="mt-3 flex flex-wrap gap-2">
                {opciones.map((opcion) => {
                    const seleccionada = Number(respuesta?.checklist_opcion_id) === opcion.id;

                    return (
                        <button
                            key={opcion.id}
                            type="button"
                            aria-pressed={seleccionada}
                            onClick={() => onRespuesta(item.id, 'checklist_opcion_id', opcion.id)}
                            className={[
                                'flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-lg border px-3 py-2 text-sm font-medium transition sm:flex-none sm:min-w-32',
                                seleccionada && opcion.es_optima
                                    ? 'border-emerald-600 bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
                                    : seleccionada
                                      ? 'border-amber-600 bg-amber-50 text-amber-900 dark:bg-amber-950 dark:text-amber-200'
                                      : 'border-input hover:bg-accent',
                            ].join(' ')}
                        >
                            {seleccionada && (opcion.es_optima ? <Check className="size-4" /> : <AlertTriangle className="size-4" />)}
                            {opcion.etiqueta}
                        </button>
                    );
                })}
            </div>
            <InputError message={errors[`respuestas.${item.id}.checklist_opcion_id`] ?? errors[`respuestas.${item.id}`]} className="mt-2" />

            {/*
                Todo lo de abajo aparece sólo cuando hay hallazgo: galones si es
                fluido (RN-06), observación (RN-10) y evidencia (RN-11).
            */}
            {hallazgo && (
                <div className="mt-4 space-y-3 rounded-lg border border-amber-300 bg-amber-50/50 p-3 dark:border-amber-900 dark:bg-amber-950/30">
                    {item.es_fluido && (
                        <div>
                            <Label htmlFor={`cantidad-${item.id}`}>Galones agregados</Label>
                            <Input
                                id={`cantidad-${item.id}`}
                                type="number"
                                inputMode="decimal"
                                step="0.01"
                                min="0"
                                value={respuesta?.cantidad_agregada ?? ''}
                                onChange={(e) => onRespuesta(item.id, 'cantidad_agregada', e.target.value)}
                                className="mt-1 h-11 max-w-40 bg-background text-base sm:h-10 sm:text-sm"
                            />
                            <InputError message={errors[`respuestas.${item.id}.cantidad_agregada`]} className="mt-1" />
                        </div>
                    )}

                    <div>
                        <Label htmlFor={`obs-item-${item.id}`}>Detalle del hallazgo</Label>
                        <textarea
                            id={`obs-item-${item.id}`}
                            rows={2}
                            value={respuesta?.observaciones ?? ''}
                            onChange={(e) => onRespuesta(item.id, 'observaciones', e.target.value)}
                            className="mt-1 w-full rounded-md border border-input bg-background p-3 text-base sm:text-sm"
                            placeholder="Ej. fuga visible en cárter, se agregaron 2 galones"
                        />
                        <InputError message={errors[`respuestas.${item.id}.observaciones`]} className="mt-1" />
                    </div>

                    <div>
                        <Label>
                            Evidencia fotográfica{' '}
                            <span className="text-muted-foreground">
                                ({totalFotos}/{maxFotos}, mínimo 1)
                            </span>
                        </Label>

                        <div className="mt-2 flex flex-wrap gap-2">
                            {fotosExistentes.map((foto) => (
                                <Miniatura
                                    key={`existente-${foto.id}`}
                                    src={foto.url}
                                    onQuitar={() => onQuitarFotoExistente(foto.id)}
                                />
                            ))}
                            {fotosNuevas.map((archivo, indice) => (
                                <Miniatura
                                    key={`nueva-${indice}-${archivo.name}`}
                                    src={URL.createObjectURL(archivo)}
                                    onQuitar={() => onQuitarFotoNueva(item.id, indice)}
                                />
                            ))}

                            {totalFotos < maxFotos && (
                                <label className="flex size-20 cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-input bg-background text-xs text-muted-foreground hover:bg-accent">
                                    <Camera className="size-5" />
                                    Agregar
                                    <input
                                        type="file"
                                        accept="image/*"
                                        // `capture` abre la cámara directamente en el teléfono.
                                        capture="environment"
                                        multiple
                                        className="sr-only"
                                        onChange={(e) => {
                                            onAgregarFotos(item.id, e.target.files);
                                            e.target.value = '';
                                        }}
                                    />
                                </label>
                            )}
                        </div>
                        <InputError message={errors[`fotos.${item.id}`]} className="mt-1" />
                    </div>
                </div>
            )}
        </div>
    );
}

function Miniatura({ src, onQuitar }: { src: string; onQuitar: () => void }) {
    return (
        <div className="relative size-20 overflow-hidden rounded-lg border border-input">
            <img src={src} alt="Evidencia del hallazgo" className="size-full object-cover" />
            <button
                type="button"
                onClick={onQuitar}
                aria-label="Quitar foto"
                className="absolute right-0.5 top-0.5 rounded-full bg-background/90 p-1 hover:bg-destructive hover:text-destructive-foreground"
            >
                <X className="size-3" />
            </button>
        </div>
    );
}
