@extends('layouts.app')

@section('titulo', 'Verificar Noticia o Contenido')
@section('pagina', 'verificar')

@section('contenido')
{{-- FASE 1: Formulario de entrada multicanal --}}
<div id="fase-entrada" class="space-y-4">
    <div class="text-center sm:text-left">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-tinta">Verificar Contenido</h1>
        <p class="text-base text-tinta-suave mt-1">
            Pegue una noticia, ingrese un enlace o cargue una captura/audio para contrastarla contra más de 80 fuentes oficiales y verificadas en Colombia.
        </p>
    </div>

    {{-- Alerta general de error --}}
    <div id="verificar-alerta" 
         class="dz-alerta border-falso bg-falso-bg text-falso font-medium" 
         role="alert" 
         tabindex="-1"
         hidden></div>

    <form id="form-verificar" novalidate class="dz-tarjeta p-0 overflow-hidden shadow-sm">
        {{-- Pestañas de tipo de entrada (WAI-ARIA Tabs) --}}
        <div role="tablist" aria-label="Canales de entrada para verificación" class="flex border-b-2 border-tinta/80 bg-neutro-bg">
            <button type="button" 
                    role="tab" 
                    id="tab-texto"
                    data-pestana="texto" 
                    aria-selected="true" 
                    aria-controls="panel-texto"
                    class="dz-tab">
                📝 Texto
            </button>
            <button type="button" 
                    role="tab" 
                    id="tab-url"
                    data-pestana="url" 
                    aria-selected="false" 
                    aria-controls="panel-url"
                    tabindex="-1"
                    class="dz-tab">
                🔗 Enlace (URL)
            </button>
            <button type="button" 
                    role="tab" 
                    id="tab-archivo"
                    data-pestana="archivo" 
                    aria-selected="false" 
                    aria-controls="panel-archivo"
                    tabindex="-1"
                    class="dz-tab">
                📁 Imagen / Audio
            </button>
        </div>

        <div class="p-4 sm:p-6 space-y-4">
            {{-- Panel 1: Texto Directo --}}
            <div id="panel-texto" role="tabpanel" data-panel="texto" aria-labelledby="tab-texto">
                <label for="contenido_original" class="dz-label">Texto o mensaje recibido:</label>
                <textarea id="contenido_original" 
                          name="contenido_original" 
                          rows="5" 
                          class="dz-campo resize-y"
                          placeholder="Pegue aquí el texto de la cadena de WhatsApp, mensaje o noticia que desea verificar (mínimo 10 caracteres)..."></textarea>
                <p data-error-de="contenido_original" class="dz-error" role="alert" hidden></p>
            </div>

            {{-- Panel 2: Enlace URL --}}
            <div id="panel-url" role="tabpanel" data-panel="url" aria-labelledby="tab-url" hidden>
                <label for="url_origen" class="dz-label">Enlace de la publicación o sitio web:</label>
                <input type="url" 
                       id="url_origen" 
                       name="url_origen" 
                       class="dz-campo"
                       placeholder="https://ejemplo.com/noticia-o-post...">
                <p class="text-xs text-tinta-suave mt-1.5">Admite páginas de noticias, publicaciones y portales informativos.</p>
                <p data-error-de="url_origen" class="dz-error" role="alert" hidden></p>
            </div>

            {{-- Panel 3: Archivo (Imagen OCR / Audio STT) --}}
            <div id="panel-archivo" role="tabpanel" data-panel="archivo" aria-labelledby="tab-archivo" hidden class="space-y-3">
                <label class="dz-label">Cargar captura de pantalla o nota de voz:</label>
                
                <div id="zona-archivo" 
                     class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-borde p-6 text-center cursor-pointer transition-colors hover:border-tinta hover:bg-neutro-bg/50">
                    <span class="text-4xl mb-2" aria-hidden="true">📂</span>
                    <p class="font-bold text-base text-tinta">Haga clic para seleccionar o arrastre el archivo aquí</p>
                    <p class="text-xs text-tinta-suave mt-1">Formatos admitidos: PNG, JPG, WEBP (capturas) o MP3, WAV, M4A (audios). Máximo 20 MB.</p>
                    <input type="file" 
                           id="archivo" 
                           name="archivo" 
                           accept="image/*,audio/*" 
                           class="sr-only">
                    <p id="archivo-nombre" class="mt-3 text-sm font-bold text-verdadero" hidden></p>
                </div>
                <p data-error-de="archivo" class="dz-error" role="alert" hidden></p>

                <div>
                    <label for="descripcion_archivo" class="dz-label text-sm">Resumen o contexto del archivo (obligatorio):</label>
                    <input type="text" 
                           id="descripcion_archivo" 
                           name="descripcion_archivo" 
                           class="dz-campo text-sm"
                           placeholder="Ej: Captura que afirma que se decretó racionamiento de agua mañana...">
                    <p data-error-de="descripcion_archivo" class="dz-error" role="alert" hidden></p>
                </div>
            </div>

            {{-- Botones de acción --}}
            <div class="flex flex-col-reverse sm:flex-row items-center justify-end gap-3 pt-3 border-t border-borde/40">
                <button type="button" 
                        id="btn-limpiar" 
                        class="dz-btn-secundario w-full sm:w-auto">
                    Limpiar campos
                </button>
                <button type="submit" 
                        id="btn-verificar" 
                        class="dz-btn-primario w-full sm:w-auto text-lg shadow-sm">
                    🔍 Verificar Ahora
                </button>
            </div>
        </div>
    </form>
