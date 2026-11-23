<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * Modelo Usuario — Tabla `usuarios`
 *
 * Mapea la tabla `usuarios` de la BD `raiden`.
 * Esquema real (MariaDB 10.4.32 / XAMPP):
 *   id_usuario     CHAR(36)     PK  DEFAULT uuid()
 *   email          VARCHAR(255) UNI NOT NULL
 *   password_hash  VARCHAR(255)     NOT NULL
 *   rol            VARCHAR(50)      NOT NULL  DEFAULT 'lector'
 *   fecha_creacion TIMESTAMP        NOT NULL  DEFAULT current_timestamp()
 *
 * @property string      $id_usuario
 * @property string      $email
 * @property string      $password_hash
 * @property string      $rol            — 'lector' | 'adulto' | 'admin' | 'editor'
 * @property \Carbon\Carbon $fecha_creacion
 *
 * @package App\Models
 */
class Usuario extends Authenticatable
{
    use HasApiTokens;
    protected $table      = 'usuarios';
    protected $primaryKey = 'id_usuario';
    public    $incrementing = false;
    protected $keyType    = 'string';

    /** La BD ya genera UUID automáticamente; CREATED_AT apunta a fecha_creacion */
    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_usuario',
        'email',
        'password_hash',
        'rol',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'fecha_creacion' => 'datetime',
        ];
    }

    /** Auto-generar UUID al crear si no viene provisto */
    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->id_usuario)) {
                $model->id_usuario = (string) Str::uuid();
            }
            if ($model->getConnection()->getDriverName() === 'pgsql') {
                if (!empty($model->rol)) {
                    $model->rol = strtolower($model->rol);
                }
            }
        });

        static::updating(function (self $model) {
            if ($model->getConnection()->getDriverName() === 'pgsql') {
                if (!empty($model->rol)) {
                    $model->rol = strtolower($model->rol);
                }
            }
        });
    }

    // ── Sanctum ──────────────────────────────────────────────────────────────

    /** Requerido por Sanctum/Auth: retorna el campo de contraseña hasheada */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    // ── Métodos de dominio ───────────────────────────────────────────────────

    /**
     * Indica si el usuario tiene rol de administrador.
     */
    public function esAdministrador(): bool
    {
        return $this->rol === 'admin';
    }

    /**
     * Indica si el usuario tiene rol de editor/verificador.
     */
    public function esEditor(): bool
    {
        return in_array($this->rol, ['admin', 'editor']);
    }

    // ── Relaciones ───────────────────────────────────────────────────────────

    public function contenidos(): HasMany
    {
        return $this->hasMany(Contenido::class, 'id_usuario', 'id_usuario');
    }

    public function notas(): HasMany
    {
        return $this->hasMany(Nota::class, 'id_usuario', 'id_usuario');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(Historial::class, 'id_usuario', 'id_usuario');
    }
}
