<?php

use App\Enums\RolUsuario;
use App\Models\ChecklistItem;
use App\Models\ChecklistOpcion;
use App\Models\ChecklistSeccion;
use App\Models\Equipo;
use App\Models\Flota;
use App\Models\Previaje;
use App\Models\TipoEquipo;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * Contrato entre el controlador y las pantallas: que cada vista reciba lo que
 * necesita para renderizarse, y sólo lo que el rol tiene permitido ver.
 */
beforeEach(function () {
    $this->flota = Flota::factory()->create(['nombre' => 'Honduras']);
    $this->otraFlota = Flota::factory()->create(['nombre' => 'Guatemala']);

    $this->cabezal = TipoEquipo::factory()->create(['nombre' => 'Cabezal']);
    $this->genset = TipoEquipo::factory()->create(['nombre' => 'Genset']);

    // El cabezal revisa motor y chasis; el genset sólo motor (RN-07).
    $this->motor = ChecklistSeccion::factory()->create(['nombre' => 'MOTOR', 'orden' => 1]);
    $this->chasis = ChecklistSeccion::factory()->create(['nombre' => 'CHASIS', 'orden' => 2]);
    $this->cabezal->secciones()->attach([$this->motor->id, $this->chasis->id]);
    $this->genset->secciones()->attach($this->motor->id);

    foreach ([$this->motor, $this->chasis] as $seccion) {
        ChecklistItem::factory()->create(['seccion_id' => $seccion->id]);
        ChecklistOpcion::factory()->create(['seccion_id' => $seccion->id]);
    }

    // Un ítem desactivado no debe llegar al formulario.
    $this->itemInactivo = ChecklistItem::factory()->create([
        'seccion_id' => $this->motor->id,
        'activo' => false,
    ]);

    $this->equipoCabezal = Equipo::factory()->create([
        'flota_id' => $this->flota->id,
        'tipo_equipo_id' => $this->cabezal->id,
        'codigo' => 'C-101',
    ]);
    $this->equipoGenset = Equipo::factory()->create([
        'flota_id' => $this->flota->id,
        'tipo_equipo_id' => $this->genset->id,
        'codigo' => 'G-301',
    ]);

    $this->mecanico = User::factory()->create([
        'rol' => RolUsuario::Mecanico,
        'flota_id' => $this->flota->id,
    ]);
});

it('arma el checklist según el tipo de equipo seleccionado', function () {
    // El cabezal trae las dos secciones…
    $this->actingAs($this->mecanico)
        ->get(route('previajes.create', ['equipo_id' => $this->equipoCabezal->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('previajes/form')
            ->has('checklist', 2)
            ->where('checklist.0.nombre', 'MOTOR')
            ->where('checklist.1.nombre', 'CHASIS'));

    // …y el genset sólo motor (RN-07).
    $this->actingAs($this->mecanico)
        ->get(route('previajes.create', ['equipo_id' => $this->equipoGenset->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('checklist', 1)
            ->where('checklist.0.nombre', 'MOTOR'));
});

it('no incluye ítems desactivados en el formulario', function () {
    $this->actingAs($this->mecanico)
        ->get(route('previajes.create', ['equipo_id' => $this->equipoCabezal->id]))
        ->assertInertia(function (AssertableInertia $page) {
            $ids = collect($page->toArray()['props']['checklist'])
                ->flatMap(fn ($s) => collect($s['items'])->pluck('id'));

            expect($ids)->not->toContain($this->itemInactivo->id);
        });
});

it('sólo ofrece equipos de las flotas que el usuario puede ver', function () {
    $ajeno = Equipo::factory()->create([
        'flota_id' => $this->otraFlota->id,
        'tipo_equipo_id' => $this->cabezal->id,
        'codigo' => 'X-999',
    ]);

    $this->actingAs($this->mecanico)
        ->get(route('previajes.create'))
        ->assertInertia(function (AssertableInertia $page) use ($ajeno) {
            $codigos = collect($page->toArray()['props']['equipos'])->pluck('codigo');

            expect($codigos)->toContain('C-101')->not->toContain($ajeno->codigo);
        });
});

it('muestra al mecánico sólo su propio historial', function () {
    $otroMecanico = User::factory()->create([
        'rol' => RolUsuario::Mecanico,
        'flota_id' => $this->flota->id,
    ]);

    crearPreviajeSimple($this, $this->mecanico);
    crearPreviajeSimple($this, $otroMecanico);

    $this->actingAs($this->mecanico)
        ->get(route('previajes.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('previajes/index')
            ->has('previajes.data', 1)
            ->where('previajes.data.0.mecanico.name', $this->mecanico->name));

    // El supervisor de la flota sí ve los de todo su equipo.
    $supervisor = User::factory()->create([
        'rol' => RolUsuario::Supervisor,
        'flota_id' => $this->flota->id,
    ]);

    $this->actingAs($supervisor)
        ->get(route('previajes.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('previajes.data', 2));
});

it('impide ver el detalle de un previaje de otra flota', function () {
    $previaje = crearPreviajeSimple($this, $this->mecanico);

    $ajeno = User::factory()->create([
        'rol' => RolUsuario::Supervisor,
        'flota_id' => $this->otraFlota->id,
    ]);

    $this->actingAs($ajeno)->get(route('previajes.show', $previaje))->assertForbidden();
    $this->actingAs($this->mecanico)->get(route('previajes.show', $previaje))->assertOk();
});

it('no deja editar un previaje anulado', function () {
    $previaje = crearPreviajeSimple($this, $this->mecanico);

    $admin = User::factory()->create(['rol' => RolUsuario::Administrador]);
    $this->actingAs($admin)->post(route('previajes.anular', $previaje), [
        'motivo_anulacion' => 'Se registró contra el equipo equivocado.',
    ])->assertRedirect();

    $this->actingAs($this->mecanico)->get(route('previajes.edit', $previaje))->assertForbidden();
});

it('exige sesión iniciada en todas las pantallas de previajes', function () {
    $this->get(route('previajes.index'))->assertRedirect(route('login'));
    $this->get(route('previajes.create'))->assertRedirect(route('login'));
});

/** Crea un previaje mínimo válido para el cabezal de la flota principal. */
function crearPreviajeSimple(object $ctx, User $mecanico): Previaje
{
    $secciones = $ctx->cabezal->secciones()
        ->with(['items' => fn ($q) => $q->where('activo', true), 'opciones'])
        ->get();

    // Se arma con bucles y no con `flatMap`, que reindexaría las claves
    // numéricas y perdería el id de cada ítem.
    $observaciones = [];
    $respuestas = [];

    foreach ($secciones as $seccion) {
        $observaciones[$seccion->id] = 'Sin novedad.';

        foreach ($seccion->items as $item) {
            $respuestas[$item->id] = ['checklist_opcion_id' => $seccion->opciones->first()->id];
        }
    }

    $ctx->actingAs($mecanico)->post(route('previajes.store'), [
        'equipo_id' => $ctx->equipoCabezal->id,
        'kilometraje' => 1000,
        'horometro' => 100,
        'observaciones_seccion' => $observaciones,
        'respuestas' => $respuestas,
    ])->assertSessionHasNoErrors();

    return Previaje::latest('id')->first();
}
