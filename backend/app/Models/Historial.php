<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Modelo Eloquent para la tabla `historial`.
 *
 * Registra las acciones significativas que un usuario realiza sobre
 * los contenidos del sistema DAGAZ (verificar, guardar, valorar, eliminar).
 * Actúa como log de auditoría a nivel de usuario.
 *
 * @property string        $id_historial  UUID v4 (PK CHAR 36)
 * @property string        $id_usuario    FK al usuario que realizó la acción
 * @property string        $id_contenido  FK al contenido involucrado
 * @property string        $accion        Tipo de acción (ver ACCIONES)
 * @property \Carbon\Carbon $fecha_evento  Timestamp del evento
 *
 * @version 1.0.0
 */
class Historial extends Model
{
    // ─── Constantes de dominio ────────────────────────────────────────────────

    /**
     * Acciones válidas que pueden registrarse en el historial.
     *
     * @var list<string>
     */
    public const ACCIONES = [
        'verificado',
        'guardado',
        'valorado',
        'eliminado',
    ];

    // ─── Configuración de tabla ───────────────────────────────────────────────

    /** @var string Nombre de la tabla en la base de datos. */
    protected $table = 'historial';

    /** @var string Clave primaria personalizada. */
    protected $primaryKey = 'id_historial';

    /** @var bool La clave primaria no es autoincremental (UUID). */
    public $incrementing = false;

    /** @var string Tipo de la clave primaria. */
    protected $keyType = 'string';

    /** @var string Columna que actúa como created_at. */
    const CREATED_AT = 'fecha_evento';

    /** @var null Sin columna updated_at en el esquema. */
    const UPDATED_AT = null;

    // ─── Asignación masiva ────────────────────────────────────────────────────

    /**
     * Atributos que pueden asignarse de forma masiva.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id_historial',
        'id_usuario',
        'id_contenido',
        'accion',
        'fecha_evento',
    ];

    // ─── Casting ─────────────────────────────────────────────────────────────

    /**
     * Conversión de tipos para los atributos del modelo.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'fecha_evento' => 'datetime',
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
     * Usuario que realizó la acción registrada.
     *
     * @return BelongsTo<Usuario, Historial>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }

    /**
     * Contenido sobre el que se realizó la acción.
     *
     * @return BelongsTo<Contenido, Historial>
     */
    public function contenido(): BelongsTo
    {
        return $this->belongsTo(Contenido::class, 'id_contenido');
    }

    // ─── Métodos de negocio ───────────────────────────────────────────────────

    /**
     * Genera un resumen legible del evento de historial.
     *
     * Combina la acción realizada y la fecha del evento en un string
     * formateado para mostrar en interfaces de usuario o reportes.
     *
     * @return string Resumen en formato "Acción: {accion} — Fecha: {fecha}".
     */
    public function getResumen(): string
    {
        $fecha = $this->fecha_evento
            ? $this->fecha_evento->format('d/m/Y H:i:s')
            : 'sin fecha';

        return sprintf('Acción: %s — Fecha: %s', $this->accion, $fecha);
    }
}
