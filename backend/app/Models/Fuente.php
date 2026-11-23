<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * Modelo Fuente — Tabla `fuentes`
 *
 * Esquema real (raiden):
 *   id_fuente            CHAR(36)     PK  DEFAULT uuid()
 *   nombre               VARCHAR(200) UNI NOT NULL
 *   url_sitio            TEXT             NULL
 *   tipo_fuente          VARCHAR(50)  MUL NOT NULL
 *   indice_credibilidad  DECIMAL(5,2)     NULL  DEFAULT 50.00
 *   fecha_registro       TIMESTAMP        NOT NULL  DEFAULT current_timestamp()
 *
 * Nota: `nombre` tiene UNIQUE KEY — no se pueden duplicar nombres de fuente.
 * Nota: `url_sitio` e `indice_credibilidad` son nullable en la BD real.
 *
 * @property string         $id_fuente
 * @property string         $nombre
 * @property string|null    $url_sitio
 * @property string         $tipo_fuente
 * @property float|null     $indice_credibilidad
 * @property \Carbon\Carbon $fecha_registro
 *
 * @package App\Models
 */
class Fuente extends Model
{
    protected $table      = 'fuentes';
    protected $primaryKey = 'id_fuente';
    public    $incrementing = false;
    protected $keyType    = 'string';

    const CREATED_AT = 'fecha_registro';
    const UPDATED_AT = null;

    /** Tipos de fuente (CHECK real de la BD raiden) */
    const TIPOS = [
        'OFICIAL',
        'PERIODISTICO',
        'ACADEMICO',
        'RED_SOCIAL',
        'OTRO',
    ];

    protected $fillable = [
        'id_fuente',
        'nombre',
        'url_sitio',
        'tipo_fuente',
        'indice_credibilidad',
    ];

