<?php

namespace App\Http\Controllers;

use App\Contracts\ConectorArchiveInterface;
use App\Contracts\ServicioInterlingueInterface;
use App\Models\Contenido;
use App\Models\Fuente;
use App\Models\Historial;
use App\Models\LogComunicadorApi;
use App\Models\Nota;
use App\Models\ProcesoVerificacion;
use App\Models\Usuario;
use App\Models\Veredicto;
use App\Services\VerificacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * VerificacionController — Controlador Principal de DAGAZ
 *
 * Orquesta todos los casos de uso del sistema (CU01 a CU07):
 * - CU01: Asignar Notas Complementarias
 * - CU02: Calificar Notas Complementarias
 * - CU03: Guardar Contenidos
 * - CU04: Mostrar Historial de Contenidos
 * - CU06: Verificar Contenidos (núcleo funcional)
 * - CU07: Asignar Confiabilidad a Fuentes
 *
 * Este controlador implementa el ControladorVerificacion del DCD.
 * Delega la lógica de negocio compleja al VerificacionService.
 *
 * @see \App\Services\VerificacionService
 * @package App\Http\Controllers
 */
class VerificacionController extends Controller
{
    /**
     * @param  \App\Services\VerificacionService        $verificacionService
     * @param  \App\Services\CircuitBreakerService      $circuitBreakerService
     * @param  \App\Contracts\ConectorArchiveInterface|null $archiveOrgService
     * @param  \App\Contracts\ServicioInterlingueInterface|null $servicioInterlingue
     */
    public function __construct(
        private readonly VerificacionService $verificacionService,
        private readonly \App\Services\CircuitBreakerService $circuitBreakerService,
        private readonly ?ConectorArchiveInterface $archiveOrgService = null,
        private readonly ?ServicioInterlingueInterface $servicioInterlingue = null,
    ) {}

    // =========================================================================
    // CU06 — VERIFICAR CONTENIDOS (NÚCLEO FUNCIONAL)
    // =========================================================================

    /**
     * Procesar y verificar un contenido multicanal.
     *
     * CU06 — Flujo Básico: Recibe texto, URL, imagen, audio o video,
     * invoca el pipeline de IA y emite un veredicto estructurado.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verificarContenido(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipo_entrada'        => ['required', 'string', 'in:TEXTO,URL,IMAGEN_OCR,RED_SOCIAL,texto,url,imagen,audio,video,imagen_ocr,red_social'],
            'contenido_original'  => ['required_without_all:url_origen,archivo', 'nullable', 'string', 'min:10', 'max:10000'],
            'url_origen'          => ['required_without_all:contenido_original,archivo', 'nullable', 'url', 'max:2048'],
            'archivo'             => ['sometimes', 'nullable', 'file', 'max:20480'], // 20MB máx
        ]);

        /** @var Usuario|null $usuario */
        $usuario = $request->user();

