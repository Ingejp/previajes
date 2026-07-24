<?php

use App\Enums\EstatusPreviaje;
use App\Enums\RolUsuario;
use App\Models\ChecklistItem;
use App\Models\ChecklistOpcion;
use App\Models\ChecklistSeccion;
use App\Models\Equipo;
use App\Models\Flota;
use App\Models\Previaje;
use App\Models\TipoEquipo;
use App\Models\User;
use App\Notifications\PreviajeConHallazgos;
use App\Notifications\PreviajeEditado;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Reglas de negocio del previaje: RN-02, RN-04, RN-06, RN-10, RN-11 y RF-13.
 */
beforeEach(function () {
    Notification::fake();
    Storage::fake(config('previajes.fotos.disco'));

    $this->flota = Flota::factory()->create(['nombre' => 'Honduras']);
    $this->tipo = TipoEquipo::factory()->create(['nombre' => 'Cabezal']);
    $this->equipo = Equipo::factory()->create([
        'flota_id' => $this->flota->id,
        'tipo_equipo_id' => $this->tipo->id,
        'codigo' => 'C-101',
    ]);

    // Una sección con un ítem de fluido y uno normal, más sus dos opciones.
    $this->seccion = ChecklistSeccion::factory()->create(['nombre' => 'MOTOR']);
    $this->tipo->secciones()->attach($this->seccion);

    $this->itemFluido = ChecklistItem::factory()->fluido()->create([
        'seccion_id' => $this->seccion->id,
        'descripcion' => 'Nivel aceite motor',
    ]);
    $this->itemNormal = ChecklistItem::factory()->create([
        'seccion_id' => $this->seccion->id,
        'descripcion' => 'Limpieza de filtros de aire',
    ]);

    $this->optima = ChecklistOpcion::factory()->create([
        'seccion_id' => $this->seccion->id,
        'etiqueta' => 'Nivel Óptimo',
    ]);
    $this->hallazgo = ChecklistOpcion::factory()->noOptima()->create([
        'seccion_id' => $this->seccion->id,
        'etiqueta' => 'Nivel bajo',
    ]);

    $this->mecanico = User::factory()->create([
        'rol' => RolUsuario::Mecanico,
        'flota_id' => $this->flota->id,
    ]);
});

/**
 * Arma un payload válido y deja sobreescribir sólo lo que cada prueba necesita.
 *
 * @param  array<string, mixed>  $sobreescribir
 * @return array<string, mixed>
 */
function payloadPreviaje(object $ctx, array $sobreescribir = []): array
{
    return array_replace_recursive([
        'equipo_id' => $ctx->equipo->id,
        'kilometraje' => 120000,
        'horometro' => 4300,
        'observaciones_seccion' => [$ctx->seccion->id => 'Sin novedad en motor.'],
        'respuestas' => [
            $ctx->itemFluido->id => ['checklist_opcion_id' => $ctx->optima->id],
            $ctx->itemNormal->id => ['checklist_opcion_id' => $ctx->optima->id],
        ],
    ], $sobreescribir);
}

it('registra un previaje sin hallazgos y le pone la fecha del servidor', function () {
    $this->travelTo(now()->setSeconds(0));

    $respuesta = $this->actingAs($this->mecanico)
        ->post(route('previajes.store'), payloadPreviaje($this));

    $respuesta->assertRedirect();

    $previaje = Previaje::sole();

    expect($previaje->estatus)->toBe(EstatusPreviaje::SinHallazgos)
        ->and($previaje->mecanico_id)->toBe($this->mecanico->id)
        // RN-01: la flota sale del equipo, no de la petición.
        ->and($previaje->flota_id)->toBe($this->flota->id)
        // RF-10: la fecha la asigna el sistema, no el usuario.
        ->and($previaje->created_at->timestamp)->toBe(now()->timestamp)
        ->and($previaje->respuestas)->toHaveCount(2);

    Notification::assertNothingSent();
});

