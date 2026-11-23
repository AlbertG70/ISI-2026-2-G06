<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Modelo Documento — Tabla `documentos`
 *
 * Núcleo de Búsqueda Semántica Vectorial de DAGAZ.
 * Almacena artículos y fragmentos atómicos de las 80 fuentes del Grafo de Credibilidad,
 * enriquecidos con representaciones vectoriales densas de 384 dimensiones (`all-MiniLM-L6-v2`)
 * gestionadas mediante la extensión `pgvector` de PostgreSQL 18 en Neon.tech.
 *
 * Esquema:
 *   id_documento      UUID         PK  DEFAULT gen_random_uuid()
 *   fuente_id         UUID         FK  REFERENCES fuentes(id_fuente)
 *   categoria         VARCHAR(100) NOT NULL DEFAULT 'Gobierno y Política'
 *   titulo            VARCHAR(500) NOT NULL
 *   fragmento         VARCHAR(2000) NOT NULL
 *   url_articulo      VARCHAR(2048) UNIQUE NOT NULL
 *   embedding         vector(384)  NULL
 *   fecha_publicacion TIMESTAMPTZ  NULL
 *   fecha_indexacion  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
 *
 * @property string         $id_documento
 * @property string         $fuente_id
 * @property string         $categoria
 * @property string         $titulo
 * @property string         $fragmento
 * @property string         $url_articulo
 * @property string|null    $embedding
 * @property \Carbon\Carbon|null $fecha_publicacion
 * @property \Carbon\Carbon $fecha_indexacion
 * @property float|null     $similitud      Atributo calculado en búsquedas KNN
 *
 * @property-read \App\Models\Fuente $fuente
 *
 * @package App\Models
 */
class Documento extends Model
{
    protected $table = 'documentos';
    protected $primaryKey = 'id_documento';
    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'fecha_indexacion';
    const UPDATED_AT = null;

    /** Categorías válidas según diseño */
    const CATEGORIAS = [
        'Finanzas y Economía',
        'Gobierno y Política',
        'Deportes',
        'Entretenimiento y Farándula',
        'Estilo de Vida y Sociedad',
        'Educación y Empleo',
    ];

    /** Dimensión oficial del modelo vectorial all-MiniLM-L6-v2 */
    const DIMENSION_VECTORIAL = 384;

    protected $fillable = [
        'id_documento',
        'fuente_id',
        'categoria',
        'titulo',
        'fragmento',
        'url_articulo',
        'embedding',
        'fecha_publicacion',
        'fecha_indexacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_publicacion' => 'datetime',
            'fecha_indexacion'  => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->id_documento)) {
                $model->id_documento = (string) Str::uuid();
            }
        });
    }

    // ── Relaciones ───────────────────────────────────────────────────────────

    /**
     * Fuente a la que pertenece el documento indexado.
     */
    public function fuente(): BelongsTo
    {
        return $this->belongsTo(Fuente::class, 'fuente_id', 'id_fuente');
    }

    // ── Scopes de Búsqueda Semántica Vectorial ─────────────────────────────────

    /**
     * Búsqueda semántica KNN exacta utilizando distancia coseno (<=>).
     *
     * Cumple con la directriz de Neon.tech Free Tier:
     * Sin índices HNSW/IVFFlat, escaneo KNN exacto secuencial vectorizado en SSD.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array<float>|string                   $vector     Vector de 384 dimensiones
     * @param  int                                    $limite     Número de candidatos (default 10)
     * @param  array<string>|null                     $fuenteIds  Filtro opcional de fuentes UUID
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBusquedaKnnExacta(
        Builder $query,
        array|string $vector,
        int $limite = 10,
        ?array $fuenteIds = null
    ): Builder {
        $vectorStr = is_array($vector)
            ? '[' . implode(',', $vector) . ']'
            : $vector;

        $driver = $this->getConnection()->getDriverName();

        // En PostgreSQL con pgvector: ejecución de consulta KNN exacta
        if ($driver === 'pgsql') {
            if (!empty($fuenteIds)) {
                $query->whereIn('fuente_id', $fuenteIds);
            }

            return $query->whereNotNull('embedding')
                ->selectRaw('*, 1 - (embedding <=> ?::vector(384)) AS similitud', [$vectorStr])
                ->orderByRaw('embedding <=> ?::vector(384)', [$vectorStr])
                ->limit($limite);
        }

        // Fallback para entornos de testing SQLite
        if (!empty($fuenteIds)) {
            $query->whereIn('fuente_id', $fuenteIds);
        }

        return $query->orderByDesc('fecha_indexacion')->limit($limite);
    }
}