    protected function casts(): array
    {
        return [
            'indice_credibilidad' => 'decimal:2',
            'fecha_registro'      => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->id_fuente)) {
                $model->id_fuente = (string) Str::uuid();
            }
            if ($model->getConnection()->getDriverName() === 'pgsql') {
                if (!empty($model->tipo_fuente)) {
                    $tipo = strtolower($model->tipo_fuente);
                    $tipo = match ($tipo) {
                        'oficial'      => 'nacional_n3',
                        'periodistico' => 'regional_n2',
                        'academico'    => 'internacional_n1',
                        'red_social'   => 'otro',
                        default        => in_array($tipo, ['internacional_n1', 'regional_n2', 'nacional_n3', 'factchecker_n4', 'otro'], true) ? $tipo : 'otro',
                    };
                    $model->tipo_fuente = $tipo;
                }
            }
        });

        static::updating(function (self $model) {
            if ($model->getConnection()->getDriverName() === 'pgsql') {
                if (!empty($model->tipo_fuente)) {
                    $tipo = strtolower($model->tipo_fuente);
                    $tipo = match ($tipo) {
                        'oficial'      => 'nacional_n3',
                        'periodistico' => 'regional_n2',
                        'academico'    => 'internacional_n1',
                        'red_social'   => 'otro',
                        default        => in_array($tipo, ['internacional_n1', 'regional_n2', 'nacional_n3', 'factchecker_n4', 'otro'], true) ? $tipo : 'otro',
                    };
                    $model->tipo_fuente = $tipo;
                }
            }
        });
    }

    /**
     * Obtiene la clasificación legible de la fuente según su tipo en la BD.
     */
    public function getClasificacion(): string
    {
        return match ($this->tipo_fuente) {
            'OFICIAL'      => 'Fuente Oficial',
            'PERIODISTICO' => 'Medio Periodístico',
            'ACADEMICO'    => 'Fuente Académica',
            'RED_SOCIAL'   => 'Red Social',
            'OTRO'         => 'Otra fuente',
            default        => $this->tipo_fuente,
        };
    }

    /**
     * Obtiene el índice de credibilidad (retorna 50.0 si es null).
     */
    public function getCredibilidad(): float
    {
        return (float) ($this->indice_credibilidad ?? 50.0);
    }

    /**
     * Determina el nivel de jerarquía en el Grafo de Credibilidad de DAGAZ (1 a 4).
     *
     * - Nivel 1: Agencias internacionales y organismos multilaterales (REQ-08)
     * - Nivel 2: Prensa regional colombiana (REQ-14 / TSK-3.1)
     * - Nivel 3: Prensa nacional de Colombia (REQ-14 / TSK-3.1)
     * - Nivel 4: Fact-checkers certificados IFCN y entidades de control (REQ-20 / TSK-4.1)
     */
    public function getNivel(): int
    {
        $nombre = mb_strtolower($this->nombre);

        // Nivel 4: Fact-checkers certificados por la IFCN y organismos oficiales de control/estadística
        if (
            str_contains($nombre, 'check') ||
            str_contains($nombre, 'factual') ||
            str_contains($nombre, 'silla vacía') ||
            str_contains($nombre, 'maldita') ||
            str_contains($nombre, 'newtral') ||
            str_contains($nombre, 'snopes') ||
            str_contains($nombre, 'politifact') ||
            str_contains($nombre, 'sabueso') ||
            str_contains($nombre, 'ojopúblico') ||
            str_contains($nombre, 'lupa') ||
            str_contains($nombre, 'dane') ||
            str_contains($nombre, 'banco de la república') ||
            str_contains($nombre, 'registraduría') ||
            str_contains($nombre, 'consejo nacional electoral') ||
            str_contains($nombre, 'instituto nacional de salud') ||
            str_contains($nombre, 'defensoría del pueblo') ||
            $this->tipo_fuente === 'ACADEMICO'
        ) {
            return 4;
        }

        // Nivel 1: Agencias internacionales y organismos multilaterales
        $agenciasN1 = ['reuters', 'associated press', 'afp', 'bbc news', 'oms', 'onu', 'efe', 'guardian', 'dw', 'cnn', 'ocde', 'unesco', 'ops/paho', 'times', 'post', 'financial', 'bloomberg', 'france 24', 'euronews'];
        foreach ($agenciasN1 as $ag) {
            if (str_contains($nombre, $ag)) {
                return 1;
            }
        }
        if ($this->tipo_fuente === 'OFICIAL' && !str_contains($nombre, 'colombia')) {
            return 1;
        }

        // Nivel 2: Prensa regional colombiana
        $mediosRegionales = [
            'colombiano', 'heraldo', 'vanguardia', 'el país (cali', 'la patria', 'universal',
            'la opinión', 'el diario', 'nuevo día', 'periódico 15', 'pilón', 'magdalena',
            'quindío', 'huila', 'sur', 'liberal', 'pacífico', 'islas', 'tarde'
        ];
        foreach ($mediosRegionales as $reg) {
            if (str_contains($nombre, $reg)) {
                return 2;
            }
        }

        // Nivel 3: Prensa nacional de Colombia
        return 3;
    }

    /**
     * Retorna la etiqueta legible del nivel de jerarquía del grafo.
     */
    public function getNivelEtiqueta(): string
    {
        return match ($this->getNivel()) {
            1 => 'Nivel 1: Internacional / Agencia Multilateral',
            2 => 'Nivel 2: Prensa Regional Colombia',
            3 => 'Nivel 3: Prensa Nacional Colombia',
            4 => 'Nivel 4: Fact-Checker Certificado IFCN / Oficial de Control',
            default => 'Nivel No Clasificado',
        };
    }

    // ── Relaciones ───────────────────────────────────────────────────────────

    public function veredictos(): BelongsToMany
    {
        return $this->belongsToMany(
            Veredicto::class,
            'veredicto_fuentes',
            'id_fuente',
            'id_veredicto'
        )->withPivot(['peso_en_veredicto', 'cita_contraste']);
    }

    /**
     * Artículos y fragmentos indexados de esta fuente con embeddings vectoriales.
     */
    public function documentos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Documento::class, 'fuente_id', 'id_fuente');
    }
}
