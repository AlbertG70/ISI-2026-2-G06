# ISI-2026-2-G06 —|— DAGAZ —|—

> **Sistema:** DAGAZ — Plataforma Multicanal de Detección de Desinformación y Verificación de Contenidos  
> **Organización:** ISI-CORP (2026-II)  
> **Autores:** Alberto Mario Galvis Echeverria, Camilo Isaac Acevedo Rueda  
> **Estado:** MVP Liberado y Evolución Activa (**v1.5.1**)  
> **Última actualización:** Noviembre 2026  
> **Stack:** PHP 8.4 · Laravel 13 · PostgreSQL 18 (`pgvector`) / MariaDB · Python 3.11 (FastAPI) · Tailwind CSS v4 · Docker · Capacitor 6 · React Native (Expo SDK 58)

---

## 📑 Tabla de Contenidos

- [Descripción del Proyecto](#descripción-del-proyecto)
- [Arquitectura General y Módulos del Sistema](#arquitectura-general-y-módulos-del-sistema)
- [Casos de Uso Implementados (CU01 al CU07)](#casos-de-uso-implementados-cu01-al-cu07)
- [Estructura del Repositorio](#estructura-del-repositorio)
- [Resumen de Sprints, Entregables y Evolución Post-MVP](#resumen-de-sprints-entregables-y-evolución-post-mvp)
- [Cronograma del Proyecto (Diagrama de Gantt)](#cronograma-del-proyecto-diagrama-de-gantt)
- [Requisitos Previos del Sistema](#requisitos-previos-del-sistema)
- [Guía de Instalación y Despliegue Paso a Paso](#guía-de-instalación-y-despliegue-paso-a-paso)
- [Aplicaciones Móviles (`mobile/` Capacitor 6 y `mobile-expo/` Expo SDK 58)](#aplicaciones-móviles-mobile-capacitor-6-y-mobile-expo-expo-sdk-58)
- [Configuración de Variables de Entorno (.env)](#configuración-de-variables-de-entorno-env)
- [Inicialización y Migración de la Base de Datos](#inicialización-y-migración-de-la-base-de-datos)
- [Puesta en Marcha y Ejecución del Sistema](#puesta-en-marcha-y-ejecución-del-sistema)
- [Sincronización Periódica e Ingesta Masiva de Noticias](#sincronización-periódica-e-ingesta-masiva-de-noticias)
- [Batería de Pruebas Automatizadas (QA — 190 Tests)](#batería-de-pruebas-automatizadas-qa--190-tests)
- [Catálogo de Endpoints de la API REST (`/api/v1`)](#catálogo-de-endpoints-de-la-api-rest-apiv1)
- [Documentación y Enlaces a Entregables](#documentación-y-enlaces-a-entregables)
- [Solución de Problemas Frecuentes (Troubleshooting)](#solución-de-problemas-frecuentes-troubleshooting)
- [Integrantes del Proyecto](#integrantes-del-proyecto)

---

## Descripción del Proyecto

**DAGAZ** es una plataforma integral de fact-checking cívico y verificación multicanal concebida para contrarrestar la viralización de desinformación, cadenas falsas y descontextualizaciones en Colombia, con especial énfasis en la usabilidad y accesibilidad para **Adultos (+40 años)** y ciudadanos digitales.

El sistema incorpora:
1. **Pipeline de Inteligencia Artificial Agnóstico y Consejo Multi-Modelo:** Integración desacoplada mediante patrón Adapter/Strategy (`OpenAiDriver` con soporte para **OpenAI** y **OpenRouter**, `GeminiDriver`, `LocalLlmDriver` vía Ollama) complementada con el **Consejo de Verificación Multi-Modelo** ([`MultiModeloHarnessService`](backend/app/Services/MultiModeloHarnessService.php)) que evalúa en paralelo vía `Http::pool` con GPT-4o, Claude 3.5 Sonnet, Gemini 2.0 Flash, DeepSeek-R1 y Qwen bajo control presupuestal con `CircuitBreakerService`.
2. **Microservicios Multimedia Dedicados:** Microservicio FastAPI de extracción OCR con Pillow + `pytesseract` para imágenes y capturas (`:5001`) y microservicio STT con `SpeechRecognition` para notas de voz (`:5002`).
3. **Grafo de Credibilidad de 80 Fuentes:** Jerarquizado en 4 niveles de reputación bajo estándares de la *International Fact-Checking Network* (IFCN).
4. **Base de Datos Semántica Vectorial:** Soporte dual para PostgreSQL 18 con extensión `pgvector` (Neon Serverless SSL) con embeddings densos de 384 dimensiones (`all-MiniLM-L6-v2`) sobre miles de artículos colombianos e internacionales indexados, normalización SQL sin tildes y compatibilidad local con MariaDB/SQLite.
5. **Ecosistema Móvil Dual:** Web PWA responsiva WCAG 2.1 AA, contenedor híbrido Android **Capacitor 6** en [`mobile/`](mobile/) y aplicación nativa multiplataforma iOS/Android en **React Native + Expo SDK 58** ([`mobile-expo/`](mobile-expo/)) con flujo CI macOS para compilación `.ipa` destinada a Sideloadly.
6. **Cumplimiento Legal Estricto:** Diseñado en conformidad con la **Ley Estatutaria 1581 de 2012** de Colombia (Protección de Datos Personales y Hábeas Data) con consentimiento explícito, exportación ARCO y derecho al olvido.

---

## Arquitectura General y Módulos del Sistema

```text
┌───────────────────────────────────────────────────────────────────────────────────┐
│                               CLIENTES & PRESENTACIÓN                             │
│  ┌───────────────────────────────────────┐  ┌──────────────────────────────────┐  │
│  │ Web Responsiva & Híbrida (PWA)        │  │ App Nativa iOS / Android         │  │
│  │ Laravel Blade · Vite 8 · Tailwind v4  │  │ React Native + Expo SDK 58       │  │
│  │ Contenedor Capacitor 6 (mobile/)      │  │ (mobile-expo/ · Sideloadly .ipa) │  │
│  └──────────────────┬────────────────────┘  └────────────────┬─────────────────┘  │
└─────────────────────┼────────────────────────────────────────┼────────────────────┘
                      └───────────────────┬────────────────────┘
                                          ▼
┌───────────────────────────────────────────────────────────────────────────────────┐
│                         GATEWAY & BACKEND (Laravel 13 / PHP 8.4)                  │
│   ┌─────────────────────────────────┐       ┌─────────────────────────────────┐   │
│   │   Rutas Web & Controladores     │       │  API REST v1 (Sanctum & APIKey) │   │
│   │   SSR Blade Views (frontend/)   │       │  Public / Auth / Partners / M2M │   │
│   └────────────────┬────────────────┘       └────────────────┬────────────────┘   │
│                    ▼                                         ▼                    │
│   ┌───────────────────────────────────────────────────────────────────────────┐   │
│   │                 Servicios de Dominio & Orquestación IA                    │   │
│   │  - VerificacionService (14 Fases)     - MultiModeloHarnessService (Pool)  │   │
│   │  - BusquedaSemanticaService (RAG)     - CircuitBreakerService             │   │
│   │  - ArchiveOrgService (Wayback)        - ServicioInterlingue (ES <-> EN)   │   │
│   │  - AuditoriaExportService             - dagaz:actualizar-noticias (RSS)   │   │
│   └────────────────┬─────────────────────────────────────────┬────────────────┘   │
└────────────────────┼─────────────────────────────────────────┼────────────────────┘
        ┌────────────┴────────────┐               ┌────────────┴────────────┐
        ▼                         ▼               ▼                         ▼
┌─────────────────┐      ┌─────────────────┐ ┌─────────────────┐ ┌──────────────────┐
│   OCR Service   │      │   STT Service   │ │  PostgreSQL 18  │ │ Adaptadores IA   │
│ Python FastAPI  │      │ Python FastAPI  │ │   + pgvector    │ │ OpenAI / Gemini  │
│  (Puerto 5001)  │      │  (Puerto 5002)  │ │   (Neon.tech)   │ │ OpenRouter/Claude│
│Pillow/Tesseract │      │SpeechRecognition│ │ 10 Tablas / KNN │ │ DeepSeek / Local │
└─────────────────┘      └─────────────────┘ └─────────────────┘ └──────────────────┘
```

---

## Casos de Uso Implementados (CU01 al CU07)

El sistema implementa el 100% de los Casos de Uso definidos en el alcance del proyecto:

| CU | Caso de Uso | Vista Web Blade / Pantalla Móvil | Endpoint API REST Real | Descripción y Reglas de Negocio |
| :---: | :--- | :--- | :--- | :--- |
| **CU01** | **Asignar Notas Complementarias** | `verificacion/resultado.blade.php`<br>`ResultadoScreen.js` | `POST /api/v1/contenidos/{id}/notas`<br>`GET /api/v1/contenidos/{id}/notas` | Permite a ciudadanos y revisores redactar notas comunitarias con sanitización de caracteres (`< > { } \ |`) y límites de longitud. |
| **CU02** | **Calificar Notas Comunitarias** | `verificacion/resultado.blade.php`<br>`ResultadoScreen.js` | `PATCH /api/v1/notas/{id}/calificar` | Votación de utilidad ciudadana (👍 Útil / 👎 No útil) con cálculo reactivo de barra de porcentaje. |
| **CU03** | **Guardar Contenidos** | `verificacion/resultado.blade.php`<br>`ResultadoScreen.js` | `POST /api/v1/historial/{id}` | Almacena noticias y verificaciones en la colección personal del usuario autenticado. |
| **CU04** | **Consultar Historial de Contenidos** | `historial/index.blade.php`<br>`HistorialScreen.js` | `GET /api/v1/historial` | Bitácora cronológica con acordeón en línea (`👁 Ver detalle`), remoción suave (`🗑 Quitar`) y filtros por veredicto. |
| **CU05** | **Autenticación y Hábeas Data** | `auth/{login,register}.blade.php`<br>`perfil/index.blade.php`<br>`PerfilScreen.js` | `POST /api/v1/auth/{register,login,logout}`<br>`GET /api/v1/auth/{perfil,datos-personales}`<br>`DELETE /api/v1/auth/cuenta` | Emisión de tokens Bearer Sanctum, consentimiento explícito previo (Ley 1581) y descarga/supresión de datos personales (Derechos ARCO). |
| **CU06** | **Verificar Contenidos (Pipeline Multicanal)** | `verificacion/{index,resultado}.blade.php`<br>`VerificarScreen.js` / `ResultadoScreen.js` | `POST /api/v1/verificacion`<br>`GET /api/v1/verificacion/{id}` | Ingesta de texto, URL, imágenes (OCR) y audio (STT). Ejecuta 14 fases algorítmicas + Consejo Multi-Modelo, generando veredicto, confianza (0-100%) y fuentes. |
| **CU07** | **Confiabilidad de Fuentes (Grafo)** | `fuentes/index.blade.php`<br>`FuentesScreen.js` | `GET /api/v1/fuentes`<br>`PATCH /api/v1/fuentes/{id}/valorar` | Directorio de 80 medios categorizados en 4 niveles IFCN, con medidor circular SVG, valoración cívica y reclasificación administrativa. |

---

## Estructura del Repositorio

```text
ISI-2026-2-G06/
├── .github/
│   └── workflows/
│       ├── ci.yml                              # Pipeline CI/CD (Backend, Frontend Vite/Vitest y Capacitor Android)
│       └── build-ios-ipa.yml                   # Workflow macOS para generar DAGAZ.ipa sin firma (Sideloadly)
├── backend/                                    # Núcleo API REST Laravel 13 (PHP 8.4) y Lógica de Negocio
│   ├── app/
│   │   ├── Adapters/Ia/                        # Drivers Strategy: OpenAiDriver (OpenAI/OpenRouter), GeminiDriver, Local
│   │   ├── Console/Commands/                   # dagaz:actualizar-noticias (Ingesta RSS)
│   │   ├── Http/Controllers/                   # VerificacionController (incl. SSE), AuthController, Auditoria, Partners
│   │   ├── Models/                             # Modelos Eloquent (10 tablas oficiales + PersonalAccessToken UUID)
│   │   └── Services/                           # VerificacionService, MultiModeloHarnessService, BusquedaSemantica, BusquedaWeb...
│   ├── config/                                 # Configuraciones (dagaz.php, database.php, cors.php, trustedproxy.php, view.php)
│   ├── database/                               # Migraciones Laravel y Seeders (Fuentes, Colombia, Documentos, Usuarios)
│   ├── Microservicios_Python/                  # Microservicios FastAPI (OCR :5001 y STT :5002) + unittest
│   ├── routes/                                 # api.php (REST v1 + SSE) y web.php (Vistas Blade SSR)
│   ├── storage/api-docs/openapi.yaml           # Especificación OpenAPI 3.1
│   └── tests/                                  # 163 tests PHPUnit (Feature y Unit, 2.464 aserciones)
├── database/                                   # Documentación y DDL de Base de Datos
│   ├── Diagrama-ER.md                          # Diagrama Entidad-Relación Mermaid
│   ├── Documentacion-Raiden.md                 # Especificación técnica y diccionario de datos
│   └── Raiden.sql                              # Script SQL DDL oficial (PostgreSQL 18 / pgvector)
├── docs/                                       # Centro de Documentación del Proyecto
│   ├── README.md                               # Índice central y mapa de navegación
│   ├── Presupuesto de Desarrollo.md            # Estimación presupuestal técnica 2026
│   ├── app-icons/                              # Iconos Android (Play Store) e iOS (AppIcon.appiconset)
│   ├── iteracion-01/                           # Análisis, Requisitos, DSS y Mockups de servilleta
│   ├── iteracion-02/                           # Guidelines, Gantt (+ Visual HTML), Historial, Mockups baja fidelidad, Sprints
│   ├── iteracion-03/                           # Cierre técnico UI Web Accesible, Streaming SSE y PWA
│   ├── iteracion-final/                        # Benchmark 500, Auditoría de Sesgo, Manuales, Onboarding y Traspaso
│   └── mockups-alta-fidelidad/                 # 10 prototipos interactivos WCAG 2.1 AA (CU01–CU07 y REQ13)
├── frontend/                                   # Capa de Presentación Web, Vistas Blade y Pruebas UI
│   ├── src/css/app.css                         # Sistema de diseño Tailwind CSS v4 accesible
│   ├── src/js/                                 # Módulos JavaScript ES y cliente API (incluye SSE CoT)
│   ├── tests/                                  # 23 pruebas automatizadas Vitest en 8 archivos (Happy-DOM)
│   └── views/                                  # Vistas Blade modulares (auth, verificacion, historial, fuentes, perfil)
├── mobile/                                     # Contenedor Híbrido Android/iOS (Capacitor 6, com.isicorp.dagaz)
├── mobile-expo/                                # App Móvil Nativa Multiplataforma (React Native + Expo SDK 58)
├── Dockerfile                                  # Imagen multi-stage Nginx + PHP-FPM 8.4 para PaaS / producción
├── ingesta_noticias_colombia.py                # Script masivo de extracción y vectorización RAG de medios colombianos
├── ingesta_noticias_colombia.ipynb             # Notebook ejecutable (Google Colab) de ingesta vectorial
├── CHANGELOG.md                                # Registro histórico de versiones (v0.0.1 a v1.5.1)
└── README.md                                   # Esta guía técnica integral
```

---

## Resumen de Sprints, Entregables y Evolución Post-MVP

El proyecto completó el **100% de los 281 puntos de historia base** en 7 Sprints hasta la liberación MVP (`v1.0.0`) y ejecutó un ciclo evolutivo (`v1.1.0` a `v1.4.1`) que suma 7 requisitos adicionales (`REQ-29` a `REQ-35`):

| Fase / Sprint | Enfoque Principal | Requerimientos / Tareas | Puntos | Estado |
| :---: | :--- | :--- | :---: | :---: |
| **Sprint 1** | Fundamentos, Análisis de Negocio e Ideación | Acciones 1 a 6 + REQ-08 (Grafo Nivel 1) | **45 pts** | ✅ 100% Completado |
| **Sprint 2** | Modelado de Casos de Uso, Mockups y Pipeline Base | Acciones 7 a 11 + REQ-01 a REQ-07 | **73 pts** | ✅ 100% Completado |
| **Sprint 3** | Persistencia, Monitoreo y Base de Datos | REQ-09 a REQ-16 | **41 pts** | ✅ 100% Completado |
| **Sprint 4** | Robustez, Gobernanza, Auditoría y Escalabilidad | REQ-17 a REQ-24 | **33 pts** | ✅ 100% Completado |
| **Sprint 5** | Interfaz Web Accesible y Vistas Blade | TSK-5.1 a TSK-5.8 (CU01–CU07, Tailwind v4, PWA) | **26 pts** | ✅ 100% Completado |
| **Sprint 6** | QA Frontend, Benchmark 500 y Auditoría de Sesgo | TSK-6.1 a TSK-6.6 (23 tests Vitest, Benchmark 500) | **39 pts** | ✅ 100% Completado |
| **Sprint 7** | Despliegue CI/CD, Manuales y Cierre MVP | REQ-25 a REQ-28 (CI/CD, Onboarding, Manuales, Traspaso) | **24 pts** | ✅ 100% Completado |
| **Evolutivo (v1.1–v1.4.1)** | Multi-Modelo, Mockups AF, Móvil Dual y Normalización 2026 | REQ-29 a REQ-35 (`MultiModeloHarnessService`, `docs/mockups-alta-fidelidad/`, `mobile/` + `mobile-expo/`, CI `.ipa`, normalización PostgreSQL/OpenRouter) | — | ✅ 100% Completado |

---

## Cronograma del Proyecto (Diagrama de Gantt)

```mermaid
gantt
    title DAGAZ — Cronograma Integral (Sprints 1 a 7 + Evolución Post-MVP)
    dateFormat  YYYY-MM-DD
    axisFormat  %d-%b

    section Sprint 1 — Fundamentos
    Matriz de ideación y metodología (Acciones 1-2)    :done, s1_1, 2026-08-03, 2026-08-08
    Análisis y modelado del negocio (Acciones 3-4)     :done, s1_2, 2026-08-09, 2026-08-14
    Análisis y modelado de requisitos (Acciones 5-6)   :done, s1_3, 2026-08-15, 2026-08-18
    Grafo de credibilidad v1 (REQ-08)                  :done, s1_4, 2026-08-12, 2026-08-18

    section Sprint 2 — Casos de Uso y Pipeline IA
    Casos de uso y mockups de UI (Acciones 7-11)       :done, s2_1, 2026-08-19, 2026-08-25
    Pipeline IA Base y Extractor (REQ-01 a REQ-06)     :done, s2_2, 2026-08-22, 2026-09-01
    Demo esqueleto funcional (REQ-07)                  :done, s2_3, 2026-09-01, 2026-09-03

    section Sprint 3 — Persistencia y Demo
    Diseño de Base de Datos y Adultos Mayores (REQ-12) :done, s3_1, 2026-09-04, 2026-09-10
    Grafo v2 y Archive.org (REQ-10, REQ-14)            :done, s3_2, 2026-09-07, 2026-09-12
    Monitor redes y Panel Revisor Humano (REQ-09, 13)  :done, s3_3, 2026-09-11, 2026-09-16
    API Pública v1 e Interfaz Tarjeta (REQ-15, 16)     :done, s3_4, 2026-09-13, 2026-09-18

    section Sprint 4 — Robustez y Gobernanza
    Auditoría sesgo v1 y Costes LLM (REQ-17, 18)       :done, s4_1, 2026-09-19, 2026-09-24
    Recuperación interlingüe y Grafo v3 (REQ-19, 20)   :done, s4_2, 2026-09-22, 2026-09-27
    Prueba de carga y Evaluación formal (REQ-21, 24)   :done, s4_3, 2026-09-25, 2026-09-30
    Incorporación BD y Socios (REQ-22, 23)             :done, s4_4, 2026-09-27, 2026-10-02

    section Sprint 5 — Frontend Blade y Accesibilidad
    Vistas Blade responsivas CU01 a CU07 (TSK-5.1)     :done, s5_1, 2026-10-01, 2026-10-04
    Sistema Tailwind v4 y Accesibilidad WCAG (TSK-5.2) :done, s5_2, 2026-10-02, 2026-10-05
    Integración API REST y PWA Offline (TSK-5.3, 5.4)  :done, s5_3, 2026-10-04, 2026-10-07

    section Sprint 6 — QA Frontend y Benchmark 500
    UI Notas Comunitarias y Accesibilidad (TSK-6.1)    :done, s6_1, 2026-10-05, 2026-10-07
    Suite Vitest Frontend 23 Tests (TSK-6.3)           :done, s6_2, 2026-10-06, 2026-10-08
    Prueba de Carga y Flujo Continuo RSS (TSK-6.5)     :done, s6_3, 2026-10-07, 2026-10-09

    section Sprint 7 — Despliegue CI/CD, Manuales y MVP
    Pipeline CI/CD GitHub Actions (TSK-7.1)            :done, s7_1, 2026-10-07, 2026-10-08
    Onboarding Redacciones Socias REQ-26 (TSK-7.2)     :done, s7_2, 2026-10-08, 2026-10-09
    Manuales Despliegue y Adulto Mayor REQ-27 (TSK-7.3):done, s7_3, 2026-10-08, 2026-10-09
    Registro Auditoría y Traspaso REQ-25, 28 (TSK-7.4) :done, s7_4, 2026-10-09, 2026-10-10

    section Evolución Post-MVP (v1.1.0 – v1.4.1)
    Neon SSL, OpenRouter y Mockups AF (REQ-29 a 32)    :done, ev_1, 2026-10-11, 2026-10-17
    Consejo Multi-Modelo e Ingesta masiva (REQ-33)     :done, ev_2, 2026-10-28, 2026-11-03
    Móvil Dual Capacitor + Expo SDK 58 + IPA (REQ-34)  :done, ev_3, 2026-11-04, 2026-11-09
    Normalización PG, OpenRouter & 169 Tests (REQ-35)  :done, ev_4, 2026-11-07, 2026-11-09
```

---

## Requisitos Previos del Sistema

| Herramienta | Versión Requerida | Propósito |
| :--- | :--- | :--- |
| **PHP** | `^8.3` o `8.4` | Intérprete principal del Backend Laravel 13 |
| **Extensiones PHP** | `pdo_pgsql`, `pgsql`, `pdo_mysql`, `sqlite3`, `intl`, `mbstring`, `curl`, `openssl` | Persistencia dual, pruebas en memoria y procesamiento UTF-8 |
| **Composer** | `2.x+` | Gestor de paquetes de PHP |
| **Node.js y npm** | `20.x` / `22.x+` (Node) y `10.x+` (npm) | Vite 8, Tailwind CSS v4, Vitest 5.0 y clientes móviles Capacitor / Expo |
| **Python** | `3.10+` o `3.11` | Microservicios FastAPI (OCR / STT) e ingesta RAG |
| **Base de Datos** | PostgreSQL 15+ (con `pgvector` en Neon.tech) o MariaDB 10.11+ | Almacenamiento relacional y búsqueda vectorial semántica |
| **Tesseract OCR** *(Opcional)* | `5.x+` con paquete de español (`spa`) | Extracción local de texto en imágenes |

---

## Guía de Instalación y Despliegue Paso a Paso

### Paso 1: Clonar el Repositorio
```bash
git clone https://github.com/ISI-CORP/ISI-2026-2-G06.git
cd ISI-2026-2-G06
```

### Paso 2: Backend (PHP 8.4 / Laravel 13)
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
cd ..
```

### Paso 3: Microservicios Multimedia (Python FastAPI)
```bash
cd backend/Microservicios_Python
python -m venv venv

# En Windows:
venv\Scripts\activate
# En Linux / macOS:
# source venv/bin/activate

pip install --upgrade pip
pip install -r requirements.txt
cd ../..
```

### Paso 4: Frontend y Assets (Node.js / Vite 8 / Tailwind CSS v4)
```bash
# Compilar assets del frontend modular
cd frontend
npm install
npm run build
cd ..

# Compilar assets del backend
cd backend
npm install
npm run build
cd ..
```

---

## Aplicaciones Móviles (`mobile/` Capacitor 6 y `mobile-expo/` Expo SDK 58)

El proyecto ofrece dos clientes móviles desacoplados que consumen la API REST v1:

1. **Contenedor Híbrido Android / iOS ([`mobile/`](mobile/), Capacitor 6):**
   - Empaqueta la compilación web accesible de `../frontend/dist` bajo el identificador `com.isicorp.dagaz`.
   - Sincronización local (requiere haber ejecutado `npm run build` en `frontend/`):
     ```bash
     cd mobile
     npm install
     npx cap sync android
     ```
2. **Aplicación Nativa Multiplataforma ([`mobile-expo/`](mobile-expo/), React Native `0.88.1` + Expo SDK 58):**
   - Ejecución inmediata en dispositivo físico iOS o Android mediante **Expo Go**:
     ```bash
     cd mobile-expo
     npm install
     npx expo start
     ```
   - Incluye **timeout extendido de 120 segundos** en las consultas de verificación para esperar el consenso completo del **Consejo Multi-Modelo**.
   - **Compilación `.ipa` sin Mac para Sideloadly:** El workflow [`.github/workflows/build-ios-ipa.yml`](.github/workflows/build-ios-ipa.yml) genera automáticamente en un runner `macos-latest` el artefacto `DAGAZ-iOS-Sideloadly-IPA` (`DAGAZ.ipa`). Más detalles en [`mobile-expo/README.md`](mobile-expo/README.md).

---

## Configuración de Variables de Entorno (.env)

Abra `backend/.env` (basado en [`backend/.env.example`](backend/.env.example)) y ajuste las credenciales según el entorno:

### Opción A: PostgreSQL 18 con pgvector (Neon Serverless — Producción)
```dotenv
DB_CONNECTION=pgsql
DB_URL="postgresql://USUARIO:CONTRASEÑA@HOST/BASE_DE_DATOS?sslmode=require"
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=Raiden
DB_USERNAME=root
DB_PASSWORD=
DB_SSLMODE=require
```

### Opción B: MariaDB Local (XAMPP / Laragon)
```dotenv
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=raiden
DB_USERNAME=root
DB_PASSWORD=
```

### Configuración de Inteligencia Artificial, Consejo Multi-Modelo y Microservicios
```dotenv
# Proveedor principal: openai | openrouter | gemini | local
DAGAZ_IA_PROVEEDOR=openai
OPENAI_API_KEY=
OPENROUTER_API_KEY=
GEMINI_API_KEY=
ANTHROPIC_API_KEY=
OLLAMA_ENDPOINT=http://localhost:11434/api/chat

# Consejo de Verificación Multi-Modelo (Harness en paralelo)
DAGAZ_MULTI_MODELO_HABILITADO=true
DAGAZ_MULTI_MODELO_TIMEOUT=20
DAGAZ_MULTI_MODELO_CALIBRAR=true
DAGAZ_COUNCIL_QWEN_ACTIVO=false

# Microservicios locales FastAPI
OCR_SERVICE_URL=http://localhost:5001/ocr
STT_SERVICE_URL=http://localhost:5002/stt

# Control de Costes (Circuit Breaker)
DAGAZ_MAX_TOKENS_POR_HORA=50000
DAGAZ_PRESUPUESTO_DIARIO_USD=5.00
```

---

## Inicialización y Migración de la Base de Datos

Ejecute los siguientes comandos dentro de `backend/`:

```bash
cd backend
php artisan migrate
php artisan db:seed
```
*Crea las 10 tablas base más `personal_access_tokens` (compatible con UUID en Neon), puebla las 80 fuentes IFCN y usuarios de prueba.*

Se encuentran disponibles seeders adicionales de noticias temáticas:
```bash
php artisan db:seed --class=NoticiasColombiaSeeder
php artisan db:seed --class=DocumentosNoticiasSeeder
```

---

## Puesta en Marcha y Ejecución del Sistema

### Terminal 1: Microservicios Multimedia Python (OCR y STT)
```bash
cd backend/Microservicios_Python
python run_services.py
```
* **OCR Service:** `http://localhost:5001` (Swagger UI: `http://localhost:5001/docs`)
* **STT Service:** `http://localhost:5002` (Swagger UI: `http://localhost:5002/docs`)

### Terminal 2: Servidor Web Backend Laravel (Blade SSR + API REST)
```bash
cd backend
php artisan serve
```
* Interfaz web ciudadana accesible en: **`http://127.0.0.1:8000`**

---

## Sincronización Periódica e Ingesta Masiva de Noticias

* **Sincronización RSS en tiempo real:**
  ```bash
  cd backend
  php artisan dagaz:actualizar-noticias
  ```
* **Ingesta y vectorización masiva (`vector(384)`):** Para poblar grandes volúmenes desde corpus colombianos usando el modelo de embeddings en Neon/PostgreSQL, utilice [`ingesta_noticias_colombia.py`](ingesta_noticias_colombia.py) o [`ingesta_noticias_colombia.ipynb`](ingesta_noticias_colombia.ipynb) configurando sus propias credenciales locales en variables de entorno.

---

## Batería de Pruebas Automatizadas (QA — 190 Tests)

Toda la suite automatizada se encuentra verificada pasando al 100%:

| Suite | Motor | Ubicación | Tests | Aserciones | Tasa de Éxito |
| :--- | :--- | :--- | :---: | :---: | :---: |
| **Backend REST & Dominio** | PHPUnit 12.5 | `backend/tests/` (Unit + Feature) | **163** | **2.464** | 100% ✅ |
| **Frontend UI & Accesibilidad** | Vitest 5.0 | `frontend/tests/` (8 archivos) | **23** | **68** | 100% ✅ |
| **Microservicios Multimedia** | `unittest` (FastAPI TestClient) | `backend/Microservicios_Python/tests/` | **4** | **12** | 100% ✅ |
| **TOTAL QA AUTOMATIZADO** | — | — | **190** | **2.544** | **100% ✅** |

### Comandos de Ejecución de Pruebas
```bash
# 1. Pruebas Backend (PHPUnit 12.5 — SQLite en memoria)
cd backend
php artisan test

# 2. Pruebas Frontend (Vitest 5.0 — Happy-DOM)
cd frontend
npm test -- --run

# 3. Pruebas Microservicios Python (unittest)
cd backend/Microservicios_Python
python -m unittest discover -s tests -v
```

---

## Catálogo de Endpoints de la API REST (`/api/v1`)

| Método | Endpoint | Caso de Uso / REQ | Descripción | Autenticación |
| :---: | :--- | :---: | :--- | :---: |
| `POST` | `/api/v1/auth/register` | CU05 | Registro de usuario con consentimiento Ley 1581 | Pública |
| `POST` | `/api/v1/auth/login` | CU05 | Autenticación y emisión de token Bearer Sanctum | Pública |
| `POST` | `/api/v1/auth/logout` | CU05 | Revocación de token y cierre de sesión | Bearer Token |
| `GET` | `/api/v1/auth/perfil`<br>`/api/v1/auth/datos-personales` | CU05 | Consulta de perfil y exportación ARCO (Hábeas Data) | Bearer Token |
| `DELETE` | `/api/v1/auth/cuenta` | CU05 | Derecho al olvido y supresión de cuenta | Bearer Token |
| `POST` | `/api/v1/verificacion` | CU06 | Ingesta y verificación multicanal (Texto, URL, OCR) | Pública / Bearer |
| `GET` | `/api/v1/verificacion/{idContenido}` | CU06 | Consulta de veredicto, certeza y fuentes | Pública / Bearer |
| `GET` | `/api/v1/fuentes` | CU07 | Listado de fuentes del Grafo de Credibilidad | Pública |
| `PATCH` | `/api/v1/fuentes/{idFuente}/valorar` | CU07 | Valoración cívica de una fuente | Bearer Token |
| `GET` | `/api/v1/historial` | CU04 | Historial cronológico de verificaciones | Bearer Token |
| `POST` | `/api/v1/historial/{idContenido}` | CU03 | Guardar contenido verificado en colección personal | Bearer Token |
| `POST` | `/api/v1/contenidos/{idContenido}/notas` | CU01 | Agregar nota comunitaria complementaria | Bearer Token |
| `PATCH` | `/api/v1/notas/{idNota}/calificar` | CU02 | Calificar utilidad cívica de una nota | Bearer Token |
| `POST` | `/api/v1/veredictos/{idVeredicto}/intervenir` | REQ-13 | Intervención y revisión editorial | Rol `admin`/`editor` |
| `POST` | `/api/v1/public/verificar` | REQ-16 | Endpoint M2M de verificación para redacciones socias | `X-API-Key` |
| `GET` | `/api/v1/public/veredictos/{idContenido}`<br>`/api/v1/public/fuentes` | REQ-16 | Consulta M2M de veredictos y grafo de 80 fuentes | `X-API-Key` |
| `GET` | `/api/v1/auditoria/veredictos/{idContenido}`<br>`/api/v1/auditoria/expedientes`<br>`/api/v1/auditoria/claim-review/{idContenido}` | REQ-25 | Exportación forense (JSON, CSV, ClaimReview Schema.org) | Rol `admin`/`editor` |
| `GET` | `/api/health` · `/api/docs` | Infra | Health check y contrato OpenAPI 3.1 YAML | Pública |

> La especificación completa reside en [`backend/routes/api.php`](backend/routes/api.php) y [`backend/storage/api-docs/openapi.yaml`](backend/storage/api-docs/openapi.yaml).

---

## Documentación y Enlaces a Entregables

Toda la documentación técnica se encuentra centralizada en [`docs/`](docs/):

- 📚 [**Centro de Documentación Integral (`docs/README.md`)**](docs/README.md)
- 📋 [**Historial de Control de Cambios (`docs/iteracion-02/Guidelines/Historial_de_Control.md`)**](docs/iteracion-02/Guidelines/Historial_de_Control.md)
- 📊 [**Sprints Backlogs Realizados (`docs/iteracion-02/Guidelines/Sprint_Backlog_Realizados.md`)**](docs/iteracion-02/Guidelines/Sprint_Backlog_Realizados.md)
- 📅 [**Diagrama de Gantt Interactivo (`docs/iteracion-02/Guidelines/Diagrama_de_Gantt.md`)**](docs/iteracion-02/Guidelines/Diagrama_de_Gantt.md)
- 🎯 [**Backlog del Proyecto (`docs/iteracion-02/Guidelines/Backlog del Proyecto.md`)**](docs/iteracion-02/Guidelines/Backlog%20del%20Proyecto.md)
- 🎨 [**Mockups de Alta Fidelidad WCAG 2.1 AA (`docs/mockups-alta-fidelidad/`)**](docs/mockups-alta-fidelidad/)
- 📜 [**Changelog del Sistema (`CHANGELOG.md`)**](CHANGELOG.md)
- 🗄️ [**Diseño de Base de Datos y Modelo Raiden (`database/Documentacion-Raiden.md`)**](database/Documentacion-Raiden.md)
- 🔍 [**Benchmark de 500 Afirmaciones y Auditoría de Sesgo**](docs/iteracion-final/Evaluacion_Benchmark_500_Afirmaciones_y_Auditoria_Sesgo.md)
- 📖 [**Manual de Usuario para Adultos (`docs/iteracion-final/Manual_Usuario_Adultos.md`)**](docs/iteracion-final/Manual_Usuario_Adultos.md)
- 🛠️ [**Manual de Despliegue e Infraestructura (`docs/iteracion-final/Manual_Despliegue_Infraestructura.md`)**](docs/iteracion-final/Manual_Despliegue_Infraestructura.md)
- 🤝 [**Onboarding de Redacciones Socias (`docs/iteracion-final/Onboarding_Redacciones_Socias_REQ26.md`)**](docs/iteracion-final/Onboarding_Redacciones_Socias_REQ26.md)
- 🏁 [**Retrospectiva Final y Acta de Traspaso (`docs/iteracion-final/Retrospectiva_Final_y_Acta_Traspaso_REQ28.md`)**](docs/iteracion-final/Retrospectiva_Final_y_Acta_Traspaso_REQ28.md)

---

## Solución de Problemas Frecuentes (Troubleshooting)

### 1. Error de controladores de base de datos (`could not find driver`)
* **Causa:** Extensiones de PHP deshabilitadas en `php.ini`.
* **Solución:** Descomente `extension=pdo_pgsql`, `extension=pgsql`, `extension=pdo_mysql`, `extension=sqlite3`, `extension=intl` y `extension=curl` en `php.ini`.

### 2. Error SSL con PostgreSQL Neon Serverless
* **Causa:** Neon exige cifrado TLS/SSL estricto.
* **Solución:** Verifique `DB_SSLMODE=require` en `backend/.env` y que `DB_URL` incluya `?sslmode=require`.

### 3. Tesseract OCR no detectado en Python
* **Causa:** Binario de Tesseract fuera del `PATH` del sistema.
* **Solución:** En Windows, instale Tesseract OCR y añada `C:\Program Files\Tesseract-OCR` al `PATH`; en Linux ejecute `sudo apt install tesseract-ocr tesseract-ocr-spa`.

### 4. Permisos de carpetas `storage` y `bootstrap/cache`
* **Causa:** Permisos insuficientes en entornos Unix/Linux.
* **Solución:** Ejecute `chmod -R 775 backend/storage backend/bootstrap/cache`.

---

## Integrantes del Proyecto

| Integrante | Código | Rol |
| :--- | :---: | :--- |
| **Alberto Mario Galvis Echeverria** | `0222410070` | Analista de Requerimientos y Calidad (QA) / Desarrollador FullStack |
| **Camilo Isaac Acevedo Rueda** | `0222410049` | Analista de Calidad (QA) / Desarrollador FullStack |
