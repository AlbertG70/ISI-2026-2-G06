# DAGAZ — Backend (Laravel 13 / PHP 8.4)

> **Plataforma de Verificación de Información y Contraste de Fuentes**  
> **Curso:** Ingeniería de Servicios de Internet — 2026-2 — Grupo 06

---

## 1. Descripción

Este directorio contiene el backend de **DAGAZ**, una plataforma de fact-checking automatizada. Está desarrollado sobre **PHP 8.4** y **Laravel 13** (`^13.17`), e implementa:

- **API REST v1** con autenticación **Laravel Sanctum** (`^4.3`, tokens Bearer compatibles con UUID) y acceso M2M con API Key.
- **Canal de Streaming Reactivo SSE** (`POST /api/v1/verificacion/stream`) para telemetría en tiempo real y Consola de Pensamiento (*Chain-of-Thought*).
- **Pipeline de verificación** multicanal de 14 etapas (`VerificacionService`: texto, URL, imagen OCR, audio).
- **Adaptadores de IA y Consejo Multi-Modelo:** Drivers agnósticos (`OpenAiDriver` para **OpenAI** y **OpenRouter** con parser tolerante a `<think>`/`reasoning`, `GeminiDriver` con cadena de respaldo multi-modelo, `LocalLlmDriver`) y [`MultiModeloHarnessService`](app/Services/MultiModeloHarnessService.php) con validación paralela vía `Http::pool` + Google Search Grounding bajo **Circuit Breaker** (`CircuitBreakerService`).
- **Búsqueda Semántica RAG y Búsqueda Web en Vivo:** [`BusquedaSemanticaService`](app/Services/BusquedaSemanticaService.php) sobre PostgreSQL 18 (`pgvector`) con `normalizarSinTildes()` y blindaje `SIN_EVIDENCIA`, más [`BusquedaWebService`](app/Services/BusquedaWebService.php) para actualidad del día.
- **Grafo de Credibilidad** de 80+ fuentes colombianas e internacionales en 4 niveles IFCN.
- **Sistema interlingüe** (ES/EN) con detección de idioma y traducción (`ServicioInterlingue`).
- **Conector Archive.org** para detección de descontextualización histórica (`ArchiveOrgService`).
- **API pública M2M** para redacciones socias (El Espectador, ColombiaCheck, La Silla Vacía) bajo `/api/v1/public/*`.
- **Auditoría forense** exportable en JSON, CSV y ClaimReview (`Schema.org`).

---

## 2. Estructura del Proyecto

```text
backend/
├── api_server.php              # Servidor ligero de desarrollo con soporte CORS
├── app/                        # Código fuente principal
│   ├── Adapters/Ia/            # Drivers IA Strategy (OpenAiDriver [OpenAI/OpenRouter], GeminiDriver, Local)
│   ├── Console/Commands/       # ActualizarNoticiasCommand (dagaz:actualizar-noticias)
│   ├── Contracts/              # Interfaces (AdaptadorApiIaInterface, ConectorArchiveInterface, etc.)
│   ├── Http/Controllers/       # Controladores REST v1, Streaming SSE y vistas web
│   ├── Models/                 # Modelos Eloquent (10 tablas oficiales + PersonalAccessToken UUID)
│   └── Services/               # VerificacionService, MultiModeloHarnessService, BusquedaSemanticaService, BusquedaWebService...
├── config/                     # Configuración (dagaz.php, database.php, cors.php, trustedproxy.php, view.php)
├── database/
│   ├── migrations/             # Migraciones del esquema PostgreSQL 18 pgvector y soporte UUID Sanctum
│   └── seeders/                # Seeders (FuentesSeeder, NoticiasColombiaSeeder, DocumentosNoticiasSeeder...)
├── routes/
│   ├── api.php                 # Definición de rutas REST (/api/v1 y /api/health, /api/docs)
│   └── web.php                 # Rutas web SSR y fallback
├── storage/api-docs/           # Contrato OpenAPI 3.1 (openapi.yaml)
├── tests/
│   ├── Feature/                # Pruebas de integración y endpoints REST (incluye SSE)
│   └── Unit/                   # Pruebas unitarias de servicios y adaptadores (Gemini, OpenAI, RAG, Web, Multi-Modelo)
│                               # Total backend: 163 tests (2.464 aserciones, 100% pasando)
├── Microservicios_Python/      # Microservicios OCR y STT (FastAPI + Uvicorn)
│   ├── ocr_service/            # Servicio de extracción de texto por imagen (:5001)
│   ├── stt_service/            # Servicio de transcripción de audio (:5002)
│   ├── tests/                  # 4 pruebas unittest (FastAPI TestClient)
│   └── requirements.txt        # Dependencias Python
├── .env.example                # Plantilla de configuración (sin credenciales)
├── composer.json               # Dependencias PHP (^8.3, laravel/framework ^13.17, phpunit ^12.5.12)
├── phpunit.xml                 # Configuración de pruebas (DAGAZ_MULTI_MODELO_HABILITADO=false)
└── README.md                   # Este archivo
```

