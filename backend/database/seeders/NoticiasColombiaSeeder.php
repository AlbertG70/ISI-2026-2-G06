<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Contenido;
use App\Models\Documento;
use App\Models\Fuente;
use App\Models\Historial;
use App\Models\Nota;
use App\Models\ProcesoVerificacion;
use App\Models\Usuario;
use App\Models\Veredicto;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * NoticiasColombiaSeeder — Banco de Noticias, Chequeos y Cadenas Virales Recientes de Colombia
 *
 * Pobla la base de datos con contenidos verificados, veredictos contrastados,
 * notas comunitarias ciudadanas y documentos semánticos indexados del contexto
 * sociopolítico, económico y sanitario colombiano.
 *
 * Cobertura de tablas:
 *  - `contenidos`
 *  - `procesos_verificacion`
 *  - `veredictos`
 *  - `veredicto_fuentes`
 *  - `notas`
 *  - `historial`
 *  - `documentos` (para el motor de búsqueda semántica y RAG)
 */
class NoticiasColombiaSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🇨🇴 Iniciando carga de noticias y verificaciones recientes de Colombia...');

        // 1. Asegurar la existencia de las fuentes y del usuario adulto
        $this->call([
            FuentesSeeder::class,
            UsuariosSeeder::class,
        ]);

        $usuarioAdulto = Usuario::where('email', 'adulto@dagaz.com')->first();
        if (!$usuarioAdulto) {
            $usuarioAdulto = Usuario::first();
        }

        $idUsuario = $usuarioAdulto ? $usuarioAdulto->id_usuario : null;

        // Catálogo de noticias colombianas recientes estructuradas
        $noticias = [
            [
                'texto'        => 'Prosperidad Social abrió inscripciones en enlace web para bono extraordinario navideño de $500.000 para madres cabeza de familia y desempleados.',
                'tipo_entrada' => 'TEXTO',
                'url_origen'   => 'https://wa.me/mensaje-viral-colombia-bono500',
                'categoria'    => 'Finanzas y Economía',
                'veredicto'    => 'FALSO',
                'confianza'    => 98.0,
                'explicacion'  => 'Es falso. Prosperidad Social y ColombiaCheck confirmaron que la entidad no realiza inscripciones mediante formularios externos o cadenas de WhatsApp. Se trata de una campaña recurrente de suplantación y robo de datos personales (phishing). Los subsidios estatales se consultan únicamente en los portales oficiales terminados en .gov.co.',
                'fuentes'      => [
                    [
                        'nombre'        => 'ColombiaCheck',
                        'peso'          => 0.95,
                        'cita'          => 'Prosperidad Social advierte que no solicita datos personales ni claves a través de cadenas de mensajería instantánea ni enlaces acortados.',
                        'url_documento' => 'https://colombiacheck.com/chequeos/falso-bono-navideno-prosperidad-social-500mil',
                    ],
                    [
                        'nombre'        => 'El Espectador',
                        'peso'          => 0.85,
                        'cita'          => 'Alertan por nueva modalidad de phishing que suplanta subsidios del gobierno nacional mediante cadenas de WhatsApp.',
                        'url_documento' => 'https://www.elespectador.com/economia/alertan-por-estafas-con-subsidios-falsos-en-whatsapp/',
                    ],
                ],
                'nota_ciudadana' => 'Cuidado con ese enlace, le pide el número de cédula y clave bancaria. En la oficina de la alcaldía confirmaron que no hay ningún bono navideño extraordinario.',
                'nota_calif'     => 9.8,
                'accion'         => 'verificado',
                'fecha'          => Carbon::now()->subDays(1),
            ],
            [
                'texto'        => 'La Junta Directiva del Banco de la República recortó la tasa de interés de intervención en 50 puntos básicos, situándola en 9.5% anual tras evaluar el comportamiento de la inflación.',
                'tipo_entrada' => 'TEXTO',
                'url_origen'   => 'https://www.banrep.gov.co/es/noticias/tasa-interes-politica-monetaria',
                'categoria'    => 'Finanzas y Economía',
                'veredicto'    => 'VERDADERO',
                'confianza'    => 96.5,
                'explicacion'  => 'Es verdadero. El Banco de la República emitió su comunicado oficial confirmando la reducción de la tasa de política monetaria en respuesta a la desaceleración del índice de precios al consumidor (IPC) reportada por el DANE.',
                'fuentes'      => [
                    [
                        'nombre'        => 'Banco de la República',
                        'peso'          => 1.00,
                        'cita'          => 'La Junta Directiva del Banco de la República decidió por mayoría reducir en 50 puntos básicos la tasa de interés de política monetaria a 9.5%.',
                        'url_documento' => 'https://www.banrep.gov.co/es/comunicado-junta-tasas-interes',
                    ],
                    [
                        'nombre'        => 'DANE',
                        'peso'          => 0.95,
                        'cita'          => 'El IPC anual continuó su senda descendente impulsado por la normalización en los precios de los alimentos procesados.',
                        'url_documento' => 'https://www.dane.gov.co/index.php/estadisticas-por-tema/precios-y-costos/indice-de-precios-al-consumidor-ipc',
                    ],
                ],
                'nota_ciudadana' => 'Excelente noticia para quienes tenemos créditos hipotecarios o de consumo, ya que los bancos comerciales comenzarán a ajustar sus tasas gradualmente.',
                'nota_calif'     => 9.2,
                'accion'         => 'guardado',
                'fecha'          => Carbon::now()->subDays(2),
            ],
            [
                'texto'        => 'La Comisión de Regulación de Energía y Gas (CREG) expidió resolución para congelar inmediatamente todas las tarifas eléctricas y suspender el pago de la opción tarifaria en los departamentos de la Costa Caribe.',
                'tipo_entrada' => 'TEXTO',
                'url_origen'   => 'https://twitter.com/noticias_caribe/status/tarifa_cero',
                'categoria'    => 'Gobierno y Política',
                'veredicto'    => 'ENGAÑOSO',
                'confianza'    => 82.0,
                'explicacion'  => 'Es engañoso. Si bien la CREG y el Ministerio de Minas publicaron medidas regulatorias para aliviar y renegociar los saldos acumulados de la opción tarifaria de Air-e y Afinia, las tarifas no han sido congeladas a cero ni se eliminó de tajo el cobro, sino que se distribuyó la amortización con aportes de la Nación.',
                'fuentes'      => [
                    [
                        'nombre'        => 'La Silla Vacía',
                        'peso'          => 0.90,
                        'cita'          => 'El marco regulatorio flexibiliza los periodos de recuperación de la opción tarifaria, pero no establece una congelación unilateral del costo del kilovatio hora.',
                        'url_documento' => 'https://lasillavacia.com/detector-de-mentiras/falso-congelacion-total-tarifas-energia-caribe/',
                    ],
                    [
                        'nombre'        => 'El Tiempo',
                        'peso'          => 0.85,
                        'cita'          => 'MinEnergía aclara que los alivios en las facturas de la costa caribe serán graduales mediante la asunción de pasivos de opción tarifaria.',
                        'url_documento' => 'https://www.eltiempo.com/economia/sectores/alivios-en-tarifas-de-energia-para-la-costa-caribe-como-aplican-33412',
                    ],
                ],
                'nota_ciudadana' => 'En Barranquilla las facturas siguen llegando con el cobro diferido. Lo que se aprobó fue que el gobierno asuma parte de la deuda, no que no se pague nada.',
                'nota_calif'     => 9.5,
                'accion'         => 'verificado',
                'fecha'          => Carbon::now()->subDays(3),
            ],
            [
                'texto'        => 'El Invima alertó sobre la comercialización ilegal y falsificación de lotes de analgésicos pediátricos sin registro sanitario en farmacias de Bogotá y Medellín.',
                'tipo_entrada' => 'TEXTO',
                'url_origen'   => 'https://www.invima.gov.co/alertas-sanitarias-medicamentos',
                'categoria'    => 'Salud y Ciencia',
                'veredicto'    => 'VERDADERO',
                'confianza'    => 95.0,
                'explicacion'  => 'Es verdadero. El Instituto Nacional de Vigilancia de Medicamentos y Alimentos (Invima) emitió una alerta sanitaria oficial advirtiendo a la ciudadanía sobre lotes identificados con empaques adulterados y recomendó abstenerse de comprarlos fuera de distribuidores autorizados.',
                'fuentes'      => [
                    [
                        'nombre'        => 'El Espectador',
                        'peso'          => 0.90,
                        'cita'          => 'El Invima solicita a las secretarías de salud realizar las inspecciones respectivas en droguerías y exhorta a los consumidores a verificar el número de registro sanitario antes del consumo.',
                        'url_documento' => 'https://www.elespectador.com/salud/invima-alerta-por-falsificacion-de-analgesicos-pediatricos/',
                    ],
                ],
                'nota_ciudadana' => 'Revisen el número de lote en la caja y comparen con la página web del Invima. Mi pediatra nos advirtió de esta misma alerta la semana pasada.',
                'nota_calif'     => 9.9,
                'accion'         => 'guardado',
                'fecha'          => Carbon::now()->subDays(4),
            ],
            [
                'texto'        => 'El Ministerio de Transporte anunció que todas las licencias de conducción de categoría B1 y C1 quedarán anuladas y se deberá pagar $1.500.000 para renovarlas este mes.',
                'tipo_entrada' => 'TEXTO',
                'url_origen'   => 'https://cadena-whatsapp.com/anulacion-licencias-colombia',
                'categoria'    => 'Gobierno y Política',
                'veredicto'    => 'FALSO',
                'confianza'    => 99.0,
                'explicacion'  => 'Es falso. El Ministerio de Transporte y el RUNT desmintieron la supuesta anulación masiva de licencias. La vigencia de las licencias para vehículos particulares sigue siendo de hasta 10 años para menores de 60 años, y el costo de renovación oficial en los centros de reconocimiento de conductores no supera las tarifas reguladas.',
                'fuentes'      => [
                    [
                        'nombre'        => 'ColombiaCheck',
                        'peso'          => 0.98,
                        'cita'          => 'El Ministerio de Transporte ratifica que no existe ningún decreto ni resolución que anule las licencias de conducción vigentes.',
                        'url_documento' => 'https://colombiacheck.com/chequeos/falso-anulacion-masiva-licencias-conduccion-colombia',
                    ],
                    [
                        'nombre'        => 'El Tiempo',
                        'peso'          => 0.85,
                        'cita'          => 'MinTransporte desmiente tarifas millonarias para trámites de licencia y aclara vigencias vigentes.',
                        'url_documento' => 'https://www.eltiempo.com/colombia/mintransporte-desmiente-cadena-sobre-anulacion-de-licencias-44512',
                    ],
                ],
                'nota_ciudadana' => 'Esta misma cadena falsa la mandan todos los años por WhatsApp cambiando solo la fecha. No caigan en pánico ni le paguen a tramitadores intermediarios.',
                'nota_calif'     => 9.7,
                'accion'         => 'verificado',
                'fecha'          => Carbon::now()->subDays(5),
            ],
            [
                'texto'        => 'Colpensiones absorberá inmediatamente todos los ahorros individuales de los fondos privados (Porvenir, Protección) sin reconocer rentabilidad a los cotizantes.',
                'tipo_entrada' => 'TEXTO',
                'url_origen'   => 'https://twitter.com/oposicion_viral/status/pensiones-expropiacion',
                'categoria'    => 'Finanzas y Economía',
                'veredicto'    => 'FALSO',
                'confianza'    => 94.0,
                'explicacion'  => 'Es falso. La Ley del Sistema de Protección Social Integral para la Vejez establece un sistema de pilares donde los ahorros existentes en cuentas individuales se respetan y las cotizaciones superiores a 2.3 salarios mínimos continúan en las administradoras de fondos de pensiones privadas. Además, los recursos son administrados con personería jurídica independiente y control del Banco de la República.',
                'fuentes'      => [
                    [
                        'nombre'        => 'La Silla Vacía',
                        'peso'          => 0.92,
                        'cita'          => 'El pilar contributivo mantiene la coexistencia entre el régimen de prima media y el componente de ahorro individual, respetando los derechos adquiridos y el régimen de transición.',
                        'url_documento' => 'https://lasillavacia.com/detector-de-mentiras/falso-colpensiones-no-expropia-ahorros-pensionales/',
                    ],
                    [
                        'nombre'        => 'El Espectador',
                        'peso'          => 0.88,
                        'cita'          => 'Fondo de ahorro del pilar contributivo será administrado por el Banco de la República para garantizar sostenibilidad e inversión.',
                        'url_documento' => 'https://www.elespectador.com/economia/como-funcionara-el-fondo-de-ahorro-pensional-administrado-por-el-banrep/',
                    ],
                ],
                'nota_ciudadana' => 'Quienes lleven más de 750 semanas (mujeres) o 900 semanas (hombres) cotizadas quedan en el régimen anterior de transición. No se confundan con desinformaciones alarmistas.',
                'nota_calif'     => 9.6,
                'accion'         => 'guardado',
                'fecha'          => Carbon::now()->subDays(6),
            ],
            [
                'texto'        => 'La Alcaldía Mayor de Bogotá y el Acueducto levantaron de forma definitiva todos los turnos de racionamiento de agua gracias a que el Sistema Chingaza superó el 85% de capacidad.',
                'tipo_entrada' => 'TEXTO',
                'url_origen'   => 'https://facebook.com/noticias_bogota_viral/chingaza_lleno',
                'categoria'    => 'Medio Ambiente',
                'veredicto'    => 'ENGAÑOSO',
                'confianza'    => 87.0,
                'explicacion'  => 'Es engañoso. Aunque las precipitaciones permitieron una recuperación paulatina de los embalses de Chuza y San Rafael, la Empresa de Acueducto y Alcantarillado de Bogotá (EAAB) mantiene el esquema de monitoreo y medidas preventivas, ya que la meta de seguridad hídrica exige mantener reservas sostenidas para la temporada seca.',
                'fuentes'      => [
                    [
                        'nombre'        => 'El Espectador',
                        'peso'          => 0.90,
                        'cita'          => 'La EAAB reitera que el ahorro voluntario y el consumo responsable continúan siendo indispensables a pesar de las lluvias registradas.',
                        'url_documento' => 'https://www.elespectador.com/bogota/estado-de-los-embalses-de-chingaza-y-medidas-en-bogota/',
                    ],
                ],
                'nota_ciudadana' => 'Ayer mismo en la zona 4 tuvimos corte programado. La alcaldía no ha levantado la medida de forma definitiva, solo flexibilizó el intervalo.',
                'nota_calif'     => 9.4,
                'accion'         => 'verificado',
                'fecha'          => Carbon::now()->subDays(7),
            ],
            [
                'texto'        => 'La Federación Nacional de Cafeteros informó que la producción y exportación de café de Colombia creció un 16% en lo corrido del año, impulsada por mejores precios internacionales en la Bolsa de Nueva York.',
                'tipo_entrada' => 'TEXTO',
                'url_origen'   => 'https://www.federaciondecafeteros.org/noticias-produccion-anual',
                'categoria'    => 'Finanzas y Economía',
                'veredicto'    => 'VERDADERO',
                'confianza'    => 95.0,
                'explicacion'  => 'Es verdadero. Las estadísticas oficiales de la Federación Nacional de Cafeteros y el DANE corroboran la reactivación productiva de las zonas cafeteras de Caldas, Huila, Antioquia y Tolima, superando los 12 millones de sacos anuales.',
                'fuentes'      => [
                    [
                        'nombre'        => 'El Tiempo',
                        'peso'          => 0.90,
                        'cita'          => 'El valor de la cosecha cafetera alcanzó cifras históricas gracias a la combinación de volumen cosechado y cotización de la libra en los mercados de futuros.',
                        'url_documento' => 'https://www.eltiempo.com/economia/sectores/produccion-de-cafe-en-colombia-crece-16-por-ciento-55123',
                    ],
                    [
                        'nombre'        => 'DANE',
                        'peso'          => 0.92,
                        'cita'          => 'Las exportaciones agropecuarias lideraron el crecimiento del comercio exterior colombiano durante el trimestre.',
                        'url_documento' => 'https://www.dane.gov.co/index.php/estadisticas-por-tema/comercio-internacional/exportaciones',
                    ],
                ],
                'nota_ciudadana' => 'El café de Colombia sigue siendo el motor de nuestras familias campesinas en el Eje Cafetero y el Huila.',
                'nota_calif'     => 9.1,
                'accion'         => 'guardado',
                'fecha'          => Carbon::now()->subDays(8),
            ],
            [
                'texto'        => 'Decreto presidencial ordena toque de queda total y militarización obligatoria en Cali, Medellín y Bucaramanga por alteración del orden público este fin de semana.',
                'tipo_entrada' => 'TEXTO',
                'url_origen'   => 'https://cadena-whatsapp.com/toque-queda-colombia-alerta',
                'categoria'    => 'Seguridad y Orden Público',
                'veredicto'    => 'FALSO',
                'confianza'    => 99.0,
                'explicacion'  => 'Es falso. Ninguna autoridad local ni el Ministerio del Interior han emitido decretos de toque de queda o restricción a la movilidad. La Alcaldía de Medellín y la Gobernación de Antioquia desmintieron este supuesto panfleto que circula en WhatsApp como una táctica de zozobra.',
                'fuentes'      => [
                    [
                        'nombre'        => 'ColombiaCheck',
                        'peso'          => 0.98,
                        'cita'          => 'El Puesto de Mando Unificado confirmó normalidad en las capitales y advirtió sobre cadenas apócrifas que reutilizan imágenes de años anteriores.',
                        'url_documento' => 'https://colombiacheck.com/chequeos/falso-toque-de-queda-panfleto-ciudades-colombia',
                    ],
                ],
                'nota_ciudadana' => 'Vivo en Medellín y todo el comercio, metro y transporte opera con total normalidad. No compartan audios anónimos sin verificar.',
                'nota_calif'     => 9.8,
                'accion'         => 'verificado',
                'fecha'          => Carbon::now()->subDays(9),
            ],
            [
                'texto'        => 'El programa "Puedo Estudiar" del Ministerio de Educación eliminó el límite de edad para acceder a gratuidad en la matrícula de pregrados en universidades públicas colombianas.',
                'tipo_entrada' => 'TEXTO',
                'url_origen'   => 'https://www.mineducacion.gov.co/gratuidad-puedo-estudiar',
                'categoria'    => 'Gobierno y Política',
                'veredicto'    => 'VERDADERO',
                'confianza'    => 93.0,
                'explicacion'  => 'Es verdadero. La reglamentación de la Ley de Gratuidad en la Educación Superior suprimió el requisito de tener menos de 28 años, permitiendo que adultos y adultos mayores puedan cursar carreras universitarias públicas con matrícula 100% cubierta por el Estado.',
                'fuentes'      => [
                    [
                        'nombre'        => 'El Espectador',
                        'peso'          => 0.90,
                        'cita'          => 'El Decreto 2271 establece que el beneficio de gratuidad aplicará sin discriminación por edad para estudiantes matriculados en instituciones públicas.',
                        'url_documento' => 'https://www.elespectador.com/educacion/gratuidad-en-educacion-superior-se-elimina-limite-de-edad/',
                    ],
                    [
                        'nombre'        => 'ColombiaCheck',
                        'peso'          => 0.92,
                        'cita'          => 'El Ministerio de Educación confirmó que la política de gratuidad universal no restringe el acceso a mayores de 28 años.',
                        'url_documento' => 'https://colombiacheck.com/chequeos/verdadero-eliminan-limite-de-edad-gratuidad-universitaria',
                    ],
                ],
                'nota_ciudadana' => 'A mis 45 años pude inscribirme en la Universidad pública sin que me exigieran límite de edad. Es una oportunidad real para quienes no pudimos estudiar antes.',
                'nota_calif'     => 9.9,
                'accion'         => 'guardado',
                'fecha'          => Carbon::now()->subDays(10),
            ],
            [
                'texto'        => 'El Ministerio de Vivienda anunció que todos los subsidios de "Mi Casa Ya" quedan suspendidos indefinidamente por falta de presupuesto en el Fondo Nacional de Vivienda.',
                'tipo_entrada' => 'TEXTO',
                'url_origen'   => 'https://noticias-cadena.com/mi-casa-ya-suspendido',
                'categoria'    => 'Finanzas y Economía',
                'veredicto'    => 'FALSO',
                'confianza'    => 96.0,
                'explicacion'  => 'Es falso. El Ministerio de Vivienda y Camacol confirmaron que la asignación de cupos para Mi Casa Ya continúa en marcha para hogares clasificados entre los subgrupos A1 y D20 del Sisbén IV, con desembolsos semanales respaldados por el presupuesto general.',
                'fuentes'      => [
                    [
                        'nombre'        => 'La Silla Vacía',
                        'peso'          => 0.92,
                        'cita'          => 'El programa Mi Casa Ya mantiene su operación con más de 50.000 subsidios anuales asignados a través de entidades financieras y cooperativas.',
                        'url_documento' => 'https://lasillavacia.com/detector-de-mentiras/falso-suspension-subsidios-mi-casa-ya/',
                    ],
                ],
                'nota_ciudadana' => 'Esta semana le asignaron el subsidio a mi hermana para su apartamento en Soacha. El trámite se hace directo con el banco donde se solicita el crédito.',
                'nota_calif'     => 9.5,
                'accion'         => 'verificado',
                'fecha'          => Carbon::now()->subDays(11),
            ],
            [
                'texto'        => 'Las antenas de telefonía 5G instaladas en Colombia causan interferencias letales en marcapasos y equipos médicos en hospitales.',
                'tipo_entrada' => 'TEXTO',
                'url_origen'   => 'https://cadena-telegram.com/mitos-antenas-5g-salud',
                'categoria'    => 'Salud y Ciencia',
                'veredicto'    => 'FALSO',
                'confianza'    => 99.5,
                'explicacion'  => 'Es falso. Es un mito tecnológico recurrente. La Organización Mundial de la Salud (OMS), la Agencia Nacional del Espectro (ANE) y la Comisión de Regulación de Comunicaciones (CRC) han demostrado mediante rigurosos estudios que las frecuencias no ionizantes de 3.5 GHz utilizadas por el 5G no interfieren con dispositivos biomédicos ni representan riesgo para la salud humana.',
                'fuentes'      => [
                    [
                        'nombre'        => 'ColombiaCheck',
                        'peso'          => 0.99,
                        'cita'          => 'Los límites de exposición a campos electromagnéticos fijados por la ANE cumplen con los estándares internacionales de la ICNIRP y no interfieren en equipos biomédicos.',
                        'url_documento' => 'https://colombiacheck.com/chequeos/falso-antenas-5g-interferencias-medicas-hospitales',
                    ],
                ],
                'nota_ciudadana' => 'Los hospitales operan con redes inalámbricas y equipos blindados certificados. Es la misma teoría de conspiración que inventaron con el 4G y el 3G.',
                'nota_calif'     => 9.7,
                'accion'         => 'verificado',
                'fecha'          => Carbon::now()->subDays(12),
            ],
        ];

        $contadorInsertados = 0;

        foreach ($noticias as $item) {
            DB::transaction(function () use ($item, $idUsuario, &$contadorInsertados) {
                // 1. Contenido
                $contenido = Contenido::updateOrCreate(
                    [
                        'contenido_original' => $item['texto'],
                    ],
                    [
                        'id_usuario'   => $idUsuario,
                        'tipo_entrada' => $item['tipo_entrada'],
                        'url_origen'   => $item['url_origen'],
                        'estado'       => 'VERIFICADO',
                        'fecha_subida' => $item['fecha'],
                    ]
                );

                // 2. Proceso de Verificación
                $proceso = ProcesoVerificacion::updateOrCreate(
                    [
                        'id_contenido' => $contenido->id_contenido,
                    ],
                    [
                        'score_confiabilidad' => $item['confianza'],
                        'analisis_lenguaje'   => [
                            'resumen_afirmacion' => Str::limit($item['texto'], 120),
                            'categoria_detectada' => $item['categoria'],
                            'polaridad'           => 'neutral',
                            'idioma_detectado'    => 'es',
                        ],
                        'contraste_temas'     => [
                            'entidades_clave' => ['Colombia', $item['categoria']],
                            'fuentes_consultadas' => count($item['fuentes']),
                        ],
                        'fecha_analisis'      => $item['fecha'],
                    ]
                );

                // 3. Veredicto
                $veredicto = Veredicto::updateOrCreate(
                    [
                        'id_contenido' => $contenido->id_contenido,
                    ],
                    [
                        'id_proceso'          => $proceso->id_proceso,
                        'tipo_veredicto'      => $item['veredicto'],
                        'nivel_confiabilidad' => $item['confianza'],
                        'explicacion'         => $item['explicacion'],
                        'fecha_emision'       => $item['fecha'],
                    ]
                );

                // 4. Fuentes asociadas y documentos semánticos
                foreach ($item['fuentes'] as $fData) {
                    $fuente = Fuente::where('nombre', 'LIKE', '%' . $fData['nombre'] . '%')->first();
                    if ($fuente) {
                        // Pivote veredicto_fuentes
                        DB::table('veredicto_fuentes')->updateOrInsert(
                            [
                                'id_veredicto' => $veredicto->id_veredicto,
                                'id_fuente'    => $fuente->id_fuente,
                            ],
                            [
                                'peso_en_veredicto' => $fData['peso'],
                                'cita_contraste'    => $fData['cita'],
                            ]
                        );

                        // Documento indexado para el motor de búsqueda semántica
                        Documento::updateOrCreate(
                            [
                                'url_articulo' => $fData['url_documento'],
                            ],
                            [
                                'fuente_id'         => $fuente->id_fuente,
                                'categoria'         => $item['categoria'],
                                'titulo'            => Str::limit($item['texto'], 200),
                                'fragmento'         => $fData['cita'],
                                'fecha_publicacion' => $item['fecha'],
                            ]
                        );
                    }
                }

                // 5. Nota Comunitaria (CU01 / CU02)
                if ($idUsuario && !empty($item['nota_ciudadana'])) {
                    $driver = DB::connection()->getDriverName();
                    $calif = $driver === 'pgsql'
                        ? (float) min(5.0, max(0.0, round($item['nota_calif'] / 2.0, 1)))
                        : (float) $item['nota_calif'];

                    Nota::updateOrCreate(
                        [
                            'id_usuario'   => $idUsuario,
                            'id_contenido' => $contenido->id_contenido,
                        ],
                        [
                            'contenido_nota' => $item['nota_ciudadana'],
                            'calificacion'   => $calif,
                            'fecha_creacion' => $item['fecha'],
                        ]
                    );
                }

                // 6. Historial de Usuario (CU04)
                if ($idUsuario) {
                    Historial::updateOrCreate(
                        [
                            'id_usuario'   => $idUsuario,
                            'id_contenido' => $contenido->id_contenido,
                            'accion'       => $item['accion'],
                        ],
                        [
                            'fecha_evento' => $item['fecha'],
                        ]
                    );
                }

                $contadorInsertados++;
            });
        }

        $this->command->info("✅ Se insertaron/actualizaron exitosamente {$contadorInsertados} noticias recientes de Colombia con sus veredictos, fuentes, notas e historial.");
    }
}
