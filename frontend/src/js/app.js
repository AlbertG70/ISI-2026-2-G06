/**
 * DAGAZ — Punto de entrada del frontend.
 *
 * Cada vista Blade declara <body data-pagina="..."> y aquí se carga
 * (bajo demanda) solo el módulo de esa pantalla.
 */
import { api } from './dagaz/api';
import { cerrarSesionLocal, exigirSesion, obtenerUsuario } from './dagaz/sesion';

const PAGINAS = {
    login: () => import('./dagaz/paginas/login'),
    registro: () => import('./dagaz/paginas/registro'),
    verificar: () => import('./dagaz/paginas/verificar'),
    resultado: () => import('./dagaz/paginas/resultado'),
    historial: () => import('./dagaz/paginas/historial'),
    fuentes: () => import('./dagaz/paginas/fuentes'),
    perfil: () => import('./dagaz/paginas/perfil'),
};

function iniciarCabecera() {
    const usuario = obtenerUsuario();
    const etiqueta = document.getElementById('dz-usuario');
    if (etiqueta && usuario?.email) etiqueta.textContent = usuario.email;

    document.querySelectorAll('[data-accion="cerrar-sesion"]').forEach((boton) =>
        boton.addEventListener('click', async () => {
            try {
                await api('/auth/logout', { method: 'POST' });
            } catch {
                /* Aunque falle la red, la sesión local se cierra igual. */
            }
            cerrarSesionLocal();
            window.location.assign('/login');
        }),
    );
}

function iniciarAccesibilidadTexto() {
    const clave = 'dagaz_tamano_texto';
    const aplicar = (tam) => {
        const btnNormal = document.getElementById('btn-tamano-normal');
        const btnGrande = document.getElementById('btn-tamano-grande');
        if (tam === 'grande') {
            document.documentElement.dataset.tamanoTexto = 'grande';
            btnGrande?.classList.add('bg-tinta', 'text-white');
            btnNormal?.classList.remove('bg-tinta', 'text-white');
        } else {
            delete document.documentElement.dataset.tamanoTexto;
            btnNormal?.classList.add('bg-tinta', 'text-white');
            btnGrande?.classList.remove('bg-tinta', 'text-white');
        }
        try {
            localStorage.setItem(clave, tam);
        } catch {
            /* Quota o navegación privada */
        }
    };

    let guardado = 'normal';
    try {
        guardado = localStorage.getItem(clave) || 'normal';
    } catch {
        guardado = 'normal';
    }
    aplicar(guardado);

    document.getElementById('btn-tamano-normal')?.addEventListener('click', () => aplicar('normal'));
    document.getElementById('btn-tamano-grande')?.addEventListener('click', () => aplicar('grande'));
}

document.addEventListener('DOMContentLoaded', async () => {
    iniciarAccesibilidadTexto();

    const { pagina, requiereSesion } = document.body.dataset;

    if (requiereSesion === 'true' && !exigirSesion()) return;

    iniciarCabecera();

    const cargar = PAGINAS[pagina];
    if (cargar) {
        const modulo = await cargar();
        modulo.iniciar?.();
    }
});