</div>

{{-- FASE 2: Pantalla de carga y proceso de pensamiento interactiva --}}
<div id="fase-procesando" class="dz-tarjeta text-center py-8 shadow-sm" hidden tabindex="-1">
    {{-- Cabecera con pulso de razonamiento --}}
    <div class="mb-4 inline-flex items-center justify-center p-3 rounded-full bg-slate-900 border border-slate-700 shadow-md">
        <span class="inline-block w-4 h-4 rounded-full bg-emerald-400 animate-ping" aria-hidden="true"></span>
    </div>
    <h2 class="text-2xl font-extrabold text-tinta mb-1">Proceso de Pensamiento y Razonamiento Fáctico</h2>
    <p class="text-sm text-tinta-suave max-w-lg mx-auto mb-4">
        Cadena de razonamiento (Chain-of-Thought) en vivo: Análisis de premisas, consulta al Grafo de 80 fuentes de Neon y deliberación multi-modelo.
    </p>

    {{-- Barra de progreso en tiempo real --}}
    <div class="max-w-xl mx-auto mb-4">
        <div class="flex justify-between items-center text-xs font-mono font-semibold text-tinta-suave mb-1">
            <span id="streaming-progreso-label">Inicializando motor de inferencia...</span>
            <span id="streaming-progreso-num" class="text-emerald-600 font-bold">10%</span>
        </div>
        <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden border border-slate-300">
            <div id="streaming-progreso-bar" class="bg-emerald-500 h-2 rounded-full transition-all duration-300" style="width: 10%"></div>
        </div>
    </div>

    {{-- Consola de Pensamiento (Thinking Process) — Reemplaza íconos por log en vivo del razonamiento --}}
    <div class="max-w-xl mx-auto bg-slate-950 border border-slate-800 rounded-xl p-4 text-left shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-800 pb-2 mb-3">
            <div class="flex items-center gap-2 font-mono text-xs font-bold uppercase tracking-wider text-emerald-400">
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span>
                Pensamiento del Sistema & Agentes LLM
            </div>
            <span id="pensamiento-timer" class="text-[11px] font-mono text-slate-400">Razonando...</span>
        </div>
        
        {{-- Feed de logs de razonamiento en vivo --}}
        <div id="pensamiento-logs" class="font-mono text-xs text-slate-300 space-y-2 max-h-56 overflow-y-auto pr-1 leading-relaxed">
            <div class="text-slate-500">[0.0s] [SISTEMA] Iniciando canal reactivo SSE con motor Raiden...</div>
        </div>

        {{-- Streaming de tokens / Dictamen fáctico en vivo (Modalidad B) --}}
        <div id="streaming-token-box" class="mt-3 pt-3 border-t border-slate-800/80" hidden>
            <div class="text-[11px] font-mono font-bold uppercase tracking-wider text-amber-400 mb-1 flex items-center gap-2">
                <span class="inline-block w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                Dictamen Preliminar en Generación:
            </div>
            <p id="streaming-token-text" class="font-sans text-xs text-slate-100 italic leading-normal bg-slate-900/90 p-2.5 rounded-lg border border-slate-800"></p>
        </div>
    </div>
</div>
@endsection
