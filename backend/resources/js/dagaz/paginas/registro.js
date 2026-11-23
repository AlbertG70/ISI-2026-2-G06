/** CU05 — Registro de credenciales con autorización de tratamiento de datos (Ley 1581). */
import { api } from '../api';
import { estaAutenticado, guardarSesion } from '../sesion';
import { $, estadoBoton, mostrarErroresFormulario } from '../ui';

export function iniciar() {
    if (estaAutenticado()) {
        window.location.replace('/verificar');
        return;
    }

    const formulario = $('#form-registro');
    const alerta = $('#registro-alerta');
    const boton = $('#registro-enviar');
    const consentimiento = $('#acepta_datos');

    formulario.addEventListener('submit', async (e) => {
        e.preventDefault();
        alerta.hidden = true;

        // La autorización es previa y expresa: sin ella no se envía nada al servidor.
        const errorConsentimiento = $('[data-error-de="acepta_datos"]');
        if (!consentimiento.checked) {
            errorConsentimiento.textContent = 'Debe autorizar el tratamiento de sus datos para crear la cuenta.';
            errorConsentimiento.hidden = false;
            consentimiento.focus();
            return;
        }
        errorConsentimiento.hidden = true;

        const datos = new FormData(formulario);
        estadoBoton(boton, true, 'Creando cuenta…');
        try {
            const respuesta = await api('/auth/register', {
                method: 'POST',
                auth: false,
                body: {
                    email: datos.get('email'),
                    password: datos.get('password'),
                    password_confirmation: datos.get('password_confirmation'),
                },
            });
            guardarSesion(respuesta, false);
            window.location.assign('/verificar?bienvenida=1');
        } catch (error) {
            if (!mostrarErroresFormulario(formulario, error)) {
                alerta.textContent = error.message;
                alerta.hidden = false;
            }
            estadoBoton(boton, false);
        }
    });
}
