/**
 * CU07 — Asignar confiabilidad a las fuentes (Grafo de Credibilidad).
 * Mockup: docs/iteracion-01/Mockups/CU07_Mockup_Confiabilidad_Fuentes.html
 */
import { api } from '../api';
import { $, avisar, bloqueEstado, el, estadoBoton, medidorCircular } from '../ui';

let fuentes = [];

async function valorar(fuente, valor, boton, medidorWrap, grupo) {
    estadoBoton(boton, true, '…');
    try {
        const r = await api(`/fuentes/${encodeURIComponent(fuente.id_fuente)}/valorar`, {
            method: 'PATCH',
            body: { calificacion: valor },
        });
        fuente.indice_credibilidad = r.indice_credibilidad;
        medidorWrap.replaceChildren(medidorCircular(r.indice_credibilidad, 64));
        grupo.replaceChildren(el('p', { class: 'font-medium text-verdadero', text: '✔ Calificación guardada', attrs: { role: 'status' } }));
    } catch (error) {
        estadoBoton(boton, false);
        avisar(error.message, 'error');
    }
}

function tarjeta(f) {
    const medidorWrap = el('div', {}, [medidorCircular(f.indice_credibilidad, 64)]);
    const grupo = el('div', { class: 'flex flex-wrap items-center gap-2' });
    grupo.append(
        el('span', { class: 'text-base text-tinta-suave', text: 'Su calificación:' }),
        el('button', {
            class: 'dz-btn-secundario px-3',
            text: '👍 Confiable',
            attrs: { type: 'button', 'aria-label': `Calificar ${f.nombre} como confiable` },
            on: { click: (e) => valorar(f, 100, e.currentTarget, medidorWrap, grupo) },
        }),
        el('button', {
            class: 'dz-btn-secundario px-3',
            text: '👎 No confiable',
            attrs: { type: 'button', 'aria-label': `Calificar ${f.nombre} como no confiable` },
            on: { click: (e) => valorar(f, 0, e.currentTarget, medidorWrap, grupo) },
        }),
    );

    return el('li', { class: 'dz-tarjeta' }, [
        el('div', { class: 'flex items-center gap-4' }, [
            medidorWrap,
            el('div', { class: 'min-w-0 flex-1' }, [
                el('p', { class: 'text-lg font-semibold break-words', text: `${f.nivel === 1 ? '⭐ ' : ''}${f.nombre}` }),
                el('p', { class: 'text-base text-tinta-suave', text: f.nivel_etiqueta || f.clasificacion || '' }),
            ]),
        ]),
        el('div', { class: 'mt-3 border-t border-dashed border-borde/60 pt-3' }, [grupo]),
    ]);
}

function pintar() {
    const lista = $('#lista-fuentes');
    const nivel = $('#filtro-nivel').value;
    const texto = $('#buscar-fuente').value.trim().toLowerCase();

    const visibles = fuentes.filter(
        (f) => (!nivel || String(f.nivel) === nivel) && (!texto || String(f.nombre).toLowerCase().includes(texto)),
    );

    $('#fuentes-total').textContent = `${visibles.length} de ${fuentes.length} fuentes`;
    lista.replaceChildren(
        ...(visibles.length ? visibles.map(tarjeta) : [bloqueEstado({ icono: '🔎', titulo: 'Ninguna fuente coincide con la búsqueda' })]),
    );
}

export async function iniciar() {
    try {
        const r = await api('/fuentes');
        fuentes = r.fuentes || [];
        pintar();
    } catch (error) {
        $('#lista-fuentes').replaceChildren(bloqueEstado({ icono: '⚠️', titulo: 'No pudimos cargar las fuentes', detalle: error.message }));
        return;
    }

    $('#filtro-nivel').addEventListener('change', pintar);
    let espera;
    $('#buscar-fuente').addEventListener('input', () => {
        clearTimeout(espera);
        espera = setTimeout(pintar, 200);
    });
}
