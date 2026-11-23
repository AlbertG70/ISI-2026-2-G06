<?php

namespace App\Services;

use App\Adapters\AdaptadorMulticanal;
use App\Adapters\Ia\IaDriverFactory;
use App\Contracts\AdaptadorApiIaInterface;
use App\Contracts\ConectorArchiveInterface;
use App\Contracts\ServicioInterlingueInterface;
use App\Models\Contenido;
use App\Models\Fuente;
use App\Models\Historial;
use App\Models\LogComunicadorApi;
use App\Models\ProcesoVerificacion;
use App\Models\Veredicto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * VerificacionService — Motor de Verificación de Contenidos DAGAZ
 *
 * Implementa el núcleo de la lógica de negocio descrita en CU06.
 * Orquesta el pipeline completo:
 *   1. Normalización multicanal (AdaptadorMulticanal)
 *   2. Descomposición de afirmaciones atómicas (AdaptadorAPI_IA)
 *   3. Contraste de fuentes del grafo de credibilidad (BDVerificacion)
 *   4. Análisis semántico ponderado (AdaptadorAPI_IA)
 *   5. Emisión de veredicto estructurado
 *   6. Registro de telemetría (logs_comunicador_api)
 *
 * Patrón Strategy: el adaptador de IA se intercambia dinámicamente
 * según la variable de entorno DAGAZ_IA_PROVEEDOR sin modificar este servicio.
 *
 * @see \App\Adapters\AdaptadorMulticanal
 * @see \App\Adapters\Ia\IaDriverFactory
 * @package App\Services
 */
class VerificacionService
{
    /** Umbral de confiabilidad mínimo para emitir veredicto definitivo (en %) */
    private const UMBRAL_CONFIABLE = 70.0;

    /** Umbral mínimo de fuentes para no etiquetar como "No verificado" */
    private const MIN_FUENTES_REQUERIDAS = 2;

    /**
     * @param  \App\Adapters\AdaptadorMulticanal            $adaptadorMulticanal
     * @param  \App\Services\CircuitBreakerService          $circuitBreakerService
     * @param  \App\Contracts\ConectorArchiveInterface|null     $archiveOrgService
     * @param  \App\Contracts\ServicioInterlingueInterface|null $servicioInterlingue
     * @param  \App\Services\BusquedaSemanticaService|null      $busquedaSemanticaService
     * @param  \App\Services\MultiModeloHarnessService|null     $multiModeloHarnessService
     * @param  \App\Services\BusquedaWebService|null            $busquedaWebService
     */
    public function __construct(
        private readonly AdaptadorMulticanal $adaptadorMulticanal,
        private readonly CircuitBreakerService $circuitBreakerService,
        private readonly ?ConectorArchiveInterface $archiveOrgService = null,
        private readonly ?ServicioInterlingueInterface $servicioInterlingue = null,
        private readonly ?BusquedaSemanticaService $busquedaSemanticaService = null,
        private readonly ?MultiModeloHarnessService $multiModeloHarnessService = null,
        private readonly ?BusquedaWebService $busquedaWebService = null,
    ) {}

