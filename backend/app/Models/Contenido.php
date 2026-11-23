<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo Contenido — Tabla `contenidos`
 *
 * Esquema real (raiden):
 *   id_contenido       CHAR(36)     PK  DEFAULT uuid()
 *   id_usuario         CHAR(36)     FK  NULL
 *   tipo_entrada       VARCHAR(50)      NOT NULL  (sin default en BD)
 *   contenido_original TEXT             NOT NULL
 *   url_origen         TEXT             NULL
 *   estado             VARCHAR(30)      NULL  DEFAULT 'PENDIENTE'
 *   fecha_subida       TIMESTAMP        NOT NULL  DEFAULT current_timestamp()
 *
 * Nota: `estado` usa MAYÚSCULAS en la BD ('PENDIENTE', no 'pendiente').
 *
 * @property string          $id_contenido
 * @property string|null     $id_usuario
 * @property string          $tipo_entrada
 * @property string          $contenido_original
 * @property string|null     $url_origen
 * @property string|null     $estado
 * @property \Carbon\Carbon  $fecha_subida
 *
 * @package App\Models
 */
class Contenido extends Model
{
    protected $table      = 'contenidos';
    protected $primaryKey = 'id_contenido';
    public    $incrementing = false;
    protected $keyType    = 'string';

    const CREATED_AT = 'fecha_subida';
    const UPDATED_AT = null;

    /** Estados tal como aparecen en la BD (mayúsculas) */
    const ESTADOS = ['PENDIENTE', 'PROCESANDO', 'VERIFICADO', 'ERROR'];

    /** Tipos de entrada (CHECK real de la BD raiden) */
    const TIPOS_ENTRADA = ['TEXTO', 'URL', 'IMAGEN_OCR', 'RED_SOCIAL'];

    protected $fillable = [
        'id_contenido',
        'id_usuario',
        'tipo_entrada',
        'contenido_original',
        'url_origen',
        'estado',
        'fecha_subida',
    ];

    protected function casts(): array
    {
        return [
            'fecha_subida' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->id_contenido)) {
                $model->id_contenido = (string) Str::uuid();
            }
            if ($model->getConnection()->getDriverName() === 'pgsql') {
                if (!empty($model->tipo_entrada)) {
                    $tipo = strtolower($model->tipo_entrada);
                    $model->tipo_entrada = match ($tipo) {
                        'imagen_ocr' => 'imagen',
                        'red_social' => 'url',
                        default      => in_array($tipo, ['texto', 'url', 'imagen', 'audio', 'video'], true) ? $tipo : 'texto',
                    };
                }
                if (!empty($model->estado)) {
                    $estado = strtolower($model->estado);
                    $model->estado = match ($estado) {
                        'verificado' => 'completado',
                        default      => in_array($estado, ['pendiente', 'procesando', 'completado', 'error'], true) ? $estado : 'pendiente',
                    };
                }
            } else {
                // Normalizar tipo_entrada a mayúsculas
                if (!empty($model->tipo_entrada)) {
                    $model->tipo_entrada = strtoupper($model->tipo_entrada);
                }
                // Normalizar estado a mayúsculas
                if (!empty($model->estado)) {
                    $model->estado = strtoupper($model->estado);
                }
            }
        });

        static::updating(function (self $model) {
            if ($model->getConnection()->getDriverName() === 'pgsql') {
                if (!empty($model->tipo_entrada)) {
                    $tipo = strtolower($model->tipo_entrada);
                    $model->tipo_entrada = match ($tipo) {
                        'imagen_ocr' => 'imagen',
                        'red_social' => 'url',
                        default      => in_array($tipo, ['texto', 'url', 'imagen', 'audio', 'video'], true) ? $tipo : 'texto',
                    };
                }
                if (!empty($model->estado)) {
                    $estado = strtolower($model->estado);
                    $model->estado = match ($estado) {
                        'verificado' => 'completado',
                        default      => in_array($estado, ['pendiente', 'procesando', 'completado', 'error'], true) ? $estado : 'pendiente',
                    };
                }
            } else {
                if (!empty($model->tipo_entrada)) {
                    $model->tipo_entrada = strtoupper($model->tipo_entrada);
                }
                if (!empty($model->estado)) {
                    $model->estado = strtoupper($model->estado);
                }
            }
        });
    }

    // ── Relaciones ───────────────────────────────────────────────────────────

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function procesos(): HasMany
    {
        return $this->hasMany(ProcesoVerificacion::class, 'id_contenido', 'id_contenido');
    }

    /** Último proceso de verificación */
    public function ultimoProceso(): HasOne
    {
        return $this->hasOne(ProcesoVerificacion::class, 'id_contenido', 'id_contenido')
            ->latestOfMany('fecha_analisis');
    }

    /** Alias de conveniencia para el proceso más reciente */
    public function procesoActual(): HasOne
    {
        return $this->ultimoProceso();
    }

    public function notas(): HasMany
    {
        return $this->hasMany(Nota::class, 'id_contenido', 'id_contenido');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(Historial::class, 'id_contenido', 'id_contenido');
    }

    public function veredictos(): HasMany
    {
        return $this->hasMany(Veredicto::class, 'id_contenido', 'id_contenido');
    }

    /** El veredicto único (la BD tiene UNIQUE en id_contenido) */
    public function veredicto(): HasOne
    {
        return $this->hasOne(Veredicto::class, 'id_contenido', 'id_contenido');
    }
}
