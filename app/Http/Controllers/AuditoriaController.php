<?php

namespace App\Http\Controllers;

use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * RF-20: punto único de monitoreo — qué cambió, quién entró y qué se hizo.
 *
 * El recorte por rol vive en `AuditoriaService`, aplicado sobre la consulta.
 */
class AuditoriaController extends Controller
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    public function index(Request $request): Response
    {
        Gate::authorize('ver-auditoria');

        $usuario = $request->user();
        $filtros = $request->only(['usuario_id', 'tipo_evento', 'log', 'desde', 'hasta', 'solo_fallidos', 'vista']);

        return Inertia::render('auditoria/index', [
            'cambios' => $this->auditoria->cambios($usuario, $filtros)
                ->paginate(25, pageName: 'pagina_cambios')
                ->withQueryString()
                ->through(fn ($actividad) => [
                    'id' => $actividad->id,
                    'fecha' => $actividad->created_at->toIso8601String(),
                    'usuario' => $actividad->causer?->name ?? 'Sistema',
                    'rol' => $actividad->causer?->rol?->etiqueta(),
                    'entidad' => $actividad->log_name,
                    'evento' => $actividad->event,
                    'registro_id' => $actividad->subject_id,
                    'anterior' => $actividad->attribute_changes['old'] ?? [],
                    'nuevo' => $actividad->attribute_changes['attributes'] ?? [],
                ]),

            'accesos' => $this->auditoria->accesos($usuario, $filtros)
                ->paginate(25, pageName: 'pagina_accesos')
                ->withQueryString()
                ->through(fn ($acceso) => [
                    'id' => $acceso->id,
                    'fecha' => $acceso->created_at?->toIso8601String(),
                    'usuario' => $acceso->usuario?->name,
                    'email_intentado' => $acceso->email_intentado,
                    'evento' => $acceso->evento,
                    'exitoso' => $acceso->exitoso,
                    'ip' => $acceso->ip,
                ]),

            'filtros' => $filtros,
            'opciones' => [
                'usuarios' => $this->auditoria->usuariosFiltrables($usuario),
                'eventos' => [
                    ['valor' => 'created', 'etiqueta' => 'Creación'],
                    ['valor' => 'updated', 'etiqueta' => 'Modificación'],
                    ['valor' => 'deleted', 'etiqueta' => 'Eliminación'],
                ],
                'entidades' => [
                    ['valor' => 'previaje', 'etiqueta' => 'Previajes'],
                    ['valor' => 'equipo', 'etiqueta' => 'Equipos'],
                    ['valor' => 'usuario', 'etiqueta' => 'Usuarios'],
                    ['valor' => 'checklist_item', 'etiqueta' => 'Ítems de checklist'],
                    ['valor' => 'checklist_seccion', 'etiqueta' => 'Secciones'],
                    ['valor' => 'checklist_opcion', 'etiqueta' => 'Opciones'],
                    ['valor' => 'tipo_equipo', 'etiqueta' => 'Tipos de equipo'],
                    ['valor' => 'flota', 'etiqueta' => 'Flotas'],
                    ['valor' => 'registro_llanta', 'etiqueta' => 'Registros de llantas'],
                    ['valor' => 'configuracion', 'etiqueta' => 'Configuraciones'],
                ],
            ],
        ]);
    }

    /**
     * RF-20: exportación a CSV para revisiones externas o de cumplimiento.
     *
     * Se transmite fila por fila en vez de armar el archivo en memoria, para
     * que una auditoría de varios meses no agote la del servidor.
     */
    public function exportar(Request $request): StreamedResponse
    {
        Gate::authorize('ver-auditoria');

        $usuario = $request->user();
        $filtros = $request->only(['usuario_id', 'tipo_evento', 'log', 'desde', 'hasta', 'solo_fallidos']);
        $tipo = $request->string('tipo')->toString() === 'accesos' ? 'accesos' : 'cambios';

        $nombre = "auditoria-{$tipo}-".now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($usuario, $filtros, $tipo) {
            $salida = fopen('php://output', 'w');

            // BOM para que Excel abra los acentos correctamente.
            fwrite($salida, "\xEF\xBB\xBF");

            if ($tipo === 'accesos') {
                fputcsv($salida, ['Fecha', 'Usuario', 'Correo intentado', 'Evento', 'Exitoso', 'IP']);

                foreach ($this->auditoria->accesos($usuario, $filtros)->lazy() as $acceso) {
                    fputcsv($salida, [
                        $acceso->created_at?->format('Y-m-d H:i:s'),
                        $acceso->usuario?->name ?? '',
                        $acceso->email_intentado ?? '',
                        $acceso->evento,
                        $acceso->exitoso ? 'Sí' : 'No',
                        $acceso->ip ?? '',
                    ]);
                }
            } else {
                fputcsv($salida, ['Fecha', 'Usuario', 'Rol', 'Entidad', 'Evento', 'Registro', 'Valor anterior', 'Valor nuevo']);

                foreach ($this->auditoria->cambios($usuario, $filtros)->lazy() as $actividad) {
                    fputcsv($salida, [
                        $actividad->created_at->format('Y-m-d H:i:s'),
                        $actividad->causer?->name ?? 'Sistema',
                        $actividad->causer?->rol?->etiqueta() ?? '',
                        $actividad->log_name,
                        $actividad->event,
                        $actividad->subject_id,
                        json_encode($actividad->attribute_changes['old'] ?? [], JSON_UNESCAPED_UNICODE),
                        json_encode($actividad->attribute_changes['attributes'] ?? [], JSON_UNESCAPED_UNICODE),
                    ]);
                }
            }

            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
