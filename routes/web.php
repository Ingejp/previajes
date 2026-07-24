<?php

use App\Http\Controllers\PreviajeController;
use App\Http\Controllers\PreviajeFotoController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', fn () => to_route('previajes.index'))->name('dashboard');

    // RF-09, RF-12, RF-15
    Route::resource('previajes', PreviajeController::class)->except(['destroy']);
    Route::post('previajes/{previaje}/anular', [PreviajeController::class, 'anular'])
        ->name('previajes.anular');

    // RF-11: la evidencia sale por una ruta autorizada, no por enlace directo.
    Route::get('previaje-fotos/{foto}', [PreviajeFotoController::class, 'show'])
        ->name('previaje-fotos.show');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
