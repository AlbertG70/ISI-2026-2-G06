/**
 * CU06 (resultado) + CU03 (guardar) + CU01 (asignar nota) + CU02 (calificar nota).
 * Mockups: CU06_Mockup_Verificar_Resultado.html, CU03, CU01, CU02.
 */
import { api } from '../api';
import { obtenerUsuario } from '../sesion';
import {
    $,
    aliasAutor,
    avisar,
    bloqueEstado,
    el,
    estadoBoton,
    formatoFecha,
    medidorCircular,
    metaFuente,
    metaVeredicto,
    mostrarErroresFormulario,
} from '../ui';

let idContenido = null;

/* ─── Veredicto ──────────────────────────────────────────────────────────── */

function pintarExplicacion(veredicto, hayFuentes) {
    const motivo = veredicto.motivo_breve?.trim();
    const enlaceFuentes = hayFuentes
        ? el('a', {
              class: 'inline-flex min-h-12 items-center font-semibold text-tinta underline underline-offset-4',
              text: 'Ver fuentes consultadas',
              attrs: { href: '#titulo-fuentes' },
          })
        : null;
    const explicacionCompleta = veredicto.explicacion
        ? el('details', {}, [
              el('summary', {
                  class: 'min-h-12 cursor-pointer content-center font-semibold',
                  text: 'Ver explicación completa',
              }),
              el('p', { class: 'mt-2 whitespace-pre-line break-words text-tinta', text: veredicto.explicacion }),
          ])
        : null;
    const clase = 'mt-4 rounded-lg border-2 border-borde p-3';

    if (motivo) {
        return el('details', { class: clase, attrs: { open: true } }, [
            el('summary', { class: 'min-h-12 cursor-pointer content-center font-semibold', text: '¿Por qué este veredicto?' }),
            el('p', { class: 'mt-2 break-words text-tinta', text: motivo }),
            enlaceFuentes,
            explicacionCompleta,
        ]);
    }

    return explicacionCompleta || enlaceFuentes
        ? el('div', { class: clase }, [enlaceFuentes, explicacionCompleta])
        : null;
}

function pintarVeredicto(contenido, veredicto, hayFuentes) {
    const destino = $('#bloque-veredicto');
    const texto = contenido.contenido_original || contenido.url_origen || '';

    if (!veredicto) {
        destino.replaceChildren(
            bloqueEstado({
                icono: '⏳',
                titulo: 'Este contenido aún no tiene veredicto',
                detalle: 'La verificación sigue en proceso o no pudo completarse.',
            }),
        );
        return;
    }

    const m = metaVeredicto(veredicto.tipo_veredicto);
    const confianza = Number(veredicto.nivel_confiabilidad) || 0;

    destino.replaceChildren(
        el('article', { class: 'dz-tarjeta', attrs: { 'aria-labelledby': 'titulo-veredicto' } }, [
            el('p', { class: 'text-sm font-semibold uppercase tracking-wide text-tinta-suave', text: 'Contenido verificado' }),
            el('blockquote', { class: 'mt-2 border-l-4 border-borde pl-3 text-lg font-medium break-words', text: `“${texto}”` }),
            el('p', {
                class: 'mt-1 text-base text-tinta-suave',
                text: `Verificado el ${formatoFecha(veredicto.fecha_emision || contenido.fecha_subida)}`,
            }),

            el('div', { class: `mt-4 flex flex-wrap items-center gap-4 rounded-xl border-[3px] p-4 ${m.borde} ${m.fondo}` }, [
                el('span', { class: 'text-5xl leading-none', text: m.icono, attrs: { 'aria-hidden': 'true' } }),
                el('div', { class: 'min-w-0 flex-1' }, [
                    el('h1', { class: `${m.texto}`, text: m.etiqueta, attrs: { id: 'titulo-veredicto' } }),
                    el('p', { class: 'mt-1 text-tinta', text: m.resumen }),
                ]),
                el('div', { class: 'flex flex-col items-center' }, [
                    medidorCircular(confianza, 72, m.trazo, m.texto),
                    el('span', { class: 'mt-1 text-xs font-bold uppercase tracking-wider text-tinta-suave', text: 'Certeza' }),
                ]),
            ]),

            pintarExplicacion(veredicto, hayFuentes),

            veredicto.explicacion?.includes('[Revisión Editorial') || veredicto.intervenido_por
                ? el('div', { class: 'mt-3 inline-flex items-center gap-1.5 rounded-lg border-2 border-marca bg-marca text-white px-3 py-1 text-xs font-bold' }, [
                      el('span', { text: '✍️ Veredicto Intervenido y Rectificado por Revisor Humano' }),
                  ])
                : null,

            confianza < 60
                ? el('p', {
                      class: 'dz-alerta mt-4 border-parcial bg-parcial-bg text-parcial',
                      text: 'Confianza baja: este resultado puede ser revisado por un editor humano. Compártalo con precaución.',
                  })
                : null,
        ]),
    );
}

