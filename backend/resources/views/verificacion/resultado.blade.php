@extends('layouts.app')

@section('titulo', 'Resultado de la Verificación')
@section('pagina', 'resultado')
@section('id-contenido', $id)

@section('contenido')
<div class="space-y-6">
    {{-- Navegación superior hacia atrás --}}
    <div class="flex items-center justify-between">
        <a href="{{ route('verificar') }}" class="inline-flex items-center gap-1.5 font-bold text-marca hover:underline min-h-12 py-2">
            ← Nueva verificación
        </a>
        <div id="acciones-resultado" hidden class="flex flex-wrap items-center gap-2">
            <button type="button" 
                    id="btn-compartir" 
                    class="dz-btn-primario text-sm py-2 px-3 flex items-center gap-1.5 shadow-sm"
                    title="Compartir veredicto en WhatsApp o copiar enlace">
                <span>📲</span> Compartir
            </button>
            <button type="button" 
                    id="btn-guardar" 
                    class="dz-btn-secundario text-sm py-2 px-3"
                    aria-pressed="false"
                    title="Guardar en mi historial personal">
                ⭐ Guardar noticia
            </button>
        </div>
    </div>

    {{-- Bloque central del Veredicto (Renderizado por resultado.js) --}}
    <section id="bloque-veredicto" aria-live="polite">
        {{-- Skeleton de carga inicial --}}
        <div class="dz-tarjeta space-y-4">
            <div class="dz-skeleton h-4 w-36"></div>
            <div class="dz-skeleton h-8 w-3/4"></div>
            <div class="dz-skeleton h-24 w-full"></div>
        </div>
    </section>

    {{-- Secciones de Detalle: Fuentes y Comunidad --}}
    <div id="secciones-detalle" hidden class="space-y-6">
        {{-- 1. Desglose de Fuentes Consultadas --}}
        <section class="dz-tarjeta space-y-3" aria-labelledby="titulo-fuentes">
            <div class="border-b-2 border-dashed border-borde/60 pb-2">
                <h2 id="titulo-fuentes" class="text-xl font-bold text-tinta flex items-center gap-2">
                    <span>📎</span> Fuentes de Contraste y Evidencias
                </h2>
                <p class="text-sm text-tinta-suave mt-0.5">
                    Medios, organismos oficiales y evidencias analizadas por el Grafo de Credibilidad y los modelos de IA del Consejo.
                </p>
            </div>
            <ul id="lista-fuentes" class="space-y-3"></ul>
        </section>

        {{-- 2. Notas de la Comunidad y Aporte Colaborativo (CU01 / CU02) --}}
        <section class="dz-tarjeta space-y-4" aria-labelledby="titulo-notas">
            <div class="border-b-2 border-dashed border-borde/60 pb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 id="titulo-notas" class="text-xl font-bold text-tinta flex items-center gap-2">
                        <span>💬</span> Notas de la Comunidad (<span id="total-notas">0</span>)
                    </h2>
                    <p class="text-sm text-tinta-suave mt-0.5">
                        Contexto adicional aportado por otros usuarios verificado y calificado colectivamente.
                    </p>
                </div>
                {{-- Filtro de notas comunitarias (CU02) --}}
                <div id="filtro-notas-grupo" class="flex items-center gap-1.5 self-start sm:self-auto" role="group" aria-label="Filtrar notas de la comunidad">
                    <button type="button" data-filtro-nota="todas" class="dz-btn-filtro-nota active">Todas</button>
                    <button type="button" data-filtro-nota="utiles" class="dz-btn-filtro-nota">👍 Útiles</button>
                    <button type="button" data-filtro-nota="no-utiles" class="dz-btn-filtro-nota">👎 No útiles</button>
                </div>
            </div>

            {{-- Formulario para redactar nota complementaria (CU01) --}}
            <form id="form-nota" novalidate class="rounded-xl border-2 border-borde/60 bg-neutro-bg p-4 space-y-3">
                <label for="contenido_nota" class="dz-label text-base">¿Tiene contexto verificado para agregar a esta noticia?</label>
                <textarea id="contenido_nota" 
                          name="contenido_nota" 
                          rows="3" 
                          maxlength="2000"
                          class="dz-campo resize-y text-base"
                          placeholder="Aporte datos objetivos, enlaces o contexto de primera mano (mínimo 20 caracteres)..."></textarea>
                <div class="flex items-center justify-between text-xs text-tinta-suave font-medium">
                    <span id="nota-contador">0 / 2000</span>
                    <p data-error-de="contenido_nota" class="dz-error !text-xs !mt-0" role="alert" hidden></p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <button type="button" 
                            id="btn-cancelar-nota" 
                            class="dz-btn-secundario text-sm py-2 px-3">
                        ✖ Cancelar
                    </button>
                    <button type="submit" 
                            id="nota-enviar" 
                            class="dz-btn-primario text-sm py-2 px-4 shadow-sm">
                        📤 Publicar nota
                    </button>
                </div>
            </form>

            {{-- Listado de notas publicadas --}}
            <ul id="lista-notas" class="space-y-3 mt-4" aria-live="polite"></ul>
        </section>

        {{-- 3. Panel de Intervención Editorial Humana (REQ-13 / TSK-3.7) --}}
        <section id="panel-editorial" class="dz-tarjeta space-y-4 border-2 border-marca/40 bg-neutro-bg/50 shadow-sm" hidden aria-labelledby="titulo-editorial">
            <div class="border-b-2 border-dashed border-borde/60 pb-2 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                    <h2 id="titulo-editorial" class="text-xl font-bold text-tinta flex items-center gap-2">
                        <span>⚖️</span> Panel de Intervención Editorial Humana (REQ-13)
                    </h2>
                    <p class="text-xs sm:text-sm text-tinta-suave mt-0.5">
                        Privilegio reservado para editores y administradores. Permite rectificar veredictos y justificar la decisión para auditoría forense.
                    </p>
                </div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-marca text-white self-start sm:self-auto">
                    Rol: Editor / Admin
                </span>
            </div>

            <form id="form-editorial" novalidate class="space-y-3.5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="editorial-tipo-veredicto" class="dz-label text-sm">Nuevo veredicto rectificado:</label>
                        <select id="editorial-tipo-veredicto" name="tipo_veredicto" required class="dz-campo text-sm min-h-10 py-2">
                            <option value="VERDADERO">✅ Verdadero</option>
                            <option value="PARCIAL">⚠️ Parcialmente cierto</option>
                            <option value="ENGANOSO">🟠 Engañoso</option>
                            <option value="FALSO">❌ Falso</option>
                            <option value="NO_VERIFICABLE">❔ No verificable</option>
                        </select>
                    </div>
                    <div>
                        <label for="editorial-confianza" class="dz-label text-sm">Nivel de confianza asignado (0 - 100%):</label>
                        <input type="number" id="editorial-confianza" name="nivel_confiabilidad" min="0" max="100" value="85" required class="dz-campo text-sm min-h-10 py-2">
                    </div>
                </div>

                <div>
                    <label for="editorial-motivo" class="dz-label text-sm">Motivo de la intervención (para registro forense):</label>
                    <input type="text" id="editorial-motivo" name="motivo_intervencion" minlength="10" maxlength="1000" placeholder="Ej: Corrección de sesgo temporal tras publicación de boletín oficial..." required class="dz-campo text-sm min-h-10 py-2">
                    <p data-error-de="motivo_intervencion" class="dz-error !text-xs !mt-0.5" role="alert" hidden></p>
                </div>

                <div>
                    <label for="editorial-explicacion" class="dz-label text-sm">Nueva explicación editorial sustentada:</label>
                    <textarea id="editorial-explicacion" name="explicacion" rows="3" minlength="20" maxlength="5000" placeholder="Describa el sustento editorial que fundamenta el cambio de veredicto (mínimo 20 caracteres)..." required class="dz-campo resize-y text-sm"></textarea>
                    <p data-error-de="explicacion" class="dz-error !text-xs !mt-0.5" role="alert" hidden></p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-borde/30">
                    <button type="submit" id="btn-intervenir-veredicto" class="dz-btn-primario text-sm py-2 px-5 shadow-sm">
                        ✍️ Registrar Intervención Editorial
                    </button>
                </div>
            </form>
        </section>
    </div>
</div>
@endsection