---

## 3. Requisitos Previos

| Componente | Versión |
|:--|:--|
| PHP | `^8.3` / `8.4` (con extensiones `pdo_pgsql`, `pgsql`, `pdo_mysql`, `sqlite3`, `intl`, `mbstring`) |
| Composer | `2.x` |
| Node.js | `20+` / `22+` (para compilación Vite de assets) |
| Python | `3.10+` (solo para microservicios OCR/STT) |
| PostgreSQL | `15+` / `18` (`pgvector` en Neon serverless) o MariaDB `10.11+` |

---

## 4. Instalación y Configuración

```bash
# 1. Entrar al directorio del backend
cd backend

# 2. Instalar dependencias PHP
composer install

# 3. Copiar la plantilla de entorno
cp .env.example .env

# 4. Generar la clave de aplicación
php artisan key:generate

# 5. Configurar la conexión a la base de datos e IA en .env
#    → Editar DB_CONNECTION, DB_URL, OPENAI_API_KEY / OPENROUTER_API_KEY, etc.

# 6. (Opcional) Instalar dependencias de microservicios Python
cd Microservicios_Python
pip install -r requirements.txt
cd ..
```

---

## 5. Base de Datos

El proyecto soporta dos motores relacionales:

### PostgreSQL (Neon Serverless — Producción)
```dotenv
DB_CONNECTION=pgsql
DB_URL="postgresql://USUARIO:CONTRASEÑA@HOST/BASE_DE_DATOS?sslmode=require"
DB_SSLMODE=require
```

### MariaDB (Desarrollo local)
```dotenv
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=raiden
DB_USERNAME=root
DB_PASSWORD=
```

> **Nota:** Las migraciones principales residen en [`database/migrations/`](database/migrations/) (incluyendo soporte UUID para Sanctum en PostgreSQL Neon). El esquema SQL completo está en [`../database/Raiden.sql`](../database/Raiden.sql).

---

## 6. Ejecución

```bash
# Opción 1: Servidor Laravel completo
php artisan serve
# → http://127.0.0.1:8000

# Opción 2: Servidor Ligero DAGAZ con CORS (Desarrollo rápido)
php api_server.php
# → API disponible en http://127.0.0.1:8000

# (Opcional) Microservicios Python Multicanal (FastAPI)
cd Microservicios_Python
python run_services.py
# → OCR en :5001, STT en :5002
```

---

## 7. Endpoints Principales de la API