/* ─── Fuentes ────────────────────────────────────────────────────────────── */

function enlaceSeguro(url) {
    try {
        const u = new URL(url);
        return ['http:', 'https:'].includes(u.protocol) ? u.href : null;
    } catch {
        return null;
    }
}

function pintarFuentes(fuentesValidas) {
    const lista = $('#lista-fuentes');

    if (!fuentesValidas.length) {
        lista.replaceChildren(
            el('li', { class: 'rounded-xl border-2 border-amber-300 bg-amber-50 p-4 text-amber-950 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200 shadow-sm' }, [
                el('div', { class: 'flex items-start gap-3' }, [
                    el('span', { class: 'text-2xl leading-none select-none', text: '⚠️', attrs: { 'aria-hidden': 'true' } }),
                    el('div', { class: 'space-y-1' }, [
                        el('p', {
                            class: 'font-semibold text-base leading-snug',
                            text: 'No hay citas de fuentes disponibles para esta verificación.',
                        }),
                    ]),
                ]),
            ]),
        );
        return;
    }

    lista.replaceChildren(
        ...fuentesValidas.map((f) => {
            const nivel = metaFuente(f.tipo_fuente);
            const href = enlaceSeguro(f.url_sitio);
            const esFuenteIa = f.origen === 'IA_CONSEJO' || Boolean(f.modelo_evaluador);
            const badgeOrigen = esFuenteIa
                ? el('span', {
                      class: 'inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 ml-2',
                      text: f.modelo_evaluador ? `🤖 Citada por ${f.modelo_evaluador}` : '🤖 Verificada por Consejo de IA',
                  })
                : null;

            return el('li', { class: 'flex items-start gap-3 border-b border-dashed border-borde/60 py-3 last:border-0' }, [
                f.indice_credibilidad != null ? medidorCircular(f.indice_credibilidad, 52) : null,
                el('div', { class: 'min-w-0 flex-1' }, [
                    el('div', { class: 'flex flex-wrap items-center' }, [
                        el('p', { class: 'font-semibold', text: f.nombre }),
                        badgeOrigen,
                    ]),
                    el('p', {
                        class: 'text-sm text-tinta-suave',
                        text: nivel.nivel ? `Nivel ${nivel.nivel} · ${nivel.etiqueta}` : nivel.etiqueta,
                    }),
                    f.cita_contraste ? el('p', { class: 'mt-1 text-base italic', text: `“${f.cita_contraste}”` }) : null,
                    href
                        ? el('a', {
                              class: 'mt-1 inline-flex min-h-12 items-center font-medium text-blue-800 underline',
                              text: 'Abrir fuente ↗',
                              attrs: { href, target: '_blank', rel: 'noopener noreferrer nofollow' },
                          })
                        : null,
                ]),
            ]);
        }),
    );
}

/* ─── Notas de la comunidad (CU01 / CU02) ────────────────────────────────── */

const CARACTERES_INVALIDOS = /[<>{}\\\|]/;
let todasLasNotas = [];
let filtroNotasActivo = 'todas';

function filtrarNotas(notas) {
    if (filtroNotasActivo === 'utiles') {
        return notas.filter((n) => Number(n.calificacion || 0) >= 6);
    }
    if (filtroNotasActivo === 'no-utiles') {
        return notas.filter((n) => Number(n.calificacion || 0) < 6);
    }
    return notas;
}

async function calificar(nota, valor, boton, filaVotos, barraFill, textoUtilidad) {
    estadoBoton(boton, true, '…');
    try {
        const r = await api(`/notas/${encodeURIComponent(nota.id_nota)}/calificar`, {
            method: 'PATCH',
            body: { calificacion: valor },
        });
        const nuevaCal = Number(r.calificacion_actual ?? valor);
        nota.calificacion = nuevaCal;
        const pct = Math.min(100, Math.max(0, Math.round((nuevaCal / 10) * 100)));

        if (barraFill) barraFill.style.width = `${pct}%`;
        if (textoUtilidad) {
            textoUtilidad.textContent = `${nuevaCal.toFixed(1)} de 10 adultos la encontraron útil (${pct}%)`;
        }

        filaVotos.replaceChildren(
            el('span', {
                class: 'inline-flex items-center gap-1 text-sm font-bold text-verdadero',
                text: '✔ Calificación guardada',
            }),
        );
        avisar('¡Gracias! Su calificación fue registrada.', 'exito');
    } catch (error) {
        estadoBoton(boton, false);
        avisar(error.message, 'error');
    }
}

