<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    @include('partials.head')
</head>
<body data-pagina="@yield('pagina')" 
      data-requiere-sesion="@yield('requiere-sesion', 'true')"
      @hasSection('id-contenido') data-id-contenido="@yield('id-contenido')" @endif
      class="min-h-full pb-28 pt-3 text-tinta antialiased bg-papel">

    {{-- Región viva para lectores de pantalla y avisos dinámicos --}}
    <div id="dz-avisos" 
         class="fixed right-4 top-4 z-50 flex w-full max-w-sm flex-col gap-2 pointer-events-none px-4"
         aria-live="polite" 
         aria-atomic="true"></div>

    {{-- Cabecera superior accesible --}}
    <header class="dz-contenedor mb-4">
        <div class="flex items-center justify-between border-b-2 border-tinta/80 pb-3">
            <a href="{{ route('verificar') }}" class="flex items-center gap-2.5 text-tinta no-underline focus:outline-none">
                <x-logo tam="36" class="text-tinta" />
                <div>
                    <span class="block text-xl font-bold tracking-widest leading-none">DAGAZ</span>
                    <span class="block text-xs font-medium text-tinta-suave">Verificación Ciudadana</span>
                </div>
            </a>
            
            <div class="flex items-center gap-2 sm:gap-3">
                {{-- Selector de tamaño de letra para Adultos +40 (WCAG 2.1 AA) --}}
                <div class="flex items-center rounded-lg border-2 border-borde/70 bg-white p-0.5" role="group" aria-label="Ajustar tamaño de letra">
                    <button type="button" 
                            id="btn-tamano-normal" 
                            class="min-h-8 min-w-8 rounded px-1.5 py-0.5 text-xs font-bold text-tinta hover:bg-neutro-bg"
                            title="Tamaño de letra estándar (18px)"
                            aria-label="Tamaño de letra estándar">
                        A
                    </button>
                    <button type="button" 
                            id="btn-tamano-grande" 
                            class="min-h-8 min-w-8 rounded px-1.5 py-0.5 text-sm font-extrabold text-tinta hover:bg-neutro-bg"
                            title="Tamaño de letra grande (+40 años)"
                            aria-label="Tamaño de letra grande">
                        A+
                    </button>
                </div>
                <span id="dz-usuario" class="hidden text-sm font-medium text-tinta-suave sm:inline-block max-w-[180px] truncate" title="Usuario activo"></span>
                <button type="button" 
                        data-accion="cerrar-sesion"
                        class="dz-btn-secundario min-h-10 py-1 px-3 text-sm"
                        title="Cerrar sesión actual">
                    Salir
                </button>
            </div>
        </div>
    </header>

    {{-- Contenido principal --}}
    <main id="main-content" class="dz-contenedor">
        @yield('contenido')
    </main>

    {{-- Navegación inferior fija --}}
    <x-barra-navegacion />

</body>
</html>
