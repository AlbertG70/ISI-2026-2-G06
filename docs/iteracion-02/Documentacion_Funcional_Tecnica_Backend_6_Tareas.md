# DAGAZ — Documentación Funcional y Técnica del Backend (6 Tareas + Evolución Multi-Agente)

> **Proyecto:** DAGAZ — Sistema Inteligente de Detección de Desinformación y Verificación Multicanal  
> **Estado Técnico:** MVP Completado + Bloque Evolutivo Post-MVP (`v1.4.1`, Noviembre 2026)  
> **Normativa y Estándares:** Ley 1581 de 2012 (Hábeas Data - Colombia), Estándares IFCN (International Fact-Checking Network), Directrices de Transparencia Algorítmica, OpenAPI 3.1.  
> **Stack Tecnológico:** PHP `^8.3` / `8.4` + **Laravel 13** (`^13.17`) + **PostgreSQL 18 + `pgvector` (Neon.tech Serverless SSL)** con compatibilidad MariaDB/SQLite + Python 3 (FastAPI / Uvicorn / Pillow / `pytesseract` / `SpeechRecognition`)  
> **Garantía de Calidad Vigente:** **142 pruebas automatizadas PHPUnit** (`27 Unit` con 94 aserciones + `115 Feature` con 2.228 aserciones = **2.322 aserciones**) + **4 pruebas Python `unittest`** (`169` pruebas totales incluyendo las **23 pruebas Vitest** de Frontend).

---

## 📑 Tabla de Contenidos