function tarjetaNota(nota, emailPropio) {
    const cal = Number(nota.calificacion || 0);
    const pct = Math.min(100, Math.max(0, Math.round((cal / 10) * 100)));

    const barraFill = el('div', {
        class: 'dz-barra-progreso-fill',
        attrs: { style: `width: ${pct}%;` },
    });
    const barra = el('div', { class: 'dz-barra-progreso mt-2' }, [barraFill]);

    const textoUtilidad = el('p', {
        class: 'mt-1 text-xs text-tinta-suave font-medium',
        text: `${cal.toFixed(1)} de 10 adultos la encontraron útil (${pct}%)`,
    });

    const esPropia = emailPropio && nota.autor === emailPropio;
    const filaVotos = el('div', { class: 'flex items-center gap-2 flex-wrap' });

    if (esPropia) {
        filaVotos.append(el('span', { class: 'text-xs font-semibold text-tinta-suave', text: 'Nota de su autoría' }));
    } else {
        const btnUtil = el('button', {
            class: 'dz-btn-secundario min-h-10 px-3 text-sm',
            text: '👍 Útil',
            attrs: { type: 'button', 'aria-label': 'Marcar como útil' },
        });
        const btnNoUtil = el('button', {
            class: 'dz-btn-secundario min-h-10 px-3 text-sm',
            text: '👎 No útil',
            attrs: { type: 'button', 'aria-label': 'Marcar como no útil' },
        });

        btnUtil.addEventListener('click', () => calificar(nota, 10, btnUtil, filaVotos, barraFill, textoUtilidad));
        btnNoUtil.addEventListener('click', () => calificar(nota, 0, btnNoUtil, filaVotos, barraFill, textoUtilidad));
        filaVotos.append(btnUtil, btnNoUtil);
    }

    return el('li', { class: 'rounded-xl border-2 border-borde/60 bg-white p-3.5 space-y-2' }, [
        el('div', { class: 'flex items-center gap-2' }, [
            el('span', { class: 'text-xl', text: '👤', attrs: { 'aria-hidden': 'true' } }),
            el('span', { class: 'font-semibold text-tinta', text: esPropia ? 'Usted' : aliasAutor(nota.autor) }),
            el('span', { class: 'ml-auto text-xs text-tinta-suave', text: formatoFecha(nota.fecha_creacion) }),
        ]),
        el('p', { class: 'text-base whitespace-pre-line break-words text-tinta', text: nota.contenido_nota }),
        barra,
        el('div', { class: 'mt-2 flex flex-wrap items-center justify-between gap-2 pt-1 border-t border-borde/30' }, [
            textoUtilidad,
            filaVotos,
        ]),
    ]);
}

function pintarNotas() {
    const lista = $('#lista-notas');
    const email = obtenerUsuario()?.email;
    const filtradas = filtrarNotas(todasLasNotas);
    $('#total-notas').textContent = String(todasLasNotas.length);

    if (!todasLasNotas.length) {
        lista.replaceChildren(
            el('li', {
                class: 'text-center py-4 text-tinta-suave font-medium',
                text: 'Aún no hay notas. ¡Sea el primero en aportar contexto!',
            }),
        );
        return;
    }

    if (!filtradas.length) {
        lista.replaceChildren(
            el('li', {
                class: 'text-center py-4 text-tinta-suave font-medium',
                text: 'Ninguna nota coincide con este filtro.',
            }),
        );
        return;
    }

    lista.replaceChildren(...filtradas.map((n) => tarjetaNota(n, email)));
}

async function recargarNotas() {
    try {
        const { notas } = await api(`/contenidos/${encodeURIComponent(idContenido)}/notas`);
        todasLasNotas = notas || [];
        pintarNotas();
    } catch (error) {
        avisar(error.message, 'error');
    }
}

function iniciarFiltrosNotas() {
    const botones = document.querySelectorAll('#filtro-notas-grupo button');
    botones.forEach((btn) => {
        btn.addEventListener('click', () => {
            botones.forEach((b) => b.classList.remove('active'));
            btn.classList.add('active');
            filtroNotasActivo = btn.dataset.filtroNota || 'todas';
            pintarNotas();
        });
    });
}