    /**
     * Procesar un contenido multicanal y emitir un veredicto estructurado.
     *
     * CU06 — Flujo Principal. Coordina el pipeline completo de verificación.
     *
     * @param  string                             $tipoEntrada   Canal: TEXTO|URL|IMAGEN_OCR|RED_SOCIAL
     * @param  string|null                        $contenidoOriginal  Texto directo o transcrito
     * @param  string|null                        $urlOrigen     URL de origen (para tipo URL)
     * @param  \Illuminate\Http\UploadedFile|null $archivo       Archivo subido (imagen OCR)
     * @param  string|null                        $idUsuario     UUID del usuario autenticado
     * @param  callable|null                      $onProgress    Callback reactivo de streaming (SSE)
     * @return array{contenido: array, proceso: array, veredicto: array, fuentes: array}
     * @throws \Throwable  Si el pipeline falla de forma irrecuperable
     */
    public function procesarContenido(
        string $tipoEntrada,
        ?string $contenidoOriginal,
        ?string $urlOrigen,
        ?UploadedFile $archivo,
        ?string $idUsuario,
        ?callable $onProgress = null
    ): array {
        @set_time_limit(120);

        // ─── PASO 1: Normalización multicanal ─────────────────────────────────
        $onProgress?->__invoke('paso', [
            'etapa'    => 'ingesta',
            'progreso' => 10,
            'mensaje'  => 'Validando entrada y procesando texto / OCR...',
        ]);

        $textoProcesado = $this->normalizarEntrada(
            tipoEntrada:       $tipoEntrada,
            contenidoOriginal: $contenidoOriginal,
            urlOrigen:         $urlOrigen,
            archivo:           $archivo,
        );

        $resultado = DB::transaction(function () use (
            $tipoEntrada,
            $textoProcesado,
            $urlOrigen,
            $idUsuario,
            $onProgress
        ) {
            $tipoEntradaDb = match (strtoupper(trim($tipoEntrada))) {
                'IMAGEN', 'IMAGEN_OCR' => 'IMAGEN_OCR',
                'RED_SOCIAL'           => 'RED_SOCIAL',
                'URL'                  => 'URL',
                default                => 'TEXTO',
            };

            // ─── PASO 2: Persistir contenido en estado "PROCESANDO" ───────────
            $contenido = Contenido::create([
                'id_usuario'         => $idUsuario,
                'tipo_entrada'       => $tipoEntradaDb,
                'contenido_original' => $textoProcesado,
                'url_origen'         => $urlOrigen,
                'estado'             => 'PROCESANDO',
            ]);

            // Registrar en historial si hay usuario
            if ($idUsuario) {
                Historial::create([
                    'id_usuario'   => $idUsuario,
                    'id_contenido' => $contenido->id_contenido,
                    'accion'       => 'verificado',
                ]);
            }

            // ─── PASO 3: Crear proceso de verificación ────────────────────────
            $proceso = ProcesoVerificacion::create([
                'id_contenido'        => $contenido->id_contenido,
                'score_confiabilidad' => 0.00,
            ]);

            $onProgress?->__invoke('paso', [
                'etapa'        => 'proceso_iniciado',
                'progreso'     => 18,
                'mensaje'      => 'Proceso de verificación inicializado en la base de datos.',
                'id_contenido' => $contenido->id_contenido,
                'id_proceso'   => $proceso->id_proceso,
            ]);

            try {
                // ─── PASO 4: Adaptador de IA (patrón Strategy con Circuit Breaker)
                $proveedorConfigurado = config('dagaz.ia_proveedor', 'openai');
                if ($this->circuitBreakerService->puedeEjecutar(800)) {
                    try {
                        $adaptadorIa = IaDriverFactory::crear($proveedorConfigurado);
                    } catch (\Throwable $e) {
                        Log::warning('[DAGAZ] Proveedor IA no disponible (' . $e->getMessage() . '). Activando driver local de contingencia.');
                        $adaptadorIa = IaDriverFactory::crear('local');
                    }
                } else {
                    Log::warning('[DAGAZ][CircuitBreaker] Límite alcanzado. Activando driver local de contingencia.');
                    $adaptadorIa = IaDriverFactory::crear('local');
                }

                // ─── PASO 4.5: Análisis de idioma y soporte interlingüe (TSK-4.4 & REQ-19)
                $analisisIdioma = null;
                $traduccionEs = null;
                $consultasInterlingues = null;

                if ($this->servicioInterlingue) {
                    $analisisIdioma = $this->servicioInterlingue->detectarIdioma($textoProcesado);

                    if ($analisisIdioma) {
                        $onProgress?->__invoke('idioma', [
                            'etapa'         => 'interlingue',
                            'progreso'      => 24,
                            'mensaje'       => "Idioma detectado: {$analisisIdioma['nombre']} ({$analisisIdioma['codigo']}).",
                            'es_anglosajon' => $analisisIdioma['es_anglosajon'],
                        ]);
                    }

                    // Si la afirmación original está en inglés, traducir para el lector colombiano
                    if ($analisisIdioma['es_anglosajon'] || $analisisIdioma['codigo'] === 'en') {
                        $resTraduccion = $this->servicioInterlingue->traducir($textoProcesado, 'es');
                        $traduccionEs = $resTraduccion['texto_traducido'];
                    }

                    // Generar consultas en inglés para contrastar con agencias internacionales Nivel 1
                    $consultasInterlingues = $this->servicioInterlingue->generarConsultasInterlingues($textoProcesado);
                }

                // ─── PASO 5: Descomposición de afirmaciones atómicas (REQ-01, REQ-03)
                $afirmaciones = $this->descomponerAfirmaciones($adaptadorIa, $textoProcesado, $proceso->id_proceso);

                $onProgress?->__invoke('afirmaciones', [
                    'etapa'        => 'afirmaciones',
                    'progreso'     => 35,
                    'mensaje'      => count($afirmaciones) . ' afirmación(es) atómica(s) extraída(s) para análisis.',
                    'afirmaciones' => $afirmaciones,
                ]);

                // ─── PASO 6: Detección de metadata y recuperación de fuentes y noticias (REQ-05)
                $analisisCategoria = $this->busquedaSemanticaService
                    ? $this->busquedaSemanticaService->detectarCategoriaMetadata($textoProcesado)
                    : ['categoria' => 'Gobierno y Política', 'confianza' => 50.0];
                $categoriaMetadata = $analisisCategoria['categoria'];

                $busquedaNoticias = $this->busquedaSemanticaService
                    ? $this->busquedaSemanticaService->buscarNoticiasPorFuenteYMetadata($textoProcesado, $categoriaMetadata)
                    : ['categoria_metadata' => $categoriaMetadata, 'fuentes_que_responden' => collect(), 'noticias_por_fuente' => []];

                $fuentesQueResponden = $busquedaNoticias['fuentes_que_responden'];
                $noticiasPorFuente   = $busquedaNoticias['noticias_por_fuente'];

                // Recuperación de fuentes del grafo guiándose por la metadata y las fuentes que responden
                $fuentes = $this->consultarFuentesRelevantes($textoProcesado, $fuentesQueResponden);

                // ─── PASO 6.2: Búsqueda Web en tiempo real (cotizaciones, volatilidad o RAG vacío) ───
                $evidenciasWeb = [];
                $esTemaVolatil = $this->busquedaWebService && $this->busquedaWebService->esTemaActualOVolatil($textoProcesado);
                if ($this->busquedaWebService && ($esTemaVolatil || $fuentes->isEmpty())) {
                    $onProgress?->__invoke('paso', [
                        'etapa'    => 'busqueda_web',
                        'progreso' => 58,
                        'mensaje'  => 'Consultando información web en tiempo real (cotizaciones, indicadores y prensa del día)...',
                    ]);

                    $evidenciasWeb = $this->busquedaWebService->buscarEnLaWeb($textoProcesado);
                }

                $onProgress?->__invoke('fuentes', [
                    'etapa'         => 'fuentes',
                    'progreso'      => 52,
                    'mensaje'       => "Categoría: {$categoriaMetadata}. Contrastando con {$fuentes->count()} fuentes del Grafo de Credibilidad" . (!empty($evidenciasWeb) ? ' + Búsqueda Web en tiempo real.' : '.'),
                    'categoria'     => $categoriaMetadata,
                    'total_fuentes' => $fuentes->count(),
                    'fuentes'       => $fuentes->map(fn ($f) => [
                        'nombre'       => $f->nombre,
                        'credibilidad' => $f->indice_credibilidad,
                        'tipo'         => $f->tipo_fuente,
                    ])->take(6)->values()->all(),
                ]);

                // ─── PASO 6.5: Detección de descontextualización histórica (REQ-10, TSK-3.2)
                $analisisArchive = null;
                $urlParaAuditar = $urlOrigen ?? $this->extraerUrlDeTexto($textoProcesado);
                if ($urlParaAuditar && $this->archiveOrgService) {
                    $analisisArchive = $this->archiveOrgService->detectarDescontextualizacion(
                        url: $urlParaAuditar,
                        idProceso: $proceso->id_proceso
                    );

                    if ($analisisArchive) {
                        $onProgress?->__invoke('archive', [
                            'etapa'              => 'archive',
                            'progreso'           => 64,
                            'mensaje'            => 'Auditoría en Archive.org / Wayback Machine: ' . ($analisisArchive['descontextualizado'] ? 'Descontextualización temporal detectada.' : 'Línea temporal consistente.'),
                            'descontextualizado' => $analisisArchive['descontextualizado'],
                        ]);
                    }
                }

                // ─── PASO 7: Análisis de lenguaje y sesgo (REQ-04)
                $analisisLenguaje = $this->analizarLenguaje($adaptadorIa, $textoProcesado, $proceso->id_proceso);

                $onProgress?->__invoke('sesgo', [
                    'etapa'       => 'sesgo',
                    'progreso'    => 72,
                    'mensaje'     => "Análisis de lenguaje: {$analisisLenguaje['analisis']}",
                    'sesgo_score' => $analisisLenguaje['sesgo_score'],
                    'tipo'        => $analisisLenguaje['tipo'],
                ]);

                // ─── PASO 8: Verificación de hechos y contraste semántico (REQ-04, REQ-06)
                $resultadoContraste = $this->contrastarConFuentes(
                    $adaptadorIa,
                    $textoProcesado,
                    $afirmaciones,
                    $fuentes,
                    $proceso->id_proceso,
                    $noticiasPorFuente,
                    $evidenciasWeb
                );

                // ─── PASO 9: Cálculo del score ponderado ──────────────────────
                $scoreConfiabilidad = $this->calcularScore(
                    $fuentes,
                    $resultadoContraste['coherencia_score'],
                    $analisisLenguaje['sesgo_score'],
                    (bool) ($resultadoContraste['ia_respondio'] ?? false)
                );

                // ─── PASO 10: Determinar tipo de veredicto ───────────────────
                [$tipoVeredicto, $explicacion] = $this->determinarVeredicto(
                    $scoreConfiabilidad,
                    $fuentes,
                    $resultadoContraste,
                    $analisisLenguaje,
                    $categoriaMetadata
                );

                // Ajustar explicación ante contenido de origen anglosajón (REQ-19)
                if ($analisisIdioma && ($analisisIdioma['es_anglosajon'] || $analisisIdioma['codigo'] === 'en')) {
                    $explicacion .= "\n\n[Origen interlingüe (REQ-19): Contenido detectado en idioma inglés. Se realizó contraste cruzado con agencias internacionales de Nivel 1 (Reuters, BBC, AP, OMS)].";
                }

                // Ajustar explicación y veredicto ante descontextualización histórica (REQ-10)
                if ($analisisArchive && $analisisArchive['descontextualizado']) {
                    $explicacion .= "\n\n[" . $analisisArchive['diagnostico'] . "]";
                    if ($analisisArchive['nivel_riesgo'] === 'CRITICO' && $tipoVeredicto === 'VERDADERO') {
                        $tipoVeredicto = 'ENGAÑOSO';
                    }
                }

                // ─── PASO 10.5: Verificación Multi-Modelo / Consejo IA ────────
                $evaluacionMultiModelo = null;
                if ($this->multiModeloHarnessService && config('dagaz.multi_modelo.habilitado', true)) {
                    $onProgress?->__invoke('paso', [
                        'etapa'    => 'consejo_iniciando',
                        'progreso' => 80,
                        'mensaje'  => 'Consultando al Consejo Multi-Modelo en paralelo (GPT-4o, Claude 3.5 Sonnet, Gemini Flash, DeepSeek-R1)...',
                    ]);

                    try {
                        $detallesHechos = $resultadoContraste['verificacion_hechos']['detalles'] ?? [];
                        $prefijosTangenciales = [];
                        foreach ($detallesHechos as $detalleHecho) {
                            if (self::esContextualizacionTangencial($detalleHecho) && !empty($detalleHecho['fuente'])) {
                                $estadoDetalle = (string) ($detalleHecho['estado_contraste'] ?? 'CONTEXTUALIZA');
                                $prefijosTangenciales[] = $detalleHecho['fuente'] . " ({$estadoDetalle}):";
                            }
                        }

                        $citasParaHarness = [];
                        if (!empty($resultadoContraste['citas'])) {
                            foreach ($resultadoContraste['citas'] as $idFuenteCita => $cita) {
                                if (empty($cita) || str_contains($cita, 'No registra cobertura')) {
                                    continue;
                                }
                                if (self::esContextualizacionTangencial($detallesHechos[$idFuenteCita] ?? null)) {
                                    continue;
                                }
                                $citasParaHarness[] = $cita;
                            }
                        }
                        if (!empty($resultadoContraste['verificacion_hechos']['hallazgos'])) {
                            foreach ($resultadoContraste['verificacion_hechos']['hallazgos'] as $hallazgo) {
                                $esTangencial = false;
                                foreach ($prefijosTangenciales as $pref) {
                                    if (str_starts_with((string) $hallazgo, $pref)) {
                                        $esTangencial = true;
                                        break;
                                    }
                                }
                                if (!$esTangencial) {
                                    $citasParaHarness[] = $hallazgo;
                                }
                            }
                        }
                        if (!empty($evidenciasWeb)) {
                            foreach ($evidenciasWeb as $ew) {
                                $citasParaHarness[] = "[Web en vivo: {$ew['fuente']}] {$ew['fragmento']}";
                            }
                        }

                        $evaluacionMultiModelo = $this->multiModeloHarnessService->evaluarConConsejo(
                            afirmacion:          $textoProcesado,
                            veredictoPreliminar: $tipoVeredicto,
                            citasEvidencia:      $citasParaHarness,
                            idProceso:           $proceso->id_proceso
                        );

                        if (!empty($evaluacionMultiModelo['habilitado'])) {
                            $onProgress?->__invoke('consejo', [
                                'etapa'      => 'consejo_completado',
                                'progreso'   => 88,
                                'mensaje'    => $evaluacionMultiModelo['resumen_consenso'] ?? 'Deliberación multi-modelo finalizada.',
                                'calibrado'  => $evaluacionMultiModelo['calibracion_aplicada'] ?? false,
                                'consenso'   => $evaluacionMultiModelo['veredicto_consenso'] ?? null,
                                'porcentaje' => $evaluacionMultiModelo['porcentaje_consenso'] ?? null,
                            ]);
                        }

                        if (!empty($evaluacionMultiModelo['calibracion_aplicada'])) {
                            $tipoVeredicto = $evaluacionMultiModelo['veredicto_calibrado'];
                            if ($tipoVeredicto === 'FALSO') {
                                $scoreConfiabilidad = 0.0;
                            } elseif (!empty($evaluacionMultiModelo['confianza_promedio'])) {
                                $scoreConfiabilidad = (float) $evaluacionMultiModelo['confianza_promedio'];
                            }

                            $introCalibrada = match ($tipoVeredicto) {
                                'FALSO'     => 'La afirmación analizada contradice los hechos documentados y ha sido clasificada como FALSA tras el contraste cruzado con fuentes y organismos oficiales.',
                                'VERDADERO' => 'La afirmación analizada ha sido confirmada como VERDADERA con respaldo de fuentes y evidencias verificadas.',
                                'ENGAÑOSO'  => 'La afirmación analizada presenta elementos engañosos o fuera de contexto según el análisis de los hechos documentados.',
                                'PARCIAL'   => 'La afirmación analizada presenta elementos parcialmente ciertos pero carece de contexto indispensable.',
                                default     => 'No fue posible establecer una conclusión concluyente con la evidencia disponible en este momento.',
                            };

                            $bloqueFuentesActual = '';
                            if (str_contains($explicacion, 'Contraste de hechos en fuentes:')) {
                                preg_match('/(Contraste de hechos en fuentes:.*?)(?:\n\n|\z)/s', $explicacion, $m);
                                if (!empty($m[1])) {
                                    $bloqueFuentesActual = "\n\n" . trim($m[1]);
                                }
                            }

                            $explicacion = $introCalibrada . $bloqueFuentesActual . "\n\n[" . $evaluacionMultiModelo['resumen_consenso'] . "]";
                        } elseif (!empty($evaluacionMultiModelo['resumen_consenso'])) {
                            if (
                                !empty($evaluacionMultiModelo['confianza_promedio'])
                                && ($evaluacionMultiModelo['veredicto_consenso'] ?? null) === $tipoVeredicto
                                && $tipoVeredicto !== 'FALSO'
                            ) {
                                $scoreConfiabilidad = (float) $evaluacionMultiModelo['confianza_promedio'];
                                $explicacion = (string) preg_replace(
                                    '/(confiabilidad(?:\s+del|:)\s*)\d+(?:\.\d+)?%/i',
                                    '${1}' . $scoreConfiabilidad . '%',
                                    $explicacion,
                                    1
                                );
                            }
                            $explicacion .= "\n\n[" . $evaluacionMultiModelo['resumen_consenso'] . "]";
                        }
                    } catch (\Throwable $e) {
                        Log::warning('[VerificacionService] Error en evaluación multi-modelo: ' . $e->getMessage());
                    }
                }

                // ─── PASO 11: Persistir proceso con análisis completo ─────────
                $contrasteGuardar = [
                    'categoria_metadata'  => $categoriaMetadata,
                    'afirmaciones'        => $afirmaciones,
                    'contraste'           => $resultadoContraste,
                    'verificacion_hechos' => $resultadoContraste['verificacion_hechos'] ?? null,
                ];
                if ($analisisArchive) {
                    $contrasteGuardar['descontextualizacion_historica'] = $analisisArchive;
                }
                if ($analisisIdioma) {
                    $contrasteGuardar['analisis_interlingue'] = [
                        'idioma'        => $analisisIdioma,
                        'traduccion_es' => $traduccionEs,
                        'consultas_en'  => $consultasInterlingues,
                    ];
                }
                if ($evaluacionMultiModelo) {
                    $contrasteGuardar['multi_modelo_evaluacion'] = $evaluacionMultiModelo;
                }

                $proceso->update([
                    'score_confiabilidad' => $scoreConfiabilidad,
                    'analisis_lenguaje'   => json_encode($analisisLenguaje, JSON_UNESCAPED_UNICODE),
                    'contraste_temas'     => json_encode($contrasteGuardar, JSON_UNESCAPED_UNICODE),
                ]);

                // ─── PASO 12: Emitir veredicto ───────────────────────────────
                $veredicto = Veredicto::create([
                    'id_contenido'       => $contenido->id_contenido,
                    'id_proceso'         => $proceso->id_proceso,
                    'tipo_veredicto'     => $tipoVeredicto,
                    'nivel_confiabilidad' => $scoreConfiabilidad,
                    'explicacion'        => $explicacion,
                ]);

                // ─── PASO 13: Asociar fuentes al veredicto (veredicto_fuentes) ──
                $detallesHechos = $resultadoContraste['verificacion_hechos']['detalles'] ?? [];
                $fuentesAsociadas = [];
                $pivotesFuentes   = [];
                foreach ($fuentes as $fuente) {
                    $pesoNivel = config("dagaz.pesos_fuente.{$fuente->tipo_fuente}", 0.5);
                    $cita = $resultadoContraste['citas'][$fuente->id_fuente] ?? null;

                    // Solo asociar y mostrar fuentes que efectivamente tengan cita/evidencia de contraste
                    if (empty($cita)) {
                        continue;
                    }

                    // Omitir menciones puramente tangenciales (CONTEXTUALIZA de un solo término en afirmación
                    // multi-concepto) cuando el veredicto es FALSO o el analizador de IA ya evaluó el contenido.
                    $detalleFuente = $detallesHechos[$fuente->id_fuente] ?? null;
                    if (
                        self::esContextualizacionTangencial($detalleFuente)
                        && ($tipoVeredicto === 'FALSO' || ($resultadoContraste['ia_respondio'] ?? false))
                    ) {
                        continue;
                    }

                    $pivotesFuentes[$fuente->id_fuente] = [
                        'peso_en_veredicto' => $pesoNivel,
                        'cita_contraste'    => $cita,
                    ];

                    $fuentesAsociadas[] = [
                        'id_fuente'           => $fuente->id_fuente,
                        'nombre'              => $fuente->nombre,
                        'url_sitio'           => $fuente->url_sitio,
                        'tipo_fuente'         => $fuente->tipo_fuente,
                        'indice_credibilidad' => $fuente->indice_credibilidad,
                        'peso_en_veredicto'   => $pesoNivel,
                        'cita_contraste'      => $cita,
                    ];
                }
                if (!empty($pivotesFuentes)) {
                    $veredicto->fuentes()->attach($pivotesFuentes);
                }

                // Enriquecer con las fuentes y organismos oficiales citados por los modelos de IA
                $fuentesIa = [];
                if (!empty($evaluacionMultiModelo['fuentes_evidencias'])) {
                    foreach ($evaluacionMultiModelo['fuentes_evidencias'] as $fia) {
                        $fuentesIa[] = [
                            'nombre'              => $fia['nombre'],
                            'url_sitio'           => $fia['url_sitio'] ?? null,
                            'tipo_fuente'         => $fia['tipo_fuente'] ?? 'OFICIAL',
                            'indice_credibilidad' => (float) ($fia['indice_credibilidad'] ?? 95.0),
                            'peso_en_veredicto'   => (float) ($fia['peso_en_veredicto'] ?? 0.90),
                            'cita_contraste'      => (string) ($fia['cita_contraste'] ?? ''),
                            'origen'              => 'IA_CONSEJO',
                            'modelo_evaluador'    => $fia['modelo_evaluador'] ?? null,
                        ];
                    }
                }

                // Enriquecer con fuentes en vivo rescatadas de la búsqueda web
                $fuentesWeb = [];
                if (!empty($evidenciasWeb)) {
                    foreach ($evidenciasWeb as $ew) {
                        $fuentesWeb[] = [
                            'nombre'              => $ew['fuente'],
                            'url_sitio'           => $ew['url'],
                            'tipo_fuente'         => 'PERIODISTICO',
                            'indice_credibilidad' => 90.0,
                            'peso_en_veredicto'   => 0.85,
                            'cita_contraste'      => $ew['fragmento'],
                            'origen'              => 'BUSQUEDA_WEB',
                            'modelo_evaluador'    => 'Búsqueda Web en Vivo',
                        ];
                    }
                }

                $fuentesAsociadas = !empty($evaluacionMultiModelo['calibracion_aplicada'])
                    ? array_merge($fuentesIa, $fuentesWeb, $fuentesAsociadas)
                    : array_merge($fuentesAsociadas, $fuentesWeb, $fuentesIa);

                // ─── PASO 14: Actualizar estado del contenido a "VERIFICADO" ──
                $contenido->update(['estado' => 'VERIFICADO']);

                // Registrar telemetría de costes en el Circuit Breaker
                $this->circuitBreakerService->registrarConsumo(
                    servicio:      $adaptadorIa->getNombreProveedor(),
                    tokensTotales: 800,
                    costoUsd:      0.002
                );

                Log::info('[DAGAZ][CU06] Verificación completada.', [
                    'id_contenido'    => $contenido->id_contenido,
                    'tipo_veredicto'  => $tipoVeredicto,
                    'score'           => $scoreConfiabilidad,
                    'fuentes_usadas'  => count($fuentesAsociadas),
                    'proveedor_ia'    => $adaptadorIa->getNombreProveedor(),
                    'archive_org'     => $analisisArchive !== null,
                    'interlingue'     => $analisisIdioma !== null,
                ]);

                $respuesta = [
                    'contenido' => [
                        'id_contenido'       => $contenido->id_contenido,
                        'tipo_entrada'       => $contenido->tipo_entrada,
                        'contenido_original' => $contenido->contenido_original,
                        'estado'             => 'VERIFICADO',
                    ],
                    'proceso' => [
                        'id_proceso'          => $proceso->id_proceso,
                        'score_confiabilidad' => $scoreConfiabilidad,
                        'proveedor_ia'        => $adaptadorIa->getNombreProveedor(),
                    ],
                    'veredicto' => [
                        'id_veredicto'        => $veredicto->id_veredicto,
                        'tipo_veredicto'      => $tipoVeredicto,
                        'nivel_confiabilidad' => $scoreConfiabilidad,
                        'explicacion'         => $explicacion,
                        'fecha_emision'       => $veredicto->fecha_emision?->toIso8601String(),
                    ],
                    'fuentes'             => $fuentesAsociadas,
                    'categoria_metadata'  => $categoriaMetadata,
                    'verificacion_hechos' => $resultadoContraste['verificacion_hechos'] ?? null,
                ];

                if ($analisisArchive) {
                    $respuesta['archive_org'] = $analisisArchive;
                }

                if ($analisisIdioma) {
                    $respuesta['interlingue'] = [
                        'idioma_detectado' => $analisisIdioma['nombre'],
                        'codigo'           => $analisisIdioma['codigo'],
                        'es_anglosajon'    => $analisisIdioma['es_anglosajon'],
                        'traduccion_es'    => $traduccionEs,
                    ];
                }

                if ($evaluacionMultiModelo) {
                    $respuesta['multi_modelo_evaluacion'] = $evaluacionMultiModelo;
                }

                return $respuesta;

            } catch (\Throwable $e) {
                // Marcar contenido como error y re-lanzar para el controlador
                $contenido->update(['estado' => 'ERROR']);

                Log::error('[DAGAZ][CU06] Pipeline de verificación falló.', [
                    'id_contenido' => $contenido->id_contenido,
                    'id_proceso'   => $proceso->id_proceso,
                    'error'        => $e->getMessage(),
                ]);

                throw $e;
            }
        });

        // ─── FASE STREAMING (Modalidad B: Tokens de Veredicto & Explicación) ───
        if ($onProgress !== null) {
            $explicacion = $resultado['veredicto']['explicacion'] ?? '';
            $onProgress('sintesis', [
                'etapa'          => 'sintesis',
                'progreso'       => 92,
                'mensaje'        => 'Transmitiendo dictamen fáctico y fundamentación periodística...',
                'tipo_veredicto' => $resultado['veredicto']['tipo_veredicto'] ?? 'NO_VERIFICABLE',
                'confianza'      => $resultado['veredicto']['nivel_confiabilidad'] ?? 0.0,
            ]);

            if (!empty($explicacion)) {
                $chunks = preg_split('/(\s+)/u', $explicacion, -1, PREG_SPLIT_DELIM_CAPTURE);
                if (is_array($chunks)) {
                    foreach ($chunks as $chunk) {
                        if ($chunk === '') {
                            continue;
                        }
                        $onProgress('token', ['chunk' => $chunk]);
                        usleep(10000); // 10ms cadencia fluida de lectura
                    }
                }
            }

            $onProgress('completado', [
                'etapa'    => 'finalizado',
                'progreso' => 100,
                'mensaje'  => 'Verificación completada exitosamente.',
            ]);
        }

        return $resultado;
    }

