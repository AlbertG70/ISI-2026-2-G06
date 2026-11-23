<?php

namespace Database\Seeders;

use App\Models\Contenido;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * ContenidosPruebaSeeder
 *
 * Genera contenidos de prueba para el ciclo completo de verificación DAGAZ.
 * Cubre los tres estados del pipeline: pendiente → procesando → completado.
 *
 * IMPORTANTE: Este seeder es EXCLUSIVO para desarrollo y testing.
 * NO está incluido en DatabaseSeeder. Ejecútalo manualmente:
 *   php artisan db:seed --class=ContenidosPruebaSeeder
 *
 * Prerequisito: UsuariosSeeder debe haberse ejecutado previamente.
 */
class ContenidosPruebaSeeder extends Seeder
{
    /**
     * Ejecuta los seeders de la base de datos.
     */
    public function run(): void
    {
        // Obtener el usuario adulto de prueba
        $usuarioAdulto = User::where('email', 'adulto@dagaz.com')->firstOrFail();

        // ----------------------------------------------------------------
        // Contenido 1 – Estado: pendiente
        // Representa una noticia recién enviada, aún no procesada por la IA.
        // ----------------------------------------------------------------
        Contenido::updateOrCreate(
            [
                'id_usuario' => $usuarioAdulto->id,
                'texto_original' => 'El gobierno colombiano anunció que el PIB creció un 5% en el último trimestre, superando todas las expectativas de los analistas económicos.',
            ],
            [
                'tipo_entrada' => 'texto',
                'estado'       => 'pendiente',
                'metadata'     => json_encode([
                    'fuente_usuario' => 'WhatsApp',
                    'idioma'         => 'es',
                ]),
            ]
        );

        // ----------------------------------------------------------------
        // Contenido 2 – Estado: procesando
        // Representa una noticia en análisis activo por el pipeline de IA.
        // ----------------------------------------------------------------
        Contenido::updateOrCreate(
            [
                'id_usuario' => $usuarioAdulto->id,
                'texto_original' => 'Una imagen viral muestra al presidente firmando un decreto que eliminaría las pensiones públicas a partir de enero de 2027.',
            ],
            [
                'tipo_entrada' => 'imagen',
                'estado'       => 'procesando',
                'metadata'     => json_encode([
                    'fuente_usuario' => 'Telegram',
                    'idioma'         => 'es',
                    'ocr_iniciado'   => true,
                ]),
            ]
        );

        // ----------------------------------------------------------------
        // Contenido 3 – Estado: completado
        // Representa una noticia con veredicto final emitido por el sistema.
        // ----------------------------------------------------------------
        Contenido::updateOrCreate(
            [
                'id_usuario' => $usuarioAdulto->id,
                'texto_original' => 'Colombia aprobó la ley que obliga a todas las empresas a implementar trabajo remoto obligatorio los viernes a partir del mes de octubre.',
            ],
            [
                'tipo_entrada' => 'texto',
                'estado'       => 'completado',
                'metadata'     => json_encode([
                    'fuente_usuario' => 'Facebook',
                    'idioma'         => 'es',
                    'tokens_usados'  => 843,
                    'modelo_ia'      => 'gpt-4o',
                ]),
            ]
        );

        $this->command->info('✅ ContenidosPruebaSeeder: 3 contenidos de prueba insertados/actualizados.');
        $this->command->warn('   ℹ️  Recuerda ejecutar VeredictosPruebaSeeder si necesitas veredictos asociados.');
    }
}