function iniciarFormularioNota(tieneVeredicto) {
    const formulario = $('#form-nota');
    const campo = $('#contenido_nota');
    const contador = $('#nota-contador');
    const boton = $('#nota-enviar');
    const btnCancelar = $('#btn-cancelar-nota');

    if (!tieneVeredicto) {
        formulario.hidden = true; // Precondición CU01: solo contenidos ya verificados
        return;
    }

    campo.addEventListener('input', () => {
        const len = campo.value.trim().length;
        contador.textContent = `${len} / 2000`;
        if (len > 250) {
            contador.classList.add('text-falso', 'font-bold');
        } else {
            contador.classList.remove('text-falso', 'font-bold');
        }
    });

    btnCancelar?.addEventListener('click', () => {
        formulario.reset();
        contador.textContent = '0 / 2000';
        contador.classList.remove('text-falso', 'font-bold');
        mostrarErroresFormulario(formulario, null);
    });

    formulario.addEventListener('submit', async (e) => {
        e.preventDefault();
        const texto = campo.value.trim();

        if (texto.length < 20) {
            mostrarErroresFormulario(formulario, {
                errores: { contenido_nota: ['La nota debe tener al menos 20 caracteres.'] },
                campo: () => 'La nota debe tener al menos 20 caracteres.',
            });
            campo.focus();
            return;
        }

        if (CARACTERES_INVALIDOS.test(texto)) {
            mostrarErroresFormulario(formulario, {
                errores: { contenido_nota: ['La nota contiene caracteres no permitidos (< > { } \\ |).'] },
                campo: () => 'La nota contiene caracteres no permitidos (< > { } \\ |).',
            });
            campo.focus();
            return;
        }

        estadoBoton(boton, true, 'Publicando…');
        try {
            await api(`/contenidos/${encodeURIComponent(idContenido)}/notas`, {
                method: 'POST',
                body: { contenido_nota: texto },
            });
            formulario.reset();
            contador.textContent = '0 / 2000';
            contador.classList.remove('text-falso', 'font-bold');
            mostrarErroresFormulario(formulario, null);
            avisar('Nota publicada exitosamente. ¡Gracias por aportar contexto!', 'exito');
            await recargarNotas();
        } catch (error) {
            if (!mostrarErroresFormulario(formulario, error)) avisar(error.message, 'error');
        } finally {
            estadoBoton(boton, false);
        }
    });
}

/* ─── Guardar en historial (CU03) ────────────────────────────────────────── */

function iniciarGuardado() {
    const boton = $('#btn-guardar');
    boton.addEventListener('click', async () => {
        estadoBoton(boton, true, 'Guardando…');
        try {
            const r = await api(`/historial/${encodeURIComponent(idContenido)}`, { method: 'POST' });
            avisar(r.mensaje || 'Noticia guardada en su historial.', 'exito');
            boton.textContent = '✔ Guardada';
            boton.disabled = true;
            boton.setAttribute('aria-pressed', 'true');
        } catch (error) {
            estadoBoton(boton, false);
            avisar(error.message, 'error'); // FA1 CU03: fallo de persistencia
        }
    });
}

/* ─── Compartir veredicto en WhatsApp o copiar enlace ─────────────────────── */

function iniciarCompartir(contenido, veredicto) {
    const boton = $('#btn-compartir');
    if (!boton) return;
    boton.addEventListener('click', async () => {
        const resumen = veredicto?.tipo_veredicto ? `Veredicto: ${veredicto.tipo_veredicto}` : 'Verificación ciudadana';
        const afirmacion = contenido?.contenido_original ? `“${contenido.contenido_original.slice(0, 120)}…”` : '';
        const texto = `🔍 *DAGAZ — Fact-Checking*\n${resumen}\n${afirmacion}\n\nConsulte el veredicto completo con fuentes aquí:\n${window.location.href}`;

        if (navigator.share) {
            try {
                await navigator.share({
                    title: 'Verificación DAGAZ',
                    text: texto,
                    url: window.location.href,
                });
                return;
            } catch {
                /* Si el usuario cancela o el dispositivo no admite share, pasa al portapapeles */
            }
        }

        try {
            await navigator.clipboard.writeText(texto);
            avisar('¡Enlace y resumen copiados al portapapeles para compartir!', 'exito');
        } catch {
            avisar(`Comparta este enlace: ${window.location.href}`, 'info');
        }
    });
}

