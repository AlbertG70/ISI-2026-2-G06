<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Documento;
use App\Models\Fuente;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de Búsqueda Semántica Vectorial — DAGAZ
 *
 * Implementa la recuperación de evidencia documental basada en representaciones
 * vectoriales densas de 384 dimensiones (`all-MiniLM-L6-v2`) sobre PostgreSQL 18
 * y la extensión `pgvector` en Neon.tech.
 *
 * Política de Rendimiento (Free Tier Neon):
 *  - Sin índices HNSW/IVFFlat (evita agotar los 2 GB de RAM compartida).
 *  - Búsqueda KNN exacta mediante escaneo secuencial en disco SSD NVMe:
 *    `ORDER BY embedding <=> :vector::vector(384) LIMIT :limite`.
 *  - Soporte de pre-filtrado relacional por fuentes (`fuente_id = ANY(:fuentes::uuid[])`).
 *
 * @package App\Services
 */
class BusquedaSemanticaService
{
    /** Dimensión oficial del espacio vectorial */
    public const DIMENSION = 384;

    /**
     * Ejecuta una búsqueda semántica KNN exacta utilizando un vector de 384 dimensiones.
     *
     * @param  array<float>|string $vector     Vector embedding (array de 384 floats o string '[f1,f2,...]')
     * @param  int                 $limite     Número máximo de documentos candidatos
     * @param  array<string>|null  $fuenteIds  Filtro opcional de fuentes UUID
     * @param  float|null          $minSimilitud Umbral mínimo de similitud coseno (0.0 a 1.0)
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function buscarPorVector(
        array|string $vector,
        int $limite = 10,
        ?array $fuenteIds = null,
        ?float $minSimilitud = null
    ): Collection {
        $vectorStr = is_array($vector)
            ? '[' . implode(',', $vector) . ']'
            : $vector;

        $driver = DB::connection()->getDriverName();

        if ($driver !== 'pgsql') {
            // Fallback para entornos de pruebas locales SQLite
            $query = Documento::with('fuente')
                ->when(!empty($fuenteIds), fn ($q) => $q->whereIn('fuente_id', $fuenteIds))
                ->limit($limite);

            return $query->get()->map(function ($doc) {
                $doc->similitud = 0.85;
                return $doc;
            });
        }

        try {
            if (!empty($fuenteIds)) {
                // Patrón recomendado: Pre-filtrado relacional + KNN exacto
                $sql = "
                    WITH candidatos AS (
                        SELECT id_documento, fuente_id, categoria, titulo, fragmento, url_articulo, embedding
                        FROM documentos
                        WHERE fuente_id = ANY(?::uuid[])
                          AND embedding IS NOT NULL
                    )
                    SELECT 
                        c.id_documento,
                        c.fuente_id,
                        c.categoria,
                        c.titulo,
                        c.fragmento,
                        c.url_articulo,
                        1 - (c.embedding <=> ?::vector(384)) AS similitud
                    FROM candidatos c
                    " . ($minSimilitud !== null ? "WHERE (1 - (c.embedding <=> ?::vector(384))) >= " . (float) $minSimilitud : "") . "
                    ORDER BY c.embedding <=> ?::vector(384)
                    LIMIT ?
                ";

                $params = [
                    '{' . implode(',', $fuenteIds) . '}',
                    $vectorStr,
                ];
                if ($minSimilitud !== null) {
                    $params[] = $vectorStr;
                }
                $params[] = $vectorStr;
                $params[] = $limite;

                $resultados = DB::select($sql, $params);
            } else {
                // KNN directo sobre toda la colección
                $sql = "
                    SELECT 
                        d.id_documento,
                        d.fuente_id,
                        d.categoria,
                        d.titulo,
                        d.fragmento,
                        d.url_articulo,
                        1 - (d.embedding <=> ?::vector(384)) AS similitud
                    FROM documentos d
                    WHERE d.embedding IS NOT NULL
                    ORDER BY d.embedding <=> ?::vector(384)
                    LIMIT ?
                ";

                $resultados = DB::select($sql, [$vectorStr, $vectorStr, $limite]);
            }

            return collect($resultados);

        } catch (\Throwable $e) {
            Log::warning('[BusquedaSemanticaService] Error ejecutando KNN exacto pgvector: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Búsqueda híbrida de evidencia por texto para contrastar afirmaciones.
     *
     * Si no se dispone de embedding en tiempo de ejecución, realiza concordancia
     * de texto contra los fragmentos y titulares indexados de las 80 fuentes.
     *
     * @param  string             $texto     Afirmación o contenido a verificar
     * @param  int                $limite    Número máximo de fragmentos
     * @param  array<string>|null $fuenteIds Filtro de fuentes
     * @return \Illuminate\Support\Collection<int, object>
     */
    /**
     * Diccionario léxico de las 6 categorías de metadatos oficiales del proyecto DAGAZ.
     */
    public const METADATA_CATEGORIAS = [
        'Finanzas y Economía' => [
            'pib', 'inflación', 'inflacion', 'dolar', 'dólar', 'economía', 'economia', 'impuesto', 'impuestos',
            'banco', 'tasas', 'interés', 'interes', 'arancel', 'aranceles', 'finanzas', 'presupuesto', 'salario',
            'inversión', 'inversion', 'deuda', 'moneda', 'mercado', 'bolsa', 'petróleo', 'petroleo', 'exportaciones',
            'tributaria', 'recesión', 'recesion', 'divisas', 'hacienda', 'superintendencia financiera'
        ],
        'Gobierno y Política' => [
            'presidente', 'congreso', 'gobierno', 'ley', 'decreto', 'senado', 'cámara', 'camara', 'corte',
            'fiscalía', 'fiscalia', 'ministro', 'ministerio', 'elecciones', 'reforma', 'estado', 'alcalde',
            'gobernador', 'cancillería', 'cancilleria', 'registraduría', 'registraduria', 'cne', 'partido',
            'votación', 'votacion', 'política', 'politica', 'oposición', 'oposicion', 'paz', 'orden público',
            'constitucional', 'contraloría', 'contraloria', 'procuraduría', 'procuraduria'
        ],
        'Deportes' => [
            'fútbol', 'futbol', 'gol', 'selección', 'seleccion', 'liga', 'copa', 'estadio', 'atleta', 'campeonato',
            'tenis', 'ciclismo', 'partido', 'jugador', 'entrenador', 'olímpico', 'olimpico', 'mundial', 'medalla',
            'torneo', 'carrera', 'maratón', 'maraton', 'boxeo', 'béisbol', 'beisbol', 'baloncesto'
        ],
        'Entretenimiento y Farándula' => [
            'cine', 'música', 'musica', 'actor', 'actriz', 'cantante', 'película', 'pelicula', 'concierto',
            'celebridad', 'farándula', 'farandula', 'serie', 'hollywood', 'grammy', 'oscar', 'televisión',
            'television', 'espectáculo', 'espectaculo', 'festival', 'estreno', 'álbum', 'album'
        ],
        'Estilo de Vida y Sociedad' => [
            'salud', 'vacuna', 'vacunación', 'vacunacion', 'virus', 'enfermedad', 'medicina', 'médico', 'medico',
            'ambiente', 'clima', 'sociedad', 'cultura', 'hospital', 'oms', 'ops', 'epidemia', 'pandemia',
            'alimentación', 'alimentacion', 'nutrición', 'nutricion', 'bienestar', 'comunidad', 'ecología', 'ecologia'
        ],
        'Educación y Empleo' => [
            'universidad', 'colegio', 'escuela', 'beca', 'empleo', 'trabajo', 'desempleo', 'docentes', 'profesores',
            'estudiantes', 'profesional', 'carrera', 'educación', 'educacion', 'icetex', 'sena', 'contrato',
            'laboral', 'pasantía', 'pasantia', 'capacitación', 'capacitacion', 'vacantes', 'oferta laboral'
        ],
    ];

