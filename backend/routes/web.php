<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| DAGAZ — Rutas Web y Vistas Blade (Mobile-First / SSR)
|--------------------------------------------------------------------------
|
| Rutas del Frontend responsivo. Las vistas se comunican asíncronamente
| con los endpoints de /api/v1 mediante tokens Bearer de Laravel Sanctum.
|
*/

// CU06 — Ruta raíz y Verificación Multicanal (Fase 1: Entrada y Procesamiento)
Route::get('/', function () {
    return view('verificacion.index');
})->name('inicio');

Route::get('/verificar', function () {
    return view('verificacion.index');
})->name('verificar');

// CU06 — Resultado de Verificación y Tarjeta de Veredicto (Fase 2)
Route::get('/verificar/{id}', function (string $id) {
    return view('verificacion.resultado', ['id' => $id]);
})->whereUuid('id')->name('resultado');

// CU04 — Historial de Noticias Verificadas y Guardadas
Route::get('/historial', function () {
    return view('historial.index');
})->name('historial');

// CU07 — Catálogo y Grafo de Credibilidad de Fuentes
Route::get('/fuentes', function () {
    return view('fuentes.index');
})->name('fuentes');

// Perfil de Usuario y Derechos Ley 1581 de 2012 (Hábeas Data)
Route::get('/perfil', function () {
    return view('perfil.index');
})->name('perfil');

// CU05 — Inicio de Sesión y Registro de Credenciales
Route::get('/login', function () {
    return view('auth.login');
})->name('login');

Route::get('/registro', function () {
    return view('auth.register');
})->name('registro');

Route::get('/health', function () {
    $dbConectada = false;
    try {
        DB::connection()->getPdo();
        $dbConectada = true;
    } catch (\Throwable) {
        $dbConectada = false;
    }

    return response()->json([
        'estado'     => $dbConectada ? 'operativo' : 'degradado',
        'sistema'    => 'DAGAZ',
        'version'    => (string) config('dagaz.version', '0.0.24'),
        'base_datos' => $dbConectada ? 'conectada' : 'desconectada',
        'timestamp'  => now()->toIso8601String(),
    ], $dbConectada ? 200 : 503);
});
