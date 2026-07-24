<?php

use App\Enums\RolUsuario;
use App\Models\ChecklistItem;
use App\Models\ChecklistOpcion;
use App\Models\ChecklistSeccion;
use App\Models\Configuracion;
use App\Models\Equipo;
use App\Models\Flota;
use App\Models\Previaje;
use App\Models\PreviajeFoto;
use App\Models\TipoEquipo;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** RF-11: evidencia fotográfica, compresión y control de acceso al archivo. */
beforeEach(function () {
    Storage::fake(config('previajes.fotos.disco'));

    $this->flota = Flota::factory()->create();
    $this->tipo = TipoEquipo::factory()->create();
    $this->equipo = Equipo::factory()->create([
        'flota_id' => $this->flota->id,
        'tipo_equipo_id' => $this->tipo->id,
    ]);

    $this->seccion = ChecklistSeccion::factory()->create();
    $this->tipo->secciones()->attach($this->seccion);
    $this->item = ChecklistItem::factory()->create(['seccion_id' => $this->seccion->id]);
    $this->hallazgo = ChecklistOpcion::factory()->noOptima()->create(['seccion_id' => $this->seccion->id]);

    $this->mecanico = User::factory()->create([
        'rol' => RolUsuario::Mecanico,
        'flota_id' => $this->flota->id,
    ]);
});

/** @param  array<int, UploadedFile>  $fotos */
function registrarConFotos(object $ctx, array $fotos): void
{
    $ctx->actingAs($ctx->mecanico)->post(route('previajes.store'), [
        'equipo_id' => $ctx->equipo->id,
        'kilometraje' => 1000,
        'horometro' => 100,
        'observaciones_seccion' => [$ctx->seccion->id => 'Observación de sección.'],
        'respuestas' => [
            $ctx->item->id => [
                'checklist_opcion_id' => $ctx->hallazgo->id,
                'observaciones' => 'Hallazgo detectado.',
            ],
        ],
        'fotos' => [$ctx->item->id => $fotos],
    ])->assertSessionHasNoErrors();
}

it('acepta varias fotos por ítem y las asocia al hallazgo', function () {
    registrarConFotos($this, [
        UploadedFile::fake()->image('a.jpg', 2000, 1500),
        UploadedFile::fake()->image('b.jpg', 2000, 1500),
    ]);

    $previaje = Previaje::sole();

    expect($previaje->fotos)->toHaveCount(2)
        ->and($previaje->fotos->pluck('checklist_item_id')->unique()->all())->toBe([$this->item->id]);
});

it('comprime y redimensiona la foto por debajo del límite configurado', function () {
    Configuracion::updateOrCreate(
        ['clave' => Configuracion::ANCHO_MAXIMO_FOTO_PX],
        ['valor' => '800'],
    );
    Configuracion::updateOrCreate(
        ['clave' => Configuracion::TAMANO_MAXIMO_FOTO_KB],
        ['valor' => '150'],
    );

    registrarConFotos($this, [UploadedFile::fake()->image('grande.jpg', 4000, 3000)]);

    $foto = PreviajeFoto::sole();

    // La cola corre en modo `sync` durante las pruebas, así que al llegar aquí
    // el job ya se ejecutó.
    expect($foto->procesada)->toBeTrue()
        ->and($foto->tamano_kb)->toBeLessThanOrEqual(150)
        ->and($foto->ruta_archivo)->toEndWith('.jpg');

    $disco = Storage::disk(config('previajes.fotos.disco'));
    expect($disco->exists($foto->ruta_archivo))->toBeTrue();

    // El lado mayor no debe exceder el máximo configurado.
    [$ancho, $alto] = getimagesizefromstring($disco->get($foto->ruta_archivo));
    expect(max($ancho, $alto))->toBeLessThanOrEqual(800);
});

