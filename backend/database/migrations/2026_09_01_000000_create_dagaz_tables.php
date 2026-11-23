<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración principal del sistema DAGAZ.
 *
 * Crea las 9 tablas del sistema con sus índices, claves foráneas
 * y configuración de charset utf8mb4 para soporte completo de Unicode.
 *
 * @version 1.0.0
 */
return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Orden de creación respetando dependencias de FK:
     * 1. usuarios
     * 2. contenidos
     * 3. fuentes
     * 4. procesos_verificacion
     * 5. veredictos
     * 6. veredicto_fuentes
     * 7. notas
     * 8. historial
     * 9. logs_comunicador_api
     */
    public function up(): void
    {
        // ─── 1. USUARIOS ─────────────────────────────────────────────────────
        Schema::create('usuarios', function (Blueprint $table) {
            $table->charset   = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->char('id_usuario', 36)->primary();
            $table->string('email', 255)->unique()->notNullable();
            $table->string('password_hash', 255)->notNullable();
            $table->string('rol', 50)->default('adulto');
            $table->timestamp('fecha_creacion')->useCurrent();
        });

        // ─── 2. CONTENIDOS ───────────────────────────────────────────────────
        Schema::create('contenidos', function (Blueprint $table) {
            $table->charset   = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->char('id_contenido', 36)->primary();
            $table->char('id_usuario', 36)->nullable();
            $table->string('tipo_entrada', 50)->default('texto');
            $table->text('contenido_original');
            $table->text('url_origen')->nullable();
            $table->string('estado', 30)->default('pendiente');
            $table->timestamp('fecha_subida')->useCurrent();

            $table->foreign('id_usuario')
                  ->references('id_usuario')
                  ->on('usuarios')
                  ->nullOnDelete();

            // Índices
            $table->index(['id_usuario', 'fecha_subida'], 'idx_contenidos_usuario_fecha');
            $table->index('estado', 'idx_contenidos_estado');
        });

        // ─── 3. FUENTES ──────────────────────────────────────────────────────
        Schema::create('fuentes', function (Blueprint $table) {
            $table->charset   = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->char('id_fuente', 36)->primary();
            $table->string('nombre', 200)->notNullable();
            $table->text('url_sitio');
            $table->string('tipo_fuente', 50)->default('nacional_n3');
            $table->decimal('indice_credibilidad', 5, 2)->default(50.00);
            $table->timestamp('fecha_registro')->useCurrent();

            // Índice compuesto tipo + credibilidad
            $table->index(['tipo_fuente', 'indice_credibilidad'], 'idx_fuentes_tipo_credibilidad');
        });

        // ─── 3.1. DOCUMENTOS (★ Núcleo Semántico Vectorial pgvector) ───────────
        Schema::create('documentos', function (Blueprint $table) {
            $table->char('id_documento', 36)->primary();
            $table->char('fuente_id', 36);
            $table->string('categoria', 100)->default('Gobierno y Política');
            $table->string('titulo', 500);
            $table->string('fragmento', 2000);
            $table->string('url_articulo', 2048)->unique();
            if (\Illuminate\Support\Facades\DB::connection()->getDriverName() === 'pgsql') {
                $table->addColumn('vector', 'embedding', ['dimensions' => 384])->nullable();
            } else {
                $table->text('embedding')->nullable();
            }
            $table->timestamp('fecha_publicacion')->nullable();
            $table->timestamp('fecha_indexacion')->useCurrent();

            $table->foreign('fuente_id')
                  ->references('id_fuente')
                  ->on('fuentes')
                  ->restrictOnDelete();

            $table->index('fuente_id', 'idx_documentos_fuente');
            $table->index('categoria', 'idx_documentos_categoria');
        });

        // ─── 4. PROCESOS_VERIFICACION ─────────────────────────────────────────
        Schema::create('procesos_verificacion', function (Blueprint $table) {
            $table->charset   = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->char('id_proceso', 36)->primary();
            $table->char('id_contenido', 36);
            $table->decimal('score_confiabilidad', 5, 2)->default(0.00);
            $table->longText('analisis_lenguaje')->nullable();
            $table->longText('contraste_temas')->nullable();
            $table->timestamp('fecha_analisis')->useCurrent();

            $table->foreign('id_contenido')
                  ->references('id_contenido')
                  ->on('contenidos')
                  ->cascadeOnDelete();
        });

        // ─── 5. VEREDICTOS ───────────────────────────────────────────────────
        Schema::create('veredictos', function (Blueprint $table) {
            $table->charset   = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->char('id_veredicto', 36)->primary();
            $table->char('id_contenido', 36);
            $table->char('id_proceso', 36);
            $table->string('tipo_veredicto', 50)->notNullable();
            $table->decimal('nivel_confiabilidad', 5, 2)->default(0.00);
            $table->text('explicacion');
            $table->timestamp('fecha_emision')->useCurrent();

            $table->foreign('id_contenido')
                  ->references('id_contenido')
                  ->on('contenidos')
                  ->cascadeOnDelete();

            $table->foreign('id_proceso')
                  ->references('id_proceso')
                  ->on('procesos_verificacion')
                  ->cascadeOnDelete();

            // Índices
            $table->index('id_contenido', 'idx_veredictos_contenido');
            $table->index('tipo_veredicto', 'idx_veredictos_tipo');
        });

        // ─── 6. VEREDICTO_FUENTES (tabla pivote) ─────────────────────────────
        Schema::create('veredicto_fuentes', function (Blueprint $table) {
            $table->charset   = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->char('id_veredicto', 36);
            $table->char('id_fuente', 36);
            $table->decimal('peso_en_veredicto', 5, 2)->default(1.00);
            $table->text('cita_contraste')->nullable();

            // Clave primaria compuesta
            $table->primary(['id_veredicto', 'id_fuente']);

            $table->foreign('id_veredicto')
                  ->references('id_veredicto')
                  ->on('veredictos')
                  ->cascadeOnDelete();

            $table->foreign('id_fuente')
                  ->references('id_fuente')
                  ->on('fuentes')
                  ->cascadeOnDelete();
        });

        // ─── 7. NOTAS ────────────────────────────────────────────────────────
        Schema::create('notas', function (Blueprint $table) {
            $table->charset   = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->char('id_nota', 36)->primary();
            $table->char('id_usuario', 36);
            $table->char('id_contenido', 36);
            $table->text('contenido_nota');
            $table->decimal('calificacion', 3, 1)->default(0.0);
            $table->timestamp('fecha_creacion')->useCurrent();

            $table->foreign('id_usuario')
                  ->references('id_usuario')
                  ->on('usuarios')
                  ->cascadeOnDelete();

            $table->foreign('id_contenido')
                  ->references('id_contenido')
                  ->on('contenidos')
                  ->cascadeOnDelete();

            // Índice
            $table->index(['id_contenido', 'calificacion'], 'idx_notas_contenido_calif');
        });

        // ─── 8. HISTORIAL ────────────────────────────────────────────────────
        Schema::create('historial', function (Blueprint $table) {
            $table->charset   = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->char('id_historial', 36)->primary();
            $table->char('id_usuario', 36);
            $table->char('id_contenido', 36);
            $table->string('accion', 100)->notNullable();
            $table->timestamp('fecha_evento')->useCurrent();

            $table->foreign('id_usuario')
                  ->references('id_usuario')
                  ->on('usuarios')
                  ->cascadeOnDelete();

            $table->foreign('id_contenido')
                  ->references('id_contenido')
                  ->on('contenidos')
                  ->cascadeOnDelete();

            // Índice
            $table->index('id_usuario', 'idx_historial_usuario');
        });

        // ─── 9. LOGS_COMUNICADOR_API ──────────────────────────────────────────
        Schema::create('logs_comunicador_api', function (Blueprint $table) {
            $table->charset   = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->char('id_log', 36)->primary();
            $table->char('id_proceso', 36)->nullable();
            $table->string('servicio', 100)->notNullable();
            $table->string('tipo_operacion', 50)->notNullable();
            $table->integer('codigo_respuesta');
            $table->text('detalle_error')->nullable();
            $table->timestamp('fecha_registro')->useCurrent();

            $table->foreign('id_proceso')
                  ->references('id_proceso')
                  ->on('procesos_verificacion')
                  ->nullOnDelete();

            // Índice
            $table->index(['id_proceso', 'servicio'], 'idx_logs_proceso_servicio');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * El orden es inverso para respetar las restricciones de FK.
     */
    public function down(): void
    {
        Schema::dropIfExists('logs_comunicador_api');
        Schema::dropIfExists('historial');
        Schema::dropIfExists('notas');
        Schema::dropIfExists('veredicto_fuentes');
        Schema::dropIfExists('veredictos');
        Schema::dropIfExists('procesos_verificacion');
        Schema::dropIfExists('documentos');
        Schema::dropIfExists('fuentes');
        Schema::dropIfExists('contenidos');
        Schema::dropIfExists('usuarios');
    }
};
