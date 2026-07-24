<?php

use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\Catalogos\CatalogoController;
use App\Http\Controllers\Catalogos\ChecklistItemController;
use App\Http\Controllers\Catalogos\ChecklistOpcionController;
use App\Http\Controllers\Catalogos\ChecklistSeccionController;
use App\Http\Controllers\Catalogos\ConfiguracionController;
use App\Http\Controllers\Catalogos\EquipoController as CatalogoEquipoController;
use App\Http\Controllers\Catalogos\FlotaController;
use App\Http\Controllers\Catalogos\TipoEquipoController;
use App\Http\Controllers\Catalogos\UsuarioController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\PreviajeController;
use App\Http\Controllers\PreviajeFotoController;
use App\Http\Controllers\RegistroLlantaController;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    // El punto de entrada es el dashboard para quien tiene acceso (RF-17);
    // el mecánico, que no lo tiene, va directo a su propio historial.
    Route::get('/', fn () => to_route(Gate::allows('ver-dashboard') ? 'dashboard' : 'previajes.index'))
        ->name('home');

    // RF-09, RF-12, RF-15
    Route::resource('previajes', PreviajeController::class)->except(['destroy']);
    Route::post('previajes/{previaje}/anular', [PreviajeController::class, 'anular'])->name('previajes.anular');
    Route::get('previajes-exportar', [PreviajeController::class, 'exportar'])->name('previajes.exportar');

    // RF-11: la evidencia sale por una ruta autorizada, no por enlace directo.
    Route::get('previaje-fotos/{foto}', [PreviajeFotoController::class, 'show'])->name('previaje-fotos.show');

    // RF-16 / RF-16.1
    Route::get('equipos', [EquipoController::class, 'index'])->name('equipos.index');

    // RF-17.1 (solución interina)
    Route::get('llantas', [RegistroLlantaController::class, 'index'])->name('llantas.index');
    Route::post('llantas', [RegistroLlantaController::class, 'store'])->name('llantas.store');

    // RF-17
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // RF-20
    Route::get('auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');
    Route::get('auditoria/exportar', [AuditoriaController::class, 'exportar'])->name('auditoria.exportar');

    // RF-18: gestión de catálogos, restringida a administrador y super
    // administrador (cada controlador vuelve a exigir el gate 'administrar').
    Route::prefix('catalogos')->name('catalogos.')->group(function () {
        Route::get('/', [CatalogoController::class, 'index'])->name('index');

        // Se fija el nombre del parámetro de cada recurso explícitamente: el
        // inflector de Laravel singulariza en inglés, y con palabras en
        // español (p. ej. "secciones", "configuraciones") el resultado no es
        // confiable para el binding implícito del modelo.
        Route::resource('flotas', FlotaController::class)
            ->except(['show', 'create', 'edit'])
            ->parameters(['flotas' => 'flota']);

        Route::resource('tipos-equipo', TipoEquipoController::class)
            ->except(['show', 'create', 'edit'])
            ->parameters(['tipos-equipo' => 'tipo_equipo']);

        Route::resource('equipos', CatalogoEquipoController::class)
            ->except(['show', 'create', 'edit'])
            ->parameters(['equipos' => 'equipo']);

        Route::resource('usuarios', UsuarioController::class)
            ->only(['index', 'store', 'update'])
            ->parameters(['usuarios' => 'usuario']);

        Route::resource('secciones', ChecklistSeccionController::class)
            ->except(['show', 'create', 'edit'])
            ->parameters(['secciones' => 'seccion']);

        // RF-06, RF-07: ítems y opciones se administran dentro de la página de
        // su sección, así que sólo necesitan store/update/destroy.
        Route::post('secciones/{seccion}/items', [ChecklistItemController::class, 'store'])->name('secciones.items.store');
        Route::put('secciones/{seccion}/items/{item}', [ChecklistItemController::class, 'update'])->name('secciones.items.update');
        Route::delete('secciones/{seccion}/items/{item}', [ChecklistItemController::class, 'destroy'])->name('secciones.items.destroy');

        Route::post('secciones/{seccion}/opciones', [ChecklistOpcionController::class, 'store'])->name('secciones.opciones.store');
        Route::put('secciones/{seccion}/opciones/{opcion}', [ChecklistOpcionController::class, 'update'])->name('secciones.opciones.update');
        Route::delete('secciones/{seccion}/opciones/{opcion}', [ChecklistOpcionController::class, 'destroy'])->name('secciones.opciones.destroy');

        Route::resource('configuraciones', ConfiguracionController::class)
            ->except(['show', 'create', 'edit'])
            ->parameters(['configuraciones' => 'configuracion']);
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
