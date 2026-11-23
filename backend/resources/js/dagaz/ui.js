/**
 * DAGAZ — Utilidades de interfaz.
 *
 * Regla de seguridad: todo dato proveniente de la API se inserta con
 * textContent (vía `el`). Nunca se usa innerHTML con datos de usuario.
 */

/**
 * Crea un elemento DOM de forma segura.
 * @param {string} tag
 * @param {object} props  class, text, attrs{}, on{}
 * @param {Array<Node|string|null>} hijos
 */
export function el(tag, { class: clase, text, attrs = {}, on = {} } = {}, hijos = []) {
    const nodo = document.createElement(tag);
    if (clase) nodo.className = clase;
    if (text !== undefined && text !== null) nodo.textContent = String(text);
    Object.entries(attrs).forEach(([k, v]) => {
        if (v !== undefined && v !== null && v !== false) nodo.setAttribute(k, v === true ? '' : String(v));
    });
    Object.entries(on).forEach(([evento, fn]) => nodo.addEventListener(evento, fn));
    hijos.filter(Boolean).forEach((h) => nodo.append(h));
    return nodo;
}

export const $ = (selector, raiz = document) => raiz.querySelector(selector);

/* ─── Veredictos ─────────────────────────────────────────────────────────── */

const VEREDICTOS = {
    VERDADERO: {
        etiqueta: 'Verdadero',
        icono: '✅',
        texto: 'text-verdadero',
        fondo: 'bg-verdadero-bg',
        borde: 'border-verdadero',
        trazo: '#166534',
        resumen: 'La afirmación está respaldada por fuentes confiables.',
    },
    PARCIAL: {
        etiqueta: 'Parcialmente cierto',
        icono: '⚠️',
        texto: 'text-parcial',
        fondo: 'bg-parcial-bg',
        borde: 'border-parcial',
        trazo: '#a16207',
        resumen: 'Tiene partes correctas, pero le falta contexto importante.',
    },
    ENGANOSO: {
        etiqueta: 'Engañoso',
        icono: '🟠',
        texto: 'text-enganoso',
        fondo: 'bg-enganoso-bg',
        borde: 'border-enganoso',
        trazo: '#ea580c',
        resumen: 'Mezcla datos reales con información sacada de contexto.',
    },
    FALSO: {
        etiqueta: 'Falso',
        icono: '❌',
        texto: 'text-falso',
        fondo: 'bg-falso-bg',
        borde: 'border-falso',
        trazo: '#991b1b',
        resumen: 'Las fuentes confiables contradicen esta afirmación.',
    },
    NO_VERIFICABLE: {
        etiqueta: 'No verificable',
        icono: '❔',
        texto: 'text-neutro',
        fondo: 'bg-neutro-bg',
        borde: 'border-neutro',
        trazo: '#475569',
        resumen: 'No encontramos suficiente evidencia para confirmarlo o desmentirlo.',
    },
};

