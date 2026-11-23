<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Modelo Eloquent para la tabla `logs_comunicador_api`.
 *
 * Registra todas las llamadas realizadas a servicios externos (IA, búsqueda, OCR)
 * durante un proceso de verificación. Permite auditar errores, tiempos de respuesta
 * y el comportamiento del ComunicadorAPI (patrón Adapter del sistema DAGAZ).
 *
 * @property string        $id_log          UUID v4 (PK CHAR 36)
 * @property string|null   $id_proceso      FK nullable al proceso de verificación
 * @property string        $servicio        Nombre del servicio llamado (ver SERVICIOS)
 * @property string        $tipo_operacion  Tipo de operación ejecutada (ver OPERACIONES)
 * @property int           $codigo_respuesta Código HTTP de la respuesta
 * @property string|null   $detalle_error   Mensaje de error (si aplica)
 * @property \Carbon\Carbon $fecha_registro  Timestamp del log
 *
 * @version 1.0.0
 */
class LogComunicadorApi extends Model
{
    // ─── Constantes de dominio ────────────────────────────────────────────────

    /**
     * Servicios externos integrados en el ComunicadorAPI de DAGAZ.
     *
     * @var list<string>
     */
    public const SERVICIOS = [
        'OpenAI',
        'Gemini',
        'Anthropic',
        'Whisper',
        'Archive.org',
        'Local',
    ];

    /**
     * Tipos de operación que el ComunicadorAPI puede ejecutar.
     *
     * @var list<string>
     */
    public const OPERACIONES = [
        'descomposicion',
        'ocr',
        'busqueda_fuentes',
        'evaluacion',
        'transcripcion',
        'busqueda_web',
    ];

    // ─── Configuración de tabla ───────────────────────────────────────────────

    /** @var string Nombre de la tabla en la base de datos. */
    protected $table = 'logs_comunicador_api';

    /** @var string Clave primaria personalizada. */
    protected $primaryKey = 'id_log';

    /** @var bool La clave primaria no es autoincremental (UUID). */
    public $incrementing = false;

    /** @var string Tipo de la clave primaria. */
    protected $keyType = 'string';

    /** @var string Columna que actúa como created_at. */
    const CREATED_AT = 'fecha_registro';

    /** @var null Sin columna updated_at en el esquema. */
    const UPDATED_AT = null;

    // ─── Asignación masiva ────────────────────────────────────────────────────

    /**
     * Atributos que pueden asignarse de forma masiva.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id_log',
        'id_proceso',
        'servicio',
        'tipo_operacion',
        'codigo_respuesta',
        'detalle_error',
        'fecha_registro',
    ];

    // ─── Casting ─────────────────────────────────────────────────────────────

    /**
     * Conversión de tipos para los atributos del modelo.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'fecha_registro'   => 'datetime',
        'codigo_respuesta' => 'integer',
    ];

    // ─── Boot: UUID automático ────────────────────────────────────────────────

    /**
     * Inicializa el modelo y registra el listener de creación de UUID.
     *
     * Genera automáticamente un UUID v4 como clave primaria si no se
     * proporciona uno al momento de crear el registro.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    // ─── Relaciones ───────────────────────────────────────────────────────────

    /**
     * Proceso de verificación al que está asociado este log.
     *
     * Puede ser null si el log se registró fuera de un proceso formal
     * (e.g. llamadas de prueba o verificaciones de salud del servicio).
     *
     * @return BelongsTo<ProcesoVerificacion, LogComunicadorApi>
     */
    public function proceso(): BelongsTo
    {
        return $this->belongsTo(ProcesoVerificacion::class, 'id_proceso');
    }
}
