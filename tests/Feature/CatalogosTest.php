<?php

use App\Enums\RolUsuario;
use App\Models\ChecklistItem;
use App\Models\ChecklistOpcion;
use App\Models\ChecklistSeccion;
use App\Models\Configuracion;
use App\Models\Equipo;
use App\Models\Flota;
use App\Models\Previaje;
use App\Models\TipoEquipo;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * RF-18: CRUD de catálogos. Cubre sobre todo autorización — quién puede
 * entrar a cada pantalla y modificar cada recurso — y las reglas de negocio
 * que impiden perder historial (RN-03, RN-09).
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['rol' => RolUsuario::Administrador]);
    $this->superAdmin = User::factory()->create(['rol' => RolUsuario::SuperAdministrador]);
    $this->supervisor = User::factory()->create(['rol' => RolUsuario::Supervisor]);
    $this->mecanico = User::factory()->create(['rol' => RolUsuario::Mecanico]);
});

it('deja entrar a la administración de catálogos desde supervisor, pero nunca al mecánico', function () {
    $rutas = [
        route('catalogos.index'),
        route('catalogos.flotas.index'),
        route('catalogos.tipos-equipo.index'),
        route('catalogos.equipos.index'),
        route('catalogos.secciones.index'),
        route('catalogos.configuraciones.index'),
    ];

    foreach ($rutas as $ruta) {
        $this->actingAs($this->mecanico)->get($ruta)->assertForbidden();
        $this->actingAs($this->supervisor)->get($ruta)->assertOk();
        $this->actingAs($this->admin)->get($ruta)->assertOk();
        $this->actingAs($this->superAdmin)->get($ruta)->assertOk();
    }
});

