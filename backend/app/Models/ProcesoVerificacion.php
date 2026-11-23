<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Modelo Eloquent para la tabla `procesos_verificacion`.
 *
 * Almacena el resultado del análisis realizado por los servicios de IA
 * sobre un contenido determinado. Actúa como registro intermedio entre
 * el contenido original y el veredicto final.
 *
 * @property string        $id_proceso           UUID v4 (PK CHAR 36)
 * @property string        $id_contenido         FK al contenido analizado
 * @property float         $score_confiabilidad  Puntuación 0.00–100.00
 * @property string|null   $analisis_lenguaje    JSON con análisis de lenguaje (IA)
 * @property string|null   $contraste_temas      JSON con contraste de temas
 * @property \Carbon\Carbon $fecha_analisis       Timestamp del análisis
 *
 * @version 1.0.0
 */
class ProcesoVerificacion extends Model
{
    // ─── Configuración de tabla ───────────────────────────────────────────────

    /** @var string Nombre de la tabla en la base de datos. */
    protected $table = 'procesos_verificacion';

    /** @var string Clave primaria personalizada. */
    protected $primaryKey = 'id_proceso';

    /** @var bool La clave primaria no es autoincremental (UUID). */
    public $incrementing = false;

    /** @var string Tipo de la clave primaria. */
    protected $keyType = 'string';

    /** @var string Columna que actúa como created_at. */
    const CREATED_AT = 'fecha_analisis';

    /** @var null Sin columna updated_at en el esquema. */
    const UPDATED_AT = null;

    // ─── Asignación masiva ────────────────────────────────────────────────────

    /**
     * Atributos que pueden asignarse de forma masiva.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id_proceso',
        'id_contenido',
        'score_confiabilidad',
        'analisis_lenguaje',
        'contraste_temas',
        'fecha_analisis',
    ];

    // ─── Casting ─────────────────────────────────────────────────────────────

    /**
     * Conversión de tipos para los atributos del modelo.
     *
     * `analisis_lenguaje` y `contraste_temas` se castean a 'array' para
     * garantizar que siempre se serialicen como JSON válido (CHECK json_valid).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score_confiabilidad' => 'decimal:2',
            'fecha_analisis'      => 'datetime',
            'analisis_lenguaje'   => 'array',
            'contraste_temas'     => 'array',
        ];
    }

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
     * Contenido al que pertenece este proceso de verificación.
     *
     * @return BelongsTo<Contenido, ProcesoVerificacion>
     */
    public function contenido(): BelongsTo
    {
        return $this->belongsTo(Contenido::class, 'id_contenido');
    }

    /**
     * Veredictos generados a partir de este proceso.
     *
     * @return HasMany<Veredicto>
     */
    public function veredictos(): HasMany
    {
        return $this->hasMany(Veredicto::class, 'id_proceso');
    }

    /**
     * Veredicto más reciente generado a partir de este proceso.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function veredicto(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Veredicto::class, 'id_proceso')->latestOfMany('fecha_emision');
    }

    /**
     * Logs de las llamadas a APIs externas realizadas durante este proceso.
     *
     * @return HasMany<LogComunicadorApi>
     */
    public function logsApi(): HasMany
    {
        return $this->hasMany(LogComunicadorApi::class, 'id_proceso');
    }
}