it('rechaza un archivo que no es imagen aunque tenga extensión de imagen', function () {
    // `UploadedFile::fake()` devuelve un MIME inventado, así que no sirve para
    // probar detección de contenido. Se usa un archivo real en disco para que
    // la validación tenga que mirarlo de verdad (§7, integridad de datos).
    $ruta = tempnam(sys_get_temp_dir(), 'falsa').'.jpg';
    file_put_contents($ruta, 'esto no es una imagen, es texto plano');

    $archivo = new UploadedFile($ruta, 'falsa.jpg', null, null, true);

    $this->actingAs($this->mecanico)->post(route('previajes.store'), [
        'equipo_id' => $this->equipo->id,
        'kilometraje' => 1000,
        'horometro' => 100,
        'observaciones_seccion' => [$this->seccion->id => 'Observación.'],
        'respuestas' => [
            $this->item->id => [
                'checklist_opcion_id' => $this->hallazgo->id,
                'observaciones' => 'Hallazgo.',
            ],
        ],
        'fotos' => [$this->item->id => [$archivo]],
    ])->assertSessionHasErrors("fotos.{$this->item->id}.0");

    expect(Previaje::count())->toBe(0);

    @unlink($ruta);
});

it('sirve la foto sólo a quien puede ver el previaje', function () {
    registrarConFotos($this, [UploadedFile::fake()->image('evidencia.jpg')]);

    $foto = PreviajeFoto::sole();

    // El mecánico que la subió sí.
    $this->actingAs($this->mecanico)->get(route('previaje-fotos.show', $foto))->assertOk();

    // Un mecánico de otra flota no.
    $ajeno = User::factory()->create([
        'rol' => RolUsuario::Mecanico,
        'flota_id' => Flota::factory()->create()->id,
    ]);
    $this->actingAs($ajeno)->get(route('previaje-fotos.show', $foto))->assertForbidden();

    // Y sin sesión, tampoco.
    auth()->logout();
    $this->get(route('previaje-fotos.show', $foto))->assertRedirect(route('login'));
});

it('borra el archivo del disco al eliminar la foto en una edición', function () {
    registrarConFotos($this, [
        UploadedFile::fake()->image('a.jpg'),
        UploadedFile::fake()->image('b.jpg'),
    ]);

    $previaje = Previaje::sole();
    $aEliminar = $previaje->fotos->first();
    $ruta = $aEliminar->ruta_archivo;

    $this->actingAs($this->mecanico)->put(route('previajes.update', $previaje), [
        'equipo_id' => $this->equipo->id,
        'kilometraje' => 1000,
        'horometro' => 100,
        'observaciones_seccion' => [$this->seccion->id => 'Observación de sección.'],
        'respuestas' => [
            $this->item->id => [
                'checklist_opcion_id' => $this->hallazgo->id,
                'observaciones' => 'Hallazgo detectado.',
            ],
        ],
        'fotos_eliminadas' => [$aEliminar->id],
    ])->assertSessionHasNoErrors();

    Storage::disk(config('previajes.fotos.disco'))->assertMissing($ruta);
    expect($previaje->fresh()->fotos)->toHaveCount(1);
});

it('impide quedarse sin evidencia al eliminar la última foto de un hallazgo', function () {
    registrarConFotos($this, [UploadedFile::fake()->image('unica.jpg')]);

    $previaje = Previaje::sole();
    $foto = $previaje->fotos->sole();

    // RN-11: el hallazgo exige mínimo una foto, así que borrar la única debe fallar.
    $this->actingAs($this->mecanico)->put(route('previajes.update', $previaje), [
        'equipo_id' => $this->equipo->id,
        'kilometraje' => 1000,
        'horometro' => 100,
        'observaciones_seccion' => [$this->seccion->id => 'Observación de sección.'],
        'respuestas' => [
            $this->item->id => [
                'checklist_opcion_id' => $this->hallazgo->id,
                'observaciones' => 'Hallazgo detectado.',
            ],
        ],
        'fotos_eliminadas' => [$foto->id],
    ])->assertSessionHasErrors("fotos.{$this->item->id}");

    expect($previaje->fresh()->fotos)->toHaveCount(1);
});
