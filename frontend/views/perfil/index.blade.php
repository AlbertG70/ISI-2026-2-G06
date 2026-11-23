@extends('layouts.app')

@section('titulo', 'Mi Perfil y Datos Personales')
@section('pagina', 'perfil')

@section('contenido')
<div class="space-y-6">
    <div class="border-b-2 border-dashed border-borde/60 pb-3">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-tinta">Mi Cuenta</h1>
        <p class="text-base text-tinta-suave mt-1">
            Información del titular y garantías de derechos de protección de datos conforme a la <strong>Ley 1581 de 2012</strong>.
        </p>
    </div>

    {{-- Tarjeta de Datos del Titular --}}
    <section class="dz-tarjeta space-y-4 shadow-sm" aria-labelledby="titulo-datos-usuario">
        <h2 id="titulo-datos-usuario" class="text-xl font-bold text-tinta flex items-center gap-2">
            <span>👤</span> Datos de Usuario
        </h2>

        <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 border-t border-borde/40 pt-3">
            <div>
                <dt class="text-xs uppercase font-bold text-tinta-suave">Correo Electrónico</dt>
                <dd id="perfil-email" class="text-base font-semibold text-tinta break-words mt-0.5">Cargando...</dd>
            </div>
            <div>
                <dt class="text-xs uppercase font-bold text-tinta-suave">Rol en la Plataforma</dt>
                <dd id="perfil-rol" class="text-base font-semibold text-tinta mt-0.5">—</dd>
            </div>
            <div>
                <dt class="text-xs uppercase font-bold text-tinta-suave">Fecha de Registro</dt>
                <dd id="perfil-fecha" class="text-base font-semibold text-tinta mt-0.5">—</dd>
            </div>
        </dl>
    </section>

    {{-- Garantías Ley 1581 de 2012 (Hábeas Data) --}}
    <section class="dz-tarjeta space-y-4 shadow-sm" aria-labelledby="titulo-habeas-data">
        <div class="border-b-2 border-dashed border-borde/60 pb-2">
            <h2 id="titulo-habeas-data" class="text-xl font-bold text-tinta flex items-center gap-2">
                <span>⚖️</span> Protección de Datos (Ley Estatutaria 1581 de 2012)
            </h2>
            <p class="text-sm text-tinta-suave mt-0.5">
                Usted cuenta con plenos derechos de acceso, conocimiento, rectificación, portabilidad y supresión sobre su información.
            </p>
        </div>

        <div class="space-y-4">
            {{-- Derecho de Acceso y Portabilidad --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-3.5 rounded-xl border-2 border-borde/60 bg-neutro-bg">
                <div>
                    <h3 class="font-bold text-base text-tinta">Descargar copia de mis datos personales</h3>
                    <p class="text-xs sm:text-sm text-tinta-suave mt-0.5">
                        Genera un archivo estructurado en formato JSON con su historial, consultas y notas registradas.
                    </p>
                </div>
                <button type="button" 
                        id="btn-descargar-datos" 
                        class="dz-btn-secundario text-sm py-2 px-4 shrink-0 shadow-sm">
                    📥 Descargar Datos (.json)
                </button>
            </div>

            {{-- Derecho al Olvido / Supresión Definitiva --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-3.5 rounded-xl border-2 border-falso/50 bg-falso-bg">
                <div>
                    <h3 class="font-bold text-base text-falso">Derecho al olvido: Eliminar mi cuenta</h3>
                    <p class="text-xs sm:text-sm text-falso/90 mt-0.5">
                        Elimina permanentemente sus credenciales, desvincula su historial y anonimiza sus notas públicas en cascada.
                    </p>
                </div>
                <button type="button" 
                        id="btn-abrir-eliminar" 
                        class="dz-btn-peligro text-sm py-2 px-4 shrink-0 shadow-sm">
                    🗑️ Eliminar Mi Cuenta
                </button>
            </div>
        </div>
    </section>

    {{-- Cierre de sesión secundario --}}
    <div class="text-center pt-2">
        <button type="button" 
                data-accion="cerrar-sesion" 
                class="dz-btn-secundario py-2.5 px-6 font-bold text-base">
            Cerrar sesión en este dispositivo
        </button>
    </div>
</div>

{{-- Diálogo modal accesible nativo (<dialog>) para confirmar supresión de cuenta --}}
<dialog id="dialogo-eliminar" class="dz-tarjeta max-w-md w-full backdrop:bg-black/60 shadow-2xl p-6 rounded-2xl">
    <div class="text-center mb-4">
        <span class="text-4xl" aria-hidden="true">⚠️</span>
        <h2 class="text-xl font-extrabold text-falso mt-2">¿Confirmar supresión definitiva?</h2>
        <p class="text-sm text-tinta-suave mt-1 leading-relaxed">
            Esta acción es irreversible conforme a la Ley 1581 de 2012. Toda su información personal será eliminada de nuestros servidores.
        </p>
    </div>

    <form id="form-eliminar" novalidate class="space-y-4">
        <div>
            <label for="password_confirmacion" class="dz-label text-sm">Ingrese su contraseña para autorizar:</label>
            <input type="password" 
                   id="password_confirmacion" 
                   name="password" 
                   required
                   autocomplete="current-password"
                   placeholder="Su contraseña actual" 
                   class="dz-campo">
            <p data-error-de="password" class="dz-error !text-xs" role="alert" hidden></p>
        </div>

        <div class="flex items-center justify-end gap-3 pt-3 border-t border-borde/40">
            <button type="button" 
                    id="btn-cancelar-eliminar" 
                    class="dz-btn-secundario text-sm py-2 px-4">
                Cancelar
            </button>
            <button type="submit" 
                    id="btn-confirmar-eliminar" 
                    class="dz-btn-peligro text-sm py-2 px-4">
                Sí, eliminar permanentemente
            </button>
        </div>
    </form>
</dialog>
@endsection