    /**
     * Normalizar la entrada multicanal a texto plano procesable.
     *
     * Implementa AdaptadorMulticanal del DCD:
     * - TEXTO: devuelve directamente
     * - URL: extrae texto de la página web
     * - IMAGEN_OCR: aplica OCR a la imagen
     * - RED_SOCIAL: escucha el contenido de la red social
     *
     * @param  string                             $tipoEntrada
     * @param  string|null                        $contenidoOriginal
     * @param  string|null                        $urlOrigen
     * @param  \Illuminate\Http\UploadedFile|null $archivo
     * @return string  Texto normalizado para procesamiento
     */
    private function normalizarEntrada(
        string $tipoEntrada,
        ?string $contenidoOriginal,
        ?string $urlOrigen,
        ?UploadedFile $archivo
    ): string {
        $tipoUpper = strtoupper(trim($tipoEntrada));

        // 1. Detección automática e ingestión directa si se proporciona un archivo adjunto
        if ($archivo) {
            $mime = strtolower((string) ($archivo->getMimeType() ?? ''));
            $ext = strtolower((string) ($archivo->getClientOriginalExtension() ?? ''));

            if (str_starts_with($mime, 'audio/') || in_array($ext, ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac'], true)) {
                $textoExtraido = $this->adaptadorMulticanal->transcribirAudio($archivo->getRealPath());
                if (!empty($textoExtraido)) {
                    return $textoExtraido;
                }
            } elseif (str_starts_with($mime, 'image/') || in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'bmp', 'gif'], true)) {
                $textoExtraido = $this->adaptadorMulticanal->extraerTextoOCR($archivo->getRealPath());
                if (!empty($textoExtraido)) {
                    return $textoExtraido;
                }
            } elseif (str_starts_with($mime, 'video/')) {
                $textoExtraido = $this->adaptadorMulticanal->transcribirAudio($archivo->getRealPath());
                if (!empty($textoExtraido)) {
                    return $textoExtraido;
                }
            }
        }