        try {
            $resultado = $this->verificacionService->procesarContenido(
                tipoEntrada:       $validated['tipo_entrada'],
                contenidoOriginal: $validated['contenido_original'] ?? null,
                urlOrigen:         $validated['url_origen'] ?? null,
                archivo:           $request->file('archivo'),
                idUsuario:         $usuario?->id_usuario,
            );

            $payload = [
                'mensaje'             => 'Contenido verificado exitosamente.',
                'contenido'           => $resultado['contenido'],
                'proceso'             => $resultado['proceso'],
                'veredicto'           => $resultado['veredicto'],
                'fuentes'             => $resultado['fuentes'],
                'categoria_metadata'  => $resultado['categoria_metadata'] ?? null,
                'verificacion_hechos' => $resultado['verificacion_hechos'] ?? null,
            ];

            if (isset($resultado['multi_modelo_evaluacion'])) {
                $payload['multi_modelo_evaluacion'] = $resultado['multi_modelo_evaluacion'];
            }
            if (isset($resultado['archive_org'])) {
                $payload['archive_org'] = $resultado['archive_org'];
            }
            if (isset($resultado['interlingue'])) {
                $payload['interlingue'] = $resultado['interlingue'];
            }

            return response()->json($payload);

        } catch (\Throwable $e) {
            Log::error('[DAGAZ][Verificacion] Error al procesar contenido.', [
                'tipo_entrada' => $validated['tipo_entrada'],
                'id_usuario'   => $usuario?->id_usuario,
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
            ]);

            return response()->json([
                'mensaje' => 'Error al procesar el contenido. El servicio de verificación no está disponible en este momento.',
                'detalle' => config('app.debug') ? $e->getMessage() : null,
            ], 503);
        }
    }

    /**
     * TSK-6.7: Procesar y verificar contenido con Streaming Reactivo SSE (Híbrido A + B).
     *
     * Emite eventos en tiempo real:
     * - 'inicio': Establecimiento de canal y porcentaje base.
     * - 'paso': Hitos del pipeline (OCR, afirmaciones, RAG, sesgo, consejo).
     * - 'afirmaciones': Lista de afirmaciones atómicas extraídas.
     * - 'fuentes': Fuentes y evidencias recuperadas de Neon.tech.
     * - 'archive': Diagnóstico de descontextualización histórica.
     * - 'sesgo': Detección de sesgo discursivo.
     * - 'consejo': Deliberación y consenso de modelos (GPT-4o, Sonnet, Gemini, DeepSeek-R1).
     * - 'token': Streaming palabra por palabra del dictamen y explicación fáctica (Modalidad B).
     * - 'final': Payload completo estructurado del veredicto y fuentes.
     * - 'fin': Marcador de finalización [DONE].
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function verificarContenidoStream(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'tipo_entrada'        => ['required', 'string', 'in:TEXTO,URL,IMAGEN_OCR,RED_SOCIAL,texto,url,imagen,audio,video,imagen_ocr,red_social'],
            'contenido_original'  => ['required_without_all:url_origen,archivo', 'nullable', 'string', 'min:10', 'max:10000'],
            'url_origen'          => ['required_without_all:contenido_original,archivo', 'nullable', 'url', 'max:2048'],
            'archivo'             => ['sometimes', 'nullable', 'file', 'max:20480'], // 20MB máx
        ]);

        /** @var Usuario|null $usuario */
        $usuario = $request->user();

        return response()->stream(function () use ($validated, $request, $usuario) {
            $enviarSse = function (string $evento, mixed $datos): void {
                echo "event: {$evento}\n";
                $payload = is_array($datos) ? json_encode($datos, JSON_UNESCAPED_UNICODE) : (string) $datos;
                echo "data: {$payload}\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            $enviarSse('inicio', [
                'mensaje'  => 'Canal reactivo de verificación establecido con DAGAZ.',
                'progreso' => 5,
            ]);

            try {
                $resultado = $this->verificacionService->procesarContenido(
                    tipoEntrada:       $validated['tipo_entrada'],
                    contenidoOriginal: $validated['contenido_original'] ?? null,
                    urlOrigen:         $validated['url_origen'] ?? null,
                    archivo:           $request->file('archivo'),
                    idUsuario:         $usuario?->id_usuario,
                    onProgress:        $enviarSse
                );

                $payload = [
                    'mensaje'             => 'Contenido verificado exitosamente.',
                    'contenido'           => $resultado['contenido'],
                    'proceso'             => $resultado['proceso'],
                    'veredicto'           => $resultado['veredicto'],
                    'fuentes'             => $resultado['fuentes'],
                    'categoria_metadata'  => $resultado['categoria_metadata'] ?? null,
                    'verificacion_hechos' => $resultado['verificacion_hechos'] ?? null,
                ];

                if (isset($resultado['multi_modelo_evaluacion'])) {
                    $payload['multi_modelo_evaluacion'] = $resultado['multi_modelo_evaluacion'];
                }
                if (isset($resultado['archive_org'])) {
                    $payload['archive_org'] = $resultado['archive_org'];
                }
                if (isset($resultado['interlingue'])) {
                    $payload['interlingue'] = $resultado['interlingue'];
                }

                $enviarSse('final', $payload);
                $enviarSse('fin', '[DONE]');

            } catch (\Throwable $e) {
                Log::error('[DAGAZ][VerificacionStream] Error en pipeline streaming.', [
                    'tipo_entrada' => $validated['tipo_entrada'],
                    'id_usuario'   => $usuario?->id_usuario,
                    'error'        => $e->getMessage(),
                ]);

                $enviarSse('error', [
                    'mensaje' => 'Error al procesar el contenido.',
                    'detalle' => config('app.debug') ? $e->getMessage() : null,
                ]);
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream; charset=utf-8',
            'Cache-Control'     => 'no-cache, no-transform',
            'Connection'        => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Obtener el resultado de un proceso de verificación previo.
     *
     * @param  string  $idContenido
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerContenido(string $idContenido): JsonResponse
    {
        $contenido = Contenido::with([
            'procesos',
            'veredictos.fuentes',
            'notas.usuario',
        ])->find($idContenido);

        if (! $contenido) {
            return response()->json(['mensaje' => 'Contenido no encontrado.'], 404);
        }

        $veredicto = $contenido->veredictos()->latest('fecha_emision')->first();
        $proceso = $contenido->procesos()->latest('fecha_analisis')->first();
        $contrasteTemas = $proceso && $proceso->contraste_temas ? json_decode($proceso->contraste_temas, true) : [];
        $multiModelo = $contrasteTemas['multi_modelo_evaluacion'] ?? null;

        $fuentesRespuesta = $veredicto ? $veredicto->fuentes
            ->filter(fn ($f) => !empty($f->pivot->cita_contraste))
            ->map(fn ($f) => [
                'nombre'              => $f->nombre,
                'url_sitio'           => $f->url_sitio,
                'tipo_fuente'         => $f->tipo_fuente,
                'indice_credibilidad' => $f->indice_credibilidad,
                'peso_en_veredicto'   => $f->pivot->peso_en_veredicto,
                'cita_contraste'      => $f->pivot->cita_contraste,
                'origen'              => 'GRAFO_LOCAL',
            ])->values()->all() : [];

        // Integrar las fuentes y evidencias oficiales del Consejo de IA si están disponibles
        $fuentesIa = [];
        if (!empty($multiModelo['fuentes_evidencias'])) {
            foreach ($multiModelo['fuentes_evidencias'] as $fia) {
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

        $fuentesRespuesta = !empty($multiModelo['calibracion_aplicada'])
            ? array_merge($fuentesIa, $fuentesRespuesta)
            : array_merge($fuentesRespuesta, $fuentesIa);

        return response()->json([
            'contenido' => [
                'id_contenido'       => $contenido->id_contenido,
                'tipo_entrada'       => $contenido->tipo_entrada,
                'contenido_original' => $contenido->contenido_original,
                'url_origen'         => $contenido->url_origen,
                'estado'             => $contenido->estado,
                'fecha_subida'       => $contenido->fecha_subida?->toIso8601String(),
            ],
            'veredicto' => $veredicto ? [
                'id_veredicto'       => $veredicto->id_veredicto,
                'tipo_veredicto'     => $veredicto->tipo_veredicto,
                'nivel_confiabilidad' => $veredicto->nivel_confiabilidad,
                'explicacion'        => $veredicto->explicacion,
                'fecha_emision'      => $veredicto->fecha_emision?->toIso8601String(),
                'fuentes'            => $fuentesRespuesta,
            ] : null,
            'multi_modelo_evaluacion' => $multiModelo,
            'notas' => $contenido->notas->map(fn ($n) => [
                'id_nota'        => $n->id_nota,
                'contenido_nota' => $n->contenido_nota,
                'calificacion'   => $n->calificacion,
                'fecha_creacion' => $n->fecha_creacion?->toIso8601String(),
                'autor'          => $n->usuario?->email,
            ]),
        ]);
    }

    // =========================================================================
    // CU03 — GUARDAR CONTENIDOS
    // =========================================================================

    /**
     * Guardar un contenido verificado en el historial personal del usuario.
     *
     * CU03 — Flujo Básico: Coordinar guardado en historial.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $idContenido
     * @return \Illuminate\Http\JsonResponse
     */
    public function guardarContenido(Request $request, string $idContenido): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();

        $contenido = Contenido::find($idContenido);

        if (! $contenido) {
            return response()->json(['mensaje' => 'Contenido no encontrado.'], 404);
        }

        try {
            DB::transaction(function () use ($usuario, $contenido) {
                // Evitar duplicados en el historial para la misma acción
                $yaGuardado = Historial::where('id_usuario', $usuario->id_usuario)
                    ->where('id_contenido', $contenido->id_contenido)
                    ->where('accion', 'guardado')
                    ->exists();

                if (! $yaGuardado) {
                    Historial::create([
                        'id_usuario'   => $usuario->id_usuario,
                        'id_contenido' => $contenido->id_contenido,
                        'accion'       => 'guardado',
                    ]);
                }
            });

            Log::info('[DAGAZ][CU03] Contenido guardado en historial.', [
                'id_usuario'   => $usuario->id_usuario,
                'id_contenido' => $idContenido,
            ]);

            return response()->json([
                'mensaje' => 'Noticia guardada exitosamente en su historial.',
            ]);

        } catch (\Throwable $e) {
            Log::error('[DAGAZ][CU03] Error al guardar contenido.', [
                'id_usuario'   => $usuario->id_usuario,
                'id_contenido' => $idContenido,
                'error'        => $e->getMessage(),
            ]);

            // FA1: Fallo de persistencia
            return response()->json([
                'mensaje' => 'Error al guardar la noticia. Por favor, intente nuevamente.',
            ], 500);
        }
    }

    // =========================================================================
    // CU04 — MOSTRAR HISTORIAL DE CONTENIDOS
    // =========================================================================

    /**
     * Consultar el historial cronológico del usuario autenticado.
     *
     * CU04 — Flujo Básico: Recuperar lista de contenidos con veredictos.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function historialUsuario(Request $request): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();

        $historial = Historial::with(['contenido.veredictos'])
            ->where('id_usuario', $usuario->id_usuario)
            ->orderByDesc('fecha_evento')
            ->paginate(20);

        if ($historial->isEmpty()) {
            // FA1: Historial vacío
            return response()->json([
                'mensaje'  => 'Aún no tiene noticias verificadas en su historial. ¡Verifique su primera noticia!',
                'historial' => [],
                'total'    => 0,
            ]);
        }

        $items = $historial->getCollection()->map(function ($registro) {
            $contenido = $registro->contenido;
            $veredicto = $contenido?->veredictos()->latest('fecha_emision')->first();

            return [
                'id_historial'       => $registro->id_historial,
                'accion'             => $registro->accion,
                'fecha_evento'       => $registro->fecha_evento?->toIso8601String(),
                'id_contenido'       => $contenido?->id_contenido,
                'tipo_entrada'       => $contenido?->tipo_entrada,
                'resumen_contenido'  => $contenido
                    ? Str::limit($contenido->contenido_original, 150)
                    : null,
                'estado'             => $contenido?->estado,
                'veredicto'          => $veredicto ? [
                    'tipo_veredicto'     => $veredicto->tipo_veredicto,
                    'nivel_confiabilidad' => $veredicto->nivel_confiabilidad,
                ] : null,
            ];
        });

        return response()->json([
            'historial'    => $items,
            'total'        => $historial->total(),
            'pagina_actual' => $historial->currentPage(),
            'ultima_pagina' => $historial->lastPage(),
        ]);
    }

    // =========================================================================
    // CU01 — ASIGNAR NOTAS COMPLEMENTARIAS
    // =========================================================================

    /**
     * Agregar una nota complementaria a un contenido verificado.
     *
     * CU01 — Flujo Básico: Redactar y publicar nota comunitaria.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $idContenido
     * @return \Illuminate\Http\JsonResponse
     */
    public function agregarNota(Request $request, string $idContenido): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();

        $validated = $request->validate([
            'contenido_nota' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        $contenido = Contenido::find($idContenido);

        if (! $contenido) {
            return response()->json(['mensaje' => 'Contenido no encontrado.'], 404);
        }

        // Validar que el contenido ya tenga un veredicto emitido (precondición CU01)
        $tieneVeredicto = $contenido->veredictos()->exists();
        if (! $tieneVeredicto) {
            return response()->json([
                'mensaje' => 'Solo se pueden agregar notas a contenidos que ya han sido verificados.',
            ], 422);
        }

        // FA1: Nota en blanco o solo espacios
        $textoLimpio = trim($validated['contenido_nota']);
        if (empty($textoLimpio)) {
            return response()->json([
                'mensaje' => 'La nota no puede estar vacía. Por favor, ingrese un texto válido.',
            ], 422);
        }

        // FA2: Detección básica de inyecciones o contenido malicioso
        if ($this->verificacionService->analizarNota($textoLimpio) === false) {
            return response()->json([
                'mensaje' => 'El contenido de la nota no es válido. Por favor, revise su texto.',
            ], 422);
        }

        try {
            $nota = Nota::create([
                'id_usuario'     => $usuario->id_usuario,
                'id_contenido'   => $idContenido,
                'contenido_nota' => $textoLimpio,
                'calificacion'   => 0.0,
            ]);

            Log::info('[DAGAZ][CU01] Nota complementaria creada.', [
                'id_nota'      => $nota->id_nota,
                'id_usuario'   => $usuario->id_usuario,
                'id_contenido' => $idContenido,
            ]);

            return response()->json([
                'mensaje' => 'Nota publicada exitosamente.',
                'nota'    => [
                    'id_nota'        => $nota->id_nota,
                    'contenido_nota' => $nota->contenido_nota,
                    'calificacion'   => $nota->calificacion,
                    'fecha_creacion' => $nota->fecha_creacion?->toIso8601String(),
                ],
            ], 201);

        } catch (\Throwable $e) {
            Log::error('[DAGAZ][CU01] Error al guardar nota.', [
                'id_usuario'   => $usuario->id_usuario,
                'id_contenido' => $idContenido,
                'error'        => $e->getMessage(),
            ]);
            return response()->json(['mensaje' => 'Error al publicar la nota.'], 500);
        }
    }

    /**
     * Listar notas complementarias de un contenido.
     *
     * @param  string  $idContenido
     * @return \Illuminate\Http\JsonResponse
     */
    public function listarNotas(string $idContenido): JsonResponse
    {
        $contenido = Contenido::find($idContenido);

        if (! $contenido) {
            return response()->json(['mensaje' => 'Contenido no encontrado.'], 404);
        }

        $notas = Nota::with('usuario')
            ->where('id_contenido', $idContenido)
            ->orderByDesc('calificacion')
            ->orderByDesc('fecha_creacion')
            ->get()
            ->map(fn ($nota) => [
                'id_nota'        => $nota->id_nota,
                'contenido_nota' => $nota->contenido_nota,
                'calificacion'   => $nota->calificacion,
                'fecha_creacion' => $nota->fecha_creacion?->toIso8601String(),
                'autor'          => $nota->usuario?->email,
            ]);

        return response()->json([
            'notas' => $notas,
            'total' => $notas->count(),
        ]);
    }

    // =========================================================================
    // CU02 — CALIFICAR NOTAS COMPLEMENTARIAS
    // =========================================================================

    /**
     * Calificar una nota complementaria de la comunidad.
     *
     * CU02 — Flujo Básico: Votar la utilidad de una nota (0.0 a 10.0).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $idNota
     * @return \Illuminate\Http\JsonResponse
     */
    public function calificarNota(Request $request, string $idNota): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();

        $validated = $request->validate([
            'calificacion' => ['required', 'numeric', 'min:0', 'max:10'],
        ]);

        $nota = Nota::find($idNota);

        if (! $nota) {
            return response()->json(['mensaje' => 'Nota no encontrada.'], 404);
        }

        // Validar que el usuario no califica su propia nota
        if ($nota->id_usuario === $usuario->id_usuario) {
            return response()->json([
                'mensaje' => 'No puede calificar su propia nota.',
            ], 422);
        }

        // Validar rango de calificación (CU02 — validarCalificacion)
        $calificacionNueva = (float) $validated['calificacion'];
        if (! $this->verificacionService->validarCalificacion($calificacionNueva)) {
            return response()->json([
                'mensaje' => 'La calificación debe estar entre 0.0 y 10.0.',
            ], 422);
        }

        try {
            // Cálculo de promedio ponderado simple (actualiza el valor acumulado)
            // En producción se usaría una tabla de votos individual por usuario
            $calificacionPromedio = round(
                ($nota->calificacion + $calificacionNueva) / 2,
                1
            );

            $nota->update(['calificacion' => $calificacionPromedio]);

            Log::info('[DAGAZ][CU02] Nota calificada.', [
                'id_nota'           => $idNota,
                'id_usuario'        => $usuario->id_usuario,
                'nueva_calificacion' => $calificacionNueva,
                'promedio'          => $calificacionPromedio,
            ]);

            return response()->json([
                'mensaje'           => 'Calificación registrada exitosamente.',
                'calificacion_actual' => $nota->fresh()->calificacion,
            ]);

        } catch (\Throwable $e) {
            // FA1: Error en el registro de calificación
            Log::error('[DAGAZ][CU02] Error al calificar nota.', [
                'id_nota' => $idNota,
                'error'   => $e->getMessage(),
            ]);
            return response()->json([
                'mensaje' => 'Error al registrar la calificación. Intente nuevamente en unos segundos.',
            ], 500);
        }
    }

    // =========================================================================
    // CU07 — ASIGNAR CONFIABILIDAD A FUENTES
    // =========================================================================

    /**
     * Listar todas las fuentes del grafo de credibilidad.
     *
     * CU07 — Flujo Básico: Obtener catálogo de fuentes con índice de credibilidad.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function listarFuentes(Request $request): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();

        // Verificar rol para determinar nivel de información mostrada
        $esAdmin = $usuario?->esAdministrador();

        $query = Fuente::orderByDesc('indice_credibilidad')
            ->orderBy('tipo_fuente');

        // Filtro opcional por tipo
        if ($request->has('tipo_fuente')) {
            $query->where('tipo_fuente', $request->string('tipo_fuente'));
        }

        // Filtro opcional por término de búsqueda en nombre (insensible a mayúsculas en PostgreSQL y MariaDB/SQLite)
        if ($request->filled('buscar')) {
            $operadorLike = DB::connection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
            $query->where('nombre', $operadorLike, '%' . $request->string('buscar') . '%');
        }

        $coleccion = $query->get();

        // Filtro opcional por nivel de jerarquía (1 a 4)
        if ($request->has('nivel')) {
            $nivelFiltro = (int) $request->input('nivel');
            $coleccion = $coleccion->filter(fn ($f) => $f->getNivel() === $nivelFiltro)->values();
        }

        $fuentes = $coleccion->map(fn ($f) => array_merge([
            'id_fuente'           => $f->id_fuente,
            'nombre'              => $f->nombre,
            'url_sitio'           => $f->url_sitio,
            'tipo_fuente'         => $f->tipo_fuente,
            'clasificacion'       => $f->getClasificacion(),
            'nivel'               => $f->getNivel(),
            'nivel_etiqueta'      => $f->getNivelEtiqueta(),
            'indice_credibilidad' => $f->indice_credibilidad,
            'fecha_registro'      => $f->fecha_registro?->toIso8601String(),
        ], $esAdmin ? [
            'admin_metadata' => [
                'total_citas' => $f->veredictos()->count(),
            ],
        ] : []));

        return response()->json([
            'fuentes' => $fuentes,
            'total'   => $fuentes->count(),
            'rol_usuario' => $usuario?->rol ?? 'anonimo',
        ]);
    }

    /**
     * Valorar la confiabilidad de una fuente (usuario común).
     *
     * CU07 — Flujo Básico: Emitir valoración de una fuente por usuario adulto.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $idFuente
     * @return \Illuminate\Http\JsonResponse
     */
    public function valorarFuente(Request $request, string $idFuente): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();

        $validated = $request->validate([
            'calificacion' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $fuente = Fuente::find($idFuente);

        if (! $fuente) {
            return response()->json(['mensaje' => 'Fuente no encontrada.'], 404);
        }

        try {
            // Cálculo ponderado: promedio exponencialmente suavizado para evitar
            // que un solo voto sesgue drásticamente el índice
            $alpha       = 0.1; // Factor de suavizado
            $nuevaCalif  = (float) $validated['calificacion'];
            $nuevoIndice = round(
                (1 - $alpha) * $fuente->indice_credibilidad + $alpha * $nuevaCalif,
                2
            );

            $fuente->update(['indice_credibilidad' => $nuevoIndice]);

            Log::info('[DAGAZ][CU07] Fuente valorada por usuario.', [
                'id_fuente'   => $idFuente,
                'id_usuario'  => $usuario->id_usuario,
                'calificacion' => $nuevaCalif,
                'nuevo_indice' => $nuevoIndice,
            ]);

            return response()->json([
                'mensaje'              => 'Valoración registrada exitosamente.',
                'indice_credibilidad'  => $fuente->fresh()->indice_credibilidad,
            ]);

        } catch (\Throwable $e) {
            Log::error('[DAGAZ][CU07] Error al valorar fuente.', [
                'id_fuente' => $idFuente,
                'error'     => $e->getMessage(),
            ]);
            return response()->json(['mensaje' => 'Error al registrar la valoración.'], 500);
        }
    }

    /**
     * Reclasificar una fuente (solo administradores).
     *
     * CU07 — FA1: Clasificación de Fuentes por Administrador.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $idFuente
     * @return \Illuminate\Http\JsonResponse
     */
    public function reclasificarFuente(Request $request, string $idFuente): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();

        if (! $usuario->esAdministrador()) {
            return response()->json([
                'mensaje' => 'Acceso denegado. Solo los administradores pueden reclasificar fuentes.',
            ], 403);
        }

        $validated = $request->validate([
            'tipo_fuente'         => ['sometimes', 'string', 'in:OFICIAL,PERIODISTICO,ACADEMICO,RED_SOCIAL,OTRO'],
            'indice_credibilidad' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'nombre'              => ['sometimes', 'string', 'max:200'],
            'url_sitio'           => ['sometimes', 'url', 'max:2048'],
        ]);

        $fuente = Fuente::find($idFuente);

        if (! $fuente) {
            return response()->json(['mensaje' => 'Fuente no encontrada.'], 404);
        }

        $fuente->update($validated);

        Log::info('[DAGAZ][CU07-Admin] Fuente reclasificada por administrador.', [
            'id_fuente'   => $idFuente,
            'id_admin'    => $usuario->id_usuario,
            'cambios'     => $validated,
        ]);

        return response()->json([
            'mensaje' => 'Fuente actualizada exitosamente.',
            'fuente'  => [
                'id_fuente'           => $fuente->id_fuente,
                'nombre'              => $fuente->nombre,
                'tipo_fuente'         => $fuente->tipo_fuente,
                'indice_credibilidad' => $fuente->indice_credibilidad,
            ],
        ]);
    }

    /**
     * Registrar una nueva fuente en el grafo de credibilidad (solo admin).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function crearFuente(Request $request): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();

        if (! $usuario->esAdministrador()) {
            return response()->json(['mensaje' => 'Acceso denegado.'], 403);
        }

        $validated = $request->validate([
            'nombre'              => ['required', 'string', 'max:200'],
            'url_sitio'           => ['required', 'url', 'max:2048'],
            'tipo_fuente'         => ['required', 'string', 'in:OFICIAL,PERIODISTICO,ACADEMICO,RED_SOCIAL,OTRO'],
            'indice_credibilidad' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $fuente = Fuente::create($validated);

        return response()->json([
            'mensaje' => 'Fuente registrada exitosamente en el grafo de credibilidad.',
            'fuente'  => [
                'id_fuente'           => $fuente->id_fuente,
                'nombre'              => $fuente->nombre,
                'tipo_fuente'         => $fuente->tipo_fuente,
                'indice_credibilidad' => $fuente->indice_credibilidad,
            ],
        ], 201);
    }

    // =========================================================================
    // TELEMETRÍA Y AUDITORÍA
    // =========================================================================

    /**
     * Listar logs de auditoría de APIs externas (solo admin).
     *
     * Implementa la auditoría de logs_comunicador_api para monitoreo de consumo.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logsApi(Request $request): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();

        if (! $usuario->esAdministrador()) {
            return response()->json(['mensaje' => 'Acceso denegado.'], 403);
        }

        $logs = LogComunicadorApi::orderByDesc('fecha_registro')
            ->when($request->has('servicio'), fn ($q) => $q->where('servicio', $request->string('servicio')))
            ->when($request->has('codigo_respuesta'), fn ($q) => $q->where('codigo_respuesta', $request->integer('codigo_respuesta')))
            ->paginate(50);

        return response()->json([
            'logs'          => $logs->items(),
            'total'         => $logs->total(),
            'pagina_actual' => $logs->currentPage(),
        ]);
    }

    // =========================================================================
    // PANEL EDITORIAL HUMANO (TSK-3.7 & REQ-13)
    // =========================================================================

    /**
     * Intervenir manualmente un veredicto (TSK-3.7 & REQ-13 — Revisor Humano / Editorial).
     *
     * Permite a un editor o administrador revisar, corregir y sobreescribir un veredicto
     * emitido por la IA cuando la confianza es baja (<60%) o existen discrepancias documentadas.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $idVeredicto
     * @return \Illuminate\Http\JsonResponse
     */
    public function intervenirVeredicto(Request $request, string $idVeredicto): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();

        if (! $usuario->esEditor()) {
            return response()->json([
                'mensaje' => 'Acceso denegado. Se requiere rol de editor o administrador para intervenir veredictos.',
            ], 403);
        }

        $validated = $request->validate([
            'tipo_veredicto'       => ['required', 'string', 'in:VERDADERO,FALSO,ENGAÑOSO,PARCIAL,NO_VERIFICABLE'],
            'explicacion'          => ['required', 'string', 'min:20', 'max:5000'],
            'nivel_confiabilidad'  => ['required', 'numeric', 'min:0', 'max:100'],
            'motivo_intervencion'  => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $veredicto = Veredicto::find($idVeredicto);

        if (! $veredicto) {
            return response()->json(['mensaje' => 'Veredicto no encontrado.'], 404);
        }

        $veredictoAnterior = [
            'tipo_veredicto'      => $veredicto->tipo_veredicto,
            'nivel_confiabilidad' => $veredicto->nivel_confiabilidad,
            'explicacion'         => $veredicto->explicacion,
        ];

        $veredicto->update([
            'tipo_veredicto'      => $validated['tipo_veredicto'],
            'nivel_confiabilidad' => $validated['nivel_confiabilidad'],
            'explicacion'         => "[Revisión Editorial por {$usuario->email}]: " . $validated['explicacion'],
        ]);

        // Registrar trazabilidad en logs_comunicador_api
        LogComunicadorApi::create([
            'id_proceso'       => $veredicto->id_proceso,
            'servicio'         => 'Panel Editorial Humano',
            'tipo_operacion'   => 'intervencion_editorial',
            'codigo_respuesta' => 200,
            'detalle_error'    => json_encode([
                'editor'               => $usuario->email,
                'id_editor'            => $usuario->id_usuario,
                'motivo_intervencion'  => $validated['motivo_intervencion'],
                'estado_anterior'      => $veredictoAnterior,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        Log::info('[DAGAZ][Editorial] Veredicto intervenido manualmente.', [
            'id_veredicto' => $idVeredicto,
            'editor'       => $usuario->email,
            'nuevo_tipo'   => $validated['tipo_veredicto'],
        ]);

        return response()->json([
            'mensaje'   => 'Veredicto actualizado exitosamente por revisión editorial.',
            'veredicto' => [
                'id_veredicto'        => $veredicto->id_veredicto,
                'tipo_veredicto'      => $veredicto->tipo_veredicto,
                'nivel_confiabilidad' => $veredicto->nivel_confiabilidad,
                'explicacion'         => $veredicto->explicacion,
                'intervenido_por'     => $usuario->email,
            ],
        ]);
    }

    // =========================================================================
    // CIRCUIT BREAKER / TELEMETRÍA DE COSTES (TSK-4.3 & REQ-18)
    // =========================================================================

    /**
     * Consultar métricas y estado del Circuit Breaker (solo admin).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function metricasCircuitBreaker(Request $request): JsonResponse
    {
        return response()->json([
            'metricas' => $this->circuitBreakerService->obtenerMetricas(),
        ]);
    }

    /**
     * Restablecer manualmente el Circuit Breaker (solo admin).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reiniciarCircuitBreaker(Request $request): JsonResponse
    {
        $this->circuitBreakerService->reiniciar();

        return response()->json([
            'mensaje'  => 'Circuit Breaker restablecido exitosamente.',
            'metricas' => $this->circuitBreakerService->obtenerMetricas(),
        ]);
    }

    // =========================================================================
    // CONECTOR ARCHIVE.ORG / WAYBACK MACHINE (TSK-3.2 & REQ-10)
    // =========================================================================

    /**
     * Consultar capturas históricas y disponibilidad en Archive.org / Wayback Machine.
     *
     * GET /api/v1/verificacion/archive/consultar?url=...
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function consultarArchive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url'    => ['required', 'url', 'max:2048'],
            'limite' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $archiveService = $this->archiveOrgService ?? app(ConectorArchiveInterface::class);

        $url = $validated['url'];
        $limite = (int) ($validated['limite'] ?? 10);

        $disponibilidad = $archiveService->consultarDisponibilidad($url);
        $capturas = $archiveService->obtenerCapturasHistoricas($url, $limite);

        return response()->json([
            'url'             => $url,
            'disponible'      => $disponibilidad['disponible'],
            'url_mas_cercana' => $disponibilidad['url_captura'],
            'timestamp'       => $disponibilidad['timestamp'],
            'total_capturas'  => count($capturas),
            'capturas'        => $capturas,
        ]);
    }

    /**
     * Detectar descontextualización histórica de una URL o noticia.
     *
     * POST /api/v1/verificacion/archive/descontextualizacion
     * Body: { "url": "https://...", "fecha_reclamada": "2026-09-01" }
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function detectarDescontextualizacion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url'             => ['required', 'url', 'max:2048'],
            'fecha_reclamada' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $archiveService = $this->archiveOrgService ?? app(ConectorArchiveInterface::class);

        $resultado = $archiveService->detectarDescontextualizacion(
            url: $validated['url'],
            fechaReclamada: $validated['fecha_reclamada'] ?? null
        );

        return response()->json($resultado);
    }

    // =========================================================================
    // SOPORTE DE RECUPERACIÓN INTERLINGÜE (TSK-4.4 & REQ-19)
    // =========================================================================

    /**
     * Detectar el idioma y grado de mezcla de un contenido textual.
     *
     * POST /api/v1/verificacion/interlingue/detectar
     * Body: { "texto": "Breaking news: Confirman nuevo caso..." }
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function detectarIdioma(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'texto' => ['required', 'string', 'min:3', 'max:10000'],
        ]);

        $servicio = $this->getServicioInterlingue();
        $deteccion = $servicio->detectarIdioma($validated['texto']);

        return response()->json([
            'deteccion' => $deteccion,
        ]);
    }

    /**
     * Traducir texto entre español e inglés preservando entidades y tecnicismos.
     *
     * POST /api/v1/verificacion/interlingue/traducir
     * Body: { "texto": "...", "idioma_origen": "auto", "idioma_destino": "en" }
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function traducir(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'texto'          => ['required', 'string', 'min:3', 'max:10000'],
            'idioma_origen'  => ['nullable', 'string', 'in:es,en,auto'],
            'idioma_destino' => ['nullable', 'string', 'in:es,en'],
        ]);

        $servicio = $this->getServicioInterlingue();
        $resultado = $servicio->traducir(
            texto: $validated['texto'],
            idiomaOrigen: $validated['idioma_origen'] ?? 'auto',
            idiomaDestino: $validated['idioma_destino'] ?? 'es'
        );

        return response()->json([
            'resultado' => $resultado,
        ]);
    }

    /**
     * Analizar distorsión semántica, sensacionalismo o tergiversación en traducciones.
     *
     * POST /api/v1/verificacion/interlingue/distorsion
     * Body: { "texto_origen": "...", "texto_traducido": "...", "idioma_origen": "en", "idioma_destino": "es" }
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function analizarDistorsionTraduccion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'texto_origen'    => ['required', 'string', 'min:3', 'max:10000'],
            'texto_traducido' => ['required', 'string', 'min:3', 'max:10000'],
            'idioma_origen'   => ['nullable', 'string', 'in:es,en'],
            'idioma_destino'  => ['nullable', 'string', 'in:es,en'],
        ]);

        $servicio = $this->getServicioInterlingue();
        $analisis = $servicio->analizarDistorsionTraduccion(
            textoOriginalEn: $validated['texto_origen'],
            textoTraducidoEs: $validated['texto_traducido'],
        );

        return response()->json([
            'analisis' => $analisis,
        ]);
    }

    /**
     * Obtener instancia resuelta de ServicioInterlingueInterface.
     */
    private function getServicioInterlingue(): ServicioInterlingueInterface
    {
        return $this->servicioInterlingue ?? app(ServicioInterlingueInterface::class);
    }
}

