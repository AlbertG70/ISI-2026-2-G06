<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Contenido;
use App\Models\Documento;
use App\Models\Fuente;
use App\Models\Historial;
use App\Models\ProcesoVerificacion;
use App\Models\Usuario;
use App\Models\Veredicto;
use Carbon\Carbon;
use Database\Seeders\NoticiasColombiaSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ActualizarNoticiasCommand extends Command
{
    /**
     * Nombre y firma del comando en consola.
     *
     * @var string
     */
    protected $signature = 'dagaz:actualizar-noticias
                            {--seed : Carga el banco predefinido de noticias y verificaciones recientes de Colombia}
                            {--rss : Ingesta noticias en tiempo real desde canales RSS de medios y fact-checkers colombianos}
                            {--manual : Asistente interactivo para registrar una noticia o cadena verificada manualmente}';

    /**
     * Descripción del comando en consola.
     *
     * @var string
     */
    protected $description = 'Actualiza la base de datos de DAGAZ con noticias y chequeos recientes del contexto colombiano (Seeder, RSS en vivo o Registro Manual)';

    /**
     * Canales RSS de medios colombianos y fact-checkers certificados.
     */
    protected array $rssFeeds = [
        [
            'fuente_nombre' => 'El Tiempo',
            'categoria'     => 'Finanzas y Economía',
            'url_rss'       => 'https://www.eltiempo.com/rss/colombia.xml',
        ],
        [
            'fuente_nombre' => 'La Silla Vacía',
            'categoria'     => 'Gobierno y Política',
            'url_rss'       => 'https://lasillavacia.com/feed/',
        ],
        [
            'fuente_nombre' => 'El Colombiano',
            'categoria'     => 'Seguridad y Orden Público',
            'url_rss'       => 'https://www.elcolombiano.com/rss/colombia.xml',
        ],
    ];

    public function handle(): int
    {
        $this->line('');
        $this->info('================================================================');
        $this->info('  🇨🇴 DAGAZ — Actualizador de Noticias y Chequeos de Colombia   ');
        $this->info('================================================================');
        $this->line('');

        $runSeed   = $this->option('seed');
        $runRss    = $this->option('rss');
        $runManual = $this->option('manual');

        // Si no se pasó ninguna opción, ejecutamos el seeder por defecto
        if (!$runSeed && !$runRss && !$runManual) {
            $runSeed = true;
        }

        if ($runSeed) {
            $this->ejecutarSeeder();
        }

        if ($runRss) {
            $this->ingestarRss();
        }

        if ($runManual) {
            $this->ingresarNoticiaManual();
        }

        $this->line('');
        $this->info('🎉 Proceso de actualización completado exitosamente.');
        $this->line('');

        return Command::SUCCESS;
    }

    /**
     * Ejecuta el seeder estructurado de noticias recientes.
     */
    protected function ejecutarSeeder(): void
    {
        $this->info('📦 Ejecutando NoticiasColombiaSeeder...');
        $this->call(NoticiasColombiaSeeder::class);
    }

    /**
     * Ingesta noticias en tiempo real desde canales RSS de Colombia.
     */
    protected function ingestarRss(): void
    {
        $this->info('📡 Conectando con canales RSS de medios y fact-checkers colombianos...');
        $totalNuevas = 0;

        $operadorLike = DB::connection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        foreach ($this->rssFeeds as $feed) {
            $this->line(" → Consultando: {$feed['fuente_nombre']} ({$feed['url_rss']})...");

            $fuente = Fuente::where('nombre', $operadorLike, '%' . $feed['fuente_nombre'] . '%')->first();
            if (!$fuente) {
                $this->warn("   ⚠️ Fuente '{$feed['fuente_nombre']}' no encontrada en la base de datos.");
                continue;
            }

            try {
                $http = Http::timeout(10)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) DAGAZ-Verifier/1.0']);

                if (app()->environment('local', 'testing') || DIRECTORY_SEPARATOR === '\\') {
                    $http = $http->withoutVerifying();
                }

                $response = $http->get($feed['url_rss']);

                if (!$response->successful()) {
                    $this->warn("   ⚠️ No se pudo obtener el feed RSS (Código HTTP {$response->status()}).");
                    continue;
                }

                $xml = @simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NOCDATA);
                if (!$xml || !isset($xml->channel->item)) {
                    $this->warn("   ⚠️ Formato XML no compatible o feed vacío.");
                    continue;
                }

                $itemsGuardados = 0;
                foreach ($xml->channel->item as $item) {
                    if ($itemsGuardados >= 5) break; // Máximo 5 por fuente para no saturar

                    $titulo = trim((string) $item->title);
                    $link   = trim((string) $item->link);
                    $desc   = trim(strip_tags((string) $item->description));
                    $pubDate = !empty($item->pubDate) ? Carbon::parse((string) $item->pubDate) : Carbon::now();

                    if (empty($titulo) || empty($link)) continue;

                    Documento::updateOrCreate(
                        ['url_articulo' => $link],
                        [
                            'fuente_id'         => $fuente->id_fuente,
                            'categoria'         => $feed['categoria'],
                            'titulo'            => Str::limit($titulo, 250),
                            'fragmento'         => Str::limit($desc ?: $titulo, 500),
                            'fecha_publicacion' => $pubDate,
                        ]
                    );

                    $itemsGuardados++;
                    $totalNuevas++;
                }

                $this->info("   ✅ {$itemsGuardados} artículos indexados de {$feed['fuente_nombre']}.");

            } catch (\Throwable $e) {
                $this->warn("   ❌ Error al procesar feed de {$feed['fuente_nombre']}: {$e->getMessage()}");
            }
        }

        $this->info("📊 Total de artículos RSS indexados en la tabla 'documentos': {$totalNuevas}");
    }

    /**
     * Asistente interactivo en consola para ingresar una nueva noticia colombiana.
     */
    protected function ingresarNoticiaManual(): void
    {
        $this->line('');
        $this->info('✍️  Asistente Interactivo de Registro de Noticia');
        $this->line('Ingrese los datos de la afirmación o noticia colombiana:');

        $texto = $this->ask('1. Texto de la noticia o afirmación (mínimo 10 caracteres)');
        while (empty($texto) || mb_strlen(trim($texto)) < 10) {
            $this->error('El texto debe tener al menos 10 caracteres.');
            $texto = $this->ask('Texto de la noticia');
        }

        $tipoVeredicto = $this->choice(
            '2. Veredicto asignado',
            ['VERDADERO', 'FALSO', 'ENGAÑOSO', 'PARCIAL', 'NO_VERIFICABLE'],
            1
        );

        $confianza = (float) $this->ask('3. Puntaje de confianza (0 a 100)', '90.0');
        $explicacion = $this->ask('4. Explicación pedagógica para ciudadanos (+40 años)', 'Afirmación verificada y contrastada con fuentes de información oficiales.');

        $usuario = Usuario::where('email', 'adulto@dagaz.com')->first() ?: Usuario::first();

        DB::transaction(function () use ($texto, $tipoVeredicto, $confianza, $explicacion, $usuario) {
            $contenido = Contenido::create([
                'id_usuario'         => $usuario?->id_usuario,
                'tipo_entrada'       => 'TEXTO',
                'contenido_original' => trim($texto),
                'estado'             => 'VERIFICADO',
                'fecha_subida'       => Carbon::now(),
            ]);

            $proceso = ProcesoVerificacion::create([
                'id_contenido'        => $contenido->id_contenido,
                'score_confiabilidad' => $confianza,
                'analisis_lenguaje'   => [
                    'categoria_detectada' => 'Noticias Nacionales',
                    'origen'              => 'Registro Manual por Consola',
                ],
                'fecha_analisis'      => Carbon::now(),
            ]);

            $veredicto = Veredicto::create([
                'id_contenido'        => $contenido->id_contenido,
                'id_proceso'          => $proceso->id_proceso,
                'tipo_veredicto'      => $tipoVeredicto,
                'nivel_confiabilidad' => $confianza,
                'explicacion'         => $explicacion,
                'fecha_emision'       => Carbon::now(),
            ]);

            if ($usuario) {
                Historial::create([
                    'id_usuario'   => $usuario->id_usuario,
                    'id_contenido' => $contenido->id_contenido,
                    'accion'       => 'verificado',
                    'fecha_evento' => Carbon::now(),
                ]);
            }
        });

        $this->info("✅ Noticia guardada con éxito en la base de datos con veredicto [{$tipoVeredicto}].");
    }
}
