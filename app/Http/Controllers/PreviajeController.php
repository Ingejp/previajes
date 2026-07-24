<?php

namespace App\Http\Controllers;

use App\Enums\EstatusPreviaje;
use App\Http\Requests\StorePreviajeRequest;
use App\Http\Requests\UpdatePreviajeRequest;
use App\Models\Equipo;
use App\Models\Flota;
use App\Models\Previaje;
use App\Models\TipoEquipo;
use App\Models\User;
use App\Services\ChecklistService;
use App\Services\PreviajeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PreviajeController extends Controller
{
    public function __construct(
        private readonly PreviajeService $previajes,
        private readonly ChecklistService $checklist,
    ) {}

    /**
     * RF-15: historial con filtros por flota, tipo de equipo, equipo, mecánico,
     * rango de fechas y estatus.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Previaje::class);

        $usuario = $request->user();

        $previajes = $this->historialFiltrado($request)
            ->with(['equipo:id,codigo,tipo_equipo_id', 'equipo.tipoEquipo:id,nombre', 'mecanico:id,name', 'flota:id,nombre'])
            ->withCount('fotos')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('previajes/index', [
            'previajes' => $previajes,
            'filtros' => $request->only(['flota_id', 'equipo_id', 'mecanico_id', 'tipo_equipo_id', 'estatus', 'desde', 'hasta']),
            'opciones' => $this->opcionesDeFiltro($usuario),
        ]);
    }

    /**
     * RF-09: el checklist no se renderiza hasta saber el equipo, porque las
     * secciones e ítems dependen de su tipo (RN-07).
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Previaje::class);

        $usuario = $request->user();
        $equipo = $this->equipoSeleccionado($request, $usuario);

        return Inertia::render('previajes/form', [
            'previaje' => null,
            'equipos' => $this->equiposDisponibles($usuario),
            'equipoSeleccionado' => $equipo?->only(['id', 'codigo', 'marca', 'modelo']),
            'checklist' => $equipo ? $this->checklistSerializado($equipo) : [],
            'ultimasLecturas' => $equipo ? $this->checklist->ultimasLecturas($equipo) : null,
            'maxFotosPorItem' => config('previajes.fotos.max_por_item'),
        ]);
    }

    public function store(StorePreviajeRequest $request): RedirectResponse
    {
        $previaje = $this->previajes->crear($request->validated(), $request->user());

        return to_route('previajes.show', $previaje)
            ->with('exito', $previaje->tieneHallazgos()
                ? 'Previaje registrado con hallazgos. Se notificó al supervisor.'
                : 'Previaje registrado correctamente.');
    }

    /** RF-15: detalle con fotos e historial de ediciones. */
    public function show(Previaje $previaje): Response
    {
        Gate::authorize('view', $previaje);

        $previaje->load([
            'equipo:id,codigo,marca,modelo,tipo_equipo_id,flota_id',
            'equipo.tipoEquipo:id,nombre',
            'mecanico:id,name',
            'flota:id,nombre',
            'anuladoPor:id,name',
            'respuestas.item:id,descripcion,seccion_id,es_fluido',
            'respuestas.item.seccion:id,nombre,orden',
            'respuestas.opcion:id,etiqueta,es_optima',
            'observacionesSeccion.seccion:id,nombre,orden',
            'fotos',
        ]);

        return Inertia::render('previajes/show', [
            'previaje' => $this->detalleSerializado($previaje),
            'auditoria' => $this->historialDeEdiciones($previaje),
            'permisos' => [
                'editar' => Gate::allows('update', $previaje),
                'anular' => Gate::allows('anular', $previaje),
            ],
        ]);
    }

    public function edit(Request $request, Previaje $previaje): Response
    {
        Gate::authorize('update', $previaje);

        $previaje->load(['equipo', 'respuestas', 'observacionesSeccion', 'fotos']);
        $equipo = $previaje->equipo;

        return Inertia::render('previajes/form', [
            'previaje' => [
                'id' => $previaje->id,
                'equipo_id' => $previaje->equipo_id,
                'kilometraje' => $previaje->kilometraje,
                'horometro' => $previaje->horometro,
                'creado_en' => $previaje->created_at->toIso8601String(),
                'respuestas' => $previaje->respuestas->keyBy('checklist_item_id')->map(fn ($r) => [
                    'checklist_opcion_id' => $r->checklist_opcion_id,
                    'cantidad_agregada' => $r->cantidad_agregada,
                    'observaciones' => $r->observaciones,
                ]),
                'observaciones_seccion' => $previaje->observacionesSeccion
                    ->keyBy('checklist_seccion_id')
                    ->map->observaciones,
                'fotos' => $previaje->fotos->map(fn ($f) => [
                    'id' => $f->id,
                    'checklist_item_id' => $f->checklist_item_id,
                    'url' => $f->url(),
                ])->values(),
            ],
            'equipos' => $this->equiposDisponibles($request->user()),
            'equipoSeleccionado' => $equipo->only(['id', 'codigo', 'marca', 'modelo']),
            'checklist' => $this->checklistSerializado($equipo),
            'ultimasLecturas' => $this->checklist->ultimasLecturas($equipo, $previaje),
            'maxFotosPorItem' => config('previajes.fotos.max_por_item'),
        ]);
    }

    public function update(UpdatePreviajeRequest $request, Previaje $previaje): RedirectResponse
    {
        $this->previajes->actualizar($previaje, $request->validated(), $request->user());

        return to_route('previajes.show', $previaje)
            ->with('exito', 'Previaje actualizado. Se notificó al supervisor y quedó registrado en auditoría.');
    }

    /** RF-12: los previajes no se eliminan, se anulan. */
    public function anular(Request $request, Previaje $previaje): RedirectResponse
    {
        Gate::authorize('anular', $previaje);

        $datos = $request->validate([
            'motivo_anulacion' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $this->previajes->anular($previaje, $request->user(), $datos['motivo_anulacion']);

        return to_route('previajes.show', $previaje)->with('exito', 'Previaje anulado.');
    }

    /**
     * RF-15: exportación del historial ya filtrado, a CSV legible por Excel.
     *
     * Se transmite por bloques para que un rango largo no cargue la flota
     * entera en memoria.
     */
    public function exportar(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Previaje::class);

        $consulta = $this->historialFiltrado($request)
            ->with(['equipo.tipoEquipo:id,nombre', 'mecanico:id,name', 'flota:id,nombre']);

        $nombre = 'previajes-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($consulta) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF"); // BOM, para que Excel respete los acentos

            fputcsv($salida, ['Fecha', 'Equipo', 'Tipo', 'Flota', 'Mecánico', 'Kilometraje', 'Horómetro', 'Estatus']);

            foreach ($consulta->lazy() as $previaje) {
                fputcsv($salida, [
                    $previaje->created_at->format('Y-m-d H:i:s'),
                    $previaje->equipo->codigo,
                    $previaje->equipo->tipoEquipo->nombre,
                    $previaje->flota->nombre,
                    $previaje->mecanico->name,
                    $previaje->kilometraje,
                    $previaje->horometro,
                    $previaje->estatus->etiqueta(),
                ]);
            }

            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Consulta base del historial (RF-15), compartida por el listado y la
     * exportación para que ambos apliquen exactamente los mismos filtros y el
     * mismo recorte por rol.
     */
    private function historialFiltrado(Request $request): Builder
    {
        $usuario = $request->user();

        return Previaje::query()
            ->deFlotasVisibles($usuario)
            // El mecánico ve su propio historial, no el de sus compañeros.
            ->when($usuario->esMecanico(), fn ($q) => $q->where('mecanico_id', $usuario->id))
            ->when($request->integer('flota_id'), fn ($q, $id) => $q->where('flota_id', $id))
            ->when($request->integer('equipo_id'), fn ($q, $id) => $q->where('equipo_id', $id))
            ->when($request->integer('mecanico_id'), fn ($q, $id) => $q->where('mecanico_id', $id))
            ->when($request->string('estatus')->toString(), fn ($q, $e) => $q->where('estatus', $e))
            ->when($request->integer('tipo_equipo_id'), fn ($q, $id) => $q->whereHas(
                'equipo',
                fn ($e) => $e->where('tipo_equipo_id', $id),
            ))
            ->when($request->date('desde'), fn ($q, $f) => $q->where('created_at', '>=', $f->startOfDay()))
            ->when($request->date('hasta'), fn ($q, $f) => $q->where('created_at', '<=', $f->endOfDay()))
            ->latest();
    }

    /** @return array<int, array<string, mixed>> */
    private function checklistSerializado(Equipo $equipo): array
    {
        return $this->checklist->paraEquipo($equipo)->map(fn ($seccion) => [
            'id' => $seccion->id,
            'nombre' => $seccion->nombre,
            'opciones' => $seccion->opciones->map->only(['id', 'etiqueta', 'es_optima'])->values(),
            'items' => $seccion->items->map->only(['id', 'descripcion', 'es_fluido'])->values(),
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    private function detalleSerializado(Previaje $previaje): array
    {
        $fotosPorItem = $previaje->fotos->groupBy('checklist_item_id');

        $secciones = $previaje->respuestas
            ->groupBy(fn ($respuesta) => $respuesta->item->seccion_id)
            ->map(function ($respuestas, $seccionId) use ($previaje, $fotosPorItem) {
                $seccion = $respuestas->first()->item->seccion;

                return [
                    'id' => $seccion->id,
                    'nombre' => $seccion->nombre,
                    'orden' => $seccion->orden,
                    'observaciones' => $previaje->observacionesSeccion
                        ->firstWhere('checklist_seccion_id', $seccionId)?->observaciones,
                    'respuestas' => $respuestas->map(fn ($r) => [
                        'item' => $r->item->descripcion,
                        'es_fluido' => $r->item->es_fluido,
                        'respuesta' => $r->opcion->etiqueta,
                        'es_hallazgo' => ! $r->opcion->es_optima,
                        'cantidad_agregada' => $r->cantidad_agregada,
                        'observaciones' => $r->observaciones,
                        'fotos' => ($fotosPorItem[$r->checklist_item_id] ?? collect())
                            ->map(fn ($f) => ['id' => $f->id, 'url' => $f->url()])->values(),
                    ])->values(),
                ];
            })
            ->sortBy('orden')
            ->values();

        return [
            'id' => $previaje->id,
            'equipo' => $previaje->equipo->only(['id', 'codigo', 'marca', 'modelo']),
            'tipo_equipo' => $previaje->equipo->tipoEquipo->nombre,
            'flota' => $previaje->flota->nombre,
            'mecanico' => $previaje->mecanico->name,
            'kilometraje' => $previaje->kilometraje,
            'horometro' => $previaje->horometro,
            'estatus' => $previaje->estatus->value,
            'estatus_etiqueta' => $previaje->estatus->etiqueta(),
            'creado_en' => $previaje->created_at->toIso8601String(),
            'actualizado_en' => $previaje->updated_at->toIso8601String(),
            'fue_editado' => $previaje->updated_at->gt($previaje->created_at),
            'motivo_anulacion' => $previaje->motivo_anulacion,
            'anulado_por' => $previaje->anuladoPor?->name,
            'anulado_en' => $previaje->anulado_en?->toIso8601String(),
            'secciones' => $secciones,
            'fotos_generales' => $previaje->fotos->whereNull('checklist_item_id')
                ->map(fn ($f) => ['id' => $f->id, 'url' => $f->url()])->values(),
        ];
    }

    /**
     * RF-15: historial de ediciones del previaje, tomado de la bitácora.
     *
     * @return array<int, array<string, mixed>>
     */
    private function historialDeEdiciones(Previaje $previaje): array
    {
        return $previaje->activitiesAsSubject()
            ->with('causer:id,name')
            ->latest()
            ->get()
            ->map(fn ($actividad) => [
                'id' => $actividad->id,
                'evento' => $actividad->event,
                'usuario' => $actividad->causer?->name ?? 'Sistema',
                'fecha' => $actividad->created_at->toIso8601String(),
                // activitylog v5 guarda los cambios en `attribute_changes`;
                // `properties` queda para propiedades personalizadas.
                'anterior' => $actividad->attribute_changes['old'] ?? [],
                'nuevo' => $actividad->attribute_changes['attributes'] ?? [],
            ])
            ->all();
    }

    private function equipoSeleccionado(Request $request, User $usuario): ?Equipo
    {
        $id = $request->integer('equipo_id');

        if (! $id) {
            return null;
        }

        $equipo = Equipo::where('activo', true)->find($id);

        return $equipo && $usuario->puedeVerFlota($equipo->flota_id) ? $equipo : null;
    }

    /** @return array<int, array<string, mixed>> */
    private function equiposDisponibles(User $usuario): array
    {
        return Equipo::query()
            ->deFlotasVisibles($usuario)
            ->where('activo', true)
            ->with(['tipoEquipo:id,nombre', 'flota:id,nombre'])
            ->orderBy('codigo')
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'codigo' => $e->codigo,
                'marca' => $e->marca,
                'modelo' => $e->modelo,
                'tipo' => $e->tipoEquipo->nombre,
                'flota' => $e->flota->nombre,
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    private function opcionesDeFiltro(User $usuario): array
    {
        return [
            'flotas' => Flota::query()
                ->whereIn('id', $usuario->flotasAccesibles())
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
            'tiposEquipo' => TipoEquipo::orderBy('nombre')->get(['id', 'nombre']),
            'equipos' => Equipo::query()
                ->deFlotasVisibles($usuario)
                ->orderBy('codigo')
                ->get(['id', 'codigo']),
            'mecanicos' => $usuario->esMecanico()
                ? []
                : User::query()
                    ->whereIn('flota_id', $usuario->flotasAccesibles())
                    ->orderBy('name')
                    ->get(['id', 'name']),
            'estatus' => EstatusPreviaje::opciones(),
        ];
    }
}
