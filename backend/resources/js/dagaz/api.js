/**
 * DAGAZ — Cliente HTTP para la API REST v1.
 *
 * - Inyecta el token Bearer de Sanctum en cada petición.
 * - Normaliza errores en ApiError (status + mensaje + errores de validación).
 * - Ante un 401 limpia la sesión y redirige al login.
 */
import { obtenerToken, cerrarSesionLocal } from './sesion';

const BASE = '/api/v1';

export class ApiError extends Error {
    constructor(status, mensaje, errores = {}) {
        super(mensaje);
        this.name = 'ApiError';
        this.status = status;
        this.errores = errores;
    }

    /** Primer mensaje de validación de un campo (422), si existe. */
    campo(nombre) {
        const lista = this.errores?.[nombre];
        return Array.isArray(lista) && lista.length ? lista[0] : null;
    }
}

const MENSAJES_POR_ESTADO = {
    0: 'No hay conexión con el servidor. Revise su conexión a internet.',
    403: 'No tiene permisos para realizar esta acción.',
    404: 'No encontramos la información solicitada.',
    429: 'Ha realizado demasiadas solicitudes. Espere un momento e intente de nuevo.',
    500: 'Ocurrió un error en el servidor. Intente nuevamente en unos minutos.',
    503: 'El servicio de verificación no está disponible en este momento.',
};

/**
 * @param {string} ruta      Ruta relativa a /api/v1 (ej. '/historial').
 * @param {object} opciones  { method, body (objeto o FormData), auth (bool, por defecto true) }
 */
export async function api(ruta, { method = 'GET', body, auth = true } = {}) {
    const headers = { Accept: 'application/json' };

    if (auth) {
        const token = obtenerToken();
        if (token) headers.Authorization = `Bearer ${token}`;
    }

    let cuerpo;
    if (body instanceof FormData) {
        cuerpo = body; // el navegador fija el boundary multipart
    } else if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
        cuerpo = JSON.stringify(body);
    }

    let respuesta;
    try {
        respuesta = await fetch(`${BASE}${ruta}`, { method, headers, body: cuerpo });
    } catch {
        throw new ApiError(0, MENSAJES_POR_ESTADO[0]);
    }

    const datos = await respuesta.json().catch(() => ({}));

    if (respuesta.ok) return datos;

    if (respuesta.status === 401 && auth) {
        cerrarSesionLocal();
        window.location.assign(`/login?expirada=1`);
        throw new ApiError(401, 'Su sesión expiró. Inicie sesión nuevamente.');
    }

    const mensaje =
        datos.mensaje || datos.message || MENSAJES_POR_ESTADO[respuesta.status] || MENSAJES_POR_ESTADO[500];
    throw new ApiError(respuesta.status, mensaje, datos.errors || {});
}
