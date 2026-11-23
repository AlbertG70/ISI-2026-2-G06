/**
 * CU04 — Mostrar historial de contenidos.
 * Mockup: docs/iteracion-01/Mockups/CU04_Mockup_Historial_Noticias.html
 */
import { api } from '../api';
import { $, bloqueEstado, claveVeredicto, el, estadoBoton, formatoFecha, insigniaVeredicto } from '../ui';

const estado = { items: new Map(), pagina: 0, ultima: 1, filtro: 'todos', busqueda: '' };

/** Un mismo contenido puede tener eventos 'verificado' y 'guardado': se agrupan. */
function acumular(registros) {
    registros.forEach((r) => {
        if (!r.id_contenido) return;
        const previo = estado.items.get(r.id_contenido);
        estado.items.set(r.id_contenido, {
            ...(previo || r),
            guardado: Boolean(previo?.guardado) || r.accion === 'guardado',
        });
    });
}

function coincide(item) {
    const clave = claveVeredicto(item.veredicto?.tipo_veredicto);
    if (estado.filtro === 'guardados' && !item.guardado) return false;
    if (!['todos', 'guardados'].includes(estado.filtro) && clave !== estado.filtro) return false;
    if (estado.busqueda && !String(item.resumen_contenido || '').toLowerCase().includes(estado.busqueda)) return false;
    return true;
}

function tarjeta(item) {
    const panelDetalle = el('div', {
        class: 'mt-3 border-t-2 border-dashed border-borde/60 pt-3 space-y-2 text-sm text-tinta',
        attrs: { hidden: true },
    }, [
        el('p', {
            class: 'text-base text-tinta-suave',
            text: item.veredicto?.explicacion || 'Contenido analizado por el motor de verificación y agentes de contraste de DAGAZ.',
        }),
        item.veredicto?.fuentes?.length
            ? el('div', { class: 'pt-1' }, [
                  el('p', { class: 'font-bold text-tinta text-xs uppercase tracking-wider', text: '📎 Fuentes principales:' }),
                  el('ul', { class: 'list-disc list-inside mt-1 space-y-0.5 text-xs text-tinta-suave' },
                      item.veredicto.fuentes.map((f) => el('li', { text: `${f.nombre} (${f.indice_credibilidad}%)` }))
                  ),
              ])
            : null,
        el('div', { class: 'pt-2 flex items-center justify-between gap-2' }, [
            el('a', {
                class: 'dz-btn-primario text-xs sm:text-sm py-2 px-3',
                text: '🔎 Ver análisis completo ↗',
                attrs: { href: `/verificar/${encodeURIComponent(item.id_contenido)}` },
            }),
        ]),
    ]);

    const btnDetalle = el('button', {
        class: 'dz-btn-secundario text-xs sm:text-sm py-1.5 px-3 min-h-10',
        text: '👁 Ver detalle',
        attrs: { type: 'button', 'aria-expanded': 'false' },
    });

    btnDetalle.addEventListener('click', (e) => {
        e.stopPropagation();
        const abierto = !panelDetalle.hidden;
        panelDetalle.hidden = abierto;
        btnDetalle.setAttribute('aria-expanded', String(!abierto));
        btnDetalle.textContent = abierto ? '👁 Ver detalle' : '🔼 Ocultar detalle';
    });

    const li = el('li', { class: 'dz-tarjeta transition-all duration-300' });

    const btnEliminar = el('button', {
        class: 'dz-btn-secundario text-xs sm:text-sm py-1.5 px-3 min-h-10 text-falso hover:bg-falso-bg hover:border-falso',
        text: '🗑 Quitar',
        attrs: { type: 'button', 'aria-label': 'Quitar esta noticia de la vista' },
    });

    btnEliminar.addEventListener('click', (e) => {
        e.stopPropagation();
        li.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        li.style.opacity = '0';
        li.style.transform = 'translateX(40px)';
        setTimeout(() => {
            estado.items.delete(item.id_contenido);
            pintar();
        }, 300);
    });

    li.append(
        el('div', { class: 'flex flex-wrap items-start justify-between gap-3' }, [
            el('p', { class: 'min-w-0 flex-1 text-lg font-medium break-words text-tinta', text: `“${item.resumen_contenido || 'Contenido sin texto'}”` }),
            item.veredicto ? insigniaVeredicto(item.veredicto.tipo_veredicto) : el('span', { class: 'text-tinta-suave text-sm', text: 'Sin veredicto' }),
        ]),
        el('p', {
            class: 'mt-2 text-sm text-tinta-suave',
            text: `🗓 ${formatoFecha(item.fecha_evento)}${item.guardado ? ' · ⭐ Guardada' : ''}${
                item.veredicto ? ` · Confianza ${Math.round(Number(item.veredicto.nivel_confiabilidad) || 0)}%` : ''
            }`,
        }),
        el('div', { class: 'mt-3 flex items-center gap-2 flex-wrap' }, [
            btnDetalle,
            btnEliminar,
        ]),
        panelDetalle,
    );

    return li;
}

function pintar() {
    const lista = $('#lista-historial');
    const visibles = [...estado.items.values()].filter(coincide);
    $('#historial-total').textContent = `${estado.items.size} noticia(s) en su historial`;

    if (!estado.items.size) {
        lista.replaceChildren(
            bloqueEstado({
                icono: '📭',
                titulo: 'Aún no tiene noticias verificadas',
                detalle: '¡Verifique su primera noticia y aparecerá aquí!',
                accion: el('a', { class: 'dz-btn-primario', text: '🔍 Verificar una noticia', attrs: { href: '/verificar' } }),
            }),
        );
    } else if (!visibles.length) {
        lista.replaceChildren(bloqueEstado({ icono: '🔎', titulo: 'Ninguna noticia coincide con el filtro' }));
    } else {
        lista.replaceChildren(...visibles.map(tarjeta));
    }

    $('#btn-mas').hidden = estado.pagina >= estado.ultima;
}

async function cargarPagina() {
    const boton = $('#btn-mas');
    estadoBoton(boton, true, 'Cargando…');
    try {
        const r = await api(`/historial?page=${estado.pagina + 1}`);
        acumular(r.historial || []);
        estado.pagina = r.pagina_actual || estado.pagina + 1;
        estado.ultima = r.ultima_pagina || estado.pagina;
        pintar();
    } catch (error) {
        $('#lista-historial').replaceChildren(bloqueEstado({ icono: '⚠️', titulo: 'No pudimos cargar su historial', detalle: error.message }));
    } finally {
        estadoBoton(boton, false);
    }
}

export function iniciar() {
    $('#btn-mas').addEventListener('click', cargarPagina);

    $('#filtro-veredicto').addEventListener('change', (e) => {
        estado.filtro = e.target.value;
        pintar();
    });

    let espera;
    $('#buscar-historial').addEventListener('input', (e) => {
        clearTimeout(espera);
        espera = setTimeout(() => {
            estado.busqueda = e.target.value.trim().toLowerCase();
            pintar();
        }, 200);
    });

    cargarPagina();
}
