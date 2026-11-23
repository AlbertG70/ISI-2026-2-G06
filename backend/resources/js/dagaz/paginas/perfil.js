/**
 * Perfil + derechos del titular (Ley 1581 de 2012 — Hábeas Data).
 * - Acceso/portabilidad: GET /auth/datos-personales
 * - Supresión (derecho al olvido): DELETE /auth/cuenta (exige contraseña)
 */
import { api } from '../api';
import { cerrarSesionLocal } from '../sesion';
import { $, avisar, estadoBoton, formatoFecha, mostrarErroresFormulario } from '../ui';

const ROLES = { adulto: 'Usuario', admin: 'Administrador', editor: 'Editor', lector: 'Lector' };

async function cargarPerfil() {
    try {
        const { usuario } = await api('/auth/perfil');
        $('#perfil-email').textContent = usuario.email;
        $('#perfil-rol').textContent = ROLES[usuario.rol] || usuario.rol;
        $('#perfil-fecha').textContent = formatoFecha(usuario.fecha_creacion) || '—';
    } catch (error) {
        avisar(error.message, 'error');
    }
}

function iniciarDescarga() {
    const boton = $('#btn-descargar-datos');
    boton.addEventListener('click', async () => {
        estadoBoton(boton, true, 'Preparando archivo…');
        try {
            const datos = await api('/auth/datos-personales');
            const blob = new Blob([JSON.stringify(datos, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `dagaz-mis-datos-${new Date().toISOString().slice(0, 10)}.json`;
            document.body.append(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
            avisar('Sus datos se descargaron correctamente.', 'exito');
        } catch (error) {
            avisar(error.message, 'error');
        } finally {
            estadoBoton(boton, false);
        }
    });
}

function iniciarSupresion() {
    const dialogo = $('#dialogo-eliminar');
    const formulario = $('#form-eliminar');
    const boton = $('#btn-confirmar-eliminar');

    $('#btn-abrir-eliminar').addEventListener('click', () => {
        formulario.reset();
        mostrarErroresFormulario(formulario, null);
        dialogo.showModal();
    });
    $('#btn-cancelar-eliminar').addEventListener('click', () => dialogo.close());

    formulario.addEventListener('submit', async (e) => {
        e.preventDefault();
        const password = new FormData(formulario).get('password');
        estadoBoton(boton, true, 'Eliminando…');
        try {
            await api('/auth/cuenta', { method: 'DELETE', body: { password } });
            cerrarSesionLocal();
            window.location.assign('/login?cuenta_eliminada=1');
        } catch (error) {
            // 422 sin "errors": contraseña incorrecta → se muestra bajo el campo.
            if (!mostrarErroresFormulario(formulario, error)) {
                mostrarErroresFormulario(formulario, { errores: { password: [error.message] }, campo: () => error.message });
            }
            estadoBoton(boton, false);
        }
    });
}

export function iniciar() {
    cargarPerfil();
    iniciarDescarga();
    iniciarSupresion();
}