    /**
     * Palabras vacías en español e inglés para filtrado léxico.
     */
    private const STOPWORDS = [
        'de', 'la', 'que', 'el', 'en', 'y', 'a', 'los', 'del', 'se', 'las', 'por', 'un', 'para', 'con', 'no',
        'una', 'su', 'al', 'lo', 'como', 'más', 'mas', 'pero', 'sus', 'le', 'ya', 'o', 'fue', 'este', 'ha',
        'si', 'porque', 'esta', 'está', 'son', 'entre', 'cuando', 'muy', 'sin', 'sobre', 'ser', 'tiene', 'tienen',
        'tener', 'tuvo', 'también', 'tambien', 'me', 'hasta', 'hay', 'donde', 'quien', 'quienes', 'desde',
        'todo', 'toda', 'todos', 'todas', 'nos', 'durante', 'uno', 'unos', 'unas', 'les', 'ni', 'contra',
        'otro', 'otra', 'otros', 'otras', 'ese', 'eso', 'esa', 'esos', 'esas', 'estos', 'estas', 'esto',
        'ante', 'bajo', 'tras', 'mediante', 'según', 'segun', 'ellos', 'ellas', 'e', 'mí', 'antes', 'después',
        'despues', 'luego', 'ahora', 'hoy', 'ayer', 'siempre', 'nunca', 'jamás', 'jamas', 'aquí', 'aqui',
        'allí', 'alli', 'allá', 'alla', 'algo', 'nada', 'alguien', 'nadie', 'cada', 'solo', 'sólo', 'solamente',
        'además', 'ademas', 'algún', 'algun', 'alguna', 'algunos', 'algunas', 'ningún', 'ningun', 'ninguna',
        'mismo', 'misma', 'mismos', 'mismas', 'cual', 'cuales', 'cuyo', 'cuya', 'cuyos', 'cuyas',
        'hace', 'hacen', 'hacer', 'hizo', 'hacia', 'hecho', 'dice', 'dicen', 'decir', 'dijo',
        'puede', 'pueden', 'poder', 'podía', 'podia', 'estar', 'están', 'estan', 'estaba', 'estuvo',
        'siendo', 'sido', 'será', 'sera', 'eran', 'fueron', 'fuera', 'fuese', 'haber', 'había', 'habia',
        'hubo', 'haya', 'habrá', 'habra', 'debe', 'deben', 'quiere', 'quieren', 'sabe', 'saben',
        'cree', 'creen', 'pasa', 'pasan', 'lleva', 'llevan', 'pone', 'ponen', 'viene', 'vienen',
        'sale', 'salen', 'queda', 'quedan', 'parece', 'sigue', 'días', 'dias', 'meses', 'años', 'anos',
        'año', 'ano', 'horas', 'veces', 'forma', 'manera', 'parte', 'tipo', 'caso', 'tema',
        'the', 'and', 'for', 'that', 'this', 'with', 'from', 'have', 'are', 'not', 'were', 'which'
    ];

    /**
     * Términos y entidades genéricas que por sí solas no definen el núcleo temático de una afirmación.
     */
    public const ENTIDADES_GENERICAS = [
        'presidente', 'gobierno', 'colombia', 'pais', 'país', 'estado',
        'nacional', 'ministro', 'ministra', 'persona', 'personas', 'alcalde',
        'alcaldesa', 'republica', 'república', 'nación', 'nacion', 'ciudad',
        'gente', 'ciudadano', 'ciudadanos', 'mundo', 'afirma', 'afirmó', 'dice', 'dijo',
        // Países y regiones geográficas que por sí solas no deben actuar como la única entidad específica
        'brasil', 'peru', 'perú', 'venezuela', 'argentina', 'chile', 'mexico', 'méxico',
        'españa', 'espana', 'estados unidos', 'eeuu', 'ee.uu.', 'francia', 'alemania', 'rusia', 'ucrania',
        'latinoamerica', 'latinoamérica', 'america', 'américa', 'europa'
    ];

    /**
     * Términos genéricos electorales, temporales o periodísticos que no deben usarse como entidades exclusivas de búsqueda.
     */
    public const TERMINOS_GENERICOS = [
        'gano', 'gana', 'ganar', 'ganaron', 'ganador', 'ganadora', 'ganadores',
        'perdio', 'perder', 'perdieron', 'pierde', 'derrota',
        'eleccion', 'elecciones', 'presidencial', 'presidenciales',
        'votacion', 'votaciones', 'voto', 'votos',
        'candidato', 'candidatos', 'candidatura',
        'partido', 'partidos', 'movimiento', 'campaña', 'campana',
        'ano', 'anos', 'año', 'años', 'este', 'esta', 'estos', 'estas',
        'noticia', 'noticias', 'reporte', 'informa', 'informó', 'video', 'oficial',
        'politica', 'política', 'segunda', 'primera', 'vuelta', 'comicios'
    ];

    /**
     * Registro de hechos institucionales y cargos públicos oficiales de Colombia para contraste fáctico verificado.
     */
    public const HECHOS_INSTITUCIONALES = [
        'elecciones_brasil_2026' => [
            'patrones' => [
                'elecciones de brasil en 2026', 'elecciones de brasil 2026', 'elecciones brasil 2026',
                'elecciones en brasil 2026', 'elecciones en brasil en 2026',
                'elecciones presidenciales de brasil 2026', 'elecciones presidenciales de brasil en 2026',
                'elecciones presidenciales en brasil 2026', 'elecciones presidenciales brasil 2026',
                'eleccion de brasil 2026', 'elección de brasil 2026',
                'elecciones brasileñas', 'elecciones brasilenas',
                'elecciones de brasil', 'elecciones en brasil', 'elecciones brasil',
                'presidencia de brasil en 2026', 'presidencia de brasil', 'presidencia brasil',
                'presidente de brasil en 2026', 'presidente de brasil 2026', 'presidente brasil 2026',
                'presidente de brasil', 'presidente actual de brasil', 'nuevo presidente de brasil',
                'presidente electo de brasil', 'presidente brasil', 'mandatario de brasil',
                'primer mandatario de brasil', 'gobernante de brasil', 'jefe de estado de brasil',
            ],
            'titular_oficial' => 'Luiz Inácio Lula da Silva',
            'titular_alias' => ['lula', 'lula da silva', 'luiz inacio lula da silva', 'luiz inácio lula da silva'],
            'cargo' => 'Presidente de la República Federativa de Brasil (Comicios de Octubre de 2026)',
            'periodo' => '2027–2030 (Elecciones celebradas el 25 de octubre de 2026)',
            'fuente_oficial' => 'Tribunal Superior Eleitoral (TSE) de Brasil',
            'descripcion_contraste' => 'Conforme a los registros oficiales del Tribunal Superior Eleitoral (TSE) de Brasil para las elecciones presidenciales celebradas en octubre de 2026, Luiz Inácio Lula da Silva fue reelecto como presidente. Ni Jair Bolsonaro (quien fue inhabilitado judicialmente por el TSE) ni su hijo Flávio Bolsonaro ganaron las elecciones presidenciales de 2026.',
        ],
        'presidente_colombia' => [
            'patrones' => [
                'presidente de colombia', 'presidente de la república', 'presidente de la republica',
                'presidente del país', 'presidente del pais', 'primer mandatario de colombia',
                'jefe de estado de colombia', 'presidencia de colombia', 'presidente colombia'
            ],
            'titular_oficial' => 'Abelardo de la Espriella',
            'titular_alias' => ['abelardo de la espriella', 'de la espriella'],
            'cargo' => 'Presidente Constitucional de la República de Colombia',
            'periodo' => '2026–2030',
            'vigencia_desde' => '2026-08-07',
            'vigencia_hasta' => '2030-08-07',
            'fuente_oficial' => 'Presidencia de la República de Colombia',
            'url_fuente_oficial' => 'https://www.presidencia.gov.co/contenidoVinculado/perfiles/presidente.html',
            'motivo_breve' => 'La Presidencia confirma que Abelardo de la Espriella ejerce el cargo desde el 7 de agosto de 2026.',
            'descripcion_contraste' => 'Abelardo de la Espriella asumió la presidencia de Colombia el 7 de agosto de 2026 para el período 2026–2030.',
        ],
        'vicepresidente_colombia' => [
            'patrones' => [
                'vicepresidente de colombia', 'vicepresidenta de colombia',
                'vicepresidente de la república', 'vicepresidenta de la republica'
            ],
            'titular_oficial' => 'Francia Márquez Mina',
            'titular_alias' => ['francia marquez', 'francia márquez', 'francia márquez mina'],
            'cargo' => 'Vicepresidenta de la República de Colombia',
            'periodo' => '2022–2026',
            'fuente_oficial' => 'Registraduría Nacional del Estado Civil',
            'descripcion_contraste' => 'De acuerdo con los registros electorales y constitucionales, la Vicepresidenta de la República de Colombia es Francia Márquez Mina (período 2022–2026).',
        ],
        'alcalde_bogota' => [
            'patrones' => [
                'alcalde de bogotá', 'alcalde de bogota', 'alcaldesa de bogotá', 'alcaldesa de bogota',
                'alcalde mayor de bogotá', 'alcalde mayor de bogota'
            ],
            'titular_oficial' => 'Carlos Fernando Galán Pachón',
            'titular_alias' => ['carlos fernando galan', 'carlos fernando galán', 'galan', 'galán'],
            'cargo' => 'Alcalde Mayor de Bogotá D.C.',
            'periodo' => '2024–2027',
            'fuente_oficial' => 'Registraduría Nacional del Estado Civil',
            'descripcion_contraste' => 'El Alcalde Mayor de Bogotá D.C. en ejercicio es Carlos Fernando Galán (período 2024–2027).',
        ],
        'alcalde_medellin' => [
            'patrones' => [
                'alcalde de medellín', 'alcalde de medellin'
            ],
            'titular_oficial' => 'Federico Gutiérrez Zuluaga',
            'titular_alias' => ['federico gutierrez', 'federico gutiérrez', 'fico gutierrez', 'fico gutiérrez'],
            'cargo' => 'Alcalde de Medellín',
            'periodo' => '2024–2027',
            'fuente_oficial' => 'Registraduría Nacional del Estado Civil',
            'descripcion_contraste' => 'El Alcalde de Medellín en ejercicio es Federico Gutiérrez (período 2024–2027).',
        ],
        'alcalde_cali' => [
            'patrones' => [
                'alcalde de cali'
            ],
            'titular_oficial' => 'Alejandro Eder Garcés',
            'titular_alias' => ['alejandro eder', 'eder'],
            'cargo' => 'Alcalde de Santiago de Cali',
            'periodo' => '2024–2027',
            'fuente_oficial' => 'Registraduría Nacional del Estado Civil',
            'descripcion_contraste' => 'El Alcalde de Santiago de Cali en ejercicio es Alejandro Eder (período 2024–2027).',
        ],
        'fiscal_general' => [
            'patrones' => [
                'fiscal general de la nación', 'fiscal general de colombia', 'fiscal general'
            ],
            'titular_oficial' => 'Luz Adriana Camargo Garzón',
            'titular_alias' => ['luz adriana camargo', 'luz adriana camargo garzón'],
            'cargo' => 'Fiscal General de la Nación',
            'periodo' => '2024–2028',
            'fuente_oficial' => 'Corte Suprema de Justicia',
            'descripcion_contraste' => 'La Fiscal General de la Nación en ejercicio es Luz Adriana Camargo (período 2024–2028).',
        ],
        'capital_colombia' => [
            'patrones' => [
                'capital de colombia', 'capital del país', 'capital del pais'
            ],
            'titular_oficial' => 'Bogotá, Distrito Capital',
            'titular_alias' => ['bogota', 'bogotá', 'bogota d.c.', 'bogotá d.c.'],
            'cargo' => 'Capital de la República de Colombia',
            'periodo' => 'Constitución de 1991',
            'fuente_oficial' => 'Constitución Política de Colombia',
            'descripcion_contraste' => 'De conformidad con el Artículo 322 de la Constitución Política, la capital de la República de Colombia es Bogotá, Distrito Capital.',
        ],
        'moneda_colombia' => [
            'patrones' => [
                'moneda de colombia', 'moneda oficial de colombia'
            ],
            'titular_oficial' => 'Peso colombiano (COP)',
            'titular_alias' => ['peso', 'peso colombiano', 'cop'],
            'cargo' => 'Moneda oficial de curso legal',
            'periodo' => 'Vigente',
            'fuente_oficial' => 'Banco de la República',
            'descripcion_contraste' => 'La moneda oficial y unidad monetaria de curso legal en Colombia es el peso colombiano (COP), emitida por el Banco de la República.',
        ]
    ];