it('marca el previaje con hallazgos y notifica al supervisor y al administrador', function () {
    $supervisor = User::factory()->create([
        'rol' => RolUsuario::Supervisor,
        'flota_id' => $this->flota->id,
    ]);
    $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
    // Un supervisor de otra flota no debe enterarse.
    $ajeno = User::factory()->create([
        'rol' => RolUsuario::Supervisor,
        'flota_id' => Flota::factory()->create()->id,
    ]);

    $this->actingAs($this->mecanico)->post(route('previajes.store'), payloadPreviaje($this, [
        'respuestas' => [
            $this->itemFluido->id => [
                'checklist_opcion_id' => $this->hallazgo->id,
                'cantidad_agregada' => 2.5,
                'observaciones' => 'Fuga visible en cárter.',
            ],
        ],
        'fotos' => [$this->itemFluido->id => [UploadedFile::fake()->image('hallazgo.jpg')]],
    ]))->assertRedirect();

    expect(Previaje::sole()->estatus)->toBe(EstatusPreviaje::ConHallazgos);

    Notification::assertSentTo([$supervisor, $administrador], PreviajeConHallazgos::class);
    Notification::assertNotSentTo($ajeno, PreviajeConHallazgos::class);
});

it('exige galones, observación y foto cuando el ítem de fluido tiene hallazgo', function () {
    $this->actingAs($this->mecanico)
        ->post(route('previajes.store'), payloadPreviaje($this, [
            'respuestas' => [
                $this->itemFluido->id => ['checklist_opcion_id' => $this->hallazgo->id],
            ],
        ]))
        ->assertSessionHasErrors([
            "respuestas.{$this->itemFluido->id}.cantidad_agregada", // RN-06
            "respuestas.{$this->itemFluido->id}.observaciones",     // RN-10
            "fotos.{$this->itemFluido->id}",                        // RN-11
        ]);

    expect(Previaje::count())->toBe(0);
});

it('no exige galones ni foto cuando la respuesta es óptima', function () {
    $this->actingAs($this->mecanico)
        ->post(route('previajes.store'), payloadPreviaje($this))
        ->assertSessionHasNoErrors();
});

it('exige observación y foto en un ítem que no es fluido pero sí tiene hallazgo', function () {
    $this->actingAs($this->mecanico)
        ->post(route('previajes.store'), payloadPreviaje($this, [
            'respuestas' => [
                $this->itemNormal->id => ['checklist_opcion_id' => $this->hallazgo->id],
            ],
        ]))
        ->assertSessionHasErrors([
            "respuestas.{$this->itemNormal->id}.observaciones",
            "fotos.{$this->itemNormal->id}",
        ])
        // El ítem no es fluido, así que los galones no aplican (RF-08).
        ->assertSessionDoesntHaveErrors("respuestas.{$this->itemNormal->id}.cantidad_agregada");
});

it('rechaza el formulario si queda un ítem activo sin responder', function () {
    $payload = payloadPreviaje($this);
    unset($payload['respuestas'][$this->itemNormal->id]);

    $this->actingAs($this->mecanico)
        ->post(route('previajes.store'), $payload)
        ->assertSessionHasErrors("respuestas.{$this->itemNormal->id}.checklist_opcion_id");
});

it('exige la observación general de cada sección', function () {
    $this->actingAs($this->mecanico)
        ->post(route('previajes.store'), payloadPreviaje($this, [
            'observaciones_seccion' => [$this->seccion->id => ''],
        ]))
        ->assertSessionHasErrors("observaciones_seccion.{$this->seccion->id}");
});

it('impide que el kilometraje y el horómetro retrocedan, cada uno por su lado', function () {
    $this->actingAs($this->mecanico)->post(route('previajes.store'), payloadPreviaje($this, [
        'kilometraje' => 120000,
        'horometro' => 4300,
    ]))->assertSessionHasNoErrors();

    // RN-02: el kilometraje baja pero el horómetro sube — sólo falla el primero.
    $this->actingAs($this->mecanico)->post(route('previajes.store'), payloadPreviaje($this, [
        'kilometraje' => 119999,
        'horometro' => 4500,
    ]))
        ->assertSessionHasErrors('kilometraje')
        ->assertSessionDoesntHaveErrors('horometro');

    // Y al revés.
    $this->actingAs($this->mecanico)->post(route('previajes.store'), payloadPreviaje($this, [
        'kilometraje' => 130000,
        'horometro' => 4299,
    ]))
        ->assertSessionHasErrors('horometro')
        ->assertSessionDoesntHaveErrors('kilometraje');

    // Igualar el último valor sí se permite ("mayor o igual").
    $this->actingAs($this->mecanico)->post(route('previajes.store'), payloadPreviaje($this, [
        'kilometraje' => 120000,
        'horometro' => 4300,
    ]))->assertSessionHasNoErrors();
});

