-- =====================================================================
-- DAGAZ — SCRIPT DDL OFICIAL POSTGRESQL 18 + pgvector
-- Entorno: Neon.tech Free Tier (0.5 GB almacenamiento / 2 CU autoscaling)
-- Estrategia Vectorial: Embeddings 384 dimensiones (all-MiniLM-L6-v2)
-- Búsqueda: KNN exacta sin índices vectoriales (evita consumo excesivo de RAM)
-- Catálogo Oficial: ~80 fuentes del Grafo de Credibilidad (REQ-20)
-- =====================================================================

-- 0. HABILITAR EXTENSIÓN VECTORIAL
CREATE EXTENSION IF NOT EXISTS vector;

-- =====================================================================
-- LIMPIEZA PREVIA (Orden inverso por integridad referencial)
-- =====================================================================
DROP TABLE IF EXISTS veredicto_fuentes CASCADE;
DROP TABLE IF EXISTS logs_comunicador_api CASCADE;
DROP TABLE IF EXISTS veredictos CASCADE;
DROP TABLE IF EXISTS procesos_verificacion CASCADE;
DROP TABLE IF EXISTS notas CASCADE;
DROP TABLE IF EXISTS historial CASCADE;
DROP TABLE IF EXISTS documentos CASCADE;
DROP TABLE IF EXISTS contenidos CASCADE;
DROP TABLE IF EXISTS fuentes CASCADE;
DROP TABLE IF EXISTS usuarios CASCADE;

-- =====================================================================
-- 1. TABLA USUARIOS
-- =====================================================================
CREATE TABLE usuarios (
    id_usuario     UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email          VARCHAR(255) NOT NULL,
    password_hash  VARCHAR(255) NOT NULL,
    rol            TEXT NOT NULL DEFAULT 'adulto'
                   CHECK (rol IN ('adulto', 'admin', 'editor')),
    fecha_creacion TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT uk_usuarios_email UNIQUE (email)
);

-- =====================================================================
-- 2. TABLA FUENTES (Catálogo del Grafo de Credibilidad: 80 fuentes objetivo)
-- =====================================================================
CREATE TABLE fuentes (
    id_fuente           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    nombre              VARCHAR(200) NOT NULL,
    url_sitio           VARCHAR(2048) NOT NULL,
    tipo_fuente         TEXT NOT NULL DEFAULT 'nacional_n3'
                        CHECK (tipo_fuente IN (
                            'internacional_n1', 'regional_n2',
                            'nacional_n3', 'factchecker_n4', 'otro'
                        )),
    indice_credibilidad DECIMAL(5,2) NOT NULL DEFAULT 50.00
                        CHECK (indice_credibilidad BETWEEN 0 AND 100),
    fecha_registro      TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT uk_fuentes_nombre UNIQUE (nombre)
);

-- =====================================================================
-- 3. TABLA DOCUMENTOS (★ BÚSQUEDA SEMÁNTICA VECTORIAL MULTI-FUENTE)
-- Almacena fragmentos/noticias de las 80 fuentes con embeddings vectoriales.
-- NO crear índices HNSW/IVFFlat: KNN exacto en SSD para cuidar los 2 GB RAM.
-- =====================================================================
CREATE TABLE documentos (
    id_documento      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    fuente_id         UUID NOT NULL REFERENCES fuentes(id_fuente)
                      ON DELETE RESTRICT,
    categoria         VARCHAR(100) NOT NULL DEFAULT 'Gobierno y Política',
    titulo            VARCHAR(500) NOT NULL,
    fragmento         VARCHAR(2000) NOT NULL,
    url_articulo      VARCHAR(2048) NOT NULL,
    embedding         vector(384),
    fecha_publicacion TIMESTAMPTZ,
    fecha_indexacion   TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT uk_documentos_url UNIQUE (url_articulo)
);

-- Índices B-Tree para pre-filtrado relacional (por fuente, categoría y fecha)
CREATE INDEX idx_documentos_fuente    ON documentos(fuente_id);
CREATE INDEX idx_documentos_categoria ON documentos(categoria);
CREATE INDEX idx_documentos_fecha     ON documentos(fecha_publicacion DESC NULLS LAST);

COMMENT ON TABLE documentos IS
    'Artículos indexados de las 80 fuentes con embeddings de 384 dimensiones. '
    'Sin índices HNSW/IVFFlat por restricción de RAM en Neon Free Tier.';