| Método | Ruta | Descripción |
|:--|:--|:--|
| `POST` | `/api/v1/verificacion` | Verificar contenido síncrono (público o con token Bearer) |
| `POST` | `/api/v1/verificacion/stream` | **Streaming Reactivo SSE** en tiempo real (`text/event-stream`) con telemetría de fases y CoT |
| `GET`  | `/api/v1/verificacion/{idContenido}` | Obtener resultado de verificación |
| `GET`  | `/api/v1/fuentes` | Listar fuentes del grafo de credibilidad (público) |
| `POST` | `/api/v1/auth/register` | Registro de usuario (Ley 1581) |
| `POST` | `/api/v1/auth/login` | Inicio de sesión (devuelve Bearer token Sanctum) |
| `POST` | `/api/v1/contenidos/{idContenido}/notas` | Agregar nota comunitaria (Bearer token) |
| `PATCH`| `/api/v1/notas/{idNota}/calificar` | Calificar nota comunitaria (Bearer token) |
| `POST` | `/api/v1/historial/{idContenido}` | Guardar contenido en historial personal (Bearer token) |
| `POST` | `/api/v1/public/verificar` | Verificación M2M para redacciones socias (`X-API-Key`) |
| `GET`  | `/api/v1/public/fuentes` | Consultar grafo de credibilidad para socios (`X-API-Key`) |
| `GET`  | `/api/v1/public/health` | Estado del servicio para socios |
| `GET`  | `/api/v1/auditoria/veredictos/{idContenido}` | Expediente forense individual (JSON, CSV, ClaimReview) |
| `GET`  | `/api/health` · `/api/docs` | Health check general y especificación OpenAPI 3.1 |

> Definición completa en [`routes/api.php`](routes/api.php) y [`storage/api-docs/openapi.yaml`](storage/api-docs/openapi.yaml).

---

## 8. Pruebas Automatizadas

```bash
# Ejecutar los 163 tests del backend (PHPUnit 12.5 sobre SQLite :memory:)
php artisan test

# Ejecutar solo Unit o Feature
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
```

Las pruebas están aisladas en **SQLite `:memory:`** con `DAGAZ_MULTI_MODELO_HABILITADO=false` en [`phpunit.xml`](phpunit.xml) y no requieren conexión externa:
- **Pruebas de Feature:** Autenticación Sanctum, Ley 1581 Hábeas Data, ingesta multicanal, pipeline de verificación de 14 fases, canal de streaming reactivo SSE, rutas web SSR (`FrontendRoutesTest`), Archive.org, sistema interlingüe, Notas Comunitarias (`CU01`, `CU02`), historial (`CU03`, `CU04`), Grafo de Credibilidad (`CU07`), auditoría forense y API pública M2M (`PublicPartnerApiTest`).
- **Pruebas Unitarias:** `VerificacionServiceTest`, `BusquedaSemanticaTest` (incluye blindaje `SIN_EVIDENCIA` y exclusión HTML/imágenes), `BusquedaWebServiceTest`, `MultiModeloHarnessTest`, `GeminiDriverTest`, `OpenAiDriverTest` y `ExampleTest`.
- **Total Backend:** **163 tests, 2.464 aserciones, 100% exitosas**.

---

## 8.1 Sincronización Automática de Noticias (RSS Feeds)

DAGAZ incluye un comando Artisan para sincronizar titulares colombianos desde fuentes RSS:

```bash
php artisan dagaz:actualizar-noticias
```

---

## 9. Casos de Uso Implementados

| CU | Nombre | Estado |
|:--|:--|:--|
| CU01 | Agregar Notas Complementarias | ✅ |
| CU02 | Calificar Notas Comunitarias | ✅ |
| CU03 | Guardar Contenidos en Historial | ✅ |
| CU04 | Consultar Historial de Contenidos | ✅ |
| CU05 | Autenticación y Credenciales (Sanctum) | ✅ |
| CU06 | Verificar Contenidos (Pipeline IA + Consejo Multi-Modelo) | ✅ |
| CU07 | Gestionar Fuentes del Grafo de Credibilidad | ✅ |

---

## 10. Integrantes

| Integrante | Código | Rol |
|:--|:--|:--|
| Alberto Mario Galvis Echeverria | 0222410070 | Analista de Requerimientos / Desarrollador FullStack |
| Camilo Isaac Acevedo Rueda | 0222410049 | Analista de Calidad / Desarrollador FullStack |
