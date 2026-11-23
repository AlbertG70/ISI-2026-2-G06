@extends('layouts.app')

@section('titulo', 'Grafo de Credibilidad de Fuentes')
@section('pagina', 'fuentes')

@section('contenido')
<div class="space-y-4">
    <div class="border-b-2 border-dashed border-borde/60 pb-3">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-tinta">Grafo de Credibilidad</h1>
        <p class="text-base text-tinta-suave mt-1">
            Catálogo de más de 80 fuentes periodísticas, regionales, nacionales e internacionales con índices de confiabilidad verificados.
        </p>
        <p id="fuentes-total" class="text-sm font-bold text-marca mt-1">Cargando catálogo de fuentes...</p>
    </div>

    {{-- Filtros por nivel IFCN y buscador --}}
    <div class="dz-tarjeta p-3.5 space-y-3 bg-white">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label for="buscar-fuente" class="dz-label text-sm">Buscar medio o fuente:</label>
                <input type="search" 
                       id="buscar-fuente" 
                       placeholder="Ej: Reuters, El Espectador, ColombiaCheck..." 
                       class="dz-campo text-sm min-h-10 py-2">
            </div>
            <div>
                <label for="filtro-nivel" class="dz-label text-sm">Filtrar por nivel de confianza:</label>
                <select id="filtro-nivel" class="dz-campo text-sm min-h-10 py-2 cursor-pointer">
                    <option value="">Todos los niveles</option>
                    <option value="1">⭐ Nivel 1: Agencias Internacionales (Reuters, AP, BBC)</option>
                    <option value="2">Nivel 2: Prensa Regional Colombia (El Colombiano, El Heraldo)</option>
                    <option value="3">Nivel 3: Prensa Nacional Colombia (El Tiempo, El Espectador)</option>
                    <option value="4">Nivel 4: Fact-Checkers Certificados IFCN (ColombiaCheck)</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Leyenda de confiabilidad --}}
    <div class="rounded-lg border-2 border-borde/50 bg-neutro-bg p-3 text-xs sm:text-sm text-tinta-suave flex flex-wrap items-center gap-x-4 gap-y-1">
        <span class="font-bold text-tinta">Escala de credibilidad:</span>
        <span class="flex items-center gap-1 font-semibold text-verdadero">🟢 &gt;75% Alta</span>
        <span class="flex items-center gap-1 font-semibold text-parcial">🟡 40% - 75% Media</span>
        <span class="flex items-center gap-1 font-semibold text-falso">🔴 &lt;40% Baja</span>
        <span class="flex items-center gap-1 text-tinta font-medium">⭐ Verificada mundialmente</span>
    </div>

    {{-- Listado de tarjetas de fuentes --}}
    <ul id="lista-fuentes" class="space-y-3" aria-live="polite">
        {{-- Skeleton de carga --}}
        <li class="dz-tarjeta flex items-center gap-4">
            <div class="dz-skeleton h-14 w-14 rounded-full"></div>
            <div class="space-y-2 flex-1">
                <div class="dz-skeleton h-5 w-1/2"></div>
                <div class="dz-skeleton h-4 w-1/3"></div>
            </div>
        </li>
    </ul>
</div>
@endsection
