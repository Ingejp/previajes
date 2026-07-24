<?php

use App\Enums\EstatusPreviaje;
use App\Enums\RolUsuario;
use App\Models\Acceso;
use App\Models\Equipo;
use App\Models\Flota;
use App\Models\Previaje;
use App\Models\TipoEquipo;
use App\Models\User;
use App\Services\AuditoriaService;
use Inertia\Testing\AssertableInertia;

/**
 * RF-20 / RN-09: reglas de visibilidad de la pantalla de auditoría.
 *
 *  - Supervisor: sólo su(s) flota(s).
 *  - Administrador: todas las flotas, pero nunca la actividad del super
 *    administrador.
 *  - Super administrador: todo.
 *  - Mecánico: sin acceso.
 */
beforeEach(function () {
    $this->flota = Flota::factory()->create(['nombre' => 'Honduras']);
    $this->otraFlota = Flota::factory()->create(['nombre' => 'Guatemala']);
    $this->tipo = TipoEquipo::factory()->create();

    $this->mecanico = User::factory()->create(['rol' => RolUsuario::Mecanico, 'flota_id' => $this->flota->id]);
    $this->supervisor = User::factory()->create(['rol' => RolUsuario::Supervisor, 'flota_id' => $this->flota->id]);
    $this->admin = User::factory()->create(['rol' => RolUsuario::Administrador]);
    $this->superAdmin = User::factory()->create(['rol' => RolUsuario::SuperAdministrador]);

    $this->auditoria = app(AuditoriaService::class);
});

it('deja fuera al mecánico', function () {
    $this->actingAs($this->mecanico)->get(route('auditoria.index'))->assertForbidden();

    $this->actingAs($this->supervisor)->get(route('auditoria.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('auditoria.index'))->assertOk();
    $this->actingAs($this->superAdmin)->get(route('auditoria.index'))->assertOk();
});

it('oculta al administrador la actividad del super administrador', function () {
    // El super administrador crea un equipo; el administrador, otro.
    $this->actingAs($this->superAdmin);
    Equipo::factory()->create(['codigo' => 'SECRETO-1', 'flota_id' => $this->flota->id, 'tipo_equipo_id' => $this->tipo->id]);

    $this->actingAs($this->admin);
    Equipo::factory()->create(['codigo' => 'VISIBLE-1', 'flota_id' => $this->flota->id, 'tipo_equipo_id' => $this->tipo->id]);

    $causantesVistosPorAdmin = $this->auditoria->cambios($this->admin)->pluck('causer_id');

    expect($causantesVistosPorAdmin)
        ->toContain($this->admin->id)
        ->not->toContain($this->superAdmin->id);

    // El super administrador sí ve ambas.
    $causantesVistosPorSuper = $this->auditoria->cambios($this->superAdmin)->pluck('causer_id');

    expect($causantesVistosPorSuper)
        ->toContain($this->admin->id)
        ->toContain($this->superAdmin->id);
});

it('limita al supervisor a los previajes de su flota', function () {
    $equipoPropio = Equipo::factory()->create(['flota_id' => $this->flota->id, 'tipo_equipo_id' => $this->tipo->id]);
    $equipoAjeno = Equipo::factory()->create(['flota_id' => $this->otraFlota->id, 'tipo_equipo_id' => $this->tipo->id]);

    $propio = crearPreviajeCrudo($equipoPropio, $this->mecanico);
    $ajeno = crearPreviajeCrudo($equipoAjeno, $this->mecanico);

    $ids = $this->auditoria->cambios($this->supervisor)->pluck('subject_id');

    expect($ids)->toContain($propio->id)->not->toContain($ajeno->id);

    // El administrador ve los de ambas flotas.
    $idsAdmin = $this->auditoria->cambios($this->admin)->pluck('subject_id');
    expect($idsAdmin)->toContain($propio->id)->toContain($ajeno->id);
});

it('deja ver al supervisor sólo los accesos de usuarios de su flota', function () {
    $ajeno = User::factory()->create(['rol' => RolUsuario::Mecanico, 'flota_id' => $this->otraFlota->id]);

    Acceso::create(['usuario_id' => $this->mecanico->id, 'evento' => 'login', 'exitoso' => true]);
    Acceso::create(['usuario_id' => $ajeno->id, 'evento' => 'login', 'exitoso' => true]);

    $vistos = $this->auditoria->accesos($this->supervisor)->pluck('usuario_id');

    expect($vistos)->toContain($this->mecanico->id)->not->toContain($ajeno->id);
});

it('conserva visibles los intentos fallidos sin usuario asociado', function () {
    Acceso::create([
        'usuario_id' => null,
        'email_intentado' => 'atacante@ejemplo.com',
        'evento' => 'fallido',
        'exitoso' => false,
    ]);
    Acceso::create(['usuario_id' => $this->superAdmin->id, 'evento' => 'login', 'exitoso' => true]);

    $vistos = $this->auditoria->accesos($this->admin)->get();

    // El intento contra un correo inexistente es justo lo que hay que vigilar…
    expect($vistos->pluck('email_intentado'))->toContain('atacante@ejemplo.com')
        // …pero el ingreso del super administrador sigue oculto (RN-09).
        ->and($vistos->pluck('usuario_id'))->not->toContain($this->superAdmin->id);
});

it('no ofrece al administrador filtrar por un super administrador', function () {
    $usuarios = $this->auditoria->usuariosFiltrables($this->admin)->pluck('id');

    expect($usuarios)->not->toContain($this->superAdmin->id)->toContain($this->admin->id);

    expect($this->auditoria->usuariosFiltrables($this->superAdmin)->pluck('id'))
        ->toContain($this->superAdmin->id);
});

it('exporta a CSV respetando el recorte por rol', function () {
    $this->actingAs($this->superAdmin);
    Equipo::factory()->create(['codigo' => 'SECRETO-2', 'flota_id' => $this->flota->id, 'tipo_equipo_id' => $this->tipo->id]);

    $respuesta = $this->actingAs($this->admin)->get(route('auditoria.exportar'));

    $respuesta->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $csv = $respuesta->streamedContent();

    expect($csv)->toContain('Valor anterior')
        ->and($csv)->not->toContain($this->superAdmin->name);
});

it('muestra la pantalla con ambas pestañas de datos', function () {
    $this->actingAs($this->admin)
        ->get(route('auditoria.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('auditoria/index')
            ->has('cambios.data')
            ->has('accesos.data')
            ->has('opciones.usuarios'));
});

/** Crea un previaje directamente, sin pasar por el formulario. */
function crearPreviajeCrudo(Equipo $equipo, User $mecanico): Previaje
{
    return Previaje::create([
        'equipo_id' => $equipo->id,
        'mecanico_id' => $mecanico->id,
        'flota_id' => $equipo->flota_id,
        'kilometraje' => 100,
        'horometro' => 10,
        'estatus' => EstatusPreviaje::SinHallazgos,
        'created_by' => $mecanico->id,
    ]);
}