/* ─── Panel Editorial / Revisor Humano (REQ-13 / TSK-3.7) ───────────────── */

function iniciarPanelEditorial(veredicto, contenido, hayFuentes) {
    const seccion = $('#panel-editorial');
    const formulario = $('#form-editorial');
    const boton = $('#btn-intervenir-veredicto');
    const usuario = obtenerUsuario();

    // Solo visible si el usuario tiene rol 'editor' o 'admin'
    const esEditor = usuario && ['editor', 'admin'].includes(String(usuario.rol || '').toLowerCase());
    if (!esEditor || !veredicto) {
        if (seccion) seccion.hidden = true;
        return;
    }

    seccion.hidden = false;

    const selectTipo = $('#editorial-tipo-veredicto');
    const inputConfianza = $('#editorial-confianza');
    const textareaExplicacion = $('#editorial-explicacion');

    if (selectTipo && veredicto.tipo_veredicto) selectTipo.value = veredicto.tipo_veredicto;
    if (inputConfianza && veredicto.nivel_confiabilidad) inputConfianza.value = Math.round(Number(veredicto.nivel_confiabilidad));
    if (textareaExplicacion && veredicto.explicacion) {
        textareaExplicacion.value = veredicto.explicacion.replace(/^\[Revisión Editorial por [^\]]+\]:\s*/, '');
    }

    formulario.addEventListener('submit', async (e) => {
        e.preventDefault();
        const tipo = selectTipo.value;
        const confianza = Number(inputConfianza.value);
        const motivo = $('#editorial-motivo').value.trim();
        const explicacion = textareaExplicacion.value.trim();

        if (motivo.length < 10) {
            mostrarErroresFormulario(formulario, {
                errores: { motivo_intervencion: ['El motivo debe tener al menos 10 caracteres.'] },
                campo: () => 'El motivo debe tener al menos 10 caracteres.',
            });
            $('#editorial-motivo').focus();
            return;
        }

        if (explicacion.length < 20) {
            mostrarErroresFormulario(formulario, {
                errores: { explicacion: ['La explicación editorial debe tener al menos 20 caracteres.'] },
                campo: () => 'La explicación editorial debe tener al menos 20 caracteres.',
            });
            textareaExplicacion.focus();
            return;
        }

        estadoBoton(boton, true, 'Registrando…');
        try {
            const r = await api(`/veredictos/${encodeURIComponent(veredicto.id_veredicto)}/intervenir`, {
                method: 'POST',
                body: {
                    tipo_veredicto: tipo,
                    nivel_confiabilidad: confianza,
                    motivo_intervencion: motivo,
                    explicacion: explicacion,
                },
            });
            avisar('Veredicto rectificado exitosamente por revisión editorial humana.', 'exito');
            mostrarErroresFormulario(formulario, null);
            pintarVeredicto(contenido, r.veredicto, hayFuentes);
            iniciarCompartir(contenido, r.veredicto);
        } catch (error) {
            if (!mostrarErroresFormulario(formulario, error)) avisar(error.message, 'error');
        } finally {
            estadoBoton(boton, false);
        }
    });
}

/* ─── Inicio ─────────────────────────────────────────────────────────────── */

export async function iniciar() {
    idContenido = document.body.dataset.idContenido;

    try {
        const datos = await api(`/verificacion/${encodeURIComponent(idContenido)}`);
        todasLasNotas = datos.notas || [];
        const fuentes = (datos.veredicto?.fuentes ?? []).filter((f) => f?.cita_contraste?.trim());
        const hayFuentes = fuentes.length > 0;
        pintarVeredicto(datos.contenido, datos.veredicto, hayFuentes);
        pintarFuentes(fuentes);
        pintarNotas();
        iniciarFiltrosNotas();
        iniciarFormularioNota(Boolean(datos.veredicto));
        iniciarGuardado();
        iniciarCompartir(datos.contenido, datos.veredicto);
        iniciarPanelEditorial(datos.veredicto, datos.contenido, hayFuentes);
        $('#acciones-resultado').hidden = false;
        $('#secciones-detalle').hidden = false;
    } catch (error) {
        $('#bloque-veredicto').replaceChildren(
            bloqueEstado({
                icono: error.status === 404 ? '🔎' : '⚠️',
                titulo: error.status === 404 ? 'No encontramos esta verificación' : 'No pudimos cargar el resultado',
                detalle: error.message,
                accion: el('a', { class: 'dz-btn-primario', text: 'Nueva verificación', attrs: { href: '/verificar' } }),
            }),
        );
    }
}
