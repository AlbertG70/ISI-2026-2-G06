@extends('layouts.auth')

@section('titulo', 'Iniciar Sesión')
@section('pagina', 'login')

@section('contenido')
<div class="dz-tarjeta shadow-md">
    <div class="border-b-2 border-dashed border-borde/70 pb-3 mb-5 text-center">
        <h1 class="text-2xl font-bold text-tinta">Iniciar Sesión</h1>
        <p class="text-sm text-tinta-suave mt-1">Ingrese sus credenciales para consultar y verificar noticias</p>
    </div>

    {{-- Alerta general de error --}}
    <div id="login-alerta" 
         class="dz-alerta mb-4 border-falso bg-falso-bg text-falso font-medium" 
         role="alert" 
         hidden></div>

    <form id="form-login" novalidate class="space-y-4">
        <div>
            <label for="email" class="dz-label">Correo electrónico</label>
            <input type="email" 
                   id="email" 
                   name="email" 
                   required
                   autocomplete="email"
                   placeholder="ejemplo@correo.com" 
                   class="dz-campo">
            <p data-error-de="email" class="dz-error" role="alert" hidden></p>
        </div>

        <div>
            <div class="flex items-center justify-between mb-1">
                <label for="password" class="dz-label !mb-0">Contraseña</label>
                <button type="button" 
                        id="ver-password"
                        aria-pressed="false"
                        class="text-sm font-semibold text-blue-700 hover:underline min-h-8">
                    Mostrar
                </button>
            </div>
            <input type="password" 
                   id="password" 
                   name="password" 
                   required
                   autocomplete="current-password"
                   placeholder="Su contraseña segura" 
                   class="dz-campo">
            <p data-error-de="password" class="dz-error" role="alert" hidden></p>
        </div>

        <div class="flex items-center gap-2 pt-1">
            <input type="checkbox" 
                   id="recordar" 
                   name="recordar" 
                   class="h-5 w-5 rounded border-2 border-borde text-marca focus:ring-0">
            <label for="recordar" class="text-sm font-medium text-tinta cursor-pointer select-none">
                Mantener sesión iniciada en este dispositivo
            </label>
        </div>

        <div class="pt-2">
            <button type="submit" 
                    id="login-enviar" 
                    class="dz-btn-primario w-full text-lg font-bold shadow">
                Ingresar a DAGAZ
            </button>
        </div>
    </form>

    <div class="mt-6 border-t border-borde/50 pt-4 text-center">
        <p class="text-sm text-tinta-suave">
            ¿Aún no tiene una cuenta?
            <a href="{{ route('registro') }}" class="font-bold text-marca underline hover:text-black">
                Registrarse aquí
            </a>
        </p>
    </div>
</div>
@endsection
