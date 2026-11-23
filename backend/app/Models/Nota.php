<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Modelo Nota — Tabla `notas`
 *
 * Esquema real (raiden):
 *   id_nota        CHAR(36)      PK  DEFAULT uuid()
 *   id_usuario     CHAR(36)      FK  NOT NULL
 *   id_contenido   CHAR(36)      FK  NOT NULL
 *   contenido_nota TEXT              NOT NULL
 *   calificacion   DECIMAL(3,1)      NULL  (nullable en la BD real)
 *   fecha_creacion TIMESTAMP         NOT NULL  DEFAULT current_timestamp()
 *
 * @property string         $id_nota
 * @property string         $id_usuario
 * @property string         $id_contenido
 * @property string         $contenido_nota
 * @property float|null     $calificacion
 * @property \Carbon\Carbon $fecha_creacion
 *
 * @package App\Models
 */
class Nota extends Model
{
    protected $table      = 'notas';
    protected $primaryKey = 'id_nota';
    public    $incrementing = false;
    protected $keyType    = 'string';

    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_nota',
        'id_usuario',
        'id_contenido',
        'contenido_nota',
        'calificacion',
    ];

    protected function casts(): array
    {
        return [
            'calificacion'   => 'decimal:1',
            'fecha_creacion' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->id_nota)) {
                $model->id_nota = (string) Str::uuid();
            }
        });
    }

    /**
     * Obtiene la calificación actual (retorna 0.0 si es null).
     */
    public function getCalificacion(): float
    {
        return (float) ($this->calificacion ?? 0.0);
    }

    /**
     * Establece una nueva calificación validando el rango (0.0 a 10.0).
     */
    public function setCalificacion(float $c): void
    {
        $this->calificacion = round(max(0.0, min(10.0, $c)), 1);
        $this->save();
    }

    // ── Relaciones ───────────────────────────────────────────────────────────

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function contenido(): BelongsTo
    {
        return $this->belongsTo(Contenido::class, 'id_contenido', 'id_contenido');
    }
}