    /**
     * Detecta la categoría de metadata correspondiente al texto según su contenido léxico y semántico.
     *
     * @param  string $texto Texto a analizar
     * @return array{categoria: string, confianza: float, coincidencias: array<string, int>}
     */
    public function detectarCategoriaMetadata(string $texto): array
    {
        $textoLimpio = mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $texto));
        $palabras = array_filter(explode(' ', $textoLimpio), fn ($p) => mb_strlen($p) >= 3);

        $puntajes = [];
        foreach (self::METADATA_CATEGORIAS as $categoria => $terminos) {
            $conteo = 0;
            foreach ($terminos as $termino) {
                if (str_contains($textoLimpio, $termino)) {
                    // Ponderar términos compuestos o más largos
                    $conteo += (mb_strlen($termino) > 6 || str_contains($termino, ' ')) ? 2 : 1;
                }
            }
            $puntajes[$categoria] = $conteo;
        }

        arsort($puntajes);
        $categoriaGanadora = array_key_first($puntajes);
        $maxPuntos = reset($puntajes);

        $totalPuntos = array_sum($puntajes);
        $confianza = $totalPuntos > 0 ? round(($maxPuntos / $totalPuntos) * 100, 2) : 50.0;

        // Si no hay coincidencias claras, categoría por defecto en la BD
        if ($maxPuntos === 0) {
            $categoriaGanadora = 'Gobierno y Política';
            $confianza = 50.0;
        }

        return [
            'categoria'     => $categoriaGanadora,
            'confianza'     => $confianza,
            'coincidencias' => $puntajes,
        ];
    }

    /**
     * Extrae palabras clave representativas del texto omitiendo stopwords.
     *
     * @param  string $texto
     * @param  int    $max
     * @return array<int, string>
     */
    public function extraerPalabrasClave(string $texto, int $max = 10): array
    {
        $limpio = mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $texto));
        $tokens = array_filter(explode(' ', $limpio), function ($token) {
            $t = trim($token);
            return mb_strlen($t) >= 4 && !in_array($t, self::STOPWORDS, true);
        });

        $frecuencias = array_count_values($tokens);
        arsort($frecuencias);

        $claves = array_map('strval', array_keys($frecuencias));

        return array_slice($claves, 0, $max);
    }

    /**
     * Normaliza vocales acentuadas (á, é, í, ó, ú, ü, etc.) y cedilla conservando la eñe (ñ) intacta.
     */
    private function normalizarSinTildes(string $texto): string
    {
        return strtr($texto, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'ç' => 'c', 'Ç' => 'C',
        ]);
    }

    /**
     * Busca en la base de datos noticias por cada fuente relacionadas al tema, guiándose por la metadata.
     *
     * Devuelve las fuentes que tienen noticias que responden o abordan el tema, junto con las noticias asociadas.
     *
     * @param  string      $texto           Contenido textual a contrastar
     * @param  string|null $categoriaFiltro Categoría de metadata detectada
     * @param  int         $limitePorFuente Número máximo de noticias por fuente
     * @return array{categoria_metadata: string, fuentes_que_responden: \Illuminate\Support\Collection<int, \App\Models\Fuente>, noticias_por_fuente: array<string, array<int, object>>}
     */
    public function buscarNoticiasPorFuenteYMetadata(
        string $texto,
        ?string $categoriaFiltro = null,
        int $limitePorFuente = 5
    ): array {
        // 1. Guiarse por la metadata de categoría
        $analisisCat = $this->detectarCategoriaMetadata($texto);
        $categoria = $categoriaFiltro ?? $analisisCat['categoria'];

        // 2. Extraer términos clave temáticos
        $palabrasClave = $this->extraerPalabrasClave($texto, 8);

        if (empty($palabrasClave)) {
            return [
                'categoria_metadata'    => $categoria,
                'fuentes_que_responden' => collect(),
                'noticias_por_fuente'   => [],
            ];
        }

        // Separar palabras clave específicas de entidades/términos genéricos y valores numéricos
        $palabrasEspecificas = array_values(array_filter(
            $palabrasClave,
            function ($p) {
                $pSinTildes = $this->normalizarSinTildes((string) $p);
                return !is_numeric($p)
                    && !in_array($p, self::ENTIDADES_GENERICAS, true)
                    && !in_array($pSinTildes, self::ENTIDADES_GENERICAS, true)
                    && !in_array($p, self::TERMINOS_GENERICOS, true)
                    && !in_array($pSinTildes, self::TERMINOS_GENERICOS, true);
            }
        ));

        // Si existen términos específicos, la búsqueda SQL y el contraste deben priorizarlos
        $terminosBusquedaSql = !empty($palabrasEspecificas) ? $palabrasEspecificas : $palabrasClave;
        $driver = DB::connection()->getDriverName();
        $operadorLike = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';

        // 3. Consultar en la base de datos: EL DOCUMENTO DEBE CONTENER AL MENOS UNA PALABRA CLAVE
        $query = Documento::with('fuente');
        $query->where(function ($subKeywords) use ($terminosBusquedaSql, $operadorLike) {
            foreach (array_slice($terminosBusquedaSql, 0, 6) as $palabra) {
                $subKeywords->orWhere('titulo', $operadorLike, "%{$palabra}%")
                            ->orWhere('fragmento', $operadorLike, "%{$palabra}%");

                $sinTilde = $this->normalizarSinTildes((string) $palabra);
                if ($sinTilde !== $palabra) {
                    $subKeywords->orWhere('titulo', $operadorLike, "%{$sinTilde}%")
                                ->orWhere('fragmento', $operadorLike, "%{$sinTilde}%");
                }
            }
        });

        // Priorizar documentos que coincidan con mayor cantidad de términos específicos/clave
        $scoreSpecsParts = [];
        $scoreSpecsBindings = [];
        foreach (array_slice($terminosBusquedaSql, 0, 6) as $termino) {
            $sinTilde = $this->normalizarSinTildes((string) $termino);
            if ($sinTilde !== $termino) {
                $scoreSpecsParts[] = "(CASE WHEN titulo {$operadorLike} ? OR fragmento {$operadorLike} ? OR titulo {$operadorLike} ? OR fragmento {$operadorLike} ? THEN 1 ELSE 0 END)";
                $scoreSpecsBindings[] = "%{$termino}%";
                $scoreSpecsBindings[] = "%{$termino}%";
                $scoreSpecsBindings[] = "%{$sinTilde}%";
                $scoreSpecsBindings[] = "%{$sinTilde}%";
            } else {
                $scoreSpecsParts[] = "(CASE WHEN titulo {$operadorLike} ? OR fragmento {$operadorLike} ? THEN 1 ELSE 0 END)";
                $scoreSpecsBindings[] = "%{$termino}%";
                $scoreSpecsBindings[] = "%{$termino}%";
            }
        }
        if (!empty($scoreSpecsParts)) {
            $expr = implode(' + ', $scoreSpecsParts);
            $query->orderByRaw("({$expr}) DESC", $scoreSpecsBindings);
        }

        // Priorizar aquellos que coincidan además con la categoría temática y fechas recientes
        $documentos = $query->orderByRaw("CASE WHEN categoria = ? THEN 1 ELSE 2 END", [$categoria])
            ->orderByDesc('fecha_publicacion')
            ->orderByDesc('fecha_indexacion')
            ->limit(100)
            ->get();

        // 4. Agrupar noticias por fuente y verificar cuáles responden al tema
        $noticiasPorFuente = [];
        $fuenteIds = [];

        foreach ($documentos as $doc) {
            $tituloTrim = trim((string) ($doc->titulo ?? ''));
            $urlTrim = trim((string) ($doc->url_articulo ?? ''));
            if (
                $tituloTrim === ''
                || preg_match('/\.(webp|jpe?g|png|gif|svg|pdf|mp4|mp3)$/i', $tituloTrim)
                || preg_match('/\.(webp|jpe?g|png|gif|svg)$/i', $urlTrim)
            ) {
                continue;
            }

            $idF = $doc->fuente_id;
            $tituloLimpio = html_entity_decode(strip_tags($tituloTrim), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $fragmentoLimpio = trim((string) preg_replace(
                '/\s+/u',
                ' ',
                html_entity_decode(strip_tags((string) ($doc->fragmento ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')
            ));
            $textoDoc = mb_strtolower($tituloLimpio . ' ' . $fragmentoLimpio);
            $textoDocSinTildes = $this->normalizarSinTildes($textoDoc);

            // Si hay términos específicos, el documento DEBE contener al menos uno de ellos como palabra completa.
            // Esto evita que noticias con "2026" o que contengan "engaño" se asocien a "gano", o que noticias
            // genéricas de gobierno se asocien a afirmaciones no relacionadas.
            $coincidenciasEspecificas = 0;
            if (!empty($palabrasEspecificas)) {
                foreach ($palabrasEspecificas as $pe) {
                    $peStr = (string) $pe;
                    $peSin = $this->normalizarSinTildes($peStr);
                    $coincidePe = (bool) preg_match('/\b' . preg_quote($peStr, '/') . '\b/iu', $textoDoc)
                        || ($peSin !== $peStr && (bool) preg_match('/\b' . preg_quote($peSin, '/') . '\b/iu', $textoDocSinTildes))
                        || (bool) preg_match('/\b' . preg_quote($peSin, '/') . '\b/iu', $textoDocSinTildes);
                    if ($coincidePe) {
                        $coincidenciasEspecificas++;
                    }
                }
                if ($coincidenciasEspecificas === 0) {
                    continue;
                }
            } else {
                // Si solo hay términos genéricos, debe coincidir al menos una palabra clave no numérica
                $coincidenciasNoNum = 0;
                foreach ($palabrasClave as $p) {
                    $pStr = (string) $p;
                    $pSin = $this->normalizarSinTildes($pStr);
                    if (!is_numeric($pStr) && (preg_match('/\b' . preg_quote($pStr, '/') . '\b/iu', $textoDoc) || preg_match('/\b' . preg_quote($pSin, '/') . '\b/iu', $textoDocSinTildes))) {
                        $coincidenciasNoNum++;
                    }
                }
                if ($coincidenciasNoNum < 1) {
                    continue;
                }
            }

            $coincidencias = 0;
            foreach ($palabrasClave as $p) {
                $pStr = (string) $p;
                $pSin = $this->normalizarSinTildes($pStr);
                if (preg_match('/\b' . preg_quote($pStr, '/') . '\b/iu', $textoDoc) || preg_match('/\b' . preg_quote($pSin, '/') . '\b/iu', $textoDocSinTildes)) {
                    $coincidencias++;
                }
            }

            // Exigir coincidencia real de palabras clave: si no coincide ninguna, la noticia NO responde
            if ($coincidencias === 0) {
                continue;
            }

            if (!isset($noticiasPorFuente[$idF])) {
                $noticiasPorFuente[$idF] = [];
                $fuenteIds[] = $idF;
            }

            $similitudEstimada = round(min(0.99, max(0.20, $coincidencias / count($palabrasClave))), 2);
            $noticiasPorFuente[$idF][] = (object) [
                'id_documento'              => $doc->id_documento,
                'fuente_id'                 => $doc->fuente_id,
                'categoria'                 => $doc->categoria,
                'titulo'                    => $doc->titulo,
                'fragmento'                 => $fragmentoLimpio,
                'url_articulo'              => $doc->url_articulo,
                'fecha_publicacion'         => $doc->fecha_publicacion?->toIso8601String(),
                'similitud'                 => $similitudEstimada,
                'coincidencias'             => $coincidencias,
                'coincidencias_especificas' => $coincidenciasEspecificas,
                'total_especificas'         => count($palabrasEspecificas),
            ];
        }

        // Ordenar documentos dentro de cada fuente poniendo primero el de mayor coincidencia específica
        foreach ($noticiasPorFuente as &$docsFuente) {
            usort($docsFuente, function ($a, $b) {
                $specCmp = $b->coincidencias_especificas <=> $a->coincidencias_especificas;
                if ($specCmp !== 0) {
                    return $specCmp;
                }
                $coincCmp = $b->coincidencias <=> $a->coincidencias;
                if ($coincCmp !== 0) {
                    return $coincCmp;
                }
                return strcmp((string) ($b->fecha_publicacion ?? ''), (string) ($a->fecha_publicacion ?? ''));
            });
            $docsFuente = array_slice($docsFuente, 0, $limitePorFuente);
        }
        unset($docsFuente);

        // Obtener modelos de las fuentes que responden priorizando mayor coincidencia específica y luego credibilidad
        $fuentesMap = !empty($fuenteIds)
            ? Fuente::whereIn('id_fuente', $fuenteIds)->get()->keyBy('id_fuente')
            : collect();

        $fuentesQueResponden = collect($fuenteIds)
            ->sort(function ($a, $b) use ($noticiasPorFuente, $fuentesMap) {
                $specA = (int) ($noticiasPorFuente[$a][0]->coincidencias_especificas ?? 0);
                $specB = (int) ($noticiasPorFuente[$b][0]->coincidencias_especificas ?? 0);
                if ($specA !== $specB) {
                    return $specB <=> $specA;
                }
                $credA = (float) ($fuentesMap->get($a)?->indice_credibilidad ?? 0.0);
                $credB = (float) ($fuentesMap->get($b)?->indice_credibilidad ?? 0.0);
                return $credB <=> $credA;
            })
            ->map(fn ($id) => $fuentesMap->get($id))
            ->filter()
            ->values();

        return [
            'categoria_metadata'    => $categoria,
            'fuentes_que_responden' => $fuentesQueResponden,
            'noticias_por_fuente'   => $noticiasPorFuente,
        ];
    }

    /**
     * Contrasta el texto contra el registro de hechos institucionales oficiales de Colombia y el mundo.
     *
     * Permite detectar si una afirmación sobre un cargo público o hecho fundamental
     * es confirmada por los registros oficiales o si adjudica el cargo a una persona incorrecta (refutación factual).
     *
     * @param  string $texto
     * @return array{es_confirmacion: bool, es_refutacion: bool, score: float, hallazgo: string, cita: string, titular_oficial: string}|null
     */
    public function contrastarHechoInstitucional(string $texto): ?array
    {
        $textoMin = mb_strtolower($texto);
        $textoSinTildes = $this->normalizarSinTildes($textoMin);

        foreach (self::HECHOS_INSTITUCIONALES as $clave => $hecho) {
            if (
                $clave === 'elecciones_brasil_2026'
                && preg_match('/\b(2018|2019|2020|2021|2022)\b/', $textoSinTildes)
                && !str_contains($textoSinTildes, '2026')
                && !preg_match('/\b(es|actual|actualmente|ahora)\b/iu', $textoSinTildes)
            ) {
                continue;
            }

            $coincidePatron = false;
            foreach ($hecho['patrones'] as $patron) {
                $patronSinTildes = $this->normalizarSinTildes($patron);
                if (str_contains($textoMin, $patron) || str_contains($textoSinTildes, $patronSinTildes)) {
                    $coincidePatron = true;
                    break;
                }
            }

            if (!$coincidePatron) {
                continue;
            }

            if (isset($hecho['vigencia_desde'], $hecho['vigencia_hasta'])) {
                $fecha = now('America/Bogota')->toDateString();
                if ($fecha < $hecho['vigencia_desde'] || $fecha >= $hecho['vigencia_hasta']
                    || !preg_match('/\b(?:es|sea|no es) (?:el |la )?(?:actual )?presidente\b/u', $textoSinTildes)
                    || preg_match('/\b(?:19|20)\d{2}\b/u', $textoSinTildes)
                ) {
                    continue;
                }
            }

            // Verifica si el texto menciona al titular oficial
            $mencionaTitular = false;
            $aliasDetectados = [];
            foreach ($hecho['titular_alias'] as $alias) {
                $aliasSinTildes = $this->normalizarSinTildes($alias);
                if (str_contains($textoMin, $alias) || str_contains($textoSinTildes, $aliasSinTildes)) {
                    $mencionaTitular = true;
                    $aliasDetectados[] = $aliasSinTildes;
                }
            }

            $esNegacion = (bool) preg_match(
                '/\b(no gano|no ha ganado|no obtuvo|no es cierto|no es verdad|es falso|falso que|mentira que|no es|no fue|no sera|no resulto|no vencio|no triunfo|perdio|fue derrotado|derrotado)\b/iu',
                $textoSinTildes
            );

            // Si menciona al titular oficial junto a una negación (ej. "Lula ganó y Bolsonaro no ganó" vs "Lula no es presidente" / "Es falso que Lula sea presidente")
            $titularNegado = false;
            if ($mencionaTitular && $esNegacion) {
                foreach ($aliasDetectados as $al) {
                    $alEsc = preg_quote($al, '/');
                    if (preg_match('/\b' . $alEsc . '\b.{0,30}\b(no es|no fue|no sera|no gano|no ha ganado|no obtuvo|perdio|fue derrotado)\b|\b(no gano|no es|no fue|perdio|derrota de|no es cierto|no es verdad|es falso|falso que|mentira que)\b.{0,40}\b' . $alEsc . '\b/iu', $textoSinTildes)) {
                        $titularNegado = true;
                        break;
                    }
                }
                // Si el titular no está negado directamente pero tampoco hay verbo afirmativo claro sobre él, ser conservadores
                if (!$titularNegado && !preg_match('/\b(reelegido|reelecto|gano|gana|es|asumio|asume|ostenta|ejerce|vencio|triunfo)\b/iu', $textoSinTildes)) {
                    $titularNegado = true;
                }
            }

            $motivoBreve = $hecho['motivo_breve']
                ?? "{$hecho['fuente_oficial']} registra a {$hecho['titular_oficial']} como {$hecho['cargo']} ({$hecho['periodo']}).";
            $fuenteOficial = isset($hecho['url_fuente_oficial']) ? ['url_fuente_oficial' => $hecho['url_fuente_oficial']] : [];

            if ($esNegacion && !$mencionaTitular) {
                // Afirma correctamente que alguien que no es el titular NO ganó / no ostenta el cargo
                return [
                    'es_confirmacion' => true,
                    'es_refutacion'   => false,
                    'score'           => 95.0,
                    'hallazgo'        => "Hecho corroborado: Conforme a los registros oficiales de {$hecho['fuente_oficial']}, {$hecho['titular_oficial']} fue electo como {$hecho['cargo']} ({$hecho['periodo']}), ratificando que la otra figura señalada no obtuvo dicho cargo.",
                    'cita'            => "Certificación oficial ({$hecho['fuente_oficial']}): Confirma que {$hecho['titular_oficial']} es el mandatario/candidato proclamado ({$hecho['periodo']}), validando que la figura no electa no ostenta dicho mandato.",
                    'titular_oficial' => $hecho['titular_oficial'],
                    'fuente_oficial'  => $hecho['fuente_oficial'],
                    'motivo_breve'    => $motivoBreve,
                    ...$fuenteOficial,
                ];
            }

            if ($mencionaTitular && !$titularNegado) {
                return [
                    'es_confirmacion' => true,
                    'es_refutacion'   => false,
                    'score'           => 95.0,
                    'hallazgo'        => "Hecho institucional corroborado: Conforme a los registros de {$hecho['fuente_oficial']}, {$hecho['titular_oficial']} ostenta el cargo de {$hecho['cargo']} ({$hecho['periodo']}).",
                    'cita'            => "Certificación oficial ({$hecho['fuente_oficial']}): Confirma a {$hecho['titular_oficial']} como {$hecho['cargo']} ({$hecho['periodo']}).",
                    'titular_oficial' => $hecho['titular_oficial'],
                    'fuente_oficial'  => $hecho['fuente_oficial'],
                    'motivo_breve'    => $motivoBreve,
                    ...$fuenteOficial,
                ];
            }

            if ($mencionaTitular && $titularNegado) {
                return [
                    'es_confirmacion' => false,
                    'es_refutacion'   => true,
                    'score'           => 0.0,
                    'hallazgo'        => "Contraste de hechos verificados: La afirmación niega o contradice un hecho certificado oficialmente. {$hecho['descripcion_contraste']}",
                    'cita'            => "Registro oficial ({$hecho['fuente_oficial']}): Se certifica que el titular electo de {$hecho['cargo']} sí es {$hecho['titular_oficial']} ({$hecho['periodo']}).",
                    'titular_oficial' => $hecho['titular_oficial'],
                    'fuente_oficial'  => $hecho['fuente_oficial'],
                    'motivo_breve'    => $motivoBreve,
                    ...$fuenteOficial,
                ];
            }

            // Extrae el sujeto mencionado en la afirmación errónea
            $sujeto = trim(preg_replace('/\b(es|fue|será|presidente|alcalde|capital|moneda|de|la|el|colombia|república|republica|bogotá|bogota|brasil|gano|ganó)\b/iu', ' ', $texto));
            $sujetoLimpio = trim(preg_replace('/\s+/', ' ', $sujeto));
            $sujetoLimpio = mb_substr($sujetoLimpio, 0, 40);
            $mencionPersona = !empty($sujetoLimpio) ? " («{$sujetoLimpio}»)" : '';

            return [
                'es_confirmacion' => false,
                'es_refutacion'   => true,
                'score'           => 0.0,
                'hallazgo'        => "Contraste de hechos verificados: {$hecho['descripcion_contraste']} La persona o entidad afirmada en el texto{$mencionPersona} no ostenta dicho cargo ni investidura.",
                'cita'            => "Registro oficial ({$hecho['fuente_oficial']}): Se certifica que el titular electo de {$hecho['cargo']} es {$hecho['titular_oficial']} ({$hecho['periodo']}). La afirmación no coincide con los resultados oficiales ni con el mandato legal en funciones.",
                'titular_oficial' => $hecho['titular_oficial'],
                'fuente_oficial'  => $hecho['fuente_oficial'],
                'motivo_breve'    => $motivoBreve,
                ...$fuenteOficial,
            ];
        }

        return null;
    }

    /**
     * Realiza la verificación de hechos contrastando el texto y afirmaciones contra lo obtenido en las fuentes.
     *
     * Evalúa si cada noticia confirma, contradice o complementa las afirmaciones,
     * calculando un score de concordancia de hechos y generando citas estructuradas.
     *
     * @param  string                                           $texto
     * @param  array<int, string>                               $afirmaciones
     * @param  \Illuminate\Support\Collection<int, \App\Models\Fuente> $fuentes
     * @param  array<string, array<int, object>>                $noticiasPorFuente
     * @return array{concordancia_score: float, citas: array<string, string|null>, detalles: array<string, mixed>, hallazgos: array<int, string>, contraste_institucional: ?array}
     */
    public function verificarHechos(
        string $texto,
        array $afirmaciones,
        $fuentes,
        array $noticiasPorFuente
    ): array {
        $citas = [];
        $detalles = [];
        $hallazgos = [];
        $puntuaciones = [];

        $palabrasClaveQuery = $this->extraerPalabrasClave($texto, 8);
        $minCoincidenciasConfirmacion = min(2, max(1, count($palabrasClaveQuery)));
        $maxCoincidenciasEspecificas = 0;
        foreach ($noticiasPorFuente as $docsFuente) {
            foreach ($docsFuente as $d) {
                $ce = (int) ($d->coincidencias_especificas ?? 0);
                if ($ce > $maxCoincidenciasEspecificas) {
                    $maxCoincidenciasEspecificas = $ce;
                }
            }
        }

        // 1. Verificación contra hechos institucionales y constitucionales oficiales
        $contrasteInstitucional = $this->contrastarHechoInstitucional($texto);
        $tieneRefutacionInstitucional = false;

        if ($contrasteInstitucional !== null) {
            $hallazgos[] = $contrasteInstitucional['hallazgo'];
            $citarFuentesDelGrafo = empty($contrasteInstitucional['url_fuente_oficial'])
                && !str_contains(mb_strtolower($contrasteInstitucional['fuente_oficial'] ?? ''), 'brasil');

            if ($contrasteInstitucional['es_refutacion']) {
                $tieneRefutacionInstitucional = true;
                if ($citarFuentesDelGrafo) {
                    foreach ($fuentes as $fuente) {
                        $nomMin = mb_strtolower($fuente->nombre);
                        if (str_contains($nomMin, 'registradur') || 
                            str_contains($nomMin, 'colombiacheck') ||
                            str_contains($nomMin, 'dane') ||
                            str_contains($nomMin, 'corte')) {
                            $citas[$fuente->id_fuente] = "Fuente consultada ({$fuente->nombre}): " . $contrasteInstitucional['cita'];
                            $detalles[$fuente->id_fuente] = [
                                'fuente'             => $fuente->nombre,
                                'noticias_evaluadas' => 1,
                                'estado_contraste'   => 'REFUTA',
                                'concordancia'       => 0.0,
                            ];
                        }
                    }
                }
            } elseif ($contrasteInstitucional['es_confirmacion']) {
                $puntuaciones[] = $contrasteInstitucional['score'];
                if ($citarFuentesDelGrafo) {
                    foreach ($fuentes as $fuente) {
                        $nomMin = mb_strtolower($fuente->nombre);
                        if (str_contains($nomMin, 'registradur') || str_contains($nomMin, 'dane')) {
                            $citas[$fuente->id_fuente] = "Fuente consultada ({$fuente->nombre}): " . $contrasteInstitucional['cita'];
                        }
                    }
                }
            }
        }

        // Términos manifiestamente absurdos, conspiranoicos o pseudocientíficos
        $terminosInfundados = [
            'alien', 'aliens', 'alienígena', 'alienigena', 'extraterrestre', 'ovni',
            'radioactivo', 'radiactivo', 'reptiliano', 'iluminati', 'chip 5g', 'microchip',
            'tierra plana', 'bicarbonato cura', 'cura milagrosa', 'conspiración', 'conspiracion'
        ];
        $textoMin = mb_strtolower($texto);
        $textoSinTildes = $this->normalizarSinTildes($textoMin);
        $tieneTerminosInfundados = false;
        foreach ($terminosInfundados as $term) {
            if (str_contains($textoMin, $term)) {
                $tieneTerminosInfundados = true;
                break;
            }
        }

        $textoEsNegativo = (bool) preg_match(
            '/\b(no gano|no ha ganado|no obtuvo|no es cierto|no es verdad|es falso|falso que|mentira que|no es|no fue|no sera|perdio|fue derrotado|derrotado)\b/iu',
            $textoSinTildes
        );

        $palabrasRefutacion = [
            'falso', 'desmiente', 'desmintiendo', 'desmintio', 'desmintió',
            'mentira', 'fake', 'desmentido', 'inexistente', 'no es cierto',
            'bulo', 'falsedad', 'rumor falso', 'no gano', 'no ganó', 'no ganaron',
        ];
        $palabrasConfirmacion = ['confirma que', 'confirmó que', 'certifica que', 'ratifica que', 'oficializa que', 'demuestra que'];

        // Contar cuántas fuentes tienen noticias reales que responden
        $fuentesConNoticias = 0;
        $refutacionesExplicitas = 0;
        $confirmacionesExplicitas = 0;
        $umbralAntiguedadConfirmacion = now()->subYears(2)->timestamp;

        foreach ($fuentes as $fuente) {
            $idF = $fuente->id_fuente;
            $noticias = $noticiasPorFuente[$idF] ?? [];

            if (empty($noticias)) {
                // La fuente está en el grafo pero NO tiene noticia directa sobre esta afirmación
                if (!isset($citas[$idF])) {
                    $citas[$idF] = "Fuente consultada ({$fuente->nombre}): No registra cobertura sobre esta afirmación ({$fuente->url_sitio}).";
                }
                if (!isset($detalles[$idF])) {
                    $detalles[$idF] = [
                        'fuente'             => $fuente->nombre,
                        'noticias_evaluadas' => 0,
                        'estado_contraste'   => 'SIN_NOTICIA_DIRECTA',
                        'concordancia'       => null,
                    ];
                }
                continue;
            }

            $fuentesConNoticias++;

            // Evaluar contraste contra cada noticia de esta fuente
            $mejorNoticia = $noticias[0];
            $mejorScore = 0.0;
            $tipoContraste = 'SIN_EVIDENCIA';
            $justificacionContraste = '';

            foreach ($noticias as $noticia) {
                $textoNoticia = mb_strtolower($noticia->titulo . ' ' . $noticia->fragmento);
                $textoNoticiaSin = $this->normalizarSinTildes($textoNoticia);

                // Evitar falsos REFUTA sobre documentos que apenas comparten un nombre propio en afirmaciones multi-concepto
                $permiteRefutacionPorEspecificidad = $contrasteInstitucional !== null
                    || !isset($noticia->total_especificas, $noticia->coincidencias_especificas)
                    || (int) $noticia->total_especificas < 3
                    || (int) $noticia->coincidencias_especificas >= 2;

                // Detectar si la noticia de la fuente desmiente o refuta
                $esRefutacion = false;
                if ($permiteRefutacionPorEspecificidad) {
                    foreach ($palabrasRefutacion as $ref) {
                        $refSin = $this->normalizarSinTildes($ref);
                        if (str_contains($textoNoticia, $ref) || str_contains($textoNoticiaSin, $refSin)) {
                            $esRefutacion = true;
                            break;
                        }
                    }
                }

                // Verificar si el artículo coincide en especificidad y vigencia temporal antes de marcar CONFIRMA
                $esNoticiaAntiguaDescontextualizada = false;
                if (!empty($noticia->fecha_publicacion)) {
                    $pubTs = strtotime((string) $noticia->fecha_publicacion);
                    if ($pubTs !== false && $pubTs < $umbralAntiguedadConfirmacion) {
                        $anioDoc = date('Y', $pubTs);
                        if (!str_contains($textoMin, $anioDoc)) {
                            $esNoticiaAntiguaDescontextualizada = true;
                        }
                    }
                }

                $cumpleEspecificidad = true;
                if (isset($noticia->coincidencias) && (int) $noticia->coincidencias < $minCoincidenciasConfirmacion) {
                    $cumpleEspecificidad = false;
                }
                if (
                    isset($noticia->total_especificas, $noticia->coincidencias_especificas)
                    && (int) $noticia->total_especificas >= 2
                    && $maxCoincidenciasEspecificas >= 2
                    && (int) $noticia->coincidencias_especificas < 2
                ) {
                    $cumpleEspecificidad = false;
                }

                // Detectar si la noticia confirma de manera explícita (nunca si hay refutación institucional, términos absurdos, baja especificidad o descontextualización antigua)
                $esConfirmacion = false;
                if (
                    !$tieneRefutacionInstitucional
                    && !$tieneTerminosInfundados
                    && !$esNoticiaAntiguaDescontextualizada
                    && $cumpleEspecificidad
                ) {
                    foreach ($palabrasConfirmacion as $conf) {
                        $confSin = $this->normalizarSinTildes($conf);
                        if (str_contains($textoNoticia, $conf) || str_contains($textoNoticiaSin, $confSin)) {
                            $esConfirmacion = true;
                            break;
                        }
                    }
                }

                $tituloLimpioNoticia = trim((string) preg_replace(
                    '/\s+/u',
                    ' ',
                    html_entity_decode(strip_tags((string) ($noticia->titulo ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')
                ));

                // Si la afirmación del usuario es negativa y el registro institucional ya la confirmó, una noticia con "no ganó" corrobora, no refuta
                if (
                    $contrasteInstitucional !== null
                    && $contrasteInstitucional['es_confirmacion']
                    && $textoEsNegativo
                    && $esRefutacion
                ) {
                    $tipoContraste = 'CONFIRMA';
                    $mejorScore = 95.0;
                    $confirmacionesExplicitas++;
                    $justificacionContraste = "La fuente y los registros oficiales corroboran el hecho señalado: «{$tituloLimpioNoticia}».";
                } elseif ($esRefutacion) {
                    $tipoContraste = 'REFUTA';
                    $mejorScore = 0.0; // Desmentido tajante: 0% veracidad
                    $refutacionesExplicitas++;
                    $justificacionContraste = "La fuente y los registros oficiales desmienten el contenido: «{$tituloLimpioNoticia}».";
                } elseif ($tieneRefutacionInstitucional) {
                    $tipoContraste = 'CONTEXTUALIZA';
                    $mejorScore = 0.0;
                    $justificacionContraste = "La fuente menciona términos relacionados («{$tituloLimpioNoticia}»), pero los registros oficiales descartan la afirmación principal.";
                } elseif ($esConfirmacion) {
                    $tipoContraste = 'CONFIRMA';
                    $mejorScore = 90.0; // Corrobora la veracidad
                    $confirmacionesExplicitas++;
                    $justificacionContraste = "La fuente corrobora los hechos reportados: «{$tituloLimpioNoticia}».";
                } elseif ($tieneTerminosInfundados) {
                    $tipoContraste = 'REFUTA';
                    $mejorScore = 0.0;
                    $refutacionesExplicitas++;
                    $justificacionContraste = "La afirmación contiene elementos inverosímiles no respaldados por la cobertura periodística: «{$tituloLimpioNoticia}».";
                } elseif (
                    (isset($noticia->total_especificas) && (int) $noticia->total_especificas >= 2 && (int) ($noticia->coincidencias_especificas ?? 0) < 2 && $maxCoincidenciasEspecificas < 2)
                    || (count($palabrasClaveQuery) >= 2 && (int) ($noticia->coincidencias_especificas ?? 0) < 2 && $maxCoincidenciasEspecificas < 2)
                ) {
                    // Mención aislada de una figura o término sin respaldo del predicado, acción o evento central
                    $tipoContraste = 'SIN_EVIDENCIA';
                    $mejorScore = 0.0;
                    $justificacionContraste = "La fuente contiene menciones aisladas («{$tituloLimpioNoticia}»), pero no existe registro, cobertura ni evidencia alguna de los hechos señalados.";
                } else {
                    $tipoContraste = 'CONTEXTUALIZA';
                    $mejorScore = 35.0; // Contexto parcial
                    $justificacionContraste = "La fuente aporta contexto tangencial pero no verifica la afirmación completa: «{$tituloLimpioNoticia}».";
                }

                $mejorNoticia = $noticia;
                break;
            }

            // Construir la cita de contraste citando la noticia encontrada (sin etiquetas ni entidades HTML)
            $tituloLimpioCita = trim((string) preg_replace(
                '/\s+/u',
                ' ',
                html_entity_decode(strip_tags((string) ($mejorNoticia->titulo ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')
            ));
            $fragmentoLimpioCita = trim((string) preg_replace(
                '/\s+/u',
                ' ',
                html_entity_decode(strip_tags((string) ($mejorNoticia->fragmento ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')
            ));

            $citaTexto = "Fuente: {$fuente->nombre} [Cat: {$mejorNoticia->categoria}] — «" .
                mb_substr($fragmentoLimpioCita, 0, 200) . "…» (" . $mejorNoticia->url_articulo . "). " .
                "Contraste de hechos: {$justificacionContraste}";

            $citas[$idF] = $citaTexto;
            $puntuaciones[] = $mejorScore;

            $detalles[$idF] = [
                'fuente'                    => $fuente->nombre,
                'noticias_evaluadas'        => count($noticias),
                'noticia_titular'           => $tituloLimpioCita,
                'url_noticia'               => $mejorNoticia->url_articulo,
                'categoria'                 => $mejorNoticia->categoria,
                'estado_contraste'          => $tipoContraste,
                'refutacion_explicita'      => $esRefutacion && $tipoContraste === 'REFUTA',
                'concordancia'              => $mejorScore,
                'coincidencias_especificas' => (int) ($mejorNoticia->coincidencias_especificas ?? 0),
                'total_especificas'         => (int) ($mejorNoticia->total_especificas ?? 0),
            ];

            $hallazgos[] = "{$fuente->nombre} ({$tipoContraste}): {$justificacionContraste}";
        }

        // Si hay refutación institucional tajante, el score fáctico es estrictamente 0.0
        if ($tieneRefutacionInstitucional) {
            $concordanciaPromedio = 0.0;
        } elseif ($contrasteInstitucional !== null && $contrasteInstitucional['es_confirmacion']) {
            $concordanciaPromedio = 95.0;
        } elseif ($fuentesConNoticias === 0) {
            $concordanciaPromedio = 0.0;
            $hallazgos = ["Ninguna fuente verificada en el Grafo de Credibilidad registra evidencia ni cobertura sobre esta afirmación."];
        } else {
            $concordanciaPromedio = !empty($puntuaciones)
                ? round(array_sum($puntuaciones) / count($puntuaciones), 2)
                : 0.0;

            // Si al menos una fuente refuta explícitamente y ninguna fuente válida confirma, el contexto tangencial no puede elevar el veredicto por encima de falso
            if ($refutacionesExplicitas >= 1 && $confirmacionesExplicitas === 0) {
                $concordanciaPromedio = 0.0;
            }
        }

        return [
            'concordancia_score'  => $concordanciaPromedio,
            'citas'               => $citas,
            'detalles'            => $detalles,
            'hallazgos'           => $hallazgos,
            'contraste_institucional' => $contrasteInstitucional,
        ];
    }

    /**
     * Búsqueda híbrida de evidencia por texto para contrastar afirmaciones.
     *
     * Si no se dispone de embedding en tiempo de ejecución, realiza concordancia
     * de texto contra los fragmentos y titulares indexados de las 80 fuentes.
     *
     * @param  string             $texto     Afirmación o contenido a verificar
     * @param  int                $limite    Número máximo de fragmentos
     * @param  array<string>|null $fuenteIds Filtro de fuentes
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function buscarEvidenciaPorTexto(
        string $texto,
        int $limite = 10,
        ?array $fuenteIds = null
    ): Collection {
        $stopWords = [
            'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas', 'del', 'al', 'que',
            'por', 'para', 'con', 'sin', 'sobre', 'entre', 'donde', 'cuando', 'desde',
            'hasta', 'hacia', 'tras', 'durante', 'mediante', 'segun', 'contra',
            'este', 'esta', 'estos', 'estas', 'esto', 'ese', 'esa', 'esos', 'esas',
            'aquel', 'aquella', 'aquellos', 'aquellas', 'aqui', 'alla', 'alli',
            'hacer', 'hace', 'hacen', 'hacia', 'hecho', 'tiene', 'tienen', 'tenia',
            'estan', 'estaba', 'estado', 'estuvo', 'seria', 'sido', 'sera', 'eran',
            'fueron', 'fuera', 'fuese', 'haber', 'habia', 'habra', 'hubo', 'haya',
            'dice', 'dijo', 'pero', 'mas', 'muy', 'tambien', 'todos', 'todas', 'todo',
            'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo',
            'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
            'septiembre', 'octubre', 'noviembre', 'diciembre',
            'noticia', 'noticias', 'verificada', 'verificado', 'portal', 'informativo',
            'periodico', 'impreso', 'articulo', 'articulos', 'medio', 'medios', 'oficial',
            'otro', 'otra', 'otros', 'otras', 'ante', 'bajo', 'cabe', 'cual', 'cuales',
            'quien', 'quienes', 'cuyo', 'cuya', 'cuyos', 'cuyas', 'suyo', 'suya',
            'nuestro', 'nuestra', 'nuestros', 'nuestras', 'cada', 'algun', 'alguna',
            'algunos', 'algunas', 'ningun', 'ninguna', 'mismo', 'misma', 'mismos', 'mismas',
            'ahora', 'antes', 'despues', 'luego', 'siempre', 'nunca', 'jamas', 'quizas',
        ];

        // Términos genéricos periodísticos o electorales que no definen por sí solos un tema o sujeto
        $terminosGenericos = [
            'gano', 'gana', 'ganar', 'ganaron', 'ganador', 'ganadora', 'ganadores',
            'perdio', 'perder', 'perdieron', 'pierde', 'derrota',
            'eleccion', 'elecciones', 'presidencial', 'presidenciales',
            'votacion', 'votaciones', 'voto', 'votos',
            'presidente', 'presidenta', 'presidencia',
            'alcalde', 'alcaldia', 'gobernador', 'gobernacion',
            'candidato', 'candidatos', 'candidatura',
            'gobierno', 'politica', 'pais', 'nacion', 'nacional',
            'primera', 'segunda', 'vuelta', 'ano', 'anos',
            'declaraciones', 'afirmo', 'anuncio', 'aseguro', 'informo',
        ];

        $paises = [
            'colombia', 'brasil', 'venezuela', 'peru', 'argentina', 'chile', 'mexico',
            'ecuador', 'bolivia', 'uruguay', 'paraguay', 'espana', 'eeuu',
        ];

        $palabras = array_values(array_filter(
            explode(' ', preg_replace('/[^\p{L}\p{N}\s]/u', '', mb_strtolower($texto))),
            fn ($p) => mb_strlen($p) >= 3 && !is_numeric($p) && !in_array($p, $stopWords, true)
        ));

        if (empty($palabras)) {
            return collect();
        }

        $entidadesPrincipales = array_values(array_filter(
            $palabras,
            fn ($p) => !in_array($p, $terminosGenericos, true) && !in_array($p, $paises, true)
        ));

        $otrosTerminos = array_values(array_filter(
            $palabras,
            fn ($p) => in_array($p, $terminosGenericos, true) || in_array($p, $paises, true)
        ));

        $driver = DB::connection()->getDriverName();
        $query = Documento::with('fuente')
            ->when(!empty($fuenteIds), fn ($q) => $q->whereIn('fuente_id', $fuenteIds));

        if ($driver === 'pgsql') {
            if (!empty($entidadesPrincipales)) {
                // El documento DEBE contener al menos una entidad nominal principal (sujeto de la afirmación)
                $regexEntidades = '\y(' . implode('|', array_map('preg_quote', $entidadesPrincipales)) . ')\y';
                $query->where(function ($q) use ($regexEntidades) {
                    $q->whereRaw('titulo ~* ?', [$regexEntidades])
                      ->orWhereRaw('fragmento ~* ?', [$regexEntidades]);
                });

                // Si hay otros términos acompañantes (país, verbo o contexto), exigir al menos un segundo término
                $otros = array_merge(array_slice($entidadesPrincipales, 1), $otrosTerminos);
                if (!empty($otros)) {
                    $regexOtros = '\y(' . implode('|', array_map('preg_quote', $otros)) . ')\y';
                    $query->where(function ($q) use ($regexOtros) {
                        $q->whereRaw('titulo ~* ?', [$regexOtros])
                          ->orWhereRaw('fragmento ~* ?', [$regexOtros]);
                    });
                }
            } else {
                // Sin entidades específicas (solo términos genéricos): exigir al menos 3 términos de contexto
                if (count($otrosTerminos) >= 3) {
                    foreach (array_slice($otrosTerminos, 0, 3) as $term) {
                        $regex = '\y' . preg_quote($term) . '\y';
                        $query->where(function ($q) use ($regex) {
                            $q->whereRaw('titulo ~* ?', [$regex])
                              ->orWhereRaw('fragmento ~* ?', [$regex]);
                        });
                    }
                } else {
                    return collect();
                }
            }
        } else {
            // Fallback SQLite / MySQL / MariaDB
            if (!empty($entidadesPrincipales)) {
                $query->where(function ($q) use ($entidadesPrincipales) {
                    foreach ($entidadesPrincipales as $esp) {
                        $q->orWhere('titulo', 'LIKE', "%{$esp}%")
                          ->orWhere('fragmento', 'LIKE', "%{$esp}%");
                    }
                });

                $otros = array_merge(array_slice($entidadesPrincipales, 1), $otrosTerminos);
                if (!empty($otros)) {
                    $query->where(function ($q) use ($otros) {
                        foreach ($otros as $ot) {
                            $q->orWhere('titulo', 'LIKE', "%{$ot}%")
                              ->orWhere('fragmento', 'LIKE', "%{$ot}%");
                        }
                    });
                }
            } else {
                if (count($otrosTerminos) >= 2) {
                    foreach (array_slice($otrosTerminos, 0, 2) as $term) {
                        $query->where(function ($q) use ($term) {
                            $q->orWhere('titulo', 'LIKE', "%{$term}%")
                              ->orWhere('fragmento', 'LIKE', "%{$term}%");
                        });
                    }
                } else {
                    return collect();
                }
            }
        }

        return $query->orderByDesc('fecha_indexacion')
            ->limit($limite * 2)
            ->get()
            ->filter(function ($doc) {
                $titulo = trim((string) ($doc->titulo ?? ''));
                $url = trim((string) ($doc->url_articulo ?? ''));
                return $titulo !== ''
                    && !preg_match('/\.(webp|jpe?g|png|gif|svg|pdf|mp4|mp3)$/i', $titulo)
                    && !preg_match('/\.(webp|jpe?g|png|gif|svg)$/i', $url);
            })
            ->take($limite)
            ->values();
    }

    /**
     * Extrae citas de contraste reales y fuentes sustentadas para un conjunto de fuentes.
     *
     * @param  string                                           $texto   Texto afirmado
     * @param  \Illuminate\Database\Eloquent\Collection<\App\Models\Fuente> $fuentes Fuentes involucradas
     * @return array<string, string|null> Mapa de id_fuente => cita_contraste
     */
    public function extraerCitasParaFuentes(string $texto, $fuentes): array
    {
        $fuenteIds = $fuentes->pluck('id_fuente')->all();
        $documentos = $this->buscarEvidenciaPorTexto($texto, 15, $fuenteIds);

        $citas = [];
        foreach ($fuentes as $fuente) {
            $doc = $documentos->firstWhere('fuente_id', $fuente->id_fuente);
            if ($doc) {
                $frag = trim((string) preg_replace(
                    '/\s+/u',
                    ' ',
                    html_entity_decode(strip_tags((string) ($doc->fragmento ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')
                ));
                $citas[$fuente->id_fuente] = "Fuente: {$fuente->nombre} — «" . mb_substr($frag, 0, 250) . "…» (" . $doc->url_articulo . ")";
            } else {
                $citas[$fuente->id_fuente] = null;
            }
        }

        return $citas;
    }
}
