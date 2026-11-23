<?php

namespace Database\Seeders;

use App\Models\Documento;
use App\Models\Fuente;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DocumentosNoticiasSeeder extends Seeder
{
    public function run(): void
    {
        $fuentes = Fuente::all()->keyBy('nombre');
        if ($fuentes->isEmpty()) {
            return;
        }

        $noticias = [
            // Finanzas y Economía
            [
                'fuente'    => 'Portafolio',
                'categoria' => 'Finanzas y Economía',
                'titulo'    => 'Banco de la República aumenta tasas de interés para contener inflación',
                'fragmento' => 'La junta directiva del Banco de la República confirma incremento en tasas de interés ante presiones inflacionarias globales y el precio del dólar.',
                'url'       => 'https://www.portafolio.co/economia/tasas-inflacion-colombia',
            ],
            [
                'fuente'    => 'La República',
                'categoria' => 'Finanzas y Economía',
                'titulo'    => 'Dólar e inflación muestran estabilidad según informe financiero',
                'fragmento' => 'El mercado cambiario registra cotización estable del dólar mientras analistas proyectan balance positivo en el crecimiento del PIB.',
                'url'       => 'https://www.larepublica.co/finanzas/dolar-inflacion-proyeccion',
            ],

            // Gobierno y Política
            [
                'fuente'    => 'Registraduría Nacional del Estado Civil (Colombia)',
                'categoria' => 'Gobierno y Política',
                'titulo'    => 'Posesión y período constitucional del Presidente de la República de Colombia',
                'fragmento' => 'La Registraduría Nacional del Estado Civil certifica que Gustavo Petro Urrego ejerce como Presidente Constitucional de la República de Colombia (período 2022-2026) y Francia Márquez Mina como Vicepresidenta. Ningún otro ciudadano ostenta la jefatura del Estado.',
                'url'       => 'https://www.registraduria.gov.co/elecciones-presidencia-colombia',
            ],
            [
                'fuente'    => 'Agencia EFE',
                'categoria' => 'Gobierno y Política',
                'titulo'    => 'Tribunal Superior Eleitoral proclama reelección de Lula en Brasil y certifica que Flavio Bolsonaro no ganó en octubre de 2026',
                'fragmento' => 'Brasilia (EFE).- El Tribunal Superior Eleitoral (TSE) oficializó los resultados definitivos de las elecciones presidenciales de Brasil celebradas en octubre de 2026, confirmando la victoria de Luiz Inácio Lula da Silva y desmintiendo categóricamente cualquier supuesto triunfo de la oposición. El TSE certificó que ni Jair Bolsonaro (inhabilitado políticamente) ni su hijo Flavio Bolsonaro ganaron la presidencia de Brasil en las elecciones de 2026.',
                'url'       => 'https://efe.com/politica/elecciones-brasil-2026-tse-resultados-oficiales',
            ],
            [
                'fuente'    => 'ColombiaCheck',
                'categoria' => 'Gobierno y Política',
                'titulo'    => 'Es falso que el presidente haya firmado decreto de expropiación',
                'fragmento' => 'ColombiaCheck desmiente rumor falso en redes: la presidencia no ha emitido ningún decreto de expropiación de tierras ni medidas similares.',
                'url'       => 'https://colombiacheck.com/chequeos/desmentido-decreto-expropiacion',
            ],
            [
                'fuente'    => 'El Espectador',
                'categoria' => 'Gobierno y Política',
                'titulo'    => 'Congreso avanza en debates de reforma constitucional',
                'fragmento' => 'El Senado y la Cámara de Representantes debaten proyectos de ley con participación de bancadas de gobierno y oposición.',
                'url'       => 'https://www.elespectador.com/politica/congreso-reformas-debates',
            ],

            // Deportes
            [
                'fuente'    => 'El Tiempo',
                'categoria' => 'Deportes',
                'titulo'    => 'Selección Colombia clasifica a la siguiente fase del torneo continental',
                'fragmento' => 'Con destacada actuación deportiva y goles en el segundo tiempo, la Selección Colombia asegura su paso en el campeonato internacional de fútbol.',
                'url'       => 'https://www.eltiempo.com/deportes/futbol/seleccion-colombia-clasificacion',
            ],

            // Entretenimiento y Farándula
            [
                'fuente'    => 'El Colombiano',
                'categoria' => 'Entretenimiento y Farándula',
                'titulo'    => 'Festival de Cine y Música premia a directores colombianos',
                'fragmento' => 'Artistas y cantantes nacionales celebran reconocimientos en certámenes internacionales de cine y televisión.',
                'url'       => 'https://www.elcolombiano.com/cultura/festival-cine-musica-premios',
            ],

            // Estilo de Vida y Sociedad
            [
                'fuente'    => 'OMS (Organización Mundial de la Salud)',
                'categoria' => 'Estilo de Vida y Sociedad',
                'titulo'    => 'OMS emite recomendaciones oficiales de vacunación y salud comunitaria',
                'fragmento' => 'La OMS comunica directrices para prevención de enfermedades respiratorias y fortalecimiento de sistemas de salud pública.',
                'url'       => 'https://www.who.int/es/news/salud-prevencion-recomendaciones',
            ],

            // Educación y Empleo
            [
                'fuente'    => 'Semana',
                'categoria' => 'Educación y Empleo',
                'titulo'    => 'SENA y Ministerio de Trabajo abren miles de vacantes laborales y becas universitarias',
                'fragmento' => 'La convocatoria oficial incluye oportunidades de empleo formal y becas de educación superior para jóvenes y profesionales.',
                'url'       => 'https://www.semana.com/educacion/convocatoria-empleo-sena-vacantes',
            ],
        ];

        foreach ($noticias as $item) {
            // Buscar la fuente por coincidencia aproximada o tomar la primera
            $fuenteObj = $fuentes->get($item['fuente'])
                ?? $fuentes->filter(fn ($f) => str_contains($f->nombre, explode(' ', $item['fuente'])[0]))->first()
                ?? $fuentes->first();

            Documento::firstOrCreate(
                ['url_articulo' => $item['url']],
                [
                    'id_documento'      => (string) Str::uuid(),
                    'fuente_id'         => $fuenteObj->id_fuente,
                    'categoria'         => $item['categoria'],
                    'titulo'            => $item['titulo'],
                    'fragmento'         => $item['fragmento'],
                    'fecha_publicacion' => now()->subDays(random_int(1, 10)),
                ]
            );
        }
    }
}
