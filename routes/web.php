<?php

use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\PreviajeController;
use App\Http\Controllers\PreviajeFotoController;
use App\Http\Controllers\RegistroLlantaController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/previajes')->name('home');

Route::middleware(['auth'])->group(function () {
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
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
