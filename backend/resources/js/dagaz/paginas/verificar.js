import { api } from '../api';
import { obtenerToken } from '../sesion';
import { $, avisar, el, mostrarErroresFormulario } from '../ui';

const MAX_ARCHIVO = 20 * 1024 * 1024;
let pestanaActiva = 'texto';

function activarPestana(nombre) {
    pestanaActiva = nombre;
    document.querySelectorAll('[role="tab"]').forEach((tab) => {
        const activa = tab.dataset.pestana === nombre;
        tab.setAttribute('aria-selected', String(activa));
        tab.tabIndex = activa ? 0 : -1;
    });
    document.querySelectorAll('[role="tabpanel"]').forEach((panel) => {
        panel.hidden = panel.dataset.panel !== nombre;
    });
}

function iniciarPestanas() {
    const tabs = [...document.querySelectorAll('[role="tab"]')];
    tabs.forEach((tab, i) => {
        tab.addEventListener('click', () => activarPestana(tab.dataset.pestana));
        tab.addEventListener('keydown', (e) => {
            if (!['ArrowLeft', 'ArrowRight'].includes(e.key)) return;
            const siguiente = tabs[(i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
            activarPestana(siguiente.dataset.pestana);
            siguiente.focus();
        });
    });
}

function iniciarArchivo() {
    const input = $('#archivo');
    const zona = $('#zona-archivo');
    const nombre = $('#archivo-nombre');
    if (!input || !zona) return;

    const mostrar = () => {
        const archivo = input.files?.[0];
        nombre.textContent = archivo ? `📄 ${archivo.name} (${(archivo.size / 1048576).toFixed(1)} MB)` : '';
        nombre.hidden = !archivo;
    };
    input.addEventListener('change', mostrar);

    ['dragenter', 'dragover'].forEach((ev) =>
        zona.addEventListener(ev, (e) => {
            e.preventDefault();
            zona.classList.add('border-tinta', 'bg-neutro-bg');
        }),
    );
    ['dragleave', 'drop'].forEach((ev) =>
        zona.addEventListener(ev, (e) => {
            e.preventDefault();
            zona.classList.remove('border-tinta', 'bg-neutro-bg');
        }),
    );
    zona.addEventListener('drop', (e) => {
        if (e.dataTransfer?.files?.length) {
            input.files = e.dataTransfer.files;
            mostrar();
        }
    });
}

function construirPeticion(formulario) {
    const datos = new FormData(formulario);

    if (pestanaActiva === 'texto') {
        const texto = String(datos.get('contenido_original') || '').trim();
        if (texto.length < 10) return { error: ['contenido_original', 'Escriba al menos 10 caracteres.'] };
        return { cuerpo: { tipo_entrada: 'texto', contenido_original: texto } };
    }

    if (pestanaActiva === 'url') {
        const url = String(datos.get('url_origen') || '').trim();
        try {
            const u = new URL(url);
            if (!['http:', 'https:'].includes(u.protocol)) throw new Error();
        } catch {
            return { error: ['url_origen', 'Ingrese un enlace válido que empiece por https://'] };
        }
        return { cuerpo: { tipo_entrada: 'url', url_origen: url } };
    }

    const archivo = datos.get('archivo');
    if (!(archivo instanceof File) || !archivo.size) return { error: ['archivo', 'Seleccione una imagen o un audio.'] };
    if (archivo.size > MAX_ARCHIVO) return { error: ['archivo', 'El archivo supera el máximo de 20 MB.'] };

    const esImagen = archivo.type.startsWith('image/');
    const esAudio = archivo.type.startsWith('audio/');
    if (!esImagen && !esAudio) return { error: ['archivo', 'Solo se admiten imágenes o notas de voz.'] };

    const descripcion = String(datos.get('descripcion_archivo') || '').trim();
    if (descripcion.length < 10) {
        return { error: ['descripcion_archivo', 'Describa en al menos 10 caracteres qué dice el archivo.'] };
    }

    const cuerpo = new FormData();
    cuerpo.append('tipo_entrada', esImagen ? 'imagen' : 'audio');
    cuerpo.append('contenido_original', descripcion);
    cuerpo.append('archivo', archivo);
    return { cuerpo };
}

// ─── GESTOR DEL PROCESO DE PENSAMIENTO (THINKING PROCESS) ───────────────────
function crearGestorPensamiento() {
    const contenedorLogs = $('#pensamiento-logs');
    const labelProgreso = $('#streaming-progreso-label');
    const numProgreso = $('#streaming-progreso-num');
    const barProgreso = $('#streaming-progreso-bar');
    const timerEtiqueta = $('#pensamiento-timer');
    const tokenBox = $('#streaming-token-box');
    const tokenText = $('#streaming-token-text');

    const tiempoInicio = Date.now();
    if (contenedorLogs) contenedorLogs.innerHTML = '';
    if (tokenBox) tokenBox.hidden = true;
    if (tokenText) tokenText.textContent = '';

    const intervalTimer = setInterval(() => {
        if (!timerEtiqueta) return;
        const segundos = ((Date.now() - tiempoInicio) / 1000).toFixed(1);
        timerEtiqueta.textContent = `Razonando (${segundos}s)...`;
    }, 100);

    const agregarLog = (tag, mensaje, colorTag = 'text-emerald-400') => {
        if (!contenedorLogs) return;
        const segundos = ((Date.now() - tiempoInicio) / 1000).toFixed(1);
        const div = document.createElement('div');
        div.className = 'flex items-start gap-1.5 transition-opacity duration-200';
        div.innerHTML = `<span class="text-slate-500 shrink-0">[+${segundos}s]</span> <span class="${colorTag} font-bold shrink-0">[${tag}]</span> <span class="text-slate-200">${mensaje}</span>`;
        contenedorLogs.appendChild(div);
        contenedorLogs.scrollTop = contenedorLogs.scrollHeight;
    };

    const actualizarProgreso = (porcentaje, etiqueta) => {
        const p = Math.min(100, Math.max(5, Number(porcentaje) || 10));
        if (barProgreso) barProgreso.style.width = `${p}%`;
        if (numProgreso) numProgreso.textContent = `${p}%`;
        if (labelProgreso && etiqueta) labelProgreso.textContent = etiqueta;
    };

    return {
        agregarLog,
        actualizarProgreso,
        agregarToken(chunk) {
            if (tokenBox && tokenBox.hidden) tokenBox.hidden = false;
            if (tokenText) {
                tokenText.textContent += chunk;
                if (contenedorLogs) contenedorLogs.scrollTop = contenedorLogs.scrollHeight;
            }
        },
        finalizar(mensaje = 'Razonamiento completado con éxito.') {
            clearInterval(intervalTimer);
            if (timerEtiqueta) timerEtiqueta.textContent = '✓ Concluido';
            actualizarProgreso(100, mensaje);
            agregarLog('SÍNTESIS', 'Veredicto emitido y persistido en la base de datos.', 'text-cyan-400');
        },
        detenerTimer() {
            clearInterval(intervalTimer);
        }
    };
}

async function procesarConStreaming(cuerpo, gestor) {
    if (!('ReadableStream' in window) || !('TextDecoder' in window)) {
        throw new Error('Streaming no soportado en este navegador.');
    }

    const token = obtenerToken();
    const headers = { Accept: 'text/event-stream' };
    if (token) headers.Authorization = `Bearer ${token}`;

    let bodyData;
    if (cuerpo instanceof FormData) {
        bodyData = cuerpo;
    } else {
        headers['Content-Type'] = 'application/json';
        bodyData = JSON.stringify(cuerpo);
    }

    gestor.agregarLog('CONEXIÓN', 'Estableciendo canal reactivo SSE con DAGAZ...', 'text-slate-400');

    const res = await fetch('/api/v1/verificacion/stream', {
        method: 'POST',
        headers,
        body: bodyData,
    });

    if (!res.ok) {
        const errorJson = await res.json().catch(() => ({}));
        throw new Error(errorJson.mensaje || `Error en el servidor (${res.status})`);
    }

    const reader = res.body.getReader();
    const decoder = new TextDecoder('utf-8');
    let buffer = '';
    let finalRecibido = null;

    while (true) {
        const { done, value } = await reader.read();
        if (done) break;

        buffer += decoder.decode(value, { stream: true });
        const bloques = buffer.split('\n\n');
        buffer = bloques.pop() || '';

        for (const bloque of bloques) {
            if (!bloque.trim()) continue;
            let evento = 'message';
            let data = '';

            for (const linea of bloque.split('\n')) {
                if (linea.startsWith('event: ')) evento = linea.substring(7).trim();
                else if (linea.startsWith('data: ')) data = linea.substring(6).trim();
            }

            if (!data) continue;

            let parsed = data;
            try { parsed = JSON.parse(data); } catch {}

            const progreso = Number(parsed?.progreso) || 20;
            const mensaje = parsed?.mensaje || '';

            if (evento === 'inicio') {
                gestor.actualizarProgreso(progreso, mensaje);
                gestor.agregarLog('SISTEMA', mensaje, 'text-blue-400');
            } else if (evento === 'paso') {
                gestor.actualizarProgreso(progreso, mensaje);
                const etapa = (parsed?.etapa || 'PROCESO').toUpperCase();
                gestor.agregarLog(etapa, mensaje, 'text-emerald-400');
            } else if (evento === 'idioma') {
                gestor.actualizarProgreso(progreso, mensaje);
                gestor.agregarLog('INTERLINGÜE', mensaje, 'text-purple-400');
            } else if (evento === 'afirmaciones') {
                gestor.actualizarProgreso(progreso, mensaje);
                const lista = Array.isArray(parsed?.afirmaciones) ? parsed.afirmaciones.map(a => `«${a}»`).join(', ') : '';
                gestor.agregarLog('ATÓMICO', `${mensaje} ${lista ? `[${lista}]` : ''}`, 'text-amber-400');
            } else if (evento === 'fuentes') {
                gestor.actualizarProgreso(progreso, mensaje);
                const nombres = Array.isArray(parsed?.fuentes) ? parsed.fuentes.map(f => `${f.nombre} (${f.credibilidad}%)`).join(', ') : '';
                gestor.agregarLog('RAG_GRAFO', `${mensaje} ${nombres ? `→ Fuentes: ${nombres}` : ''}`, 'text-teal-400');
            } else if (evento === 'archive') {
                gestor.actualizarProgreso(progreso, mensaje);
                gestor.agregarLog('WAYBACK', mensaje, 'text-indigo-400');
            } else if (evento === 'sesgo') {
                gestor.actualizarProgreso(progreso, mensaje);
                gestor.agregarLog('SESGO', `${mensaje} [Score: ${parsed?.sesgo_score ?? 0}]`, 'text-yellow-400');
            } else if (evento === 'consejo') {
                gestor.actualizarProgreso(progreso, mensaje);
                gestor.agregarLog('CONSEJO_IA', mensaje, 'text-pink-400');
            } else if (evento === 'sintesis') {
                gestor.actualizarProgreso(progreso, mensaje);
                gestor.agregarLog('VEREDICTO', `Dictamen: ${parsed?.tipo_veredicto} (${parsed?.confianza}% confianza). Generando fundamentación...`, 'text-cyan-400');
            } else if (evento === 'token') {
                gestor.agregarToken(parsed?.chunk || '');
            } else if (evento === 'final') {
                finalRecibido = parsed;
                gestor.finalizar();
            } else if (evento === 'error') {
                throw new Error(parsed?.mensaje || 'Error durante la verificación.');
            }
        }
    }

    return finalRecibido;
}

function mostrarFase(fase) {
    const fEntrada = $('#fase-entrada');
    const fProcesando = $('#fase-procesando');
    if (fEntrada) fEntrada.hidden = fase !== 'entrada';
    if (fProcesando) {
        fProcesando.hidden = fase !== 'procesando';
        if (fase === 'procesando') fProcesando.focus();
    }
}

export function iniciar() {
    const formulario = $('#form-verificar');
    const alerta = $('#verificar-alerta');
    if (!formulario) return;

    if (new URLSearchParams(window.location.search).get('bienvenida')) {
        avisar('¡Cuenta creada! Ya puede verificar su primera noticia.', 'exito');
    }

    iniciarPestanas();
    iniciarArchivo();

    $('#btn-limpiar')?.addEventListener('click', () => {
        formulario.reset();
        const archivoNombre = $('#archivo-nombre');
        if (archivoNombre) archivoNombre.hidden = true;
        mostrarErroresFormulario(formulario, null);
        if (alerta) alerta.hidden = true;
    });

    formulario.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (alerta) alerta.hidden = true;
        mostrarErroresFormulario(formulario, null);

        const { cuerpo, error } = construirPeticion(formulario);
        if (error) {
            const [campo, mensaje] = error;
            mostrarErroresFormulario(formulario, { errores: { [campo]: [mensaje] }, campo: () => mensaje });
            formulario.querySelector(`[name="${campo}"]`)?.focus();
            return;
        }

        mostrarFase('procesando');
        const gestor = crearGestorPensamiento();

        try {
            const respuesta = await procesarConStreaming(cuerpo, gestor);
            const id = respuesta?.contenido?.id_contenido;
            setTimeout(() => {
                window.location.assign(id ? `/verificar/${encodeURIComponent(id)}` : '/historial');
            }, 800);
        } catch (streamErr) {
            gestor.agregarLog('AVISO', `Streaming interrumpido (${streamErr.message}). Conmutando a modo de contingencia síncrono...`, 'text-amber-400');
            try {
                const respuesta = await api('/verificacion', { method: 'POST', body: cuerpo });
                gestor.finalizar('Verificación completada mediante canal de contingencia.');
                const id = respuesta?.contenido?.id_contenido;
                setTimeout(() => {
                    window.location.assign(id ? `/verificar/${encodeURIComponent(id)}` : '/historial');
                }, 800);
            } catch (err) {
                gestor.detenerTimer();
                mostrarFase('entrada');
                if (!mostrarErroresFormulario(formulario, err)) {
                    if (alerta) {
                        alerta.textContent = err.message || streamErr.message;
                        alerta.hidden = false;
                        alerta.focus();
                    }
                }
            }
        }
    });
}
