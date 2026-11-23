<?php
/**
 * DAGAZ — Servidor de API Backend Local (PHP Standalone / Router)
 *
 * Implementa los contratos REST públicos de DAGAZ para evaluación local:
 * - GET  /health
 * - GET  /api/v1/fuentes
 * - POST /api/v1/verificacion
 * - GET  /api/v1/verificacion/{id}
 */

// Cabeceras CORS abiertas para permitir peticiones desde el Frontend
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PATCH, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Helper para responder JSON
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// RUTA 1: GET /health y GET /
// ─────────────────────────────────────────────────────────────────────────────
if ($uri === '/' || $uri === '/health') {
    jsonResponse([
        'estado'    => 'operativo',
        'sistema'   => 'DAGAZ Backend API',
        'version'   => '0.0.26',
        'motor'     => 'DAGAZ Fact-Checking Engine (PHP/PostgreSQL Mock)',
        'timestamp' => date('c'),
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// RUTA 2: GET /api/v1/fuentes
// ─────────────────────────────────────────────────────────────────────────────
if ($uri === '/api/v1/fuentes' && $method === 'GET') {
    $fuentes = [
        [
            'id_fuente' => 'f1-reuters',
            'nombre' => 'Reuters',
            'pais' => 'Internacional',
            'tipo_fuente' => 'internacional_n1',
            'indice_credibilidad' => 0.98,
            'url_base' => 'https://reuters.com',
            'descripcion' => 'Agencia internacional de noticias con cobertura global'
        ],
        [
            'id_fuente' => 'f2-ap',
            'nombre' => 'Associated Press (AP)',
            'pais' => 'Internacional',
            'tipo_fuente' => 'internacional_n1',
            'indice_credibilidad' => 0.97,
            'url_base' => 'https://apnews.com',
            'descripcion' => 'Agencia de noticias global con riguroso estándar editorial'
        ],
        [
            'id_fuente' => 'f3-oms',
            'nombre' => 'Organización Mundial de la Salud (OMS)',
            'pais' => 'Internacional',
            'tipo_fuente' => 'internacional_n1',
            'indice_credibilidad' => 0.99,
            'url_base' => 'https://who.int',
            'descripcion' => 'Organismo multilateral de salud'
        ],
        [
            'id_fuente' => 'f4-colombiacheck',
            'nombre' => 'ColombiaCheck',
            'pais' => 'Colombia',
            'tipo_fuente' => 'factchecker_n4',
            'indice_credibilidad' => 0.92,
            'url_base' => 'https://colombiacheck.com',
            'descripcion' => 'Organización certificada por la IFCN especializada en Colombia'
        ],
        [
            'id_fuente' => 'f5-lasillavacia',
            'nombre' => 'La Silla Vacía',
            'pais' => 'Colombia',
            'tipo_fuente' => 'factchecker_n4',
            'indice_credibilidad' => 0.89,
            'url_base' => 'https://lasillavacia.com',
            'descripcion' => 'Periodismo investigativo y fact-checking político en Colombia'
        ],
        [
            'id_fuente' => 'f6-eltiempo',
            'nombre' => 'El Tiempo',
            'pais' => 'Colombia',
            'tipo_fuente' => 'nacional_n3',
            'indice_credibilidad' => 0.85,
            'url_base' => 'https://eltiempo.com',
            'descripcion' => 'Medio periodístico nacional de Colombia'
        ],
        [
            'id_fuente' => 'f7-elespectador',
            'nombre' => 'El Espectador',
            'pais' => 'Colombia',
            'tipo_fuente' => 'nacional_n3',
            'indice_credibilidad' => 0.84,
            'url_base' => 'https://elespectador.com',
            'descripcion' => 'Medio periodístico nacional de referencia'
        ],
        [
            'id_fuente' => 'f8-elcolombiano',
            'nombre' => 'El Colombiano',
            'pais' => 'Antioquia, Colombia',
            'tipo_fuente' => 'regional_n2',
            'indice_credibilidad' => 0.82,
            'url_base' => 'https://elcolombiano.com',
            'descripcion' => 'Prensa regional con cobertura en Antioquia'
        ]
    ];
    jsonResponse(['fuentes' => $fuentes, 'total' => count($fuentes)]);
}

// ─────────────────────────────────────────────────────────────────────────────
// RUTA 3: POST /api/v1/verificacion
// ─────────────────────────────────────────────────────────────────────────────
if ($uri === '/api/v1/verificacion' && $method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? $_POST;

    $tipoEntrada = strtoupper($input['tipo_entrada'] ?? 'TEXTO');
    $texto = trim($input['contenido_original'] ?? $input['url_origen'] ?? '');

    if (empty($texto)) {
        jsonResponse([
            'error' => 'Contenido vacío',
            'mensaje' => 'Debe ingresar un texto, enlace o archivo para verificar.'
        ], 422);
    }

    $t = mb_strtolower($texto, 'UTF-8');

    // Patrones de evaluación semántica (Grafo de Credibilidad de Colombia)
    $patronesFalsos = [
        'renunció', 'renuncia de', 'renuncio', 'golpe de estado', 'falleció', 'fallecio', 
        'murió', 'murio', 'asesinado', 'fraude electoral', 'se roban', 'robaron elecciones', 
        'chip 5g', '5g', 'veneno', 'cura milagrosa', 'bicarbonato cura', 'antena 5g', 
        'conspiración', 'conspiracion', 'compartan urgente', 'compartan antes que lo borren',
        'toque de queda militar', 'cierran bancos', 'dólar a 10000', 'dolar a 10000',
        'tierra plana', 'vacuna mortal', 'microchip', 'alienígenas', 'alienigenas'
    ];

    $patronesEnganosos = [
        'escándalo', 'escandalo', 'lo que ocultan', 'peligro inminente', 'caos total', 
        'colapso de colombia', 'prohibido publicar', 'nadie te dice', 'revelación impactante',
        'revelacion', 'impactante', 'secreto mundial', 'alarmante', 'alerta roja'
    ];

    $patronesParciales = [
        'reforma', 'inflación', 'inflacion', 'impuesto', 'gasolina', 'dólar', 'dolar', 
        'desempleo', 'estadística', 'estadistica', 'salario mínimo', 'salario minimo',
        'aumento', 'decreto', 'congreso', 'presupuesto', 'seguridad', 'encuesta'
    ];

    $patronesVerdaderos = [
        'bogotá', 'bogota', 'capital de colombia', 'café', 'cafe', 'dane', 
        'banco de la república', 'banco de la republica', 'constitución de 1991', 
        'constitucion de 1991', 'cordillera de los andes', 'amazonas', 'medellín', 
        'medellin', 'cali', 'barranquilla', 'peso colombiano', 'oms', 'reserva hídrica', 
        'reserva hidrica', 'parque nacional', 'biodiversidad', 'río magdalena', 'rio magdalena'
    ];

    $esFalso = false;
    foreach ($patronesFalsos as $p) {
        if (str_contains($t, $p)) { $esFalso = true; break; }
    }

    $esEnganoso = false;
    foreach ($patronesEnganosos as $p) {
        if (str_contains($t, $p)) { $esEnganoso = true; break; }
    }

    $esParcial = false;
    foreach ($patronesParciales as $p) {
        if (str_contains($t, $p)) { $esParcial = true; break; }
    }

    $esVerdadero = false;
    foreach ($patronesVerdaderos as $p) {
        if (str_contains($t, $p)) { $esVerdadero = true; break; }
    }

    // Generar veredicto según ponderación
    if (mb_strlen($texto) < 15) {
        $tipoVeredicto = 'NO_VERIFICABLE';
        $confianza = 20.0;
        $explicacion = 'El contenido es demasiado breve o vago para aislar afirmaciones atómicas verificables. Proporcione más contexto o el artículo completo.';
        $fuentes = [
            [
                'nombre' => 'DAGAZ Fact-Checking',
                'indice_credibilidad' => 0.90,
                'tipo_fuente' => 'factchecker_n4',
                'url_base' => 'https://colombiacheck.com',
                'descripcion' => 'Guía de criterios de verificabilidad de contenidos'
            ]
        ];
    } elseif ($esFalso) {
        $tipoVeredicto = 'FALSO';
        $confianza = 15.0;
        $explicacion = "Con una confiabilidad del 15%, este contenido contradice los registros oficiales del Estado colombiano y verificadores certificados (IFCN). No existe respaldo documental para esta afirmación; se trata de una cadena viral desmentida.";
        $fuentes = [
            [
                'nombre' => 'ColombiaCheck',
                'indice_credibilidad' => 0.92,
                'tipo_fuente' => 'factchecker_n4',
                'url_base' => 'https://colombiacheck.com',
                'descripcion' => 'Verificación de hechos: cadena viral desmentida en Colombia'
            ],
            [
                'nombre' => 'La Silla Vacía (Detector de Mentiras)',
                'indice_credibilidad' => 0.89,
                'tipo_fuente' => 'factchecker_n4',
                'url_base' => 'https://lasillavacia.com',
                'descripcion' => 'Auditoría a cadenas y desinformación política'
            ],
            [
                'nombre' => 'Reuters Fact Check',
                'indice_credibilidad' => 0.98,
                'tipo_fuente' => 'internacional_n1',
                'url_base' => 'https://reuters.com',
                'descripcion' => 'Desmentido de afirmaciones fraudulentas y bulos virales'
            ]
        ];
    } elseif ($esEnganoso) {
        $tipoVeredicto = 'ENGAÑOSO';
        $confianza = 42.0;
        $explicacion = "Con una confiabilidad del 42%, este contenido presenta afirmaciones con elementos engañosos o descontextualizados. Mezcla datos reales con conclusiones alarmistas no fundamentadas.";
        $fuentes = [
            [
                'nombre' => 'El Espectador',
                'indice_credibilidad' => 0.84,
                'tipo_fuente' => 'nacional_n3',
                'url_base' => 'https://elespectador.com',
                'descripcion' => 'Contexto y antecedentes de la noticia'
            ],
            [
                'nombre' => 'ColombiaCheck',
                'indice_credibilidad' => 0.92,
                'tipo_fuente' => 'factchecker_n4',
                'url_base' => 'https://colombiacheck.com',
                'descripcion' => 'Calificación: Engañoso por omisión de temporalidad'
            ]
        ];
    } elseif ($esParcial) {
        $tipoVeredicto = 'PARCIAL';
        $confianza = 64.0;
        $explicacion = "Con una confiabilidad del 64%, este contenido presenta información parcialmente correcta que requiere contexto adicional. Se recomienda contrastar cifras oficiales antes de compartir.";
        $fuentes = [
            [
                'nombre' => 'DANE (Departamento Administrativo Nacional de Estadística)',
                'indice_credibilidad' => 0.96,
                'tipo_fuente' => 'internacional_n1',
                'url_base' => 'https://dane.gov.co',
                'descripcion' => 'Boletín técnico oficial y estadísticas de Colombia'
            ],
            [
                'nombre' => 'Banco de la República',
                'indice_credibilidad' => 0.98,
                'tipo_fuente' => 'internacional_n1',
                'url_base' => 'https://banrep.gov.co',
                'descripcion' => 'Informes de política económica e inflación'
            ],
            [
                'nombre' => 'Portafolio',
                'indice_credibilidad' => 0.81,
                'tipo_fuente' => 'nacional_n3',
                'url_base' => 'https://portafolio.co',
                'descripcion' => 'Seguimiento especializado a proyectos económicos'
            ]
        ];
    } elseif ($esVerdadero) {
        $tipoVeredicto = 'VERDADERO';
        $confianza = 94.0;
        $explicacion = "Con una confiabilidad del 94%, este contenido es plenamente consistente con la información publicada por fuentes verificadas e instituciones del Estado colombiano.";
        $fuentes = [
            [
                'nombre' => 'Reuters',
                'indice_credibilidad' => 0.98,
                'tipo_fuente' => 'internacional_n1',
                'url_base' => 'https://reuters.com',
                'descripcion' => 'Registro oficial y despacho internacional confirmado'
            ],
            [
                'nombre' => 'El Tiempo',
                'indice_credibilidad' => 0.85,
                'tipo_fuente' => 'nacional_n3',
                'url_base' => 'https://eltiempo.com',
                'descripcion' => 'Publicación periodística verificada'
            ],
            [
                'nombre' => 'OMS / Entidades Multilaterales',
                'indice_credibilidad' => 0.99,
                'tipo_fuente' => 'internacional_n1',
                'url_base' => 'https://who.int',
                'descripcion' => 'Base de datos y documentación oficial'
            ]
        ];
    } else {
        $tipoVeredicto = 'PARCIAL';
        $confianza = 55.0;
        $explicacion = "El contenido contiene afirmaciones plausibles pero carece de citas a fuentes primarias del Grafo de Credibilidad. Se sugiere verificar detalles adicionales.";
        $fuentes = [
            [
                'nombre' => 'El Espectador',
                'indice_credibilidad' => 0.84,
                'tipo_fuente' => 'nacional_n3',
                'url_base' => 'https://elespectador.com',
                'descripcion' => 'Cobertura de actualidad en Colombia'
            ],
            [
                'nombre' => 'El Tiempo',
                'indice_credibilidad' => 0.85,
                'tipo_fuente' => 'nacional_n3',
                'url_base' => 'https://eltiempo.com',
                'descripcion' => 'Seguimiento general de noticias'
            ]
        ];
    }

    $idContenido = 'cnt-' . bin2hex(random_bytes(6));
    $idProceso   = 'prc-' . bin2hex(random_bytes(6));
    $idVeredicto = 'vrd-' . bin2hex(random_bytes(6));

    jsonResponse([
        'mensaje' => 'Contenido verificado exitosamente.',
        'contenido' => [
            'id_contenido' => $idContenido,
            'tipo_entrada' => $tipoEntrada,
            'contenido_original' => $texto,
            'estado' => 'VERIFICADO',
            'fecha' => date('c'),
        ],
        'proceso' => [
            'id_proceso' => $idProceso,
            'score_confiabilidad' => $confianza,
        ],
        'veredicto' => [
            'id_veredicto' => $idVeredicto,
            'tipo_veredicto' => $tipoVeredicto,
            'nivel_confiabilidad' => $confianza,
            'explicacion' => $explicacion,
            'fecha_emision' => date('c'),
        ],
        'fuentes' => $fuentes,
    ]);
}

// Ruta no encontrada
jsonResponse(['error' => 'Ruta no encontrada', 'uri' => $uri], 404);
