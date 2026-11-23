{{-- Barra de navegación inferior fija: botones táctiles >= 48px, estado activo con aria-current. --}}
@php
    $enlaces = [
        ['ruta' => 'verificar', 'activo' => ['inicio', 'verificar', 'resultado'], 'icono' => '🔍', 'texto' => 'Verificar'],
        ['ruta' => 'historial', 'activo' => ['historial'], 'icono' => '🗂️', 'texto' => 'Historial'],
        ['ruta' => 'fuentes', 'activo' => ['fuentes'], 'icono' => '📰', 'texto' => 'Fuentes'],
        ['ruta' => 'perfil', 'activo' => ['perfil'], 'icono' => '👤', 'texto' => 'Mi cuenta'],
    ];
@endphp
<nav aria-label="Navegación principal"
     class="fixed inset-x-0 bottom-0 z-40 border-t-2 border-tinta/80 bg-white"
     style="padding-bottom: env(safe-area-inset-bottom);">
    <ul class="mx-auto grid max-w-2xl grid-cols-4">
        @foreach ($enlaces as $enlace)
            @php $esActivo = request()->routeIs(...$enlace['activo']); @endphp
            <li>
                <a href="{{ route($enlace['ruta']) }}"
                   @if ($esActivo) aria-current="page" @endif
                   class="flex min-h-16 flex-col items-center justify-center gap-0.5 px-1 text-sm font-medium
                          {{ $esActivo ? 'bg-neutro-bg font-bold text-tinta' : 'text-tinta-suave hover:text-tinta' }}">
                    <span class="text-2xl leading-none" aria-hidden="true">{{ $enlace['icono'] }}</span>
                    <span>{{ $enlace['texto'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>
