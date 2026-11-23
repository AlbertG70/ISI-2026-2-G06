/**
 * DAGAZ — Gestión de sesión en el cliente.
 *
 * El backend emite tokens personales de Sanctum (Bearer). Se guardan en
 * sessionStorage por defecto (se borran al cerrar el navegador) y en
 * localStorage solo si el usuario marca "Mantener sesión iniciada".
 */
const CLAVE_TOKEN = 'dagaz.token';
const CLAVE_USUARIO = 'dagaz.usuario';

function almacen() {
    return localStorage.getItem(CLAVE_TOKEN) ? localStorage : sessionStorage;
}

export function obtenerToken() {
    return localStorage.getItem(CLAVE_TOKEN) || sessionStorage.getItem(CLAVE_TOKEN);
}

export function obtenerUsuario() {
    try {
        return JSON.parse(almacen().getItem(CLAVE_USUARIO) || 'null');
    } catch {
        return null;
    }
}

export function guardarSesion({ token, usuario }, recordar = false) {
    cerrarSesionLocal();
    const destino = recordar ? localStorage : sessionStorage;
    destino.setItem(CLAVE_TOKEN, token);
    destino.setItem(CLAVE_USUARIO, JSON.stringify(usuario));
}

export function cerrarSesionLocal() {
    [localStorage, sessionStorage].forEach((s) => {
        s.removeItem(CLAVE_TOKEN);
        s.removeItem(CLAVE_USUARIO);
    });
}

export function estaAutenticado() {
    return Boolean(obtenerToken());
}

/** Redirige al login si no hay sesión. Devuelve false si redirigió. */
export function exigirSesion() {
    if (estaAutenticado()) return true;
    const destino = encodeURIComponent(window.location.pathname + window.location.search);
    window.location.replace(`/login?siguiente=${destino}`);
    return false;
}

/** Solo permite redirecciones internas (evita open-redirect vía ?siguiente=). */
export function destinoSeguro(valor, porDefecto = '/verificar') {
    if (typeof valor === 'string' && valor.startsWith('/') && !valor.startsWith('//')) return valor;
    return porDefecto;
}
