<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Modelo Veredicto — Tabla `veredictos`
 *
 * Esquema real (raiden):
 *   id_veredicto        CHAR(36)     PK  DEFAULT uuid()
 *   id_contenido        CHAR(36)     UNI NOT NULL  (1 veredicto por contenido)
 *   id_proceso          CHAR(36)     FK  NULL
 *   tipo_veredicto      VARCHAR(50)      NOT NULL
 *   nivel_confiabilidad DECIMAL(5,2)     NOT NULL
 *   explicacion         TEXT             NOT NULL
 *   fecha_emision       TIMESTAMP        NOT NULL  DEFAULT current_timestamp()
 *
 * @property string         $id_veredicto
 * @property string         $id_contenido
 * @property string|null    $id_proceso
 * @property string         $tipo_veredicto
 * @property float          $nivel_confiabilidad
 * @property string         $explicacion
 * @property string|null    $motivo_breve
 * @property \Carbon\Carbon $fecha_emision
 *
 * @package App\Models
 */
class Veredicto extends Model
{
    protected $table      = 'veredictos';
    protected $primaryKey = 'id_veredicto';
    public    $incrementing = false;
    protected $keyType    = 'string';

    const CREATED_AT = 'fecha_emision';
    const UPDATED_AT = null;

    /** Tipos de veredicto (CHECK real de la BD raiden — todo MAYÚSCULAS) */
    const TIPOS = ['VERDADERO', 'FALSO', 'ENGAÑOSO', 'PARCIAL', 'NO_VERIFICABLE'];

    protected $fillable = [
        'id_veredicto',
        'id_contenido',
        'id_proceso',
        'tipo_veredicto',
        'nivel_confiabilidad',
        'explicacion',
        'motivo_breve',
        'fecha_emision',
    ];

    protected function casts(): array
    {
        return [
            'nivel_confiabilidad' => 'decimal:2',
            'fecha_emision'       => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->id_veredicto)) {
                $model->id_veredicto = (string) Str::uuid();
            }
            if ($model->getConnection()->getDriverName() === 'pgsql') {
                if (!empty($model->tipo_veredicto)) {
                    $tipo = mb_strtolower($model->tipo_veredicto, 'UTF-8');
                    $tipo = match ($tipo) {
                        'enganoso' => 'engañoso',
                        default    => in_array($tipo, ['verdadero', 'falso', 'engañoso', 'parcial', 'no_verificable'], true) ? $tipo : 'no_verificable',
                    };
                    $model->tipo_veredicto = $tipo;
                }
            }
        });

        static::updating(function (self $model) {
            if ($model->getConnection()->getDriverName() === 'pgsql') {
                if (!empty($model->tipo_veredicto)) {
                    $tipo = mb_strtolower($model->tipo_veredicto, 'UTF-8');
                    $tipo = match ($tipo) {
                        'enganoso' => 'engañoso',
                        default    => in_array($tipo, ['verdadero', 'falso', 'engañoso', 'parcial', 'no_verificable'], true) ? $tipo : 'no_verificable',
                    };
                    $model->tipo_veredicto = $tipo;
                }
            }
        });
    }

    /**
     * Determina si el veredicto tiene nivel de confiabilidad alto (≥70%).
     */
    public function esConfiable(): bool
    {
        return (float) $this->nivel_confiabilidad >= 70.0;
    }

    // ── Relaciones ───────────────────────────────────────────────────────────

    public function contenido(): BelongsTo
    {
        return $this->belongsTo(Contenido::class, 'id_contenido', 'id_contenido');
    }

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(ProcesoVerificacion::class, 'id_proceso', 'id_proceso');
    }

    public function fuentes(): BelongsToMany
    {
        return $this->belongsToMany(
            Fuente::class,
            'veredicto_fuentes',
            'id_veredicto',
            'id_fuente'
        )->withPivot(['peso_en_veredicto', 'cita_contraste']);
    }
}