1. [Visión General de la Arquitectura Backend](#1-visión-general-de-la-arquitectura-backend)
2. [Tarea 1: Microservicios Python Multicanal de Extracción OCR y STT (TSK-2.3 & REQ-02)](#tarea-1-microservicios-python-multicanal-de-extracción-ocr-y-stt-tsk-23--req-02)
3. [Tarea 2: Conector Archive.org / Wayback Machine y Detección de Descontextualización (TSK-3.2 & REQ-10)](#tarea-2-conector-archiveorg--wayback-machine-y-detección-de-descontextualización-tsk-32--req-10)
4. [Tarea 3: Recuperación Interlingüe y Detección de Distorsión Cross-Lingual ES-EN (TSK-4.4 & REQ-19)](#tarea-3-recuperación-interlingüe-y-detección-de-distorsión-cross-lingual-es-en-tsk-44--req-19)
5. [Tarea 4: Grafo de Credibilidad de 80 Fuentes en 4 Niveles IFCN (TSK-3.1 & TSK-4.1 / REQ-14 & REQ-20)](#tarea-4-grafo-de-credibilidad-de-80-fuentes-en-4-niveles-ifcn-tsk-31--tsk-41--req-14--req-20)
6. [Tarea 5: Autenticación por API Key para Redacciones Socias (TSK-4.2 & REQ-16)](#tarea-5-autenticación-por-api-key-para-redacciones-socias-tsk-42--req-16)
7. [Tarea 6: Exportador de Auditoría para Reguladores y Medios (TSK-4.6 & REQ-25)](#tarea-6-exportador-de-auditoría-para-reguladores-y-medios-tsk-46--req-25)
8. [Tarea 7: Integración Frontend Móvil, Servidor Ligero y CORS (TSK-5.1 a TSK-5.8)](#tarea-7-integración-frontend-móvil-servidor-ligero-y-cors-tsk-51-a-tsk-58)
9. [Evolución Post-MVP (`v1.1.0`–`v1.4.1`): Consejo Multi-Modelo, OpenRouter y Normalización PostgreSQL](#9-evolución-post-mvp-v110v141-consejo-multi-modelo-openrouter-y-normalización-postgresql)
10. [Matriz de Cobertura de Pruebas Automatizadas](#10-matriz-de-cobertura-de-pruebas-automatizadas)
11. [Catálogo de Endpoints REST v1 Implementados](#11-catálogo-de-endpoints-rest-v1-implementados)

---

## 1. Visión General de la Arquitectura Backend

El backend de DAGAZ está estructurado bajo una arquitectura desacoplada y orientada a contratos en **Laravel 13**, integrando drivers agnósticos de IA, un Consejo Multi-Modelo paralelo, microservicios Python FastAPI y persistencia híbrida **PostgreSQL 18 (`pgvector` en Neon.tech)** / **MariaDB** / **SQLite**.

```text
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                                 CAPA DE CLIENTES Y CONSUMIDORES                        │
│  Web Blade/Vite (+40) │ mobile/ Capacitor 6 │ mobile-expo/ SDK 58 │ Redacciones M2M    │
└───────────────────────────────┬──────────────────────────────┬─────────────────────────┘
                                │ Bearer Sanctum / Público     │ X-API-KEY / Api-Key
                                ▼                              ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                              CAPA DE SEGURIDAD Y ROUTING                               │
│  - Middleware auth:sanctum (Tokens Bearer UUID en personal_access_tokens)              │
│  - Middleware EnsureValidApiKey ('api.key') con Rate Limiting ('throttle:partners')    │
│  - Middleware VerificarRol ('rol:admin,editor') + Config CORS desacoplada              │
└───────────────────────────────────────────┬────────────────────────────────────────────┘
                                            │
                                            ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                                  CONTROLADORES REST API                                │
│   AuthController  │  VerificacionController  │  PublicPartnerApiController │ Auditoria │
└───────────────────────────────────────────┬────────────────────────────────────────────┘
                                            │
               ┌────────────────────────────┴────────────────────────────┐
               ▼                                                         ▼
┌──────────────────────────────────────────────┐  ┌──────────────────────────────────────┐
│        SERVICIOS DE NEGOCIO (CORE)           │  │      SERVICIOS DE SOPORTE & IA       │
│ - VerificacionService (Pipeline 14 etapas)   │  │ - MultiModeloHarnessService (Pool)   │
│ - BusquedaSemanticaService (KNN + Léxico)    │  │ - AuditoriaExportService (JSON/CSV)  │
│ - ArchiveOrgService (Wayback / CDX)          │  │ - PartnerApiService (M2M Socios)     │
│ - ServicioInterlingue (ES <-> EN)            │  │ - IaDriverFactory / OpenAiDriver     │
│ - CircuitBreakerService (Costes/Tokens)      │  │   (OpenAI, OpenRouter, Gemini, Local)│
└──────────────────────┬───────────────────────┘  └──────────────────────────────────────┘
                       │
        ┌──────────────┴─────────────────────────┐
        ▼                                        ▼
┌─────────────────────────────────┐   ┌──────────────────────────────────────────────────┐
│   MICROSERVICIOS PYTHON (LOCAL) │   │          PERSISTENCIA DUAL POSTGRES / MARIADB    │
│ - Servicio OCR (FastAPI :5001)  │   │  Primaria Cloud: PostgreSQL 18 + pgvector (Neon) │
│   (Pillow + pytesseract)        │   │  Local Alternativa: MariaDB 10.4+ (`raiden`)     │
│ - Servicio STT (FastAPI :5002)  │   │  - 10 Tablas de dominio con UUIDs nativos +      │
│   (wave + SpeechRecognition)    │   │    personal_access_tokens (tokenable_id UUID)    │
└─────────────────────────────────┘   └──────────────────────────────────────────────────┘
```

---

## Tarea 1: Microservicios Python Multicanal de Extracción OCR y STT (TSK-2.3 & REQ-02)

### 1.1 Objetivo y Problema de Negocio
En Colombia, gran parte de la desinformación dirigida a adultos mayores de 40 años circula en WhatsApp, Facebook y Telegram mediante capturas de pantalla de titulares apócrifos (imágenes) y cadenas de notas de voz (audio). Estos microservicios extraen el texto previo al contraste semántico.

### 1.2 Arquitectura y Componentes Reales
- **Ubicación de Microservicios:** [`backend/Microservicios_Python/`](../../backend/Microservicios_Python/)
- **Dependencias declaradas ([`requirements.txt`](../../backend/Microservicios_Python/requirements.txt)):** `fastapi>=0.115.0`, `uvicorn[standard]>=0.30.0`, `pydantic>=2.8.0`, `python-multipart>=0.0.9`, `Pillow>=10.4.0`, `pytesseract>=0.3.10`, `SpeechRecognition>=3.10.4`, `requests>=2.32.0`, `httpx>=0.27.0`.
- **Microservicio OCR ([`ocr_service/app.py`](../../backend/Microservicios_Python/ocr_service/app.py)) — Puerto `5001`:**
  - **Pipeline real de procesamiento:**
    1. Recepción mediante archivo multipart (`POST /upload`) o payload JSON (`image_url`, `image_path` o `image_base64`).
    2. Preprocesamiento con **Pillow** (`Image.convert("L")` a escala de grises y ajuste de contraste mediante `ImageEnhance.Contrast`).
    3. Extracción óptica con **`pytesseract`** (`lang="spa+eng"`).
    4. Estrategia de respaldo defensivo: inspección de metadatos EXIF/comentarios embebidos o fallback descriptivo controlado si el binario Tesseract no está instalado en el sistema anfitrión.
  - **Rutas expuestas:** `GET /health`, `POST /ocr`, `POST /ocr/ocr`, `POST /`, `POST /upload`.
- **Microservicio STT ([`stt_service/app.py`](../../backend/Microservicios_Python/stt_service/app.py)) — Puerto `5002`:**
  - **Pipeline real de procesamiento:**
    1. Recepción de audio multipart (`POST /upload`) o payload JSON (`audio_url`, `audio_path` o `audio_base64`, con parámetro opcional `idioma`, por defecto `es-CO`).
    2. Inspección de metadatos WAV nativos con librería estándar `wave` (`duration_seconds`, `channels`, `sample_rate`).
    3. Transcripción mediante **`SpeechRecognition`** (`recognizer.recognize_google`) para archivos `.wav`, con manejo de silencios y fallback controlado (`dagaz_stt_fallback`) para formatos comprimidos o entornos sin conectividad externa.
  - **Rutas expuestas:** `GET /health`, `POST /transcribe`, `POST /stt`, `POST /stt/transcribe`, `POST /`, `POST /upload`.
- **Cliente Integrador en Laravel ([`AdaptadorMulticanal.php`](../../backend/app/Adapters/AdaptadorMulticanal.php)):**
  - Implementa [`AdaptadorMulticanalInterface`](../../backend/app/Contracts/AdaptadorMulticanalInterface.php), utilizando `OCR_SERVICE_URL` (`http://localhost:5001/ocr`) y `STT_SERVICE_URL` (`http://localhost:5002/stt`) configurados en [`backend/config/dagaz.php`](../../backend/config/dagaz.php).

### 1.3 Pruebas de Validación
- Archivo: [`backend/Microservicios_Python/tests/test_services.py`](../../backend/Microservicios_Python/tests/test_services.py)
- Comando oficial: `python -m unittest discover -s tests -v` (`4` pruebas unitarias/endpoints pasando al 100%).

---

## Tarea 2: Conector Archive.org / Wayback Machine y Detección de Descontextualización (TSK-3.2 & REQ-10)

### 2.1 Objetivo y Problema de Negocio
Un vector frecuente de desinformación en Colombia es la **descontextualización temporal**: reutilizar noticias, protestas o declaraciones reales ocurridas años atrás y difundirlas como hechos recientes.

### 2.2 Arquitectura y Componentes
- **Servicio Principal ([`ArchiveOrgService.php`](../../backend/app/Services/ArchiveOrgService.php)):**
  - Implementa [`ConectorArchiveInterface`](../../backend/app/Contracts/ConectorArchiveInterface.php).
  - **Métodos públicos implementados:**
    1. `consultarDisponibilidad(string $url, ?string $idProceso = null): array`: Consulta la Availability API de Wayback Machine (`config/dagaz.php` -> `archive_org.base_url`).
    2. `obtenerCapturasHistoricas(string $url, int $limite = 10, ?string $idProceso = null): array`: Recupera el índice cronológico de capturas vía CDX API (`archive_org.cdx_url`).
    3. `detectarDescontextualizacion(string $url, ?string $fechaReclamada = null, ?string $idProceso = null): array`: Evalúa la antigüedad frente al umbral configurable `archive_org.umbral_descontextualizacion_dias` (`ARCHIVE_ORG_UMBRAL_DIAS`, **365 días por defecto**), calculando el desfase temporal y auditando la consulta en `logs_comunicador_api`.

### 2.3 Endpoints REST Asociados
| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/v1/verificacion/archive/consultar` | Consulta snapshots históricos disponibles para una URL. |
| `POST` | `/api/v1/verificacion/archive/descontextualizacion` | Evalúa si una URL o contenido está descontextualizado temporalmente. |

### 2.4 Pruebas de Validación
- Archivo: [`tests/Feature/ArchiveOrgTest.php`](../../backend/tests/Feature/ArchiveOrgTest.php) (`10` pruebas pasando al 100%).

---

## Tarea 3: Recuperación Interlingüe y Detección de Distorsión Cross-Lingual ES-EN (TSK-4.4 & REQ-19)

### 3.1 Objetivo y Problema de Negocio
Numerosas narrativas desinformativas sobre salud, economía y política nacen en inglés y sufren alteraciones de sentido (omisión de condicionales, inserción de superlativos alarmistas o inversión de negaciones) al traducirse al español en cadenas colombianas.

### 3.2 Arquitectura y Componentes
- **Servicio Principal ([`ServicioInterlingue.php`](../../backend/app/Services/ServicioInterlingue.php)):**
  - Implementa [`ServicioInterlingueInterface`](../../backend/app/Contracts/ServicioInterlingueInterface.php).
  - **Métodos públicos implementados:**
    - `detectarIdioma(string $texto): array` — Identificación idiomática y detección de mezcla bilingüe ES/EN.
    - `traducir(string $texto, string $idiomaDestino = 'es', string $idiomaOrigen = 'auto'): array` — Traducción bidireccional vía adaptador IA activo o respaldo glosario local.
    - `generarConsultasInterlingues(string $afirmacionEs): array` — Generación de consultas en inglés para contraste con agencias Nivel 1 (Reuters, AP, BBC).
    - `analizarDistorsionTraduccion(string $textoOriginalEn, string $textoTraducidoEs): array` — Evalúa polaridad, superlativos añadidos e inversión de negaciones, retornando métricas cuantitativas de distorsión.

### 3.3 Endpoints REST Asociados
| Método | Ruta | Descripción |
|---|---|---|
| `POST` | `/api/v1/verificacion/interlingue/detectar` | Identifica el idioma del texto y nivel de confianza. |
| `POST` | `/api/v1/verificacion/interlingue/traducir` | Traduce textos bidireccionalmente (ES <-> EN). |
| `POST` | `/api/v1/verificacion/interlingue/distorsion` | Evalúa si una traducción contiene distorsiones semánticas o sesgo sensacionalista. |

### 3.4 Pruebas de Validación
- Archivo: [`tests/Feature/InterlingueTest.php`](../../backend/tests/Feature/InterlingueTest.php) (`12` pruebas pasando al 100%).

---

## Tarea 4: Grafo de Credibilidad de 80 Fuentes en 4 Niveles IFCN (TSK-3.1 & TSK-4.1 / REQ-14 & REQ-20)

### 4.1 Objetivo y Problema de Negocio
Todo veredicto requiere respaldo en un catálogo transparente, equilibrado y jerarquizado de fuentes confiables nacionales, regionales, internacionales y signatarias IFCN.

### 4.2 Jerarquía de Niveles del Grafo (`80` Fuentes en `FuentesSeeder.php`)
1. **Nivel 1 — Agencias Internacionales y Multilaterales (`20` fuentes):** Reuters, AP, AFP, BBC News Mundo, OMS, ONU, EFE, Deutsche Welle, OCDE, etc.
2. **Nivel 2 — Prensa Regional Colombiana (`20` fuentes):** El País (Cali), El Heraldo (Barranquilla), El Colombiano (Medellín), Vanguardia (Bucaramanga), La Patria, El Universal, La Opinión, etc.
3. **Nivel 3 — Prensa Nacional Colombiana (`20` fuentes):** El Espectador, El Tiempo, Noticias Caracol, Noticias RCN, W Radio, Blu Radio, Portafolio, Revista Cambio, etc.
4. **Nivel 4 — Fact-Checkers Certificados IFCN y Entidades Oficiales de Control (`20` fuentes):** ColombiaCheck, La Silla Vacía (Detector de Mentiras), AFP Factual Colombia, Chequeado, Maldita.es, Snopes, PolitiFact, FactCheck.org, Registraduría Nacional, CNE, DANE, Banco de la República, INS, Defensoría del Pueblo, etc.

### 4.3 Componentes Técnicos
- **Modelo Eloquent ([`Fuente.php`](../../backend/app/Models/Fuente.php)):** Métodos `getNivel()`, `getNivelEtiqueta()`, `getClasificacion()` y tipos estrictos definidos en [`config/dagaz.php`](../../backend/config/dagaz.php) (`OFICIAL`, `PERIODISTICO`, `ACADEMICO`, `RED_SOCIAL`, `OTRO`).
- **Seeder Idempotente ([`FuentesSeeder.php`](../../backend/database/seeders/FuentesSeeder.php)):** Inserción/actualización idempotente (`updateOrCreate` por `nombre`) de las **80 fuentes**.

### 4.4 Pruebas de Validación
- Archivo: [`tests/Feature/GrafoFuentesTest.php`](../../backend/tests/Feature/GrafoFuentesTest.php) (`11` pruebas pasando al 100%).

---

## Tarea 5: Autenticación por API Key para Redacciones Socias (TSK-4.2 & REQ-16)

### 5.1 Objetivo y Problema de Negocio
Facilitar a medios aliados (*El Espectador*, *ColombiaCheck*, *La Silla Vacía*) consumir DAGAZ de forma máquina a máquina (M2M) mediante credenciales aisladas y cuotas diferenciadas.

### 5.2 Arquitectura y Componentes
- **Configuración de Socios ([`config/dagaz.php`](../../backend/config/dagaz.php)):** Bloques `'partners.keys'` con cuotas diferenciadas (`rate_limit_minute`: 60, 90, 120 req/min) y permisos granulares (`verificar`, `consultar`, `fuentes`, `auditoria`).
- **Servicio de Partners ([`PartnerApiService.php`](../../backend/app/Services/PartnerApiService.php)):** Validación con comparación en tiempo constante (`hash_equals`), resolución de usuario editorial y registro en `logs_comunicador_api`.
- **Middleware de Seguridad ([`EnsureValidApiKey.php`](../../backend/app/Http/Middleware/EnsureValidApiKey.php)):** Soporta `X-API-KEY` o `Authorization: Api-Key <token>`; retorna `401` si falta clave o `403` si es inválida o carece de permiso; inyecta cabeceras `X-Partner-Id` y `X-Partner-Name`.
- **Controlador Público ([`PublicPartnerApiController.php`](../../backend/app/Http/Controllers/PublicPartnerApiController.php)):** Protegido con `['api.key', 'throttle:partners']`.

### 5.3 Endpoints REST de la API Pública (`/api/v1/public/*`)
| Método | Ruta | Permiso / Protección | Descripción |
|---|---|---:|---|
| `POST` | `/api/v1/public/verificar` | `verificar` | Ingesta y emisión de veredicto con citas de fuentes. |
| `GET` | `/api/v1/public/veredictos/{idContenido}` | `consultar` | Consulta de veredicto por UUID. |
| `GET` | `/api/v1/public/fuentes` | `fuentes` | Consulta filtrable del Grafo de Credibilidad (80 fuentes). |
| `GET` | `/api/v1/public/health` | Key activa | Estado operativo del servicio de socios. |

### 5.4 Pruebas de Validación
- Archivo: [`tests/Feature/PublicPartnerApiTest.php`](../../backend/tests/Feature/PublicPartnerApiTest.php) (`11` pruebas pasando al 100%).

---

## Tarea 6: Exportador de Auditoría para Reguladores y Medios (TSK-4.6 & REQ-25)

### 6.1 Formatos y Garantías Implementadas
1. **JSON Forense Integral con SHA-256 (`?formato=json`):** Incluye trazabilidad de `logs_comunicador_api`, ponderación de fuentes, notas e intervenciones editoriales más checksum `calcularChecksum()`.
2. **Esquema Schema.org `ClaimReview` JSON-LD (`?formato=json-ld` o `/claim-review/{idContenido}`):** Compatible con Google Fact Check Tools / IFCN mediante `generarClaimReview()`.
3. **CSV Tabular UTF-8 BOM (`?formato=csv`):** Generado por `generarCsvAuditable()` con saneamiento contra CSV Injection y compatibilidad directa con Excel.
4. **Exportación por Lotes (`generarLoteAuditoria`):** Filtrado por rango de fechas y tipo de veredicto.

### 6.2 Endpoints REST de Auditoría
| Método | Ruta | Autenticación | Salida |
|---|---|:---:|---|
| `GET` | `/api/v1/auditoria/veredictos/{idContenido}` | Sanctum (`admin`, `editor`) | JSON forense, ClaimReview JSON-LD o CSV (`?formato=`) |
| `GET` | `/api/v1/auditoria/expedientes` | Sanctum (`admin`, `editor`) | Lote masivo filtrable en JSON o CSV |
| `GET` | `/api/v1/auditoria/claim-review/{idContenido}` | Sanctum (`admin`, `editor`) | `application/ld+json` directo |
| `GET` | `/api/v1/public/auditoria/{idContenido}` | API Key (`auditoria`) | JSON forense o CSV para socios |
| `GET` | `/api/v1/public/claim-review/{idContenido}` | API Key (`auditoria`) | `application/ld+json` para socios |

### 6.3 Pruebas de Validación
- Archivo: [`tests/Feature/AuditoriaExportTest.php`](../../backend/tests/Feature/AuditoriaExportTest.php) (`14` pruebas pasando al 100%).

---

## Tarea 7: Integración Frontend Móvil, Servidor Ligero y CORS (TSK-5.1 a TSK-5.8)

- **Frontend Web Accesible (+40 WCAG 2.1 AA):** Vistas Blade en [`frontend/views/`](../../frontend/views/) (`verificacion/index.blade.php`, `verificacion/resultado.blade.php`, `fuentes/index.blade.php`, `historial/index.blade.php`, `perfil/index.blade.php`, `auth/`) empaquetadas con **Vite 8 + Tailwind CSS v4**, plus páginas estáticas de apoyo (`index.html`, `fuentes.html`, `resultado.html`, `acerca.html`) y manifiesto PWA.
- **Servidor Ligero & CORS:** [`backend/api_server.php`](../../backend/api_server.php) + [`backend/config/cors.php`](../../backend/config/cors.php).
- **Clientes Móviles Duales (`REQ-34`):** Contenedor híbrido **Capacitor 6** en [`mobile/`](../../mobile/) (`webDir: ../frontend/dist`) y app nativa **React Native / Expo SDK 58** en [`mobile-expo/`](../../mobile-expo/) con timeout de **120 segundos** en `api.js`.

---

## 9. Evolución Post-MVP (`v1.1.0`–`v1.4.1`): Consejo Multi-Modelo, OpenRouter y Normalización PostgreSQL

1. **Soporte Cloud Neon SSL y Sanctum UUID (`REQ-29`, `v1.1.0`):**
   - Migración `2026_09_24_214547_create_personal_access_tokens_table.php` adaptada con `uuidMorphs('tokenable')` para compatibilidad con IDs UUID de PostgreSQL 18 en Neon.tech (`DB_SSLMODE=require`), preservando compatibilidad MariaDB/SQLite.
2. **Proveedor Unificado OpenRouter & Parser de Razonamiento (`REQ-32`, `REQ-35`):**
   - [`OpenAiDriver.php`](../../backend/app/Adapters/Ia/OpenAiDriver.php) soporta `DAGAZ_IA_PROVEEDOR=openrouter` (`OPENROUTER_API_KEY`) e incorpora limpieza de bloques `<think>...</think>`, fallback al campo `reasoning` cuando `content` llega vacío (modelos como DeepSeek-R1) y soporte tolerante para la clave JSON `justificación` (validado en [`OpenAiDriverTest.php`](../../backend/tests/Unit/OpenAiDriverTest.php)).
3. **Consejo de Verificación Multi-Modelo en Paralelo (`REQ-33`, `v1.3.0`–`v1.4.1`):**
   - [`MultiModeloHarnessService.php`](../../backend/app/Services/MultiModeloHarnessService.php) ejecuta concurrentemente vía `Http::pool` GPT-4o / OpenRouter, Claude 3.5 Sonnet, Gemini 2.0 Flash, DeepSeek-R1 y Qwen activable (`DAGAZ_COUNCIL_QWEN_ACTIVO`), con calibración configurable y **salvaguarda anti-degradación** que impide degradar veredictos con contradicción explícita (`FALSO`) a `NO_VERIFICABLE`.
   - En [`backend/phpunit.xml`](../../backend/phpunit.xml) se fija `DAGAZ_MULTI_MODELO_HABILITADO=false` para pruebas rápidas y deterministas sin llamadas externas.
4. **Normalización Sin Tildes PostgreSQL e Ingesta Colombia (`REQ-30`, `REQ-33`, `REQ-35`):**
   - [`BusquedaSemanticaService.php`](../../backend/app/Services/BusquedaSemanticaService.php) implementa `normalizarSinTildes()` para evitar falsos negativos en consultas SQL sobre PostgreSQL causadas por acentos, fallback pedagógico sin errores 500 cuando no hay evidencia coincidente y reglas de contraste electoral Colombia 2026 ([`BusquedaSemanticaTest.php`](../../backend/tests/Unit/BusquedaSemanticaTest.php)).
   - Scripts de población e ingesta continua: [`NoticiasColombiaSeeder.php`](../../backend/database/seeders/NoticiasColombiaSeeder.php), comando Artisan `dagaz:actualizar-noticias` ([`ActualizarNoticiasCommand.php`](../../backend/app/Console/Commands/ActualizarNoticiasCommand.php)) y vectorización externa [`ingesta_noticias_colombia.py`](../../ingesta_noticias_colombia.py) / [`ingesta_noticias_colombia.ipynb`](../../ingesta_noticias_colombia.ipynb).

---

## 10. Matriz de Cobertura de Pruebas Automatizadas

Ejecución verificada al 100% (`php artisan test` desde `backend/` y `python -m unittest discover -s tests -v` desde `backend/Microservicios_Python/`):

```text
Backend PHPUnit 12.5:  142 passed (27 Unit [94 aserciones] + 115 Feature [2.228 aserciones] = 2.322 aserciones en 15 archivos)
Python unittest:         4 passed (test_services.py)
Frontend Vitest 5.0:    23 passed (8 suites en frontend/tests/)
──────────────────────────────────────────────────────────────────────────────────────────────────────
TOTAL VERIFICADO:      169 pruebas automatizadas pasando al 100% (0 fallos)
```

| Suite / Capa | Archivo de Prueba | Tests | Áreas Funcionales Cubiertas |
|:---|:---|:---:|:---|
| **Unit (PHPUnit)** | [`BusquedaSemanticaTest.php`](../../backend/tests/Unit/BusquedaSemanticaTest.php) | 10 | Búsqueda semántica/léxica, normalización de tildes, fallback pedagógico y contraste factual 2026. |
| **Unit (PHPUnit)** | [`ExampleTest.php`](../../backend/tests/Unit/ExampleTest.php) | 1 | Aserción unitaria base del framework. |
| **Unit (PHPUnit)** | [`OpenAiDriverTest.php`](../../backend/tests/Unit/OpenAiDriverTest.php) | 2 | Limpieza de etiquetas `<think>`, lectura de `reasoning` y clave JSON `justificación` en OpenRouter. |
| **Unit (PHPUnit)** | [`VerificacionServiceTest.php`](../../backend/tests/Unit/VerificacionServiceTest.php) | 14 | Validación léxica de notas, detección de XSS/SQLi, descomposición atómica y rango de calificaciones. |
| **Feature (PHPUnit)** | [`ArchiveOrgTest.php`](../../backend/tests/Feature/ArchiveOrgTest.php) | 10 | `TSK-3.2`: API Wayback Machine, capturas CDX y detección de descontextualización histórica. |
| **Feature (PHPUnit)** | [`AuditoriaExportTest.php`](../../backend/tests/Feature/AuditoriaExportTest.php) | 14 | `TSK-4.6`: Auditoría forense JSON SHA-256, `ClaimReview` JSON-LD, CSV UTF-8 BOM y lotes. |
| **Feature (PHPUnit)** | [`AuthTest.php`](../../backend/tests/Feature/AuthTest.php) | 10 | `CU05`: Registro, Bcrypt, tokens Sanctum Bearer, perfiles y roles. |
| **Feature (PHPUnit)** | [`BackendAdvancedFeatureTest.php`](../../backend/tests/Feature/BackendAdvancedFeatureTest.php) | 9 | `TSK-3.7` (intervención editorial `REQ-13`), `TSK-4.3` (Circuit Breaker) y Ley 1581 Hábeas Data. |
| **Feature (PHPUnit)** | [`ExampleTest.php`](../../backend/tests/Feature/ExampleTest.php) | 1 | Smoke test HTTP raíz `/`. |
| **Feature (PHPUnit)** | [`FrontendRoutesTest.php`](../../backend/tests/Feature/FrontendRoutesTest.php) | 10 | Validación SSR de todas las rutas Blade en `backend/routes/web.php` y `/health`. |
| **Feature (PHPUnit)** | [`GrafoFuentesTest.php`](../../backend/tests/Feature/GrafoFuentesTest.php) | 11 | `TSK-3.1` / `TSK-4.1`: Catálogo de 80 fuentes en 4 niveles IFCN y valoración/reclasificación. |
| **Feature (PHPUnit)** | [`InterlingueTest.php`](../../backend/tests/Feature/InterlingueTest.php) | 12 | `TSK-4.4`: Detección idiomática, traducción ES-EN y análisis de distorsión semántica. |
| **Feature (PHPUnit)** | [`PublicPartnerApiTest.php`](../../backend/tests/Feature/PublicPartnerApiTest.php) | 11 | `TSK-4.2`: Autenticación `X-API-KEY`, rate limiting `throttle:partners` y endpoints `/api/v1/public/*`. |
| **Feature (PHPUnit)** | [`VerificacionesBackendFeatureTest.php`](../../backend/tests/Feature/VerificacionesBackendFeatureTest.php) | 10 | Validación E2E del flujo público multicanal, consulta documental y emisión de veredicto con citas. |
| **Feature (PHPUnit)** | [`VerificacionFeatureTest.php`](../../backend/tests/Feature/VerificacionFeatureTest.php) | 17 | `CU01`–`CU04`, `CU07`: notas ciudadanas, historial de noticias y valoración de fuentes. |
| **Python (`unittest`)** | [`test_services.py`](../../backend/Microservicios_Python/tests/test_services.py) | 4 | `TSK-2.3`: Endpoints `/ocr`, `/transcribe`, `/health` y ejecución local OCR/STT. |

---

## 11. Catálogo de Endpoints REST v1 Implementados

Contrato oficial OpenAPI 3.1 en [`backend/storage/api-docs/openapi.yaml`](../../backend/storage/api-docs/openapi.yaml), definido en [`backend/routes/api.php`](../../backend/routes/api.php) (bajo prefijo `/api`) y [`backend/routes/web.php`](../../backend/routes/web.php):

### 11.1 Diagnóstico y Documentación (Públicos)
- `GET /api/health` y `GET /health`: Health check operativo del servidor.
- `GET /api/docs`: Descarga del contrato REST OpenAPI 3.1 en YAML.

### 11.2 Autenticación y Hábeas Data (CU05 y Ley 1581)
- `POST /api/v1/auth/register`: Registro de nuevos usuarios.
- `POST /api/v1/auth/login`: Autenticación y entrega de token Bearer Sanctum.
- `POST /api/v1/auth/logout`: Revocación del token activo *(Sanctum)*.
- `GET /api/v1/auth/perfil`: Consulta de perfil en sesión *(Sanctum)*.
- `GET /api/v1/auth/datos-personales`: Descarga de datos personales — Ley 1581 *(Sanctum)*.
- `DELETE /api/v1/auth/cuenta`: Supresión y derecho al olvido — Ley 1581 *(Sanctum)*.

### 11.3 Verificación Multicanal, Archive.org e Interlingüe (CU06, TSK-3.2, TSK-4.4)
- `POST /api/v1/verificacion`: Verificación pública de contenidos (`TEXTO`, `URL`, `IMAGEN_OCR`, `RED_SOCIAL`).
- `GET /api/v1/verificacion/{idContenido}`: Consulta pública de veredicto y fuentes por UUID.
- `GET /api/v1/verificacion/archive/consultar`: Consulta de snapshots históricos Wayback Machine *(Sanctum)*.
- `POST /api/v1/verificacion/archive/descontextualizacion`: Detección de descontextualización temporal *(Sanctum)*.
- `POST /api/v1/verificacion/interlingue/detectar`: Detección de idioma *(Sanctum)*.
- `POST /api/v1/verificacion/interlingue/traducir`: Traducción bidireccional ES <-> EN *(Sanctum)*.
- `POST /api/v1/verificacion/interlingue/distorsion`: Análisis de distorsión en traducciones *(Sanctum)*.

### 11.4 Historial y Notas Comunitarias (CU01–CU04, `auth:sanctum`)
- `POST /api/v1/historial/{idContenido}`: Guardar contenido verificado en historial personal.
- `GET /api/v1/historial`: Listar historial personal paginado.
- `POST /api/v1/contenidos/{idContenido}/notas`: Publicar nota complementaria ciudadana.
- `GET /api/v1/contenidos/{idContenido}/notas`: Listar notas de un contenido.
- `PATCH /api/v1/notas/{idNota}/calificar`: Calificar utilidad de una nota (`0.0` a `10.0`).

### 11.5 Grafo de Credibilidad de Fuentes (CU07)
- `GET /api/v1/fuentes`: Consultar fuentes del grafo (acceso público, filtrable por tipo/nivel).
- `PATCH /api/v1/fuentes/{idFuente}/valorar`: Valorar credibilidad de fuente *(Sanctum)*.
- `PATCH /api/v1/fuentes/{idFuente}/clasificar`: Reclasificar tipo de fuente *(Sanctum)*.
- `POST /api/v1/fuentes`: Registrar nueva fuente en el grafo *(Sanctum)*.

### 11.6 Panel Editorial y Administración (`rol:admin,editor`)
- `POST /api/v1/veredictos/{idVeredicto}/intervenir`: Rectificación editorial humana (`rol:admin,editor`).
- `GET /api/v1/admin/circuit-breaker`: Métricas de costes y estado de disyuntor (`rol:admin`).
- `POST /api/v1/admin/circuit-breaker/reset`: Reinicio manual de disyuntor (`rol:admin`).
- `GET /api/v1/admin/logs-api`: Auditoría de llamadas externas (`rol:admin`).

### 11.7 API Pública para Redacciones Socias (`api.key` + `throttle:partners`)
- `POST /api/v1/public/verificar`: Ingesta y emisión de veredictos M2M.
- `GET /api/v1/public/veredictos/{idContenido}`: Consulta de veredicto por UUID.
- `GET /api/v1/public/fuentes`: Catálogo de 80 fuentes IFCN.
- `GET /api/v1/public/health`: Estado de salud operativo para redacciones.

### 11.8 Auditoría Forense y ClaimReview (`REQ-25`)
- `GET /api/v1/auditoria/veredictos/{idContenido}`: Expediente forense (`?formato=json|json-ld|csv`, `rol:admin,editor`).
- `GET /api/v1/auditoria/expedientes`: Lote masivo filtrable (`rol:admin,editor`).
- `GET /api/v1/auditoria/claim-review/{idContenido}`: ClaimReview JSON-LD directo (`rol:admin,editor`).
- `GET /api/v1/public/auditoria/{idContenido}`: Expediente forense M2M (`X-API-KEY`).
- `GET /api/v1/public/claim-review/{idContenido}`: ClaimReview JSON-LD M2M (`X-API-KEY`).
