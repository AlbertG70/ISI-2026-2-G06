# Sprint Backlogs Realizados y Evolución Post-MVP — Proyecto DAGAZ

> **Sistema:** DAGAZ — Plataforma Multicanal de Detección de Desinformación y Verificación de Contenidos  
> **Organización:** ISI-CORP (Periodo Académico 2026-II)  
> **Equipo de Desarrollo (Developers):** Alberto Mario Galvis Echeverria (`0222410070`), Camilo Isaac Acevedo Rueda (`0222410049`)  
> **Metodología de Gestión:** Scrum con Enfoque de Ingeniería *Backend-First*  
> **Fecha de Corte:** 09 de Noviembre de 2026 (Rama activa: `Frontend-Raiden` | Versión vigente: **`v1.4.1`**)  
> **Alcance de este Documento:** Detalle exhaustivo de los Sprint Backlogs ejecutados desde el **Sprint 1** hasta el **Sprint 7** más el **Bloque Evolutivo Post-MVP (`v1.0.0`–`v1.4.1`)**.

---

## 📑 Tabla de Contenidos

1. [Visión General y Resumen Ejecutivo de Sprints](#1-visión-general-y-resumen-ejecutivo-de-sprints)
2. [Sprint Backlog 1: Arquitectura Base, Persistencia PostgreSQL y Autenticación](#2-sprint-backlog-1-arquitectura-base-persistencia-postgresql-y-autenticación)
3. [Sprint Backlog 2: Pipeline de Ingesta Multicanal y Motor Central de Veredictos (Core IA)](#3-sprint-backlog-2-pipeline-de-ingesta-multicanal-y-motor-central-de-veredictos-core-ia)
4. [Sprint Backlog 3: Grafo de 80 Fuentes IFCN, Monitoreo de Redes y Módulos Comunitarios](#4-sprint-backlog-3-grafo-de-80-fuentes-ifcn-monitoreo-de-redes-y-módulos-comunitarios)
5. [Sprint Backlog 4: API Pública para Redacciones, Circuit Breaker y Auditoría Forense](#5-sprint-backlog-4-api-pública-para-redacciones-circuit-breaker-y-auditoría-forense)
6. [Sprint Backlog 5: Capa Frontend Móvil Responsiva (+40), Vistas Blade y Servidor Ligero](#6-sprint-backlog-5-capa-frontend-móvil-responsiva-40-vistas-blade-y-servidor-ligero)
7. [Sprint Backlog 6: Benchmark Formal de 500 Afirmaciones, Auditoría de Sesgo y Piloto](#7-sprint-backlog-6-benchmark-formal-de-500-afirmaciones-auditoría-de-sesgo-y-piloto)
8. [Sprint Backlog 7: Pipeline CI/CD, Onboarding y Traspaso Pre-Alpha](#8-sprint-backlog-7-pipeline-cicd-onboarding-y-traspaso-pre-alpha)
9. [Bloque Evolutivo Post-MVP (`v1.0.0` a `v1.4.1`: Multi-Modelo, Móvil Dual y Normalización)](#9-bloque-evolutivo-post-mvp-v100-a-v141-multi-modelo-móvil-dual-y-normalización)
10. [Matriz de Trazabilidad Integral: Requisitos ↔ Tareas ↔ Código ↔ Pruebas](#10-matriz-de-trazabilidad-integral-requisitos--tareas--código--pruebas)
11. [Conclusiones y Estado del Incremento Vigente (`v1.4.1`)](#11-conclusiones-y-estado-del-incremento-vigente-v141)

---

## 1. Visión General y Resumen Ejecutivo de Sprints

El proyecto DAGAZ fue orquestado bajo ciclos iterativos de **2 semanas por sprint (10 días hábiles)** para el MVP y liberaciones continuas post-MVP. Siguiendo el principio de arquitectura **Backend-First**, se aseguró la solidez transaccional, el contraste semántico y vectorial, y los contratos OpenAPI 3.1 antes de acoplar las interfaces gráficas web y las aplicaciones móviles duales.

### 1.1 Resumen Comparativo de Sprints y Evolución Ejecutada

| Sprint / Bloque | Período de Ejecución | Enfoque Tecnológico, de Ingeniería y Negocio | Pts Comprometidos | Pts Logrados | % Cumplimiento | Hito / Quality Gate |
| :---: | :---: | :--- | :---: | :---: | :---: | :--- |
| **Sprint 1** | 23-Ago-26 – 05-Sep-26 | **Ingeniería Base & Persistencia:** Acciones 1–6 (22 pts) + PostgreSQL 18 `pgvector` en Neon, DDL Raiden, Modelos **Laravel 13** y Auth Sanctum (23 pts). | 45 pts | 45 pts | **100%** | Persistencia & Modelo Base Certificado |
| **Sprint 2** | 06-Sep-26 – 19-Sep-26 | **Casos de Uso, UI & Core IA:** Acciones 7–11 (31 pts) + Pipeline IA de 14 etapas, Strategy Drivers y Microservicios Python OCR/STT (42 pts). | 73 pts | 73 pts | **100%** | **Gate 0:** Aprobación Supervisor (4.0/5.0) |
| **Sprint 3** | 20-Sep-26 – 03-Oct-26 | **Grafo 80 Fuentes, Redes & Comunidad:** Grafo 80 Fuentes IFCN, Ingesta 8.200 noticias Neon, Conector Archive.org, Notas (CU01/02) e Historial (CU03/04). | 41 pts | 41 pts | **100%** | Grafo de 80 fuentes y 8k corpus activo |
| **Sprint 4** | 27-Sep-26 – 02-Oct-26 | **API Pública, Resiliencia & Auditoría:** API M2M `/api/v1/public/*`, Circuit Breaker, Interlingüe ES-EN, Auditoría ClaimReview. | 33 pts | 33 pts | **100%** | **Gate 1: Backend Freeze** (107 Tests PHPUnit) |
| **Sprint 5** | 02-Oct-26 – 05-Oct-26 | **Frontend Accesible (+40), Blade & Vitest:** Vite 8 + Tailwind CSS v4 (WCAG 2.1 AA), Ingesta Multicanal Web, Vistas Blade en `frontend/views/`, 23 Vitest. | 26 pts | 26 pts | **100%** | **Gate 2: Frontend Freeze** (23 Vitest + 122 PHPUnit) |
| **Sprint 6** | 03-Oct-26 – 06-Oct-26 | **Benchmark 500, Sesgo & Piloto:** Benchmark 500 afirmaciones ($F_1 = 0{,}828$), Auditoría Sesgo ($\chi^2 = 0{,}682$, $p > 0{,}05$), Piloto Redacción 1 (50 veredictos). | 39 pts | 39 pts | **100%** | Certificación Estadística e Imparcialidad |
| **Sprint 7** | 05-Oct-26 – 06-Oct-26 | **CI/CD, Onboarding & Traspaso:** Pipeline `ci.yml`, Onboarding Socios 2 y 3, Manuales `REQ-27` y Retrospectiva `REQ-28`. | 24 pts | 24 pts | **100%** | **Gate 3: Pre-Alpha (`v0.1.0`)** |
| **Post-MVP (`v1.0–v1.4.1`)** | 10-Oct-26 – 09-Nov-26 | **MVP, Multi-Modelo, Móvil Dual & Resiliencia 2026:** `v1.0.0`, Neon UUID, OpenRouter, 10 Mockups AF, `MultiModeloHarnessService`, Capacitor 6 + Expo SDK 58 + CI `.ipa`, `normalizarSinTildes()` PG. | 39 pts | 39 pts | **100%** | **Gates 4 & 5: Release `v1.4.1` (169 Tests)** |
| **Total Realizado** | **Ciclo Integral** | **Sprints 1–7 (281 pts) + Bloque Evolutivo (39 pts)** | **320 pts** | **320 pts** | **100%** | **169 Tests Automatizados (100% Pasando)** |

```text
Progreso General del Esfuerzo Acumulado (S1 - S7 + Evolutivo v1.4.1):
[██████████████████████████████████████████████████] 100% Completado (320 / 320 Puntos de Esfuerzo)
```

---

## 2. Sprint Backlog 1: Ingeniería de Requisitos, Modelado del Negocio, Persistencia y Autenticación

* **Período:** 23 de Agosto de 2026 al 05 de Septiembre de 2026 (Semanas 1 y 2).  
* **Sprint Goal:** Establecer los fundamentos metodológicos y de ingeniería del sistema en paralelo con la construcción del núcleo transaccional en **Laravel 13**, persistencia en PostgreSQL 18 (`pgvector` en Neon.tech), autenticación por roles (CU05) y catálogo inicial de fuentes Nivel 1.  
* **Requisitos y Acciones Asociadas:** `ACT-01` a `ACT-06`, `REQ-08`, `REQ-12`, `REQ-23`, `CU05`.

### 2.1 Ingeniería de Requisitos, Análisis y Modelado del Negocio (Acciones 1 a 6 — 22 pts)

| # | Acción de Ingeniería / Modelado | Descripción Técnica y Entregable | Responsable | Estimación | Pts | Estado | Artefacto / Evidencia en Repositorio |
| :-: | :--- | :--- | :--- | :---: | :---: | :---: | :--- |
| **ACT-01** | **Matriz de ideación/identificación de problemáticas** | Diagnóstico del fenómeno de desinformación electoral en Colombia, matriz de problemas y justificación formal. | Equipo Scrum | 3 días | 3 | ✅ Hecho | [`01_Contexto_y_Metodologia_Dagaz.md`](../../iteracion-01/Guideline-Persistencia/01_Contexto_y_Metodologia_Dagaz.md) |
| **ACT-02** | **Elección de metodología** | Evaluación comparativa RUP vs. Scrum y adopción de Scrum con enfoque Backend-First. | Equipo Scrum | 4 días | 5 | ✅ Hecho | [`01_Contexto_y_Metodologia_Dagaz.md`](../../iteracion-01/Guideline-Persistencia/01_Contexto_y_Metodologia_Dagaz.md) |
| **ACT-03** | **Análisis de negocio** | Formulación de objetivos general/específicos y propuesta de valor del servicio. | Equipo Scrum | 3 días | 3 | ✅ Hecho | [`01_Contexto_y_Metodologia_Dagaz.md`](../../iteracion-01/Guideline-Persistencia/01_Contexto_y_Metodologia_Dagaz.md) |
| **ACT-04** | **Modelado del negocio** | Lienzo Canvas del Modelo de Negocios y diagrama de Modelo de Dominio de verificación de hechos. | Equipo Scrum | 4 días | 5 | ✅ Hecho | [`Canvas_Modelo_Negocio.png`](../../iteracion-01/Diagramas/Canvas_Modelo_Negocio.png), [`Modelo_Dominio.png`](../../iteracion-01/Diagramas/Modelo_Dominio.png) |
| **ACT-05** | **Análisis de requisitos** | Estructuración inicial de los 28 requisitos en 4 fases de prioridad (ampliado a 35 en `v1.4.1`). | Alberto Galvis | 3 días | 3 | ✅ Hecho | [`Backlog del Proyecto.md`](Backlog%20del%20Proyecto.md) |
| **ACT-06** | **Modelado de requisitos** | Diseño de taxonomía visual mapeando requisitos funcionales, no funcionales y dependencias. | Camilo Acevedo | 3 días | 3 | ✅ Hecho | [`Modelo_Requisitos.png`](../../iteracion-01/Diagramas/Modelo_Requisitos.png) |

### 2.2 Tareas de Persistencia, Base de Datos y Autenticación (TSK-1.1 a TSK-1.7 — 23 pts)

| ID Tarea | Módulo / Capa | Descripción Técnica de la Tarea | Responsable | Estimación | Pts | Estado |
| :--- | :--- | :--- | :--- | :---: | :---: | :---: |
| **TSK-1.1** | Infraestructura | Configurar conexión remota a PostgreSQL 18 sobre Neon.tech con soporte SSL y extensión `vector`, manteniendo compatibilidad local con MariaDB y SQLite. | Alberto Galvis | 5 días | 2 | ✅ Completado |
| **TSK-1.2** | Base de Datos | Diseñar y ejecutar script DDL maestro [`Raiden.sql`](../../../database/Raiden.sql) y migraciones Laravel con las 10 tablas de dominio con UUIDs nativos (complementado con `personal_access_tokens` UUID para Sanctum). | Alberto Galvis | 5 días | 5 | ✅ Completado |
| **TSK-1.3** | Base de Datos | Crear índices B-Tree relacionales y estrategia de búsqueda KNN sin HNSW para respetar cuota de RAM en Neon.tech. | Camilo Acevedo | 4 días | 2 | ✅ Completado |
| **TSK-1.4** | Dominio / ORM | Implementar 10 modelos Eloquent con integridad referencial, casteos `jsonb`/`array`, timestamps y `HasUuids`. | Alberto Galvis | 4 días | 3 | ✅ Completado |
| **TSK-1.5** | Semillas de Datos | Implementar [`FuentesSeeder.php`](../../../backend/database/seeders/FuentesSeeder.php) con las fuentes iniciales Nivel 1 (luego ampliado a 80). | Camilo Acevedo | 3 días | 3 | ✅ Completado |
| **TSK-1.6** | Seguridad / Auth | Implementar [`AuthController.php`](../../../backend/app/Http/Controllers/AuthController.php) con emisión de tokens Sanctum, hashing Bcrypt y middleware de roles ([`VerificarRol.php`](../../../backend/app/Http/Middleware/VerificarRol.php)). | Alberto Galvis | 3 días | 5 | ✅ Completado |
| **TSK-1.7** | QA & Testing | Crear batería de pruebas para autenticación y perfiles ([`AuthTest.php`](../../../backend/tests/Feature/AuthTest.php)). | Camilo Acevedo | 2 días | 3 | ✅ Completado |

**Balance del Sprint 1:** 45 / 45 Puntos Completados (100%).

### 2.3 Definición de Hecho (DoD) y Evidencias
- [x] Matriz de problemas y objetivos: [`01_Contexto_y_Metodologia_Dagaz.md`](../../iteracion-01/Guideline-Persistencia/01_Contexto_y_Metodologia_Dagaz.md).
- [x] Canvas y Modelo de Dominio: [`Canvas_Modelo_Negocio.png`](../../iteracion-01/Diagramas/Canvas_Modelo_Negocio.png), [`Modelo_Dominio.png`](../../iteracion-01/Diagramas/Modelo_Dominio.png).
- [x] DDL maestro y diccionario BD: [`database/Raiden.sql`](../../../database/Raiden.sql), [`database/Documentacion-Raiden.md`](../../../database/Documentacion-Raiden.md), [`database/Diagrama-ER.md`](../../../database/Diagrama-ER.md).
- [x] Modelos Eloquent: [`Usuario.php`](../../../backend/app/Models/Usuario.php), [`Fuente.php`](../../../backend/app/Models/Fuente.php), [`Documento.php`](../../../backend/app/Models/Documento.php), [`Contenido.php`](../../../backend/app/Models/Contenido.php), [`Veredicto.php`](../../../backend/app/Models/Veredicto.php).
- [x] Seeders: [`UsuariosSeeder.php`](../../../backend/database/seeders/UsuariosSeeder.php) y [`FuentesSeeder.php`](../../../backend/database/seeders/FuentesSeeder.php).

---

## 3. Sprint Backlog 2: Casos de Uso, Secuencias, Mockups de UI y Pipeline Core IA

* **Período:** 06 de Septiembre de 2026 al 19 de Septiembre de 2026 (Semanas 3 y 4).  
* **Sprint Goal:** Culminar el modelado conductual (`CU01`–`CU07`, UML, DSS Caja Negra/Gris, Mockups UI) en sincronía con el pipeline de 14 etapas de inferencia IA en **Laravel 13**, drivers Strategy y microservicios Python OCR/STT.  
* **Hito Intermedio:** **GATE 0 — Aprobación Formal del Supervisor (2026-09-14)** con calificación **4.0 / 5.0**.  
* **Requisitos Asociados:** `ACT-07` a `ACT-11`, `REQ-01` a `REQ-07`, `CU06`.

### 3.1 Análisis de Casos de Uso, Secuencias y Mockups de UI (Acciones 7 a 11 — 31 pts)

| # | Acción de Ingeniería / Modelado | Descripción Técnica y Entregable | Responsable | Estimación | Pts | Estado | Artefacto / Evidencia en Repositorio |
| :-: | :--- | :--- | :--- | :---: | :---: | :---: | :--- |
| **ACT-07** | **Análisis de casos de uso** | Delimitación de CU01 a CU07 e identificación de actores. | Alberto Galvis | 4 días | 5 | ✅ Hecho | [`02_Especificacion_Casos_de_Uso.md`](../../iteracion-01/Guideline-Persistencia/02_Especificacion_Casos_de_Uso.md) |
| **ACT-08** | **Descripción de casos de uso** | Fichas narrativas con precondiciones, flujos básicos/alternativos y postcondiciones. | Camilo Acevedo | 4 días | 5 | ✅ Hecho | [`02_Especificacion_Casos_de_Uso.md`](../../iteracion-01/Guideline-Persistencia/02_Especificacion_Casos_de_Uso.md) |
| **ACT-09** | **Modelado de casos de uso** | Diagramación UML formal con relaciones `<<include>>` y `<<extend>>`. | Alberto Galvis | 5 días | 8 | ✅ Hecho | [`Diagrama_Casos_de_Uso.png`](../../iteracion-01/Diagramas/Diagrama_Casos_de_Uso.png) |
| **ACT-10** | **Modelado de secuencias** | Diagramas DSS Caja Negra y Caja Gris con flujos alternativos. | Camilo Acevedo | 4 días | 5 | ✅ Hecho | [`DSS (Caja Negra)/`](../../iteracion-01/Diagramas/DSS%20%28Caja%20Negra%29/) y [`DSS (Caja Gris)/`](../../iteracion-01/Diagramas/DSS%20%28Caja%20Gris%29/) |
| **ACT-11** | **Propuesta de mockups de UI** | Prototipos de baja fidelidad accesibles para Adultos (+40). | Alberto Galvis | 5 días | 8 | ✅ Hecho | [`Mockups/`](../../iteracion-01/Mockups/) (`CU01` a `CU07`) |

### 3.2 Tareas de Implementación Técnica (Pipeline IA, Microservicios y Veredicto — 42 pts)

| ID Tarea | Módulo / Capa | Descripción Técnica de la Tarea | Responsable | Estimación | Pts | Estado |
| :--- | :--- | :--- | :--- | :---: | :---: | :---: |
| **TSK-2.1** | Adaptador IA | Diseñar [`IaDriverFactory.php`](../../../backend/app/Adapters/Ia/IaDriverFactory.php) y drivers ([`OpenAiDriver.php`](../../../backend/app/Adapters/Ia/OpenAiDriver.php), [`GeminiDriver.php`](../../../backend/app/Adapters/Ia/GeminiDriver.php), [`LocalLlmDriver.php`](../../../backend/app/Adapters/Ia/LocalLlmDriver.php)). | Alberto Galvis | 5 días | 5 | ✅ Completado |
| **TSK-2.2** | Pipeline IA | Implementar fases de extracción y atomización de afirmaciones dentro de [`VerificacionService.php`](../../../backend/app/Services/VerificacionService.php). | Alberto Galvis | 5 días | 8 | ✅ Completado |
| **TSK-2.3** | Microservicios Python | Construir microservicios FastAPI: OCR en `:5001` ([`ocr_service/app.py`](../../../backend/Microservicios_Python/ocr_service/app.py) con Pillow/pytesseract) y STT en `:5002` ([`stt_service/app.py`](../../../backend/Microservicios_Python/stt_service/app.py) con SpeechRecognition), integrados mediante [`AdaptadorMulticanal.php`](../../../backend/app/Adapters/AdaptadorMulticanal.php). | Camilo Acevedo | 6 días | 5 | ✅ Completado |
| **TSK-2.4** | Búsqueda Semántica | Implementar [`BusquedaSemanticaService.php`](../../../backend/app/Services/BusquedaSemanticaService.php) con distancia coseno `<=>` en `pgvector` y coincidencia léxica híbrida. | Alberto Galvis | 5 días | 8 | ✅ Completado |
| **TSK-2.5** | Verificación Cruzada | Diseñar algoritmo de contraste ponderado según credibilidad de fuentes del grafo. | Alberto Galvis | 4 días | 8 | ✅ Completado |
| **TSK-2.6** | Síntesis de Veredicto | Emitir veredictos (`VERDADERO`, `FALSO`, `ENGAÑOSO`, `PARCIAL`, `NO_VERIFICABLE`) con `score_confianza` y citas en `veredictos` y `veredicto_fuentes`. | Camilo Acevedo | 4 días | 5 | ✅ Completado |
| **TSK-2.7** | Telemetría & Logs | Registrar latencias, tokens y fallos en [`LogComunicadorApi.php`](../../../backend/app/Models/LogComunicadorApi.php). | Camilo Acevedo | 2 días | 3 | ✅ Completado |

**Balance del Sprint 2:** 73 / 73 Puntos Completados (100%).

### 3.3 Definición de Hecho (DoD) y Evidencias
- [x] Pipeline orquestado en [`VerificacionService.php`](../../../backend/app/Services/VerificacionService.php) y [`VerificacionController.php`](../../../backend/app/Http/Controllers/VerificacionController.php).
- [x] Pruebas Python: [`test_services.py`](../../../backend/Microservicios_Python/tests/test_services.py) (`4/4` tests `unittest` pasando).
- [x] Pruebas unitarias core: [`VerificacionServiceTest.php`](../../../backend/tests/Unit/VerificacionServiceTest.php) (`14` pruebas vigentes).

---

## 4. Sprint Backlog 3: Grafo de 80 Fuentes IFCN, Monitoreo de Redes y Módulos Comunitarios

* **Período:** 20 de Septiembre de 2026 al 03 de Octubre de 2026 (Semanas 5 y 6).  
* **Sprint Goal:** Escalar el grafo de credibilidad a 80 fuentes en 4 niveles IFCN, poblar corpus vectorizado de noticias en Neon.tech, conectar Archive.org para detectar descontextualizaciones y desplegar módulos comunitarios (`CU01`–`CU04`, `CU07`).  
* **Requisitos Asociados:** `REQ-09`, `REQ-10`, `REQ-13`, `REQ-14`, `REQ-20`, `CU01`–`CU04`, `CU07`.

| ID Tarea | Módulo / Capa | Descripción Técnica de la Tarea | Responsable | Estimación | Pts | Estado |
| :--- | :--- | :--- | :--- | :---: | :---: | :---: |
| **TSK-3.1** | Grafo de Credibilidad | Expandir catálogo a **80 fuentes** en 4 niveles IFCN en [`FuentesSeeder.php`](../../../backend/database/seeders/FuentesSeeder.php). | Camilo Acevedo | 6 días | 8 | ✅ Completado |
| **TSK-3.2** | Ingesta Masiva / Corpus | Pipeline `all-MiniLM-L6-v2` con embeddings `vector(384)` para 8.200+ noticias en `documentos` (Neon.tech). | Alberto Galvis | 7 días | 8 | ✅ Completado |
| **TSK-3.3** | Conector Archive.org | Implementar [`ArchiveOrgService.php`](../../../backend/app/Services/ArchiveOrgService.php) para snapshots históricos Wayback Machine. | Alberto Galvis | 5 días | 5 | ✅ Completado |
| **TSK-3.4** | Notas Comunitarias | Endpoints y modelos para `CU01` y `CU02` en [`Nota.php`](../../../backend/app/Models/Nota.php). | Camilo Acevedo | 6 días | 10 | ✅ Completado |
| **TSK-3.5** | Historial de Usuario | Endpoints para `CU03` y `CU04` en [`Historial.php`](../../../backend/app/Models/Historial.php). | Camilo Acevedo | 5 días | 5 | ✅ Completado |
| **TSK-3.6** | Moderación de Fuentes | Valoración ciudadana y gestión de fuentes (`CU07`) en [`Fuente.php`](../../../backend/app/Models/Fuente.php). | Alberto Galvis | 4 días | 5 | ✅ Completado |

**Balance del Sprint 3:** 41 / 41 Puntos Completados (100%).  
**Evidencias:** [`GrafoFuentesTest.php`](../../../backend/tests/Feature/GrafoFuentesTest.php), [`ArchiveOrgTest.php`](../../../backend/tests/Feature/ArchiveOrgTest.php), [`VerificacionFeatureTest.php`](../../../backend/tests/Feature/VerificacionFeatureTest.php).

---

## 5. Sprint Backlog 4: API Pública para Redacciones, Circuit Breaker y Auditoría Forense

* **Período:** 27 de Septiembre de 2026 al 02 de Octubre de 2026 (Semanas 7 y 8).  
* **Sprint Goal:** Exponer la API REST M2M para salas de redacción socias, implementar Circuit Breaker de costes LLM, recuperación interlingüe ES-EN, exportación forense ClaimReview y congelar el núcleo backend (`GATE 1: 107 pruebas PHPUnit`).  
* **Requisitos Asociados:** `REQ-16`, `REQ-18`, `REQ-19`, `REQ-25`.

| ID Tarea | Módulo / Capa | Descripción Técnica de la Tarea | Responsable | Estimación | Pts | Estado |
| :--- | :--- | :--- | :--- | :---: | :---: | :---: |
| **TSK-4.1** | API Pública REST | Desarrollar [`PublicPartnerApiController.php`](../../../backend/app/Http/Controllers/PublicPartnerApiController.php) con rutas `POST /api/v1/public/verificar`, `GET /api/v1/public/veredictos/{idContenido}`, `/fuentes` y `/health` bajo [`openapi.yaml`](../../../backend/storage/api-docs/openapi.yaml). | Alberto Galvis | 4 días | 5 | ✅ Completado |
| **TSK-4.2** | Seguridad M2M | Implementar middleware [`EnsureValidApiKey.php`](../../../backend/app/Http/Middleware/EnsureValidApiKey.php) con `X-API-KEY` / `Authorization: Api-Key` y `throttle:partners`. | Alberto Galvis | 4 días | 5 | ✅ Completado |
| **TSK-4.3** | Resiliencia & Finanzas | Construir [`CircuitBreakerService.php`](../../../backend/app/Services/CircuitBreakerService.php) (`CLOSED`, `OPEN`, `HALF_OPEN`) con cuotas horarias y diarias. | Camilo Acevedo | 3 días | 5 | ✅ Completado |
| **TSK-4.4** | Lingüística IA | Implementar [`ServicioInterlingue.php`](../../../backend/app/Services/ServicioInterlingue.php) para detección, traducción y análisis de distorsión ES-EN. | Alberto Galvis | 3 días | 8 | ✅ Completado |
| **TSK-4.5** | Gobernanza / Auditoría | Desarrollar [`AuditoriaExportService.php`](../../../backend/app/Services/AuditoriaExportService.php) y [`AuditoriaController.php`](../../../backend/app/Http/Controllers/AuditoriaController.php) con salida `ClaimReview` JSON-LD, CSV UTF-8 BOM y JSON forense SHA-256. | Camilo Acevedo | 2 días | 5 | ✅ Completado |
| **TSK-4.6** | QA / Gate 1 | Certificar batería inicial de **107 pruebas PHPUnit** al cierre del sprint (2.107 aserciones). | Equipo Scrum | 2 días | 5 | ✅ Completado |

**Balance del Sprint 4:** 33 / 33 Puntos Completados (100%).  
**Evidencias:** [`PublicPartnerApiTest.php`](../../../backend/tests/Feature/PublicPartnerApiTest.php), [`InterlingueTest.php`](../../../backend/tests/Feature/InterlingueTest.php), [`AuditoriaExportTest.php`](../../../backend/tests/Feature/AuditoriaExportTest.php), [`BackendAdvancedFeatureTest.php`](../../../backend/tests/Feature/BackendAdvancedFeatureTest.php).

---

## 6. Sprint Backlog 5: Capa Frontend Móvil Responsiva (+40), Vistas Blade y Servidor Ligero

* **Período:** 02 de Octubre de 2026 al 05 de Octubre de 2026 (Semanas 9 y 10).  
* **Sprint Goal:** Implementar la capa de presentación accesible bajo WCAG 2.1 AA para Adultos (+40), empaquetada con Vite 8 + Tailwind CSS v4 sobre vistas Blade en `frontend/views/`, además de páginas estáticas de respaldo, servidor ligero `api_server.php` y pruebas Vitest.  
* **Hito Alcanzado:** **GATE 2 — Frontend & Multicanal Freeze (2026-10-05)**.  
* **Requisitos Asociados:** `REQ-02`, `REQ-07`, `REQ-15`, `CU01`–`CU07` UI.

| ID Tarea | Módulo / Capa | Descripción Técnica de la Tarea | Responsable | Estimación | Pts | Estado |
| :--- | :--- | :--- | :--- | :---: | :---: | :---: |
| **TSK-5.1** | UI Framework | Configurar Vite 8 + Tailwind CSS v4 con tipografía escalable ($\ge 18\text{px}$), selector `A / A+` y contraste alto. | Alberto Galvis | 2 días | 5 | ✅ Completado |
| **TSK-5.2** | Accesibilidad (+40) | Estandarizar botones táctiles ($\ge 48\text{px}$), semáforo accesible y controles ARIA en [`frontend/`](../../../frontend/). | Camilo Acevedo | 3 días | 3 | ✅ Completado |
| **TSK-5.3** | Ingesta Multicanal | Implementar [`frontend/views/verificacion/index.blade.php`](../../../frontend/views/verificacion/index.blade.php) y [`frontend/index.html`](../../../frontend/index.html) con pestañas Texto, URL, Imagen OCR y Audio STT. | Alberto Galvis | 2 días | 5 | ✅ Completado |
| **TSK-5.4** | Tarjeta de Veredicto | Implementar [`frontend/views/verificacion/resultado.blade.php`](../../../frontend/views/verificacion/resultado.blade.php) y [`frontend/resultado.html`](../../../frontend/resultado.html) con medidor circular SVG y citas auditables. | Alberto Galvis | 2 días | 5 | ✅ Completado |
| **TSK-5.5** | Explorador de Fuentes | Construir [`frontend/views/fuentes/index.blade.php`](../../../frontend/views/fuentes/index.blade.php) y [`frontend/fuentes.html`](../../../frontend/fuentes.html) para consultar las 80 fuentes IFCN. | Camilo Acevedo | 2 días | 3 | ✅ Completado |
| **TSK-5.6** | Servidor Ligero & CORS | Crear [`backend/api_server.php`](../../../backend/api_server.php) y configurar [`backend/config/cors.php`](../../../backend/config/cors.php). | Alberto Galvis | 2 días | 3 | ✅ Completado |
| **TSK-5.7** | Testing Requisitos | Crear [`DocumentosNoticiasSeeder.php`](../../../backend/database/seeders/DocumentosNoticiasSeeder.php) y [`VerificacionesBackendFeatureTest.php`](../../../backend/tests/Feature/VerificacionesBackendFeatureTest.php). | Camilo Acevedo | 1 día | 2 | ✅ Completado |
| **TSK-5.8** | Integración Blade, PWA & Vitest | Consolidar vistas [`frontend/views/`](../../../frontend/views/), manifiesto [`manifest.webmanifest`](../../../frontend/public/manifest.webmanifest), [`FrontendRoutesTest.php`](../../../backend/tests/Feature/FrontendRoutesTest.php) (**122 tests PHPUnit acumulados**) y **23 pruebas Vitest** en [`frontend/tests/`](../../../frontend/tests/). | Equipo Scrum | 2 días | 5 | ✅ Completado |

**Balance del Sprint 5:** 26 / 26 Puntos Completados (100%).

---

## 7. Sprint Backlog 6: Benchmark Formal de 500 Afirmaciones, Auditoría de Sesgo y Piloto

* **Período:** 03 de Octubre de 2026 al 06 de Octubre de 2026.  
* **Sprint Goal:** Evaluar formalmente el sistema contra un benchmark de 500 afirmaciones ($F_1 \ge 0{,}75$, Precisión $\ge 0{,}80$), auditar sesgo electoral, habilitar flujo UI completo de notas comunitarias y realizar el piloto con la primera redacción aliada.  
* **Requisitos Asociados:** `REQ-11`, `REQ-17`, `REQ-21`, `REQ-22`, `REQ-24`, `CU01`, `CU02`.

| Código | Tarea | Descripción Técnica | Responsable | Estimación | Pts | Estado |
| :--- | :--- | :--- | :--- | :---: | :---: | :---: |
| **TSK-6.1** | UI Notas Comunitarias | Interfaz reactiva para redacción y votación ciudadana de notas en [`resultado.blade.php`](../../../frontend/views/verificacion/resultado.blade.php) y [`notas.test.js`](../../../frontend/tests/notas.test.js). | Alberto Galvis | 2 días | 5 | ✅ Completado |
| **TSK-6.2** | Benchmark 500 Afirmaciones | Evaluación del dataset de 500 afirmaciones colombianas con acuerdo $\kappa = 0{,}794$ en [`Evaluacion_Benchmark_500_Afirmaciones_y_Auditoria_Sesgo.md`](../../iteracion-final/Evaluacion_Benchmark_500_Afirmaciones_y_Auditoria_Sesgo.md). | Camilo Acevedo | 3 días | 8 | ✅ Completado |
| **TSK-6.3** | Auditoría de Sesgo Político | Prueba Chi-cuadrado ($\chi^2 = 0{,}682$, $p = 0{,}711 > 0{,}05$) certificando ausencia de sesgo partidista. | Alberto Galvis | 3 días | 8 | ✅ Completado |
| **TSK-6.4** | Evaluación F1 y Precisión | Precisión **84.6%** ($\ge 80\%$), Exhaustividad **81.2%** ($\ge 75\%$) y $F_1 = 0{,}828$ ($\ge 0{,}75$). | Camilo Acevedo | 2 días | 5 | ✅ Completado |
| **TSK-6.5** | Prueba de Carga e Ingesta RSS | Comando `dagaz:actualizar-noticias` ([`ActualizarNoticiasCommand.php`](../../../backend/app/Console/Commands/ActualizarNoticiasCommand.php)) y resiliencia con disponibilidad $\ge 99\%$. | Alberto Galvis | 2 días | 8 | ✅ Completado |
| **TSK-6.6** | Piloto Redacción Socia 1 | Consumo auditado de 50 veredictos por la primera sala aliada vía `/api/v1/public/verificar`. | Camilo Acevedo | 2 días | 5 | ✅ Completado |

**Balance del Sprint 6:** 39 / 39 Puntos Completados (100%).

---

## 8. Sprint Backlog 7: Pipeline CI/CD, Onboarding y Traspaso Pre-Alpha

* **Período:** 05 de Octubre de 2026 al 06 de Octubre de 2026.  
* **Sprint Goal:** Automatizar el ciclo de vida CI/CD en GitHub Actions, publicar manuales operativos, formalizar onboarding de redacciones socias 2 y 3 y firmar acta de traspaso.  
* **Requisitos Asociados:** `REQ-26`, `REQ-27`, `REQ-28`.

| Código | Tarea | Descripción Técnica | Responsable | Estimación | Pts | Estado |
| :--- | :--- | :--- | :--- | :---: | :---: | :---: |
| **TSK-7.1** | Pipeline CI/CD | Flujo automatizado en [`.github/workflows/ci.yml`](../../../.github/workflows/ci.yml) ejecutando PHPUnit, Vitest, compilación Vite y sincronización Capacitor. | Alberto Galvis | 2 días | 5 | ✅ Completado |
| **TSK-7.2** | Onboarding Socios 2 y 3 | Aprovisionamiento API Key para *ColombiaCheck* y *La Silla Vacía* en [`Onboarding_Redacciones_Socias_REQ26.md`](../../iteracion-final/Onboarding_Redacciones_Socias_REQ26.md). | Camilo Acevedo | 2 días | 8 | ✅ Completado |
| **TSK-7.3** | Manuales de Usuario y Ops | Publicación de [`Manual_Usuario_Adultos.md`](../../iteracion-final/Manual_Usuario_Adultos.md), [`Manual_Administrador_Revisor.md`](../../iteracion-final/Manual_Administrador_Revisor.md) y [`Manual_Despliegue_Infraestructura.md`](../../iteracion-final/Manual_Despliegue_Infraestructura.md). | Alberto Galvis | 3 días | 8 | ✅ Completado |
| **TSK-7.4** | Retrospectiva y Traspaso | Balance Scrum y acta formal en [`Retrospectiva_Final_y_Acta_Traspaso_REQ28.md`](../../iteracion-final/Retrospectiva_Final_y_Acta_Traspaso_REQ28.md). | Camilo Acevedo | 1 día | 3 | ✅ Completado |

**Balance del Sprint 7:** 24 / 24 Puntos Completados (100%) — **GATE 3 Pre-Alpha (`v0.1.0`)**.

---

## 9. Bloque Evolutivo Post-MVP (`v1.0.0` a `v1.4.1`: Multi-Modelo, Móvil Dual y Normalización)

* **Período:** 10 de Octubre de 2026 al 09 de Noviembre de 2026.  
* **Objetivo:** Liberar el MVP `v1.0.0`, robustecer compatibilidad cloud (Neon PostgreSQL SSL + Sanctum UUID), incorporar el Consejo de Verificación Multi-Modelo en paralelo, entregar el ecosistema móvil dual (`Capacitor 6` + `Expo SDK 58` con CI `.ipa`) y resolver incidencias reales sobre PostgreSQL y modelos de razonamiento en OpenRouter (`v1.4.1`).

| ID | Versión | Descripción del Incremento | Evidencia Técnica | Pts | Estado |
| :--- | :---: | :--- | :--- | :---: | :---: |
| **REQ-29** | `v1.1.0` | Compatibilidad Neon PostgreSQL SSL y `personal_access_tokens` UUID para Sanctum sin romper MariaDB. | Migración Sanctum UUID y [`backend/.env.example`](../../../backend/.env.example) | 5 | ✅ Completado |
| **REQ-30** | `v1.1.0` | Fallback pedagógico semántico cuando la búsqueda documental no arroja coincidencias suficientes. | [`BusquedaSemanticaService.php`](../../../backend/app/Services/BusquedaSemanticaService.php), [`VerificacionService.php`](../../../backend/app/Services/VerificacionService.php) | 5 | ✅ Completado |
| **REQ-31** | `v1.2.0` | Suite de 10 prototipos HTML interactivos de alta fidelidad WCAG 2.1 AA (`CU01`–`CU07` + `REQ13`). | [`docs/mockups-alta-fidelidad/`](../../mockups-alta-fidelidad/) | 6 | ✅ Completado |
| **REQ-32** | `v1.1.0` | Integración de proveedor **OpenRouter** (`OPENROUTER_API_KEY`) en `OpenAiDriver` y `config/dagaz.php`. | [`OpenAiDriver.php`](../../../backend/app/Adapters/Ia/OpenAiDriver.php), [`dagaz.php`](../../../backend/config/dagaz.php) | 5 | ✅ Completado |
| **REQ-33** | `v1.3.0` | **Consejo de Verificación Multi-Modelo en Paralelo** (`Http::pool`: GPT-4o, Claude 3.5 Sonnet, Gemini 2.0 Flash, DeepSeek-R1, Qwen activable) + aislamiento en [`phpunit.xml`](../../../backend/phpunit.xml) + ingesta masiva Colombia (`ingesta_noticias_colombia`). | [`MultiModeloHarnessService.php`](../../../backend/app/Services/MultiModeloHarnessService.php), [`ingesta_noticias_colombia.py`](../../../ingesta_noticias_colombia.py), [`ingesta_noticias_colombia.ipynb`](../../../ingesta_noticias_colombia.ipynb) | 6 | ✅ Completado |
| **REQ-34** | `v1.4.0` | **Ecosistema Móvil Dual:** Restauración de [`mobile/`](../../../mobile/) (**Capacitor 6**) sincronizado en CI `mobile-container` + aplicación nativa **React Native / Expo SDK 58** en [`mobile-expo/`](../../../mobile-expo/) (timeout 120s para Multi-Modelo) + compilación macOS `.ipa` para Sideloadly en [`.github/workflows/build-ios-ipa.yml`](../../../.github/workflows/build-ios-ipa.yml). | [`mobile/capacitor.config.json`](../../../mobile/capacitor.config.json), [`mobile-expo/package.json`](../../../mobile-expo/package.json), [`mobile-expo/src/services/api.js`](../../../mobile-expo/src/services/api.js), [`mobile-expo/eas.json`](../../../mobile-expo/eas.json) | 7 | ✅ Completado |
| **REQ-35** | `v1.4.1` | `normalizarSinTildes()` en consultas PostgreSQL, contraste electoral Colombia 2026, parser OpenRouter (`<think>`, `reasoning`, `justificación`), salvaguarda anti-degradación `FALSO` -> `NO_VERIFICABLE` y ampliación a **169 pruebas verificadas**. | [`BusquedaSemanticaTest.php`](../../../backend/tests/Unit/BusquedaSemanticaTest.php), [`OpenAiDriverTest.php`](../../../backend/tests/Unit/OpenAiDriverTest.php), [`MultiModeloHarnessService.php`](../../../backend/app/Services/MultiModeloHarnessService.php) | 5 | ✅ Completado |

**Balance del Bloque Evolutivo:** 39 / 39 Puntos Completados (100%).

---

## 10. Matriz de Trazabilidad Integral: Requisitos ↔ Tareas ↔ Código ↔ Pruebas

| Requisito / Caso de Uso | Sprint / Bloque | Tarea / ID | Componente de Código / Artefacto | Suite de Pruebas Automatizadas | Estado |
| :--- | :---: | :---: | :--- | :--- | :---: |
| **ACT-01** a **ACT-11** (Ingeniería y Modelado) | S1–S2 | `ACT-01..11` | [`01_Contexto_y_Metodologia_Dagaz.md`](../../iteracion-01/Guideline-Persistencia/01_Contexto_y_Metodologia_Dagaz.md), [`02_Especificacion_Casos_de_Uso.md`](../../iteracion-01/Guideline-Persistencia/02_Especificacion_Casos_de_Uso.md), [`Mockups/`](../../iteracion-01/Mockups/) | Inspección formal y aprobación Gate 0 | ✅ Hecho |
| **REQ-01** / **REQ-03** / **REQ-04** / **REQ-06** (Pipeline Core) | S2 | `TSK-2.2/2.5/2.6` | [`VerificacionService.php`](../../../backend/app/Services/VerificacionService.php) | [`VerificacionServiceTest.php`](../../../backend/tests/Unit/VerificacionServiceTest.php) (`14`), [`VerificacionFeatureTest.php`](../../../backend/tests/Feature/VerificacionFeatureTest.php) (`17`) | ✅ Hecho |
| **REQ-02** (OCR y STT Multicanal) | S2 | `TSK-2.3` | [`ocr_service/app.py`](../../../backend/Microservicios_Python/ocr_service/app.py), [`stt_service/app.py`](../../../backend/Microservicios_Python/stt_service/app.py), [`AdaptadorMulticanal.php`](../../../backend/app/Adapters/AdaptadorMulticanal.php) | [`test_services.py`](../../../backend/Microservicios_Python/tests/test_services.py) (`4` Python `unittest`) | ✅ Hecho |
| **REQ-05** / **REQ-30** / **REQ-35** (Semántica & PostgreSQL) | S2 / Evo | `TSK-2.4`, `REQ-30/35` | [`BusquedaSemanticaService.php`](../../../backend/app/Services/BusquedaSemanticaService.php) (`normalizarSinTildes`) | [`BusquedaSemanticaTest.php`](../../../backend/tests/Unit/BusquedaSemanticaTest.php) (`10`) | ✅ Hecho |
| **REQ-07** (Demo Esqueleto Funcional) | S2 / S5 | `TSK-5.7` | [`VerificacionController.php`](../../../backend/app/Http/Controllers/VerificacionController.php), [`index.blade.php`](../../../frontend/views/verificacion/index.blade.php) | [`VerificacionesBackendFeatureTest.php`](../../../backend/tests/Feature/VerificacionesBackendFeatureTest.php) (`10`) | ✅ Hecho |
| **REQ-08** / **REQ-14** / **REQ-20** / **CU07** (Grafo 80 Fuentes) | S1 / S3 | `TSK-1.5`, `TSK-3.1` | [`FuentesSeeder.php`](../../../backend/database/seeders/FuentesSeeder.php), [`fuentes/index.blade.php`](../../../frontend/views/fuentes/index.blade.php) | [`GrafoFuentesTest.php`](../../../backend/tests/Feature/GrafoFuentesTest.php) (`11`), [`FrontendRoutesTest.php`](../../../backend/tests/Feature/FrontendRoutesTest.php) (`10`) | ✅ Hecho |
| **REQ-09** / **REQ-21** (Ingesta RSS & Flujo Continuo) | S3 / S6 | `TSK-3.2`, `TSK-6.5` | [`ActualizarNoticiasCommand.php`](../../../backend/app/Console/Commands/ActualizarNoticiasCommand.php), [`NoticiasColombiaSeeder.php`](../../../backend/database/seeders/NoticiasColombiaSeeder.php) | [`VerificacionesBackendFeatureTest.php`](../../../backend/tests/Feature/VerificacionesBackendFeatureTest.php) | ✅ Hecho |
| **REQ-10** (Conector Archive.org) | S3 | `TSK-3.3` | [`ArchiveOrgService.php`](../../../backend/app/Services/ArchiveOrgService.php) | [`ArchiveOrgTest.php`](../../../backend/tests/Feature/ArchiveOrgTest.php) (`10`) | ✅ Hecho |
| **REQ-11** / **REQ-17** / **REQ-24** (Benchmark 500 & Sesgo) | S6 | `TSK-6.2/6.3/6.4` | [`Evaluacion_Benchmark_500_Afirmaciones_y_Auditoria_Sesgo.md`](../../iteracion-final/Evaluacion_Benchmark_500_Afirmaciones_y_Auditoria_Sesgo.md) | $\kappa = 0{,}794$, $F_1 = 0{,}828$, $\chi^2 = 0{,}682$ ($p > 0{,}05$) | ✅ Hecho |
| **REQ-12** / **REQ-29** (Diseño BD 10 Tablas + Sanctum UUID) | S1 / Evo | `TSK-1.2`, `REQ-29` | [`Raiden.sql`](../../../database/Raiden.sql), migraciones Laravel | [`TestCase.php`](../../../backend/tests/TestCase.php) + toda la suite Feature | ✅ Hecho |
| **REQ-13** / **REQ-18** (Editorial & Circuit Breaker) | S3 / S4 | `TSK-3.6`, `TSK-4.3` | [`VerificacionController.php`](../../../backend/app/Http/Controllers/VerificacionController.php), [`CircuitBreakerService.php`](../../../backend/app/Services/CircuitBreakerService.php) | [`BackendAdvancedFeatureTest.php`](../../../backend/tests/Feature/BackendAdvancedFeatureTest.php) (`9`), [`editorial.test.js`](../../../frontend/tests/editorial.test.js) | ✅ Hecho |
| **REQ-15** (Tarjeta Veredicto UI) | S5 | `TSK-5.4` | [`resultado.blade.php`](../../../frontend/views/verificacion/resultado.blade.php), [`resultado.js`](../../../frontend/src/js/dagaz/paginas/resultado.js) | [`ui.test.js`](../../../frontend/tests/ui.test.js), [`accesibilidad.test.js`](../../../frontend/tests/accesibilidad.test.js) | ✅ Hecho |
| **REQ-16** / **REQ-22** (API Pública Redacciones M2M) | S4 / S6 | `TSK-4.1/4.2/6.6` | [`PublicPartnerApiController.php`](../../../backend/app/Http/Controllers/PublicPartnerApiController.php), [`EnsureValidApiKey.php`](../../../backend/app/Http/Middleware/EnsureValidApiKey.php) | [`PublicPartnerApiTest.php`](../../../backend/tests/Feature/PublicPartnerApiTest.php) (`11`) | ✅ Hecho |
| **REQ-19** (Recuperación Interlingüe ES-EN) | S4 | `TSK-4.4` | [`ServicioInterlingue.php`](../../../backend/app/Services/ServicioInterlingue.php) | [`InterlingueTest.php`](../../../backend/tests/Feature/InterlingueTest.php) (`12`) | ✅ Hecho |
| **REQ-23** / **CU05** (Auth, Roles y Hábeas Data) | S1 / S5 | `TSK-1.6`, `TSK-5.2` | [`AuthController.php`](../../../backend/app/Http/Controllers/AuthController.php), [`auth/login.blade.php`](../../../frontend/views/auth/login.blade.php) | [`AuthTest.php`](../../../backend/tests/Feature/AuthTest.php) (`10`), [`sesion.test.js`](../../../frontend/tests/sesion.test.js) | ✅ Hecho |
| **REQ-25** (Auditoría Forense ClaimReview) | S4 | `TSK-4.5` | [`AuditoriaExportService.php`](../../../backend/app/Services/AuditoriaExportService.php), [`AuditoriaController.php`](../../../backend/app/Http/Controllers/AuditoriaController.php) | [`AuditoriaExportTest.php`](../../../backend/tests/Feature/AuditoriaExportTest.php) (`14`) | ✅ Hecho |
| **REQ-26** / **REQ-27** / **REQ-28** (Onboarding, Manuales, Cierre) | S7 | `TSK-7.2/7.3/7.4` | Documentación completa en [`docs/iteracion-final/`](../../iteracion-final/) | Certificación documental y CI/CD | ✅ Hecho |
| **REQ-31** (Mockups Alta Fidelidad) | Evo | `EVO-3` | [`docs/mockups-alta-fidelidad/`](../../mockups-alta-fidelidad/) (`10` vistas) | Revisión ergonómica +40 WCAG 2.1 AA | ✅ Hecho |
| **REQ-32** / **REQ-33** / **REQ-35** (OpenRouter, Multi-Modelo, Parser `<think>`) | Evo | `EVO-2/4/6` | [`MultiModeloHarnessService.php`](../../../backend/app/Services/MultiModeloHarnessService.php), [`OpenAiDriver.php`](../../../backend/app/Adapters/Ia/OpenAiDriver.php) | [`OpenAiDriverTest.php`](../../../backend/tests/Unit/OpenAiDriverTest.php) (`2`) + aislamiento [`phpunit.xml`](../../../backend/phpunit.xml) | ✅ Hecho |
| **REQ-34** (Móvil Dual Capacitor 6 + Expo SDK 58 + IPA CI) | Evo | `EVO-5` | [`mobile/`](../../../mobile/), [`mobile-expo/`](../../../mobile-expo/), [`.github/workflows/build-ios-ipa.yml`](../../../.github/workflows/build-ios-ipa.yml) | Job `mobile-container` en [`ci.yml`](../../../.github/workflows/ci.yml) + compilación macOS `.ipa` | ✅ Hecho |
| **CU01** / **CU02** / **CU03** / **CU04** / **CU06** (UI JS) | S3 / S5 / S6 | `TSK-3.4/3.5/5.8` | [`Nota.php`](../../../backend/app/Models/Nota.php), [`Historial.php`](../../../backend/app/Models/Historial.php), [`frontend/src/`](../../../frontend/src/) | [`notas.test.js`](../../../frontend/tests/notas.test.js), [`historial.test.js`](../../../frontend/tests/historial.test.js), [`verificar.test.js`](../../../frontend/tests/verificar.test.js), [`api.test.js`](../../../frontend/tests/api.test.js) | ✅ Hecho |

---

## 11. Conclusiones y Estado del Incremento Vigente (`v1.4.1`)

1. **Cumplimiento del 100% del Plan Base y Evolutivo:** Se completaron los 7 Sprints del ciclo Scrum (**281 pts**) junto con el Bloque Evolutivo Post-MVP (**39 pts**), alcanzando **320 puntos de esfuerzo ejecutados** y **35 requisitos cerrados**.
2. **Calidad Automatizada Verificada (`169` pruebas al 100%):** **142 pruebas PHPUnit** (`27 Unit` con 94 aserciones + `115 Feature` con 2.228 aserciones = **2.322 aserciones**), **23 pruebas Vitest** en 8 suites frontend y **4 pruebas Python `unittest`** en los microservicios OCR/STT.
3. **Resiliencia Cloud e IA Multi-Agente:** La combinación de PostgreSQL 18 `pgvector` en Neon.tech (con búsqueda normalizada sin tildes), OpenRouter, Consejo Multi-Modelo en paralelo (`Http::pool`) con salvaguardas anti-degradación y clientes móviles duales (`Capacitor 6` y `Expo SDK 58` con empaquetado `.ipa` en CI) consolida a DAGAZ en su versión productiva **`v1.4.1`**.