-- =====================================================================
-- 4. TABLA CONTENIDOS (Entradas enviadas por los usuarios para revisión)
-- =====================================================================
CREATE TABLE contenidos (
    id_contenido       UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    id_usuario         UUID REFERENCES usuarios(id_usuario)
                       ON DELETE SET NULL,
    tipo_entrada       TEXT NOT NULL DEFAULT 'texto'
                       CHECK (tipo_entrada IN (
                           'texto', 'url', 'imagen', 'audio', 'video'
                       )),
    contenido_original TEXT NOT NULL,
    url_origen         VARCHAR(2048),
    estado             TEXT NOT NULL DEFAULT 'pendiente'
                       CHECK (estado IN (
                           'pendiente', 'procesando', 'completado', 'error'
                       )),
    fecha_subida       TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_contenidos_usuario ON contenidos(id_usuario);
CREATE INDEX idx_contenidos_estado  ON contenidos(estado);

-- =====================================================================
-- 5. TABLA PROCESOS_VERIFICACION (Telemetría y análisis semántico IA)
-- =====================================================================
CREATE TABLE procesos_verificacion (
    id_proceso          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    id_contenido        UUID NOT NULL REFERENCES contenidos(id_contenido)
                        ON DELETE CASCADE,
    score_confiabilidad DECIMAL(5,2) NOT NULL DEFAULT 0.00
                        CHECK (score_confiabilidad BETWEEN 0 AND 100),
    analisis_lenguaje   JSONB,
    contraste_temas     JSONB,
    fecha_analisis      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_procesos_contenido ON procesos_verificacion(id_contenido);

-- =====================================================================
-- 6. TABLA VEREDICTOS (Resultado formal emitido para el usuario)
-- =====================================================================
CREATE TABLE veredictos (
    id_veredicto        UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    id_contenido        UUID NOT NULL REFERENCES contenidos(id_contenido)
                        ON DELETE CASCADE,
    id_proceso          UUID NOT NULL REFERENCES procesos_verificacion(id_proceso)
                        ON DELETE CASCADE,
    tipo_veredicto      TEXT NOT NULL
                        CHECK (tipo_veredicto IN (
                            'verdadero', 'falso', 'engañoso',
                            'parcial', 'no_verificable'
                        )),
    nivel_confiabilidad DECIMAL(5,2) NOT NULL DEFAULT 0.00
                        CHECK (nivel_confiabilidad BETWEEN 0 AND 100),
    explicacion         TEXT NOT NULL,
    motivo_breve        TEXT,
    fecha_emision       TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    -- Máximo un veredicto formal vigente por contenido
    CONSTRAINT uk_veredictos_contenido UNIQUE (id_contenido)
);

CREATE INDEX idx_veredictos_tipo ON veredictos(tipo_veredicto);

-- =====================================================================
-- 7. TABLA VEREDICTO_FUENTES (Trazabilidad y procedencia N:M)
-- =====================================================================
CREATE TABLE veredicto_fuentes (
    id_veredicto      UUID NOT NULL REFERENCES veredictos(id_veredicto)
                      ON DELETE CASCADE,
    id_fuente         UUID NOT NULL REFERENCES fuentes(id_fuente)
                      ON DELETE RESTRICT,
    peso_en_veredicto DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    cita_contraste    TEXT,

    PRIMARY KEY (id_veredicto, id_fuente)
);

-- =====================================================================
-- 8. TABLA NOTAS (Notas comunitarias colaborativas)
-- =====================================================================
CREATE TABLE notas (
    id_nota        UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    id_usuario     UUID NOT NULL REFERENCES usuarios(id_usuario)
                   ON DELETE CASCADE,
    id_contenido   UUID NOT NULL REFERENCES contenidos(id_contenido)
                   ON DELETE CASCADE,
    contenido_nota TEXT NOT NULL,
    calificacion   DECIMAL(3,1) NOT NULL DEFAULT 0.0
                   CHECK (calificacion BETWEEN -1.0 AND 5.0),
    fecha_creacion TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_notas_contenido ON notas(id_contenido);
CREATE INDEX idx_notas_usuario   ON notas(id_usuario);

-- =====================================================================
-- 9. TABLA HISTORIAL (Registro cronológico de actividad del usuario)
-- =====================================================================
CREATE TABLE historial (
    id_historial  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    id_usuario    UUID NOT NULL REFERENCES usuarios(id_usuario)
                  ON DELETE CASCADE,
    id_contenido  UUID NOT NULL REFERENCES contenidos(id_contenido)
                  ON DELETE CASCADE,
    accion        VARCHAR(100) NOT NULL,
    fecha_evento  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_historial_usuario_fecha ON historial(id_usuario, fecha_evento DESC);

-- =====================================================================
-- 10. TABLA LOGS_COMUNICADOR_API (Auditoría técnica de consumo de APIs)
-- =====================================================================
CREATE TABLE logs_comunicador_api (
    id_log           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    id_proceso       UUID REFERENCES procesos_verificacion(id_proceso)
                     ON DELETE SET NULL,
    servicio         VARCHAR(100) NOT NULL,
    tipo_operacion   VARCHAR(50) NOT NULL,
    codigo_respuesta INT,
    detalle_error    TEXT,
    fecha_registro   TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_logs_proceso ON logs_comunicador_api(id_proceso);

-- =====================================================================
-- SEMILLAS: GRAFO DE CREDIBILIDAD OFICIAL (80 FUENTES - REQ-20)
-- =====================================================================
INSERT INTO fuentes (nombre, url_sitio, tipo_fuente, indice_credibilidad) VALUES
    -- Nivel 1: Internacionales y Multilaterales (15)
    ('Reuters', 'https://www.reuters.com', 'internacional_n1', 98.50),
    ('Associated Press (AP)', 'https://apnews.com', 'internacional_n1', 98.00),
    ('Agence France-Presse (AFP)', 'https://www.afp.com', 'internacional_n1', 97.50),
    ('BBC News Mundo', 'https://www.bbc.com/mundo', 'internacional_n1', 96.00),
    ('Agencia EFE', 'https://efe.com', 'internacional_n1', 95.00),
    ('Deutsche Welle (DW)', 'https://www.dw.com/es', 'internacional_n1', 94.00),
    ('The Guardian', 'https://www.theguardian.com', 'internacional_n1', 96.00),
    ('El País España', 'https://elpais.com', 'internacional_n1', 95.00),
    ('OPS / OMS Colombia', 'https://www.paho.org/es/colombia', 'internacional_n1', 98.50),
    ('ONU Colombia', 'https://colombia.un.org', 'internacional_n1', 97.00),
    ('Banco Mundial', 'https://www.bancomundial.org', 'internacional_n1', 96.50),
    ('CEPAL', 'https://www.cepal.org', 'internacional_n1', 95.50),
    ('UNICEF Colombia', 'https://www.unicef.org/colombia', 'internacional_n1', 97.50),
    ('OCDE', 'https://www.oecd.org', 'internacional_n1', 97.00),
    ('Fondo Monetario Internacional (FMI)', 'https://www.imf.org', 'internacional_n1', 96.00),

    -- Nivel 2: Prensa Regional Colombiana (25)
    ('El Colombiano (Antioquia)', 'https://www.elcolombiano.com', 'regional_n2', 82.00),
    ('El País Cali (Valle)', 'https://www.elpais.com.co', 'regional_n2', 80.00),
    ('El Heraldo (Caribe/Barranquilla)', 'https://www.elheraldo.co', 'regional_n2', 79.00),
    ('Vanguardia (Santander/Bucaramanga)', 'https://www.vanguardia.com', 'regional_n2', 78.00),
    ('El Universal (Cartagena/Bolívar)', 'https://www.eluniversal.com.co', 'regional_n2', 77.00),
    ('La Patria (Manizales/Caldas)', 'https://www.lapatria.com', 'regional_n2', 76.00),
    ('La Opinión (Cúcuta/Norte Santander)', 'https://www.laopinion.com.co', 'regional_n2', 75.00),
    ('El Nuevo Día (Tolima/Ibagué)', 'https://www.elnuevodia.com.co', 'regional_n2', 74.00),
    ('Diario del Huila (Neiva)', 'https://diariodelhuila.com', 'regional_n2', 73.00),
    ('El Meridiano (Córdoba/Sucre)', 'https://elmeridiano.co', 'regional_n2', 72.00),
    ('Diario del Sur (Pasto/Nariño)', 'https://diariodelsur.com.co', 'regional_n2', 73.00),
    ('El Pilón (Valledupar/Cesar)', 'https://elpilon.com.co', 'regional_n2', 74.00),
    ('El Diario (Pereira/Risaralda)', 'https://www.eldiario.com.co', 'regional_n2', 75.00),
    ('La Crónica del Quindío', 'https://www.cronicadelquindio.com', 'regional_n2', 74.00),
    ('Boyacá 7 Días', 'https://boyaca7dias.com.co', 'regional_n2', 75.00),
    ('Periódico del Meta (Llanos)', 'https://periodicodelmeta.com', 'regional_n2', 73.00),
    ('Hoy Diario del Magdalena (Santa Marta)', 'https://www.hoydiariodelmagdalena.com.co', 'regional_n2', 72.00),
    ('El Tabloide (Valle)', 'https://eltabloide.com.co', 'regional_n2', 71.00),
    ('Q''hubo Bogotá', 'https://qhubobogota.com', 'regional_n2', 70.00),
    ('Q''hubo Medellín', 'https://qhubomedellin.com', 'regional_n2', 70.00),
    ('Q''hubo Cali', 'https://qhubocali.com', 'regional_n2', 70.00),
    ('El Quindiano', 'https://www.elquindiano.com', 'regional_n2', 72.00),
    ('Periódico 15 (Bucaramanga)', 'https://periodico15.com', 'regional_n2', 73.00),
    ('La Voz del Chocó', 'https://lavozdelchoco.com', 'regional_n2', 71.00),
    ('Amazonas Real (Leticia)', 'https://amazonasreal.com', 'regional_n2', 70.00),

    -- Nivel 3: Prensa Nacional Colombiana (20)
    ('El Espectador', 'https://www.elespectador.com', 'nacional_n3', 88.00),
    ('El Tiempo', 'https://www.eltiempo.com', 'nacional_n3', 87.00),
    ('Portafolio', 'https://www.portafolio.co', 'nacional_n3', 86.00),
    ('Noticias Caracol', 'https://noticias.caracoltv.com', 'nacional_n3', 84.00),
    ('Noticias RCN', 'https://www.noticiasrcn.com', 'nacional_n3', 82.00),
    ('Caracol Radio', 'https://caracol.com.co', 'nacional_n3', 85.00),
    ('W Radio Colombia', 'https://www.wradio.com.co', 'nacional_n3', 85.00),
    ('Blu Radio', 'https://www.bluradio.com', 'nacional_n3', 83.00),
    ('Revista Cambio', 'https://cambiocolombia.com', 'nacional_n3', 84.00),
    ('Cuestión Pública', 'https://cuestionpublica.com', 'nacional_n3', 87.00),
    ('Vorágine', 'https://voragine.co', 'nacional_n3', 86.00),
    ('La República', 'https://www.larepublica.co', 'nacional_n3', 86.00),
    ('Valora Analitik', 'https://www.valoraanalitik.com', 'nacional_n3', 85.00),
    ('Red+ Noticias', 'https://redmas.com.co', 'nacional_n3', 80.00),
    ('Señal Colombia / RTVC Noticias', 'https://www.senalcolombia.tv', 'nacional_n3', 83.00),
    ('Revista Semana', 'https://www.semana.com', 'nacional_n3', 79.00),
    ('RCN Radio (Cadena)', 'https://www.rcnradio.com', 'nacional_n3', 82.00),
    ('La FM', 'https://www.lafm.com.co', 'nacional_n3', 81.00),
    ('Pulzo', 'https://www.pulzo.com', 'nacional_n3', 78.00),
    ('Las2Orillas', 'https://www.las2orillas.co', 'nacional_n3', 80.00),

    -- Nivel 4: Fact-Checkers e Instituciones Oficiales (20)
    ('ColombiaCheck', 'https://colombiacheck.com', 'factchecker_n4', 98.00),
    ('La Silla Vacía (Detector de Mentiras)', 'https://www.lasillavacia.com', 'factchecker_n4', 97.50),
    ('AFP Factual', 'https://factual.afp.com', 'factchecker_n4', 97.00),
    ('Chequeado', 'https://chequeado.com', 'factchecker_n4', 96.00),
    ('Maldita.es', 'https://maldita.es', 'factchecker_n4', 95.50),
    ('Newtral', 'https://www.newtral.es', 'factchecker_n4', 95.00),
    ('Animal Político (El Sabueso)', 'https://animalpolitico.com/sabueso', 'factchecker_n4', 95.00),
    ('OjoPúblico (Perú)', 'https://ojo-publico.com', 'factchecker_n4', 95.50),
    ('Salud con Lupa', 'https://saludconlupa.com', 'factchecker_n4', 96.00),
    ('Snopes', 'https://www.snopes.com', 'factchecker_n4', 96.50),
    ('DANE (Estadísticas Oficiales)', 'https://www.dane.gov.co', 'factchecker_n4', 99.00),
    ('Banco de la República de Colombia', 'https://www.banrep.gov.co', 'factchecker_n4', 99.50),
    ('Registraduría Nacional del Estado Civil', 'https://www.registraduria.gov.co', 'factchecker_n4', 98.00),
    ('Consejo Nacional Electoral (CNE)', 'https://www.cne.gov.co', 'factchecker_n4', 97.00),
    ('Instituto Nacional de Salud (INS)', 'https://www.ins.gov.co', 'factchecker_n4', 98.50),
    ('Defensoría del Pueblo', 'https://www.defensoria.gov.co', 'factchecker_n4', 97.50),
    ('Procuraduría General de la Nación', 'https://www.procuraduria.gov.co', 'factchecker_n4', 97.00),
    ('Contraloría General de la República', 'https://www.contraloria.gov.co', 'factchecker_n4', 97.50),
    ('Corte Constitucional de Colombia', 'https://www.corteconstitucional.gov.co', 'factchecker_n4', 99.00),
    ('MinCiencias', 'https://minciencias.gov.co', 'factchecker_n4', 96.50)
ON CONFLICT (nombre) DO UPDATE SET 
    url_sitio = EXCLUDED.url_sitio,
    tipo_fuente = EXCLUDED.tipo_fuente,
    indice_credibilidad = EXCLUDED.indice_credibilidad;
