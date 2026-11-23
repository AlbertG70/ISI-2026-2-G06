@extends('layouts.app')

@section('titulo', 'Historial de Noticias')
@section('pagina', 'historial')

@section('contenido')
<div class="space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b-2 border-dashed border-borde/60 pb-3">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-tinta">Historial de Noticias</h1>
            <p id="historial-total" class="text-sm font-semibold text-tinta-suave mt-0.5">Cargando noticias...</p>
        </div>
        <a href="{{ route('verificar') }}" class="dz-btn-primario text-sm py-2 px-4 shadow-sm self-start sm:self-auto">
            + Nueva verificación
        </a>
    </div>

    {{-- Filtros y búsqueda rápida --}}
    <div class="dz-tarjeta p-3.5 space-y-3 bg-white">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label for="buscar-historial" class="dz-label text-sm">Buscar en mis noticias:</label>
                <input type="search" 
                       id="buscar-historial" 
                       placeholder="Escriba palabras clave..." 
                       class="dz-campo text-sm min-h-10 py-2">
            </div>
            <div>
                <label for="filtro-veredicto" class="dz-label text-sm">Filtrar por estado:</label>
                <select id="filtro-veredicto" class="dz-campo text-sm min-h-10 py-2 cursor-pointer">
                    <option value="todos">Todos los veredictos</option>
                    <option value="guardados">⭐ Solo noticias guardadas</option>
                    <option value="VERDADERO">✅ Verdadero</option>
                    <option value="PARCIAL">⚠️ Parcialmente cierto</option>
                    <option value="ENGANOSO">🟠 Engañoso</option>
                    <option value="FALSO">❌ Falso</option>
                    <option value="NO_VERIFICABLE">❔ No verificable</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Lista de noticias del historial --}}
    <ul id="lista-historial" class="space-y-3" aria-live="polite">
        {{-- Skeleton de carga inicial --}}
        <li class="dz-tarjeta space-y-3">
            <div class="dz-skeleton h-5 w-2/3"></div>
            <div class="dz-skeleton h-4 w-1/3"></div>
            <div class="dz-skeleton h-10 w-32"></div>
        </li>
    </ul>

    {{-- Botón para cargar más páginas --}}
    <div class="text-center pt-2">
        <button type="button" 
                id="btn-mas" 
                class="dz-btn-secundario w-full sm:w-auto px-6 py-2.5 text-base font-semibold" 
                hidden>
            Cargar más noticias
        </button>
    </div>
</div>
@endsection
