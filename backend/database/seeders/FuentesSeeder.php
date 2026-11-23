<?php

namespace Database\Seeders;

use App\Models\Fuente;
use Illuminate\Database\Seeder;

/**
 * FuentesSeeder — Grafo de Credibilidad Completo de DAGAZ (80 Fuentes)
 *
 * TSK-3.1 (Sprint 3 / REQ-14): Ampliación a 40 fuentes (Prensa Regional N2 y Nacional N3).
 * TSK-4.1 (Sprint 4 / REQ-20): Ampliación a 80 fuentes (Verificadores certificados IFCN y Nivel 1).
 *
 * Pobla la tabla `fuentes` de la BD raiden con las 80 fuentes categorizadas
 * respetando el CHECK constraint de MariaDB:
 *   OFICIAL       → Agencias internacionales, organismos multilaterales y entidades de control
 *   PERIODISTICO  → Medios de prensa nacional/regional y fact-checkers certificados IFCN
 *   ACADEMICO     → Institutos de investigación y centros de pensamiento
 *   RED_SOCIAL    → Redes sociales
 *   OTRO          → Otras fuentes
 *
 * Utiliza `updateOrCreate` por `nombre` para ser estrictamente idempotente.
 */
class FuentesSeeder extends Seeder
{
    public function run(): void
    {
        $fuentes = [
            // =================================================================
            // NIVEL 1: Agencias Internacionales y Multilaterales (20 Fuentes)
            // =================================================================
            [
                'nombre'              => 'Reuters',
                'url_sitio'           => 'https://www.reuters.com',
                'tipo_fuente'         => 'OFICIAL',
                'indice_credibilidad' => 98.50,
            ],
            [
                'nombre'              => 'Associated Press (AP)',
                'url_sitio'           => 'https://apnews.com',
                'tipo_fuente'         => 'OFICIAL',
                'indice_credibilidad' => 98.00,
            ],
            [
                'nombre'              => 'Agence France-Presse (AFP)',
                'url_sitio'           => 'https://www.afp.com',
                'tipo_fuente'         => 'OFICIAL',
                'indice_credibilidad' => 97.50,
            ],
            [
                'nombre'              => 'BBC News Mundo',
                'url_sitio'           => 'https://www.bbc.com/mundo',
                'tipo_fuente'         => 'OFICIAL',
                'indice_credibilidad' => 96.00,
            ],
            [
                'nombre'              => 'Organización Mundial de la Salud (OMS)',
                'url_sitio'           => 'https://www.who.int/es',
                'tipo_fuente'         => 'OFICIAL',
                'indice_credibilidad' => 99.00,
            ],
            [
                'nombre'              => 'Organización de las Naciones Unidas (ONU)',
                'url_sitio'           => 'https://www.un.org/es',
                'tipo_fuente'         => 'OFICIAL',
                'indice_credibilidad' => 98.00,
            ],
            [
                'nombre'              => 'Agencia EFE',
                'url_sitio'           => 'https://efe.com',
                'tipo_fuente'         => 'OFICIAL',
                'indice_credibilidad' => 95.00,
            ],
            [
                'nombre'              => 'The Guardian',
                'url_sitio'           => 'https://www.theguardian.com',
                'tipo_fuente'         => 'OFICIAL',
                'indice_credibilidad' => 94.00,
            ],
            [
                'nombre'              => 'El País (España)',
                'url_sitio'           => 'https://elpais.com',
                'tipo_fuente'         => 'OFICIAL',
                'indice_credibilidad' => 93.00,
            ],
            [
                'nombre'              => 'Deutsche Welle (DW Español)',
                'url_sitio'           => 'https://www.dw.com/es',
                'tipo_fuente'         => 'OFICIAL',
                'indice_credibilidad' => 95.00,
            ],
            [
                'nombre'              => 'CNN en Español',
                'url_sitio'           => 'https://cnnespanol.cnn.com',
                'tipo_fuente'         => 'OFICIAL',
                'indice_credibilidad' => 91.00,
            ],
            [
                'nombre'              => 'Organización Panamericana de la Salud (OPS/PAHO)',
                'url_sitio'           => 'https://www.paho.org/es',
                'tipo_fuente'         => 'OFICIAL',
                'indice_credibilidad' => 98.50,
            ],
            [
                'nombre'              => 'UNESCO',
                'url_sitio'           => 'https://www.unesco.org/es',
                'tipo_fuente'         => 'OFICIAL',
                'indice_credibilidad' => 97.00,
            ],
            [
                'nombre'              => 'Organización para la Cooperación y el Desarrollo Económicos (OCDE)',
                'url_sitio'           => 'https://www.oecd.org/es',
                'tipo_fuente'         => 'OFICIAL',
                'indice_credibilidad' => 96.50,
            ],
            [
                'nombre'              => 'The New York Times',
                'url_sitio'           => 'https://www.nytimes.com/es',
                'tipo_fuente'         => 'OFICIAL',
                'indice_credibilidad' => 94.50,
            ],
            [
                'nombre'              => 'The Washington Post',
                'url_sitio'           => 'https://www.washingtonpost.com',
                'tipo_fuente'         => 'OFICIAL',
                'indice_credibilidad' => 93.50,
            ],
            [
                'nombre'              => 'Financial Times',
                'url_sitio'           => 'https://www.ft.com',
                'tipo_fuente'         => 'OFICIAL',
                'indice_credibilidad' => 95.50,
            ],
            [
                'nombre'              => 'Bloomberg News',
                'url_sitio'           => 'https://www.bloomberg.com/latam',
                'tipo_fuente'         => 'OFICIAL',
                'indice_credibilidad' => 96.00,
            ],
            [
                'nombre'              => 'France 24 Español',
                'url_sitio'           => 'https://www.france24.com/es',
                'tipo_fuente'         => 'OFICIAL',
                'indice_credibilidad' => 94.00,
            ],
            [
                'nombre'              => 'Euronews Español',
                'url_sitio'           => 'https://es.euronews.com',
                'tipo_fuente'         => 'OFICIAL',
                'indice_credibilidad' => 92.50,
            ],

            // =================================================================
            // NIVEL 2: Prensa Regional Colombia (20 Fuentes — REQ-14 / TSK-3.1)
            // =================================================================
            [
                'nombre'              => 'El Colombiano (Antioquia / Medellín)',
                'url_sitio'           => 'https://www.elcolombiano.com',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 85.00,
            ],
            [
                'nombre'              => 'El Heraldo (Caribe / Barranquilla)',
                'url_sitio'           => 'https://www.elheraldo.co',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 84.00,
            ],
            [
                'nombre'              => 'Vanguardia (Santander / Bucaramanga)',
                'url_sitio'           => 'https://www.vanguardia.com',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 83.50,
            ],
            [
                'nombre'              => 'El País (Cali / Valle del Cauca)',
                'url_sitio'           => 'https://www.elpais.com.co',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 84.50,
            ],
            [
                'nombre'              => 'La Patria (Manizales / Caldas)',
                'url_sitio'           => 'https://www.lapatria.com',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 82.00,
            ],
            [
                'nombre'              => 'El Universal (Cartagena / Bolívar)',
                'url_sitio'           => 'https://www.eluniversal.com.co',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 83.00,
            ],
            [
                'nombre'              => 'La Opinión (Cúcuta / Norte de Santander)',
                'url_sitio'           => 'https://www.laopinion.com.co',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 81.50,
            ],
            [
                'nombre'              => 'El Diario (Pereira / Risaralda)',
                'url_sitio'           => 'https://www.eldiario.com.co',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 81.00,
            ],
            [
                'nombre'              => 'El Nuevo Día (Tolima / Ibagué)',
                'url_sitio'           => 'https://www.elnuevodia.com.co',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 80.50,
            ],
            [
                'nombre'              => 'Periódico 15 (Bucaramanga / UNAB)',
                'url_sitio'           => 'https://periodico15.com',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 82.50,
            ],
            [
                'nombre'              => 'El Pilón (Valledupar / Cesar)',
                'url_sitio'           => 'https://elpilon.com.co',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 79.50,
            ],
            [
                'nombre'              => 'Hoy Diario del Magdalena (Santa Marta)',
                'url_sitio'           => 'https://www.hoydiariodelmagdalena.com.co',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 79.00,
            ],
            [
                'nombre'              => 'La Crónica del Quindío (Armenia)',
                'url_sitio'           => 'https://www.cronicadelquindio.com',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 80.00,
            ],
            [
                'nombre'              => 'Diario del Huila (Neiva)',
                'url_sitio'           => 'https://diariodelhuila.com',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 78.50,
            ],
            [
                'nombre'              => 'Diario del Sur (Pasto / Nariño)',
                'url_sitio'           => 'https://diariodelsur.com.co',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 78.00,
            ],
            [
                'nombre'              => 'El Liberal (Popayán / Cauca)',
                'url_sitio'           => 'https://elliberal.com.co',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 77.50,
            ],
            [
                'nombre'              => 'El Magazín del Pacífico (Buenaventura / Chocó)',
                'url_sitio'           => 'https://elmagazindelpacifico.com',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 76.50,
            ],
            [
                'nombre'              => 'La Voz de las Islas (San Andrés y Providencia)',
                'url_sitio'           => 'https://lavozdelasislas.com.co',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 77.00,
            ],
            [
                'nombre'              => 'La Nación (Huila)',
                'url_sitio'           => 'https://lanacion.com.co',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 78.00,
            ],
            [
                'nombre'              => 'Periódico La Tarde (Eje Cafetero)',
                'url_sitio'           => 'https://www.latarde.com.co',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 79.00,
            ],

            // =================================================================
            // NIVEL 3: Prensa Nacional Colombia (20 Fuentes — REQ-14 / TSK-3.1)
            // =================================================================
            [
                'nombre'              => 'El Espectador',
                'url_sitio'           => 'https://www.elespectador.com',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 89.00,
            ],
            [
                'nombre'              => 'El Tiempo',
                'url_sitio'           => 'https://www.eltiempo.com',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 88.50,
            ],
            [
                'nombre'              => 'Semana',
                'url_sitio'           => 'https://www.semana.com',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 82.00,
            ],
            [
                'nombre'              => 'Portafolio',
                'url_sitio'           => 'https://www.portafolio.co',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 87.00,
            ],
            [
                'nombre'              => 'La República',
                'url_sitio'           => 'https://www.larepublica.co',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 86.50,
            ],
            [
                'nombre'              => 'Noticias Caracol',
                'url_sitio'           => 'https://noticias.caracoltv.com',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 85.00,
            ],
            [
                'nombre'              => 'Noticias RCN',
                'url_sitio'           => 'https://www.noticiasrcn.com',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 82.50,
            ],
            [
                'nombre'              => 'W Radio Colombia',
                'url_sitio'           => 'https://www.wradio.com.co',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 84.00,
            ],
            [
                'nombre'              => 'Caracol Radio',
                'url_sitio'           => 'https://caracol.com.co',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 85.50,
            ],
            [
                'nombre'              => 'Blu Radio',
                'url_sitio'           => 'https://www.bluradio.com',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 83.50,
            ],
            [
                'nombre'              => 'RTVC Noticias (Pública)',
                'url_sitio'           => 'https://www.rtvcnoticias.com',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 86.00,
            ],
            [
                'nombre'              => 'Radio Nacional de Colombia',
                'url_sitio'           => 'https://www.radionacional.co',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 87.50,
            ],
            [
                'nombre'              => 'Cuestión Pública (Investigación)',
                'url_sitio'           => 'https://cuestionpublica.com',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 88.00,
            ],
            [
                'nombre'              => 'Vorágine (Investigación)',
                'url_sitio'           => 'https://voragine.co',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 87.50,
            ],
            [
                'nombre'              => 'Rutas del Conflicto',
                'url_sitio'           => 'https://rutasdelconflicto.com',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 89.00,
            ],
            [
                'nombre'              => 'Consejo de Redacción (CdR Colombia)',
                'url_sitio'           => 'https://consejoderedaccion.org',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 90.00,
            ],
            [
                'nombre'              => 'Cambio Colombia',
                'url_sitio'           => 'https://cambiocolombia.com',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 85.00,
            ],
            [
                'nombre'              => 'El Nuevo Siglo',
                'url_sitio'           => 'https://www.elnuevosiglo.com.co',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 81.00,
            ],
            [
                'nombre'              => 'Noticentro 1 CM&',
                'url_sitio'           => 'https://noticias.canal1.com.co',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 84.50,
            ],
            [
                'nombre'              => 'Valora Analitik',
                'url_sitio'           => 'https://www.valoraanalitik.com',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 83.00,
            ],

            // =================================================================
            // NIVEL 4: Fact-Checkers Certificados IFCN y Control (20 Fuentes — REQ-20 / TSK-4.1)
            // =================================================================
            [
                'nombre'              => 'ColombiaCheck',
                'url_sitio'           => 'https://colombiacheck.com',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 97.50,
            ],
            [
                'nombre'              => 'La Silla Vacía (Detector de Mentiras)',
                'url_sitio'           => 'https://www.lasillavacia.com',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 96.00,
            ],
            [
                'nombre'              => 'AFP Factual Colombia',
                'url_sitio'           => 'https://factual.afp.com/es',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 96.50,
            ],
            [
                'nombre'              => 'Chequeado (IFCN Argentina / Latam)',
                'url_sitio'           => 'https://chequeado.com',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 97.00,
            ],
            [
                'nombre'              => 'Maldita.es (IFCN España)',
                'url_sitio'           => 'https://maldita.es',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 96.50,
            ],
            [
                'nombre'              => 'Newtral (IFCN España)',
                'url_sitio'           => 'https://www.newtral.es',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 96.00,
            ],
            [
                'nombre'              => 'FactCheck.org (IFCN / Annenberg USA)',
                'url_sitio'           => 'https://www.factcheck.org',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 98.00,
            ],
            [
                'nombre'              => 'PolitiFact (IFCN / Pulitzer USA)',
                'url_sitio'           => 'https://www.politifact.com',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 97.50,
            ],
            [
                'nombre'              => 'Snopes (IFCN USA)',
                'url_sitio'           => 'https://www.snopes.com',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 95.50,
            ],
            [
                'nombre'              => 'Animal Político (El Sabueso - IFCN México)',
                'url_sitio'           => 'https://animalpolitico.com/sabueso',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 95.00,
            ],
            [
                'nombre'              => 'OjoPúblico (OjoBiónico - IFCN Perú)',
                'url_sitio'           => 'https://ojo-publico.com',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 94.50,
            ],
            [
                'nombre'              => 'Mala Espina Check (IFCN Chile)',
                'url_sitio'           => 'https://malaespinacheck.cl',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 94.00,
            ],
            [
                'nombre'              => 'Agência Lupa (IFCN Brasil)',
                'url_sitio'           => 'https://lupa.uol.com.br',
                'tipo_fuente'         => 'PERIODISTICO',
                'indice_credibilidad' => 94.50,
            ],
            [
                'nombre'              => 'Registraduría Nacional del Estado Civil (Colombia)',
                'url_sitio'           => 'https://www.registraduria.gov.co',
                'tipo_fuente'         => 'OFICIAL',
                'indice_credibilidad' => 98.00,
            ],
            [
                'nombre'              => 'Consejo Nacional Electoral (CNE Colombia)',
                'url_sitio'           => 'https://www.cne.gov.co',
                'tipo_fuente'         => 'OFICIAL',
                'indice_credibilidad' => 96.50,
            ],
            [
                'nombre'              => 'DANE (Departamento Administrativo Nacional de Estadística)',
                'url_sitio'           => 'https://www.dane.gov.co',
                'tipo_fuente'         => 'OFICIAL',
                'indice_credibilidad' => 99.00,
            ],
            [
                'nombre'              => 'Banco de la República (Colombia)',
                'url_sitio'           => 'https://www.banrep.gov.co',
                'tipo_fuente'         => 'OFICIAL',
                'indice_credibilidad' => 99.50,
            ],
            [
                'nombre'              => 'Instituto Nacional de Salud (INS Colombia)',
                'url_sitio'           => 'https://www.ins.gov.co',
                'tipo_fuente'         => 'OFICIAL',
                'indice_credibilidad' => 98.50,
            ],
            [
                'nombre'              => 'Defensoría del Pueblo de Colombia',
                'url_sitio'           => 'https://www.defensoria.gov.co',
                'tipo_fuente'         => 'OFICIAL',
                'indice_credibilidad' => 95.50,
            ],
            [
                'nombre'              => 'Instituto de Ciencia Política Hernán Echavarría Olózaga',
                'url_sitio'           => 'https://icpcolombia.org',
                'tipo_fuente'         => 'ACADEMICO',
                'indice_credibilidad' => 91.00,
            ],
        ];

        $creadas   = 0;
        $actualizadas = 0;

        foreach ($fuentes as $datos) {
            $resultado = Fuente::updateOrCreate(
                ['nombre' => $datos['nombre']],
                [
                    'url_sitio'           => $datos['url_sitio'],
                    'tipo_fuente'         => $datos['tipo_fuente'],
                    'indice_credibilidad' => $datos['indice_credibilidad'],
                ]
            );

            if ($resultado->wasRecentlyCreated) {
                $creadas++;
            } else {
                $actualizadas++;
            }
        }

        $totalEnBd = Fuente::count();

        if (isset($this->command)) {
            $this->command->info("Grafo de Credibilidad DAGAZ: {$creadas} creadas, {$actualizadas} actualizadas. Total fuentes en BD: {$totalEnBd}.");
        }
    }
}
