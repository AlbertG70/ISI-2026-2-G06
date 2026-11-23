<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    @include('partials.head')
</head>
<body data-pagina="@yield('pagina')" 
      data-requiere-sesion="false"
      class="flex min-h-full flex-col justify-center py-8 px-4 text-tinta antialiased bg-papel">

    {{-- Región viva para lectores de pantalla y avisos dinámicos --}}
    <div id="dz-avisos" 
         class="fixed right-4 top-4 z-50 flex w-full max-w-sm flex-col gap-2 pointer-events-none px-4"
         aria-live="polite" 
         aria-atomic="true"></div>

    <main class="dz-contenedor w-full max-w-md">
        {{-- Logotipo e identidad visual central --}}
        <div class="mb-6 text-center">
            <a href="{{ url('/') }}" class="inline-flex flex-col items-center gap-2 text-tinta no-underline focus:outline-none">
                <x-logo tam="64" class="text-tinta mx-auto" />
                <span class="block text-3xl font-extrabold tracking-widest leading-none">DAGAZ</span>
                <span class="block text-sm font-semibold text-tinta-suave">Plataforma de Fact-Checking y Verificación</span>
            </a>
        </div>

        @yield('contenido')
    </main>

</body>
</html>