        // 2. Normalización multicanal basada en el tipo especificado
        $texto = match ($tipoUpper) {
            'TEXTO' => $contenidoOriginal ?? '',

            // FA2: Enlace a sitio web o portal de noticias
            'URL' => $urlOrigen
                ? $this->adaptadorMulticanal->escucharRedSocial($urlOrigen)['texto'] ?? $contenidoOriginal ?? ''
                : ($contenidoOriginal ?? ''),

            // FA3: Imagen / Captura de pantalla → OCR
            'IMAGEN_OCR', 'IMAGEN' => $archivo
                ? $this->adaptadorMulticanal->extraerTextoOCR($archivo->getRealPath())
                : ($urlOrigen
                    ? $this->adaptadorMulticanal->extraerTextoOCR($urlOrigen)
                    : ($contenidoOriginal ? $this->adaptadorMulticanal->extraerTextoOCR($contenidoOriginal) : '')),

            // FA4: Red social → extracción de texto
            'RED_SOCIAL' => $urlOrigen
                ? $this->adaptadorMulticanal->escucharRedSocial($urlOrigen)['texto'] ?? $contenidoOriginal ?? ''
                : ($contenidoOriginal ?? ''),

            // FA5: Audio / Grabación de voz / Video → Transcripción STT
            'AUDIO', 'VIDEO' => $archivo
                ? $this->adaptadorMulticanal->transcribirAudio($archivo->getRealPath())
                : ($urlOrigen
                    ? $this->adaptadorMulticanal->transcribirAudio($urlOrigen)
                    : ($contenidoOriginal ? $this->adaptadorMulticanal->transcribirAudio($contenidoOriginal) : '')),

            default => $contenidoOriginal ?? '',
        };