/** La gestión de usuarios es la excepción dentro de catálogos: sigue siendo sólo administrador. */
it('reserva la gestión de usuarios a administrador, ni siquiera el supervisor entra', function () {
    $this->actingAs($this->mecanico)->get(route('catalogos.usuarios.index'))->assertForbidden();
    $this->actingAs($this->supervisor)->get(route('catalogos.usuarios.index'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('catalogos.usuarios.index'))->assertOk();
    $this->actingAs($this->superAdmin)->get(route('catalogos.usuarios.index'))->assertOk();
});

/** La tarjeta de "Usuarios" no debe ofrecerse a quien no puede entrar ahí. */
it('oculta la tarjeta de usuarios en el landing de catálogos para el supervisor', function () {
    $this->actingAs($this->supervisor)
        ->get(route('catalogos.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('puedeGestionarUsuarios', false));

    $this->actingAs($this->admin)
        ->get(route('catalogos.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('puedeGestionarUsuarios', true));
});

// --- Flotas ---

it('crea, edita y elimina una flota', function () {
    $this->actingAs($this->admin)
        ->post(route('catalogos.flotas.store'), ['nombre' => 'Panamá', 'pais' => 'Panamá', 'activo' => true])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $flota = Flota::where('nombre', 'Panamá')->sole();

    $this->actingAs($this->admin)
        ->put(route('catalogos.flotas.update', $flota), ['nombre' => 'Panamá', 'pais' => 'Panamá', 'activo' => false])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($flota->fresh()->activo)->toBeFalse();

    $this->actingAs($this->admin)->delete(route('catalogos.flotas.destroy', $flota))->assertRedirect();
    expect(Flota::find($flota->id))->toBeNull();
});

it('no permite eliminar una flota que tiene equipos', function () {
    $flota = Flota::factory()->create();
    Equipo::factory()->create(['flota_id' => $flota->id]);

    $this->actingAs($this->admin)->delete(route('catalogos.flotas.destroy', $flota))->assertRedirect();

    expect(Flota::find($flota->id))->not->toBeNull();
});

it('un mecánico no puede crear flotas aunque adivine la ruta', function () {
    $this->actingAs($this->mecanico)
        ->post(route('catalogos.flotas.store'), ['nombre' => 'Intrusa', 'pais' => 'X'])
        ->assertForbidden();

    expect(Flota::where('nombre', 'Intrusa')->exists())->toBeFalse();
});

it('un supervisor sí puede crear flotas', function () {
    $this->actingAs($this->supervisor)
        ->post(route('catalogos.flotas.store'), ['nombre' => 'Panamá', 'pais' => 'Panamá'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Flota::where('nombre', 'Panamá')->exists())->toBeTrue();
});

// --- Tipos de equipo ---

it('crea un tipo de equipo y sincroniza sus secciones (RN-07)', function () {
    $motor = ChecklistSeccion::factory()->create(['nombre' => 'MOTOR']);
    $chasis = ChecklistSeccion::factory()->create(['nombre' => 'CHASIS']);

    $this->actingAs($this->admin)->post(route('catalogos.tipos-equipo.store'), [
        'nombre' => 'Excavadora',
        'dias_alerta_sin_previaje' => 3,
        'activo' => true,
        'secciones' => [$motor->id],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $tipo = TipoEquipo::where('nombre', 'Excavadora')->sole();
    expect($tipo->secciones->pluck('id')->all())->toBe([$motor->id]);

    // Editar cambia la asociación en vez de acumularla.
    $this->actingAs($this->admin)->put(route('catalogos.tipos-equipo.update', $tipo), [
        'nombre' => 'Excavadora',
        'dias_alerta_sin_previaje' => 3,
        'activo' => true,
        'secciones' => [$chasis->id],
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect($tipo->fresh()->secciones->pluck('id')->all())->toBe([$chasis->id]);
});

it('no permite eliminar un tipo de equipo que tiene equipos dados de alta', function () {
    $tipo = TipoEquipo::factory()->create();
    Equipo::factory()->create(['tipo_equipo_id' => $tipo->id]);

    $this->actingAs($this->admin)->delete(route('catalogos.tipos-equipo.destroy', $tipo))->assertRedirect();

    expect(TipoEquipo::find($tipo->id))->not->toBeNull();
});

// --- Equipos ---

it('crea un equipo y valida que el código sea único dentro de su flota, no globalmente', function () {
    $flotaA = Flota::factory()->create();
    $flotaB = Flota::factory()->create();
    $tipo = TipoEquipo::factory()->create();

    $datos = fn ($flota) => ['codigo' => 'C-1', 'tipo_equipo_id' => $tipo->id, 'flota_id' => $flota->id, 'activo' => true];

    $this->actingAs($this->admin)->post(route('catalogos.equipos.store'), $datos($flotaA))->assertSessionHasNoErrors()->assertRedirect();

    // Mismo código, otra flota: permitido.
    $this->actingAs($this->admin)->post(route('catalogos.equipos.store'), $datos($flotaB))->assertSessionHasNoErrors();

    // Mismo código, misma flota: rechazado.
    $this->actingAs($this->admin)->post(route('catalogos.equipos.store'), $datos($flotaA))->assertSessionHasErrors('codigo');

    expect(Equipo::where('codigo', 'C-1')->count())->toBe(2);
});

it('no permite eliminar un equipo con previajes registrados', function () {
    $equipo = Equipo::factory()->create();
    Previaje::create([
        'equipo_id' => $equipo->id,
        'mecanico_id' => $this->mecanico->id,
        'flota_id' => $equipo->flota_id,
        'kilometraje' => 1,
        'horometro' => 1,
        'estatus' => 'sin_hallazgos',
        'created_by' => $this->mecanico->id,
    ]);

    $this->actingAs($this->admin)->delete(route('catalogos.equipos.destroy', $equipo))->assertRedirect();

    expect(Equipo::find($equipo->id))->not->toBeNull();
});

// --- Secciones, ítems y opciones ---

it('crea una sección con su asociación a tipos de equipo, y sus ítems y opciones anidados', function () {
    $tipo = TipoEquipo::factory()->create();

    $this->actingAs($this->admin)->post(route('catalogos.secciones.store'), [
        'nombre' => 'HIDRÁULICO',
        'orden' => 4,
        'activo' => true,
        'tipos_equipo' => [$tipo->id],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $seccion = ChecklistSeccion::where('nombre', 'HIDRÁULICO')->sole();
    expect($seccion->tiposEquipo->pluck('id')->all())->toBe([$tipo->id]);

    $this->actingAs($this->admin)->post(route('catalogos.secciones.items.store', $seccion), [
        'descripcion' => 'Nivel de aceite hidráulico',
        'es_fluido' => true,
        'orden' => 1,
        'activo' => true,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $item = $seccion->items()->sole();
    expect($item->es_fluido)->toBeTrue();

    $this->actingAs($this->admin)->post(route('catalogos.secciones.opciones.store', $seccion), [
        'etiqueta' => 'Nivel bajo',
        'es_optima' => false,
        'orden' => 1,
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect($seccion->opciones()->sole()->es_optima)->toBeFalse();
});

it('impide eliminar un ítem que ya tiene respuestas en un previaje', function () {
    $seccion = ChecklistSeccion::factory()->create();
    $item = ChecklistItem::factory()->create(['seccion_id' => $seccion->id]);
    $opcion = ChecklistOpcion::factory()->create(['seccion_id' => $seccion->id]);
    $equipo = Equipo::factory()->create();

    $previaje = Previaje::create([
        'equipo_id' => $equipo->id,
        'mecanico_id' => $this->mecanico->id,
        'flota_id' => $equipo->flota_id,
        'kilometraje' => 1,
        'horometro' => 1,
        'estatus' => 'sin_hallazgos',
        'created_by' => $this->mecanico->id,
    ]);
    $previaje->respuestas()->create(['checklist_item_id' => $item->id, 'checklist_opcion_id' => $opcion->id]);

    $this->actingAs($this->admin)
        ->delete(route('catalogos.secciones.items.destroy', [$seccion, $item]))
        ->assertRedirect();

    expect(ChecklistItem::find($item->id))->not->toBeNull();
});

it('no dejar administrar un ítem que pertenece a otra sección (parámetro cruzado)', function () {
    $seccionA = ChecklistSeccion::factory()->create();
    $seccionB = ChecklistSeccion::factory()->create();
    $item = ChecklistItem::factory()->create(['seccion_id' => $seccionA->id]);

    $this->actingAs($this->admin)
        ->put(route('catalogos.secciones.items.update', [$seccionB, $item]), ['descripcion' => 'Hackeado', 'orden' => 1])
        ->assertNotFound();
});

// --- Usuarios ---

it('crea un usuario con contraseña obligatoria y permite editarlo sin cambiarla', function () {
    $flota = Flota::factory()->create();

    $this->actingAs($this->admin)->post(route('catalogos.usuarios.store'), [
        'name' => 'Nuevo Mecánico',
        'email' => 'nuevo@previajes.test',
        'password' => 'clave-larga-segura',
        'rol' => 'mecanico',
        'flota_id' => $flota->id,
        'activo' => true,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $usuario = User::where('email', 'nuevo@previajes.test')->sole();
    $hashOriginal = $usuario->password;

    $this->actingAs($this->admin)->put(route('catalogos.usuarios.update', $usuario), [
        'name' => 'Nuevo Mecánico',
        'email' => 'nuevo@previajes.test',
        'password' => '',
        'rol' => 'mecanico',
        'flota_id' => $flota->id,
        'activo' => true,
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect($usuario->fresh()->password)->toBe($hashOriginal);
});

it('desactivar un usuario le cierra la sesión de inmediato', function () {
    $flota = Flota::factory()->create();
    $usuario = User::factory()->create(['rol' => RolUsuario::Mecanico, 'flota_id' => $flota->id, 'activo' => true]);

    $this->actingAs($usuario)->get(route('previajes.index'))->assertOk();

    $this->actingAs($this->admin)->put(route('catalogos.usuarios.update', $usuario), [
        'name' => $usuario->name,
        'email' => $usuario->email,
        'password' => '',
        'rol' => 'mecanico',
        'flota_id' => $flota->id,
        'activo' => false,
    ])->assertSessionHasNoErrors()->assertRedirect();

    // `actingAs` fija el objeto en memoria en el guard de autenticación; en
    // una petición real, cada request resuelve al usuario de nuevo desde la
    // base de datos, así que se simula exactamente eso con `fresh()` en vez
    // de reutilizar la instancia ya obsoleta (todavía con `activo = true`).
    $this->actingAs($usuario->fresh())->get(route('previajes.index'))->assertRedirect(route('login'));
});

it('un administrador no puede crear ni ver usuarios con rol super administrador', function () {
    $this->actingAs($this->admin)->post(route('catalogos.usuarios.store'), [
        'name' => 'Impostor',
        'email' => 'impostor@previajes.test',
        'password' => 'clave-larga-segura',
        'rol' => 'super_administrador',
    ])->assertSessionHasErrors('rol');

    expect(User::where('email', 'impostor@previajes.test')->exists())->toBeFalse();

    // Tampoco puede editar a un super administrador existente.
    $this->actingAs($this->admin)
        ->put(route('catalogos.usuarios.update', $this->superAdmin), [
            'name' => 'Cambiado',
            'email' => $this->superAdmin->email,
            'rol' => 'super_administrador',
        ])
        ->assertForbidden();

    // Ni siquiera aparece en el listado (RN-09).
    $this->actingAs($this->admin)
        ->get(route('catalogos.usuarios.index'))
        ->assertInertia(function (AssertableInertia $page) {
            $ids = collect($page->toArray()['props']['usuarios'])->pluck('id');

            expect($ids)->not->toContain($this->superAdmin->id);
        });
});

it('el super administrador sí puede gestionar otro super administrador', function () {
    $otroSuperAdmin = User::factory()->create(['rol' => RolUsuario::SuperAdministrador]);

    $this->actingAs($this->superAdmin)
        ->put(route('catalogos.usuarios.update', $otroSuperAdmin), [
            'name' => 'Renombrado',
            'email' => $otroSuperAdmin->email,
            'rol' => 'super_administrador',
        ])
        ->assertSessionHasNoErrors();

    expect($otroSuperAdmin->fresh()->name)->toBe('Renombrado');
});

// --- Configuraciones ---

it('crea, edita sin poder cambiar la clave, y elimina una configuración', function () {
    $this->actingAs($this->admin)->post(route('catalogos.configuraciones.store'), [
        'clave' => 'ejemplo_parametro',
        'valor' => '100',
        'descripcion' => 'De prueba',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $config = Configuracion::where('clave', 'ejemplo_parametro')->sole();

    $this->actingAs($this->admin)->put(route('catalogos.configuraciones.update', $config), [
        'clave' => 'otra_clave_distinta',
        'valor' => '200',
        'descripcion' => 'Actualizada',
    ])->assertSessionHasNoErrors()->assertRedirect();

    // La clave se ignora en la edición; sólo cambian valor y descripción.
    expect($config->fresh())
        ->clave->toBe('ejemplo_parametro')
        ->valor->toBe('200');

    $this->actingAs($this->admin)->delete(route('catalogos.configuraciones.destroy', $config))->assertRedirect();
    expect(Configuracion::find($config->id))->toBeNull();
});