/** Normaliza 'ENGAÑOSO' / 'engañoso' / 'No verificable' → clave interna. */
export function claveVeredicto(tipo) {
    return String(tipo || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toUpperCase()
        .replace(/\s+/g, '_');
}

export function metaVeredicto(tipo) {
    return VEREDICTOS[claveVeredicto(tipo)] || VEREDICTOS.NO_VERIFICABLE;
}

/** Insignia compacta (icono + texto) para listados. */
export function insigniaVeredicto(tipo) {
    const m = metaVeredicto(tipo);
    return el(
        'span',
        {
            class: `inline-flex items-center gap-1 rounded-full border-2 px-3 py-1 text-base font-semibold ${m.texto} ${m.fondo} ${m.borde}`,
            attrs: { role: 'status' },
        },
        [el('span', { text: m.icono, attrs: { 'aria-hidden': 'true' } }), m.etiqueta],
    );
}

/* ─── Fuentes / credibilidad ─────────────────────────────────────────────── */

const NIVELES_FUENTE = {
    internacional_n1: { nivel: 1, etiqueta: 'Internacional' },
    regional_n2: { nivel: 2, etiqueta: 'Prensa regional' },
    nacional_n3: { nivel: 3, etiqueta: 'Prensa nacional' },
    factchecker_n4: { nivel: 4, etiqueta: 'Verificador IFCN' },
    oficial: { nivel: 1, etiqueta: 'Organismo Oficial / Estatal' },
    periodistico: { nivel: 2, etiqueta: 'Medio Periodístico Verificado' },
    academico: { nivel: 1, etiqueta: 'Académico / Documental' },
    historico: { nivel: 1, etiqueta: 'Registro Histórico / Constitucional' },
    otro: { nivel: null, etiqueta: 'Otra fuente' },
};

export function metaFuente(tipo) {
    return NIVELES_FUENTE[String(tipo || '').toLowerCase()] || NIVELES_FUENTE.otro;
}

/** Clases de color según porcentaje de credibilidad (leyenda del mockup CU06). */
export function colorCredibilidad(pct) {
    const v = Number(pct) || 0;
    if (v >= 75) return { texto: 'text-verdadero', trazo: '#166534', nombre: 'alta' };
    if (v >= 40) return { texto: 'text-parcial', trazo: '#a16207', nombre: 'media' };
    return { texto: 'text-falso', trazo: '#991b1b', nombre: 'baja' };
}

/** Medidor circular SVG con porcentaje accesible. */
export function medidorCircular(pct, tam = 56, colorTrazo = null, claseTexto = null) {
    const v = Math.max(0, Math.min(100, Number(pct) || 0));
    const r = 15;
    const circ = 2 * Math.PI * r;
    const color = colorCredibilidad(v);
    const trazoFinal = colorTrazo || color.trazo;
    const textoFinal = claseTexto || color.texto;
    const ns = 'http://www.w3.org/2000/svg';

    const svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('viewBox', '0 0 36 36');
    svg.setAttribute('width', tam);
    svg.setAttribute('height', tam);
    svg.setAttribute('aria-hidden', 'true');
    svg.classList.add('-rotate-90');

    const fondo = document.createElementNS(ns, 'circle');
    Object.entries({ cx: 18, cy: 18, r, fill: 'none', stroke: '#e5e7eb', 'stroke-width': 3.5 }).forEach(([k, val]) =>
        fondo.setAttribute(k, val),
    );
    const arco = document.createElementNS(ns, 'circle');
    Object.entries({
        cx: 18,
        cy: 18,
        r,
        fill: 'none',
        stroke: trazoFinal,
        'stroke-width': 3.5,
        'stroke-linecap': 'round',
        'stroke-dasharray': circ.toFixed(2),
        'stroke-dashoffset': (circ * (1 - v / 100)).toFixed(2),
    }).forEach(([k, val]) => arco.setAttribute(k, val));
    svg.append(fondo, arco);

    return el(
        'div',
        {
            class: 'relative inline-flex shrink-0 items-center justify-center',
            attrs: { role: 'img', 'aria-label': `${Math.round(v)}%` },
        },
        [svg, el('span', { class: `absolute text-sm font-bold ${textoFinal}`, text: `${Math.round(v)}%` })],
    );
}

/* ─── Formato ────────────────────────────────────────────────────────────── */

export function formatoFecha(iso) {
    if (!iso) return '';
    const fecha = new Date(iso);
    if (Number.isNaN(fecha.getTime())) return '';
    return fecha.toLocaleDateString('es-CO', { day: 'numeric', month: 'short', year: 'numeric' });
}

/**
 * Protección de datos (Ley 1581): la API expone el email del autor de las
 * notas; en pantalla solo se muestra un alias parcial.
 */
export function aliasAutor(email) {
    if (!email || !String(email).includes('@')) return 'Usuario de la comunidad';
    const local = String(email).split('@')[0];
    return `${local.slice(0, 2)}${'•'.repeat(Math.max(3, Math.min(local.length - 2, 6)))}`;
}

/* ─── Avisos y estados ───────────────────────────────────────────────────── */

const ESTILOS_AVISO = {
    exito: 'border-verdadero bg-verdadero-bg text-verdadero',
    error: 'border-falso bg-falso-bg text-falso',
    info: 'border-neutro bg-neutro-bg text-neutro',
};

/** Muestra un aviso en la región viva global (lectores de pantalla lo anuncian). */
export function avisar(mensaje, tipo = 'info', ms = 5000) {
    const region = document.getElementById('dz-avisos');
    if (!region) return;
    const nodo = el('div', {
        class: `dz-alerta pointer-events-auto shadow-lg ${ESTILOS_AVISO[tipo] || ESTILOS_AVISO.info}`,
        text: mensaje,
        attrs: { role: tipo === 'error' ? 'alert' : 'status' },
    });
    region.append(nodo);
    setTimeout(() => nodo.remove(), ms);
}

/** Bloque de error/estado vacío reutilizable dentro de un contenedor. */
export function bloqueEstado({ icono = 'ℹ️', titulo, detalle, accion } = {}) {
    return el('div', { class: 'dz-tarjeta text-center' }, [
        el('p', { class: 'text-4xl', text: icono, attrs: { 'aria-hidden': 'true' } }),
        el('p', { class: 'mt-2 text-lg font-semibold', text: titulo }),
        detalle ? el('p', { class: 'mt-1 text-tinta-suave', text: detalle }) : null,
        accion ? el('div', { class: 'mt-4' }, [accion]) : null,
    ]);
}

/** Pinta errores de validación 422 bajo cada campo del formulario. */
export function mostrarErroresFormulario(formulario, error) {
    formulario.querySelectorAll('[data-error-de]').forEach((n) => {
        n.textContent = '';
        n.hidden = true;
    });
    formulario.querySelectorAll('[aria-invalid]').forEach((n) => n.removeAttribute('aria-invalid'));

    let alguno = false;
    Object.keys(error?.errores || {}).forEach((campo) => {
        const destino = formulario.querySelector(`[data-error-de="${campo}"]`);
        const input = formulario.querySelector(`[name="${campo}"]`);
        if (destino) {
            destino.textContent = error.campo(campo);
            destino.hidden = false;
            alguno = true;
        }
        if (input) input.setAttribute('aria-invalid', 'true');
    });
    return alguno;
}

export function estadoBoton(boton, cargando, textoCarga = 'Procesando…') {
    if (cargando) {
        boton.dataset.textoOriginal = boton.textContent;
        boton.disabled = true;
        boton.replaceChildren(el('span', { class: 'dz-spinner', attrs: { 'aria-hidden': 'true' } }), textoCarga);
    } else {
        boton.disabled = false;
        if (boton.dataset.textoOriginal) boton.textContent = boton.dataset.textoOriginal;
    }
}
