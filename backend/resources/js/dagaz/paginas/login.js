/** CU05 — Inicio de sesión. */
import { api } from '../api';
import { destinoSeguro, estaAutenticado, guardarSesion } from '../sesion';
import { $, estadoBoton, mostrarErroresFormulario } from '../ui';

export function iniciar() {
    const params = new URLSearchParams(window.location.search);
    const siguiente = destinoSeguro(params.get('siguiente'));

    if (estaAutenticado()) {
        window.location.replace(siguiente);
        return;
    }

    const formulario = $('#form-login');
    const alerta = $('#login-alerta');
    const boton = $('#login-enviar');

    if (params.get('expirada')) {
        alerta.textContent = 'Su sesión expiró. Por favor, inicie sesión nuevamente.';
        alerta.hidden = false;
    }

    $('#ver-password')?.addEventListener('click', (e) => {
        const campo = $('#password');
        const visible = campo.type === 'text';
        campo.type = visible ? 'password' : 'text';
        e.currentTarget.setAttribute('aria-pressed', String(!visible));
        e.currentTarget.textContent = visible ? 'Mostrar' : 'Ocultar';
    });

    formulario.addEventListener('submit', async (e) => {
        e.preventDefault();
        alerta.hidden = true;
        const datos = new FormData(formulario);

        estadoBoton(boton, true, 'Ingresando…');
        try {
            const respuesta = await api('/auth/login', {
                method: 'POST',
                auth: false,
                body: { email: datos.get('email'), password: datos.get('password') },
            });
            guardarSesion(respuesta, datos.get('recordar') === 'on');
            window.location.assign(siguiente);
        } catch (error) {
            // FA1 (credenciales incorrectas) llega como 422 sobre el campo email.
            if (!mostrarErroresFormulario(formulario, error)) {
                alerta.textContent = error.message;
                alerta.hidden = false;
            }
            estadoBoton(boton, false);
        }
    });
}