        $textoLimpio = trim((string) $texto);

        // Fallback defensivo si el canal retornó vacío pero se proveyó contenido original
        if (empty($textoLimpio) && !empty($contenidoOriginal)) {
            $textoLimpio = trim((string) $contenidoOriginal);
        }

        if (empty($textoLimpio)) {
            throw new \InvalidArgumentException(
                "No fue posible extraer o transcribir texto del contenido proporcionado ({$tipoUpper}) para realizar la contrastación."
            );
        }

        return $textoLimpio;
    }

    /**
     * Descomponer el texto en afirmaciones atómicas verificables.
     *
     * REQ-01, REQ-03: Agente Extractor y Agente de Descomposición.
     *
     * @param  AdaptadorApiIaInterface  $adaptadorIa
     * @param  string                  $texto
     * @param  string                  $idProceso
     * @return string[]  Lista de afirmaciones atómicas
     */
    private function descomponerAfirmaciones(
        AdaptadorApiIaInterface $adaptadorIa,
        string $texto,
        string $idProceso
    ): array {
        $inicio = microtime(true);

        // Si el texto es una sola oración corta/mediana, ya es una afirmación atómica (evita llamada HTTP extra)
        $limpio = trim($texto);
        if (mb_strlen($limpio) <= 280 && substr_count($limpio, '.') <= 1) {
            $this->registrarLog($idProceso, $adaptadorIa->getNombreProveedor(), 'descomposicion', 200);
            return [$limpio];
        }

        try {
            $afirmaciones = $adaptadorIa->descomponerAfirmaciones($texto);
            $this->registrarLog($idProceso, $adaptadorIa->getNombreProveedor(), 'descomposicion', 200);
            return !empty($afirmaciones) ? $afirmaciones : [$limpio];

        } catch (\Throwable $e) {
            $latencia = round((microtime(true) - $inicio) * 1000);
            $this->registrarLog($idProceso, $adaptadorIa->getNombreProveedor(), 'descomposicion', 500, $e->getMessage());
            Log::warning('[DAGAZ][IA] Descomposición de afirmaciones falló. Se usará texto completo.', [
                'id_proceso' => $idProceso,
                'error'      => $e->getMessage(),
            ]);
            // Fallback: usar el texto completo como una sola afirmación
            return [$texto];
        }
    }

    /**
     * Consultar fuentes relevantes del grafo de credibilidad para el texto dado.
     *
     * REQ-05: Agente de Recuperación de Evidencia.
     * Prioriza fuentes que responden con noticias del tema y fuentes Nivel 1.
     *
     * @param  string  $texto
     * @param  \Illuminate\Support\Collection<\App\Models\Fuente>|null $fuentesPrioritarias
     * @return \Illuminate\Support\Collection<\App\Models\Fuente>
     */
    private function consultarFuentesRelevantes(string $texto, $fuentesPrioritarias = null)
    {
        $coleccion = $fuentesPrioritarias ? clone $fuentesPrioritarias : collect();

        if ($coleccion->count() >= self::MIN_FUENTES_REQUERIDAS) {
            return $coleccion->take(10);
        }

        // 1. Recuperación semántica de fuentes a través de documentos indexados
        if ($this->busquedaSemanticaService) {
            try {
                $docs = $this->busquedaSemanticaService->buscarEvidenciaPorTexto($texto, 10);
                if ($docs->isNotEmpty()) {
                    $fuenteIds = $docs->pluck('fuente_id')->unique()->all();
                    $fuentesSemanticas = Fuente::whereIn('id_fuente', $fuenteIds)->get();
                    $coleccion = $coleccion->merge($fuentesSemanticas)->unique('id_fuente');
                    if ($coleccion->count() >= self::MIN_FUENTES_REQUERIDAS) {
                        return $coleccion->take(10);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[VerificacionService] Error en recuperación semántica: ' . $e->getMessage());
            }
        }

        // 2. Fallback: Recuperar las 10 fuentes más confiables del grafo
        $fuentesFallback = Fuente::orderByDesc('indice_credibilidad')
            ->orderByRaw("CASE tipo_fuente 
                WHEN 'internacional_n1' THEN 1 
                WHEN 'regional_n2' THEN 2 
                WHEN 'nacional_n3' THEN 3 
                WHEN 'factchecker_n4' THEN 4 
                WHEN 'OFICIAL' THEN 5 
                WHEN 'PERIODISTICO' THEN 6 
                WHEN 'ACADEMICO' THEN 7 
                WHEN 'RED_SOCIAL' THEN 8 
                ELSE 9 END")
            ->limit(10)
            ->get();

        return $coleccion->merge($fuentesFallback)->unique('id_fuente')->take(10)->values();
    }

    /**
     * Analizar el lenguaje del texto: sesgo, sensacionalismo y amarillismo.
     *
     * @param  AdaptadorApiIaInterface  $adaptadorIa
     * @param  string                  $texto
     * @param  string                  $idProceso
     * @return array{sesgo_score: float, analisis: string, tipo: string}
     */
    private function analizarLenguaje(
        AdaptadorApiIaInterface $adaptadorIa,
        string $texto,
        string $idProceso
    ): array {
        // Evaluación léxica instantánea para evitar 2 llamadas HTTP adicionales al LLM
        $minusculas = mb_strtolower($texto);
        $marcadoresSesgo = [
            'urgente', 'alerta', 'escándalo', 'escandalo', 'impactante', 'terrible',
            'increíble', 'increible', 'ocultan', 'secreto', 'bomba', 'destruye',
            'vergüenza', 'verguenza', 'traición', 'traicion', 'dictadura', 'corrupto',
        ];
        $coincidencias = 0;
        foreach ($marcadoresSesgo as $m) {
            if (mb_strpos($minusculas, $m) !== false) {
                $coincidencias++;
            }
        }
        $exclamaciones = substr_count($texto, '!');
        $sesgo = min(1.0, round(($coincidencias * 0.25) + ($exclamaciones * 0.10), 2));
        $tipo  = $sesgo > 0.5 ? 'sensacionalista' : 'noticia';

        $this->registrarLog($idProceso, $adaptadorIa->getNombreProveedor(), 'evaluacion', 200);

        return [
            'sesgo_score' => $sesgo,
            'tipo'        => $tipo,
            'analisis'    => $sesgo > 0.6
                ? 'Lenguaje con carga emocional alta o sensacionalista detectado.'
                : 'Lenguaje neutral o moderado.',
        ];
    }

    /**
     * Contrastar el texto con las fuentes del grafo de credibilidad y sus noticias.
     *
     * REQ-04: Agente de Verificación Cruzada y Ponderación.
     *
     * @param  AdaptadorApiIaInterface                                         $adaptadorIa
     * @param  string                                                           $texto
     * @param  string[]                                                         $afirmaciones
     * @param  \Illuminate\Database\Eloquent\Collection<\App\Models\Fuente>     $fuentes
     * @param  string                                                           $idProceso
     * @param  array<string, array<int, object>>                                $noticiasPorFuente
     * @param  array<int, array{titulo: string, url: string, fragmento: string, fuente: string}> $evidenciasWeb
     * @return array{coherencia_score: float, coherencia_ia_score: float, justificacion: string, citas: array<string,string|null>, verificacion_hechos: ?array}
     */
    private function contrastarConFuentes(
        AdaptadorApiIaInterface $adaptadorIa,
        string $texto,
        array $afirmaciones,
        $fuentes,
        string $idProceso,
        array $noticiasPorFuente = [],
        array $evidenciasWeb = []
    ): array {
        try {
            $fragmentosDb = [];
            foreach ($noticiasPorFuente as $docsFuente) {
                foreach ($docsFuente as $doc) {
                    $totEspDoc = (int) ($doc->total_especificas ?? 0);
                    $coincEspDoc = (int) ($doc->coincidencias_especificas ?? 0);
                    if ($totEspDoc >= 2 && $coincEspDoc < 2) {
                        continue;
                    }
                    $fragClean = trim((string) preg_replace(
                        '/\s+/u',
                        ' ',
                        html_entity_decode(strip_tags((string) ($doc->fragmento ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')
                    ));
                    $fragmentosDb[] = [
                        'score' => ($coincEspDoc * 10) + (int) ($doc->coincidencias ?? 0),
                        'texto' => "- [{$doc->titulo}] (" . ($doc->fecha_publicacion ?? 'sin fecha') . "): " . mb_substr($fragClean, 0, 260),
                    ];
                }
            }
            usort($fragmentosDb, fn ($a, $b) => $b['score'] <=> $a['score']);
            $topEvidencia = array_slice(array_column($fragmentosDb, 'texto'), 0, 3);
            $textoParaIa  = !empty($topEvidencia)
                ? $texto . "\n\nEVIDENCIA DOCUMENTAL ACTUALIZADA EN LA BASE DE DATOS:\n" . implode("\n", $topEvidencia)
                : $texto;

            if (!empty($evidenciasWeb)) {
                $lineasWeb = [];
                foreach (array_slice($evidenciasWeb, 0, 4) as $ew) {
                    $lineasWeb[] = "- [{$ew['fuente']}] ({$ew['url']}): {$ew['fragmento']}";
                }
                $textoParaIa .= "\n\nEVIDENCIA WEB EN TIEMPO REAL (BÚSQUEDA DEL DÍA):\n" . implode("\n", $lineasWeb);
            }

            $resultado = $adaptadorIa->verificarCoherencia($textoParaIa);
            $coherenciaScore = (float) ($resultado['score'] ?? 50.0);
            $coherenciaIaNorm = $coherenciaScore > 1.0 ? ($coherenciaScore / 100.0) : $coherenciaScore;
            if ($coherenciaScore <= 1.0) {
                $coherenciaScore = round($coherenciaScore * 100, 2);
            }
            $justIA = trim((string) ($resultado['justificacion'] ?? ''));
            $iaRespondio = $justIA !== '' && $justIA !== 'No se pudo verificar la coherencia.';

            $this->registrarLog($idProceso, $adaptadorIa->getNombreProveedor(), 'busqueda_fuentes', 200);

            // Verificación de hechos contrastando con lo obtenido en las fuentes
            $verificacionHechos = null;
            $citas = [];
            if ($this->busquedaSemanticaService) {
                try {
                    $verificacionHechos = $this->busquedaSemanticaService->verificarHechos(
                        $texto,
                        $afirmaciones,
                        $fuentes,
                        $noticiasPorFuente
                    );
                    $citas = $verificacionHechos['citas'] ?? [];
                } catch (\Throwable $e) {
                    Log::warning('[VerificacionService] Error en verificación de hechos: ' . $e->getMessage());
                }
            }

            // Integrar citas web en tiempo real
            if (!empty($evidenciasWeb)) {
                foreach ($evidenciasWeb as $ew) {
                    $citas['web_' . substr(md5($ew['url']), 0, 8)] = "Evidencia Web ({$ew['fuente']}): «{$ew['fragmento']}» ({$ew['url']}).";
                }
            }

            if (empty($citas)) {
                foreach ($fuentes as $fuente) {
                    $citas[$fuente->id_fuente] = "Fuente: {$fuente->nombre} ({$fuente->url_sitio}) — Registro verificado en el Grafo de Credibilidad.";
                }
            }

            $tieneConfirmacionDb = false;
            foreach (($verificacionHechos['detalles'] ?? []) as $detalleFuente) {
                if (($detalleFuente['estado_contraste'] ?? '') === 'CONFIRMA') {
                    $tieneConfirmacionDb = true;
                    break;
                }
            }

            // Score ponderado de contraste:
            // 1. Si la verificación de hechos no halló noticias locales en la base de datos (concordancia = 0)
            //    pero la IA activa o la búsqueda web verificaron la afirmación fáctica, prevalece la veracidad fáctica.
            // 2. Si las fuentes sólo muestran coincidencias tangenciales (CONTEXTUALIZA <= 35 sin CONFIRMA)
            //    y el adaptador de IA activo clasificó la afirmación como falsa/sin sustento (score <= 0.15),
            //    prevalece 0.0 para evitar que menciones laterales inflen una falsedad a 'ENGAÑOSO'.
            if ($verificacionHechos !== null) {
                $concordancia = (float) ($verificacionHechos['concordancia_score'] ?? 0.0);
                if ($concordancia <= 0.0 && $iaRespondio && (!empty($evidenciasWeb) || $coherenciaScore >= 70.0)) {
                    $scoreContrasteFinal = $coherenciaScore;
                } elseif (
                    $iaRespondio
                    && !$tieneConfirmacionDb
                    && $concordancia <= 35.0
                    && $coherenciaIaNorm <= 0.15
                ) {
                    $scoreContrasteFinal = 0.0;
                } else {
                    $scoreContrasteFinal = $concordancia;
                }
            } else {
                $scoreContrasteFinal = $coherenciaScore;
            }

            return [
                'coherencia_score'    => $scoreContrasteFinal,
                'coherencia_ia_score' => $coherenciaScore,
                'ia_respondio'        => $iaRespondio,
                'justificacion'       => $resultado['justificacion'] ?? '',
                'citas'               => $citas,
                'verificacion_hechos' => $verificacionHechos,
            ];

        } catch (\Throwable $e) {
            $this->registrarLog($idProceso, $adaptadorIa->getNombreProveedor(), 'busqueda_fuentes', 500, $e->getMessage());
            return [
                'coherencia_score'    => 50.0,
                'coherencia_ia_score' => 50.0,
                'justificacion'       => '',
                'citas'               => [],
                'verificacion_hechos' => null,
            ];
        }
    }

    /**
     * Calcular el score de confiabilidad ponderado (0.00 a 100.00).
     *
     * Pondera el score de coherencia semántica contra el nivel de sesgo de lenguaje
     * y el promedio de credibilidad de las fuentes consultadas.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<\App\Models\Fuente>  $fuentes
     * @param  float  $coherenciaScore  Score de coherencia del LLM (0.0 a 100.0)
     * @param  float  $sesgoScore       Score de sesgo lingüístico (0.0 a 1.0)
     * @param  bool   $iaRespondio      Indica si el LLM respondió activamente
     * @return float  Score final de confiabilidad (0.00 a 100.00)
     */
    private function calcularScore($fuentes, float $coherenciaScore, float $sesgoScore, bool $iaRespondio = false): float
    {
        // Si no hay evidencia factual o está refutado tajantemente, el score es 0.0
        if ($coherenciaScore <= 0.0) {
            return 0.0;
        }

        if ($fuentes->isEmpty()) {
            // Sin fuentes documentales o de control en la base de datos,
            // el score preliminar se sitúa en un rango prudente bajo (10.0 a 25.0) indicando ausencia de respaldo en BD.
            $scoreBase = max(10.0, min(25.0, $coherenciaScore * 0.3 * (1 - $sesgoScore)));
            return round($scoreBase, 2);
        }

        // Promedio ponderado de credibilidad de fuentes por nivel
        $pesoTotal     = 0.0;
        $scoreAcum     = 0.0;

        foreach ($fuentes as $fuente) {
            $peso         = config("dagaz.pesos_fuente.{$fuente->tipo_fuente}", 0.5);
            $pesoTotal   += $peso;
            $scoreAcum   += $fuente->indice_credibilidad * $peso;
        }

        $credibilidadPromedio = $pesoTotal > 0 ? ($scoreAcum / $pesoTotal) : 50.0;
        $factorCredibilidad = $credibilidadPromedio / 100.0;

        // La credibilidad de las fuentes modula la evidencia, NUNCA otorga puntos gratuitos a afirmaciones sin evidencia
        $score = ($coherenciaScore * (0.50 + 0.50 * $factorCredibilidad))
               - ($sesgoScore * 15.0);

        return round(max(0.0, min(100.0, $score)), 2);
    }

    /**
     * Determinar el tipo de veredicto y generar la explicación narrativa.
     *
     * REQ-06: Agente de Síntesis del Veredicto.
     * Categorías (CHECK real BD raiden): VERDADERO | FALSO | ENGAÑOSO | PARCIAL | NO_VERIFICABLE
     *
     * @param  float  $score
     * @param  \Illuminate\Database\Eloquent\Collection<\App\Models\Fuente>  $fuentes
     * @param  array  $contraste
     * @param  array  $analisisLenguaje
     * @param  string|null $categoriaMetadata
     * @return array{0: string, 1: string}  [tipo_veredicto, explicacion]
     */
    private function determinarVeredicto(
        float $score,
        $fuentes,
        array $contraste,
        array $analisisLenguaje,
        ?string $categoriaMetadata = null
    ): array {
        $hechos      = $contraste['verificacion_hechos'] ?? null;
        $hallazgos   = $hechos['hallazgos'] ?? [];
        $detalles    = $hechos['detalles'] ?? [];

        $prefijosTangenciales = [];
        foreach ($detalles as $detalleItem) {
            if (self::esContextualizacionTangencial($detalleItem) && !empty($detalleItem['fuente'])) {
                $estadoDetalle = (string) ($detalleItem['estado_contraste'] ?? 'CONTEXTUALIZA');
                $prefijosTangenciales[] = $detalleItem['fuente'] . " ({$estadoDetalle}):";
            }
        }

        // Si el score es 0.0 (sin evidencia, afirmación infundada o falsa comprobada)
        if ($score <= 0.0) {
            $primerHallazgo = !empty($hallazgos) ? $hallazgos[0] : null;
            $hallazgosRefutacion = array_values(array_filter(
                $hallazgos,
                fn ($h) => is_string($h) && str_contains($h, '(REFUTA):')
            ));
            $justIA      = trim((string) ($contraste['justificacion'] ?? ''));
            $tieneJustIA = $justIA !== '' && $justIA !== 'No se pudo verificar la coherencia.';

            if ($primerHallazgo && str_contains($primerHallazgo, 'Contraste de hechos verificados')) {
                $extraRefTxt = !empty($hallazgosRefutacion)
                    ? "\n\n" . implode(' | ', array_slice($hallazgosRefutacion, 0, 2))
                    : '';
                $explicacionFalsa = "Con una confiabilidad del 0.0%, la afirmación es falsa y contradice los hechos comprobados por los registros oficiales.\n\n{$primerHallazgo}{$extraRefTxt}";
            } elseif (!empty($hallazgosRefutacion)) {
                $hallazgosTxt = implode(' | ', array_slice($hallazgosRefutacion, 0, 3));
                $explicacionFalsa = "Con una confiabilidad del 0.0%, la afirmación es falsa porque es desmentida directamente por las fuentes verificadas consultadas.\n\n{$hallazgosTxt}";
            } else {
                // Excluir menciones tangenciales (CONTEXTUALIZA / SIN_EVIDENCIA) cuando el veredicto es FALSO (0.0%)
                // para no citar noticias ajenas en la explicación al usuario.
                $hallazgosRelevantes = array_values(array_filter(
                    $hallazgos,
                    fn ($h) => is_string($h) && !str_contains($h, '(CONTEXTUALIZA):') && !str_contains($h, '(SIN_EVIDENCIA):')
                ));
                $hallazgosTxt = !empty($hallazgosRelevantes) ? "\n\n" . implode(' | ', array_slice($hallazgosRelevantes, 0, 3)) : '';
                $explicacionFalsa = "Con una confiabilidad del 0.0%, no se encontró ninguna evidencia, registro oficial ni cobertura periodística en las fuentes verificadas que respalde esta afirmación. Se clasifica como información falsa o infundada.{$hallazgosTxt}";
            }

            if ($tieneJustIA) {
                $explicacionFalsa .= "\n\n[Análisis de IA: {$justIA}]";
            }

            return [
                'FALSO',
                $explicacionFalsa . ($categoriaMetadata ? "\n[Categoría temática: {$categoriaMetadata}]" : ''),
            ];
        }

        // Caso: No se encontró ninguna fuente o noticia relacionada en la base de datos documental local
        if ($fuentes->isEmpty()) {
            $justIA      = $contraste['justificacion'] ?? '';
            $tieneJustIA = $justIA !== '' && $justIA !== 'No se pudo verificar la coherencia.';

            $explicacion = "No se encontró ninguna noticia o fuente relacionada a este tema, este veredicto es posiblemente falso, pues ningún organismo de control se ha pronunciado aún. " .
                "Se enfatiza que todavía no hay noticias del tema ni pronunciamientos oficiales registrados en la base de datos.";

            if ($tieneJustIA) {
                $explicacion .= "\n\n[Análisis preliminar de IA: {$justIA}]";
            }

            if ($categoriaMetadata) {
                $explicacion .= "\n[Categoría temática: {$categoriaMetadata}]";
            }

            return ['FALSO', $explicacion];
        }

        // FA5: Sin fuentes suficientes
        if ($fuentes->count() < self::MIN_FUENTES_REQUERIDAS) {
            return [
                'NO_VERIFICABLE',
                'No se encontraron suficientes fuentes oficiales para confirmar o desmentir esta afirmación. ' .
                'Se recomienda consultar medios certificados como Reuters, AP o ColombiaCheck antes de compartir este contenido.',
            ];
        }

        $tipo = match (true) {
            $score >= 75.0 => 'VERDADERO',
            $score >= 55.0 => 'PARCIAL',
            $score >= 30.0 => 'ENGAÑOSO',
            default        => 'FALSO',
        };

        $sesgo       = $analisisLenguaje['sesgo_score'] ?? 0.0;
        $numFuentes  = $fuentes->count();
        $justIA      = trim((string) ($contraste['justificacion'] ?? ''));
        $tieneJustIA = $justIA !== '' && $justIA !== 'No se pudo verificar la coherencia.';
        $hechos      = $contraste['verificacion_hechos'] ?? null;
        $hallazgos   = $hechos['hallazgos'] ?? [];

        $explicacion = match ($tipo) {
            'VERDADERO'     =>
                "Con una confiabilidad del {$score}%, este contenido es consistente con la información " .
                "publicada por {$numFuentes} fuente(s) verificada(s). " .
                ($tieneJustIA ? "Análisis: {$justIA}" : ''),

            'PARCIAL'       =>
                "Este contenido presenta información parcialmente correcta que requiere contexto adicional " .
                "(confiabilidad: {$score}%). " .
                ($tieneJustIA ? "Análisis: {$justIA} " : '') .
                ($sesgo > 0.5 ? "Se detectó lenguaje con carga emocional moderada. " : '') .
                "Verifique los detalles con fuentes primarias antes de compartir.",

            'ENGAÑOSO'      =>
                "Este contenido presenta información con elementos engañosos o decontextualizados " .
                "(confiabilidad: {$score}%). " .
                ($tieneJustIA ? "Análisis: {$justIA} " : '') .
                ($sesgo > 0.5 ? "Se detectó lenguaje con carga emocional alta. " : '') .
                "Verifique los detalles con fuentes primarias antes de compartir.",

            'FALSO'         =>
                "La información analizada contradice los hechos documentados y lo publicado por fuentes verificadas. " .
                ($tieneJustIA ? "Hallazgos: {$justIA} " : '') .
                "Le recomendamos no compartir este contenido.",

            default         =>
                "No fue posible establecer una conclusión definitiva sobre este contenido con la evidencia disponible. " .
                "Las fuentes consultadas no proporcionaron evidencia concluyente. Proceda con precaución.",
        };

        $hallazgosFiltrados = array_values(array_filter($hallazgos, function ($h) use ($prefijosTangenciales) {
            if (!is_string($h)) {
                return false;
            }
            foreach ($prefijosTangenciales as $pref) {
                if (str_starts_with($h, $pref)) {
                    return false;
                }
            }
            return true;
        }));

        if (!empty($hallazgosFiltrados)) {
            $explicacion .= "\n\nContraste de hechos en fuentes: " . implode(' | ', array_slice($hallazgosFiltrados, 0, 3));
        }

        if ($categoriaMetadata) {
            $explicacion .= "\n[Categoría temática: {$categoriaMetadata}]";
        }

        return [$tipo, $explicacion];
    }

    /**
     * Indica si el contraste de una fuente es una mención tangencial aislada
     * (CONTEXTUALIZA con < 2 términos específicos coincidentes en afirmaciones multi-concepto).
     */
    private static function esContextualizacionTangencial(mixed $detalle): bool
    {
        if (!is_array($detalle)) {
            return false;
        }

        return in_array($detalle['estado_contraste'] ?? '', ['CONTEXTUALIZA', 'SIN_EVIDENCIA'], true)
            && (int) ($detalle['total_especificas'] ?? 0) >= 2
            && (int) ($detalle['coincidencias_especificas'] ?? 0) < 2;
    }

    /**
     * Registrar una operación de API externa en la tabla de telemetría.
     *
     * @param  string       $idProceso
     * @param  string       $servicio
     * @param  string       $tipoOperacion
     * @param  int          $codigoRespuesta
     * @param  string|null  $detalleError
     * @return void
     */
    private function registrarLog(
        string $idProceso,
        string $servicio,
        string $tipoOperacion,
        int $codigoRespuesta,
        ?string $detalleError = null
    ): void {
        try {
            LogComunicadorApi::create([
                'id_proceso'       => $idProceso,
                'servicio'         => $servicio,
                'tipo_operacion'   => $tipoOperacion,
                'codigo_respuesta' => $codigoRespuesta,
                'detalle_error'    => $detalleError,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[DAGAZ][Log] No se pudo registrar log de API.', [
                'servicio' => $servicio,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Validar que el texto de una nota sea apropiado (CU01 — analizarNota).
     *
     * Detecta texto vacío, inyecciones de código o contenido malicioso básico.
     *
     * @param  string  $texto
     * @return bool  true si la nota es válida, false si debe rechazarse
     */
    public function analizarNota(string $texto): bool
    {
        // Texto muy corto
        if (mb_strlen(trim($texto)) < 20) {
            return false;
        }

        // Detección básica de inyección de script/SQL
        $patronesPeligrosos = [
            '/<script/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/union\s+select/i',
            '/drop\s+table/i',
            '/insert\s+into/i',
        ];

        foreach ($patronesPeligrosos as $patron) {
            if (preg_match($patron, $texto)) {
                Log::warning('[DAGAZ][CU01] Nota rechazada por contenido malicioso.', [
                    'patron_detectado' => $patron,
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * Validar que una calificación numérica esté dentro del rango permitido (CU02).
     *
     * @param  float  $calificacion  Valor a validar (0.0 a 10.0)
     * @return bool
     */
    public function validarCalificacion(float $calificacion): bool
    {
        return $calificacion >= 0.0 && $calificacion <= 10.0;
    }

    /**
     * Extrae la primera URL HTTP/HTTPS bien formada contenida en un texto.
     *
     * @param  string $texto
     * @return string|null
     */
    private function extraerUrlDeTexto(string $texto): ?string
    {
        if (preg_match('#https?://[^\s<>"]+#i', $texto, $matches)) {
            return $matches[0];
        }
        return null;
    }
}
