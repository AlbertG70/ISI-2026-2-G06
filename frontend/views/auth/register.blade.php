@extends('layouts.auth')

@section('titulo', 'Crear Cuenta')
@section('pagina', 'registro')

@section('contenido')
<div class="dz-tarjeta shadow-md">
    <div class="border-b-2 border-dashed border-borde/70 pb-3 mb-5 text-center">
        <h1 class="text-2xl font-bold text-tinta">Crear Cuenta</h1>
        <p class="text-sm text-tinta-suave mt-1">Únase a la red comunitaria de verificación contra la desinformación</p>
    </div>

    {{-- Alerta general de error --}}
    <div id="registro-alerta" 
         class="dz-alerta mb-4 border-falso bg-falso-bg text-falso font-medium" 
         role="alert" 
         hidden></div>

    <form id="form-registro" novalidate class="space-y-4">
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
            <label for="password" class="dz-label">Contraseña (mínimo 8 caracteres)</label>
            <input type="password" 
                   id="password" 
                   name="password" 
                   required
                   autocomplete="new-password"
                   placeholder="Contraseña segura" 
                   class="dz-campo">
            <p data-error-de="password" class="dz-error" role="alert" hidden></p>
        </div>

        <div>
            <label for="password_confirmation" class="dz-label">Confirmar contraseña</label>
            <input type="password" 
                   id="password_confirmation" 
                   name="password_confirmation" 
                   required
                   autocomplete="new-password"
                   placeholder="Repita su contraseña" 
                   class="dz-campo">
            <p data-error-de="password_confirmation" class="dz-error" role="alert" hidden></p>
        </div>

        {{-- Consentimiento obligatorio bajo la Ley 1581 de 2012 (Hábeas Data) --}}
        <div class="rounded-lg border-2 border-borde/60 bg-neutro-bg p-3.5 mt-2">
            <div class="flex items-start gap-2.5">
                <input type="checkbox" 
                       id="acepta_datos" 
                       name="acepta_datos" 
                       required
                       class="h-5 w-5 mt-0.5 rounded border-2 border-borde text-marca focus:ring-0">
                <label for="acepta_datos" class="text-xs sm:text-sm leading-relaxed text-tinta select-none cursor-pointer">
                    Autorizo de manera previa, expresa e informada el tratamiento de mis datos personales conforme a la 
                    <strong>Ley 1581 de 2012</strong> (Hábeas Data - Colombia) y las políticas de privacidad de DAGAZ para fines de autenticación y auditoría.
                </label>
            </div>
            <p data-error-de="acepta_datos" class="dz-error" role="alert" hidden></p>
        </div>

        <div class="pt-2">
            <button type="submit" 
                    id="registro-enviar" 
                    class="dz-btn-primario w-full text-lg font-bold shadow">
                Crear mi cuenta
            </button>
        </div>
    </form>

    <div class="mt-6 border-t border-borde/50 pt-4 text-center">
        <p class="text-sm text-tinta-suave">
            ¿Ya tiene una cuenta registrada?
            <a href="{{ route('login') }}" class="font-bold text-marca underline hover:text-black">
                Iniciar sesión aquí
            </a>
        </p>
    </div>
</div>
@endsection