it('rechaza respuestas de ítems que no pertenecen al checklist del equipo', function () {
    $itemAjeno = ChecklistItem::factory()->create();

    $this->actingAs($this->mecanico)
        ->post(route('previajes.store'), payloadPreviaje($this, [
            'respuestas' => [$itemAjeno->id => ['checklist_opcion_id' => $this->optima->id]],
        ]))
        ->assertSessionHasErrors("respuestas.{$itemAjeno->id}");
});

it('rechaza una opción que no pertenece a la sección del ítem', function () {
    $opcionAjena = ChecklistOpcion::factory()->create();

    $this->actingAs($this->mecanico)
        ->post(route('previajes.store'), payloadPreviaje($this, [
            'respuestas' => [
                $this->itemNormal->id => ['checklist_opcion_id' => $opcionAjena->id],
            ],
        ]))
        ->assertSessionHasErrors("respuestas.{$this->itemNormal->id}.checklist_opcion_id");
});

it('impide registrar un previaje contra un equipo de otra flota', function () {
    $equipoAjeno = Equipo::factory()->create(['tipo_equipo_id' => $this->tipo->id]);

    $this->actingAs($this->mecanico)
        ->post(route('previajes.store'), payloadPreviaje($this, ['equipo_id' => $equipoAjeno->id]))
        ->assertSessionHasErrors('equipo_id');
});

it('conserva la fecha original al editar y avisa de la edición', function () {
    $this->travelTo(now()->subDays(2));

    $this->actingAs($this->mecanico)->post(route('previajes.store'), payloadPreviaje($this));
    $previaje = Previaje::sole();
    $creadoEn = $previaje->created_at;

    $this->travelBack();

    $this->actingAs($this->mecanico)
        ->put(route('previajes.update', $previaje), payloadPreviaje($this, ['kilometraje' => 125000]))
        ->assertRedirect();

    $previaje->refresh();

    // RF-10: la fecha de creación nunca cambia; la edición sólo mueve updated_at.
    expect($previaje->created_at->timestamp)->toBe($creadoEn->timestamp)
        ->and($previaje->kilometraje)->toBe(125000)
        ->and($previaje->updated_at->gt($previaje->created_at))->toBeTrue();

    // RF-12: la edición queda en la bitácora con el valor anterior y el nuevo.
    $auditoria = $previaje->activitiesAsSubject()->where('event', 'updated')->sole();
    expect($auditoria->attribute_changes['old']['kilometraje'])->toBe(120000)
        ->and($auditoria->attribute_changes['attributes']['kilometraje'])->toBe(125000);
});

it('avisa al supervisor cuando se edita un previaje ya enviado', function () {
    $supervisor = User::factory()->create([
        'rol' => RolUsuario::Supervisor,
        'flota_id' => $this->flota->id,
    ]);

    $this->actingAs($this->mecanico)->post(route('previajes.store'), payloadPreviaje($this));
    $previaje = Previaje::sole();

    $this->actingAs($this->mecanico)
        ->put(route('previajes.update', $previaje), payloadPreviaje($this, ['kilometraje' => 121000]));

    Notification::assertSentTo($supervisor, PreviajeEditado::class);
});

it('no permite cambiar el equipo de un previaje ya registrado', function () {
    $this->actingAs($this->mecanico)->post(route('previajes.store'), payloadPreviaje($this));
    $previaje = Previaje::sole();

    $otro = Equipo::factory()->create([
        'flota_id' => $this->flota->id,
        'tipo_equipo_id' => $this->tipo->id,
    ]);

    $this->actingAs($this->mecanico)
        ->put(route('previajes.update', $previaje), payloadPreviaje($this, ['equipo_id' => $otro->id]))
        ->assertSessionHasErrors('equipo_id');
});
