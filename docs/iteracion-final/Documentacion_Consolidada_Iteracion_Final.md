# DAGAZ — Documentación Consolidada de la Iteración Final
## Memoria Técnica y de Cierre Integral del Proyecto (`v1.0.0` MVP → `v1.4.1`)

> **Proyecto:** DAGAZ — Plataforma de Verificación de Información y Desinformación en Colombia  
> **Asignatura:** Ingeniería de Servicios de Internet — 2026-2 — Grupo 06  
> **Integrantes:**  
> • **Alberto Mario Galvis Echeverria** (`0222410070`) — Analista de Requerimientos / Desarrollador FullStack  
> • **Camilo Isaac Acevedo Rueda** (`0222410049`) — Analista de Calidad (QA) / Desarrollador FullStack  
> **Fecha de Cierre Actualizado:** Noviembre de 2026 (`v1.4.1`)

---

## 1. Introducción y Propósito del Documento

El presente documento consolida la memoria técnica final de la plataforma **DAGAZ**, diseñada para la detección, verificación y combate de la desinformación en Colombia, con enfoque prioritario en la accesibilidad para **adultos y adultos mayores (+40 años)**, movilidad nativa Android/iOS y la interoperabilidad máquina a máquina con **redacciones periodísticas aliadas**.

Este informe integra las evidencias verificadas de arquitectura, código fuente, pruebas automatizadas, manuales operativos y evaluación formal ejecutadas a lo largo de las iteraciones del ciclo de vida del proyecto hasta la versión consolidada **`v1.4.1`**.

---

## 2. Matriz de Cobertura del Backlog MVP (28 Requisitos / 157 Puntos)

| ID Requisito | Descripción del Requisito | Pts | Prioridad | Evidencia Técnica Real / Módulo | Estado |
| :---: | :--- | :---: | :---: | :--- | :---: |
| **REQ-01** | Extractor de afirmaciones verificables de titulares | 8 | P1 | [`VerificacionService.php`](../../backend/app/Services/VerificacionService.php) + [`VerificacionServiceTest.php`](../../backend/tests/Unit/VerificacionServiceTest.php) | ✅ Completado |
| **REQ-02** | OCR y extracción de texto de imágenes y audio STT | 5 | P1 | Microservicios Python FastAPI (`:5001/ocr` Tesseract/PIL y `:5002/transcribe` SpeechRecognition) + [`AdaptadorMulticanal.php`](../../backend/app/Adapters/AdaptadorMulticanal.php) | ✅ Completado |
| **REQ-03** | Agente de descomposición de afirmaciones complejas | 8 | P1 | `VerificacionService::descomponerAfirmaciones()` + drivers LLM (`IaDriverFactory`) | ✅ Completado |
| **REQ-04** | Agente de verificación cruzada con ponderación de fuentes | 8 | P1 | `VerificacionService::contrastarConFuentes()` y `calcularScore()` (`config/dagaz.php`) | ✅ Completado |
| **REQ-05** | Recuperación semántica y léxica de evidencias | 8 | P1 | [`BusquedaSemanticaService.php`](../../backend/app/Services/BusquedaSemanticaService.php) (`buscarEvidenciaPorTexto()`, `buscarNoticiasPorFuenteYMetadata()`) + `pgvector` KNN | ✅ Completado |
| **REQ-06** | Síntesis de veredicto con trazabilidad y confianza | 8 | P1 | `VerificacionService::determinarVeredicto()` (`VERDADERO`, `FALSO`, `ENGAÑOSO`, `PARCIAL`, `NO_VERIFICABLE`) | ✅ Completado |
| **REQ-07** | Demo esqueleto funcional de extremo a extremo | 3 | P1 | Pipeline E2E integrado en `POST /api/v1/verificacion` y `GET /api/v1/verificacion/{idContenido}` | ✅ Completado |
| **REQ-08** | Grafo de credibilidad v1 con fuentes internacionales Nivel 1 | 5 | P1 | [`FuentesSeeder.php`](../../backend/database/seeders/FuentesSeeder.php) (Reuters, AP, AFP, BBC, OMS, ONU, etc.) | ✅ Completado |
| **REQ-09** | Monitor de redes sociales y normalización multicanal | 8 | P2 | `AdaptadorMulticanal::procesarEntrada()` / `escucharRedSocial()` para `TEXTO`, `URL`, `IMAGEN_OCR`, `RED_SOCIAL` | ✅ Completado |
| **REQ-10** | Consulta histórica en Archive.org / Wayback Machine | 5 | P2 | [`ArchiveOrgService.php`](../../backend/app/Services/ArchiveOrgService.php) + rutas `/api/v1/verificacion/archive/*` + [`ArchiveOrgTest.php`](../../backend/tests/Feature/ArchiveOrgTest.php) | ✅ Completado |
| **REQ-11** | Dataset de referencia de 500 afirmaciones etiquetadas | 8 | P2 | [`Evaluacion_Benchmark_500_Afirmaciones_y_Auditoria_Sesgo.md`](Evaluacion_Benchmark_500_Afirmaciones_y_Auditoria_Sesgo.md) ($\kappa = 0{,}794$) | ✅ Completado |
| **REQ-12** | Modelo relacional y vectorial PostgreSQL / MariaDB | 8 | P2 | Esquema `Raiden` en [`database/Raiden.sql`](../../database/Raiden.sql) y migración `2026_09_01_000000_create_dagaz_tables.php` (10 tablas + Sanctum UUID) | ✅ Completado |
| **REQ-13** | Panel para revisores humanos y rectificación editorial | 8 | P2 | `POST /api/v1/veredictos/{idVeredicto}/intervenir` + [`resultado.blade.php`](../../frontend/views/verificacion/resultado.blade.php) | ✅ Completado |
| **REQ-14** | Grafo de credibilidad v2 con prensa nacional y regional | 5 | P2 | `FuentesSeeder.php` (El Tiempo, El Espectador, Caracol, Regionales de Antioquia/Valle/Caribe) | ✅ Completado |
| **REQ-15** | Interfaz de tarjeta de veredicto accesible (+40 años) | 5 | P2 | Vistas Blade en [`frontend/views/`](../../frontend/views/) + pruebas WCAG/DOM en [`ui.test.js`](../../frontend/tests/ui.test.js) | ✅ Completado |
| **REQ-16** | API pública v1 para redacciones socias con API Key | 5 | P2 | [`PublicPartnerApiController.php`](../../backend/app/Http/Controllers/PublicPartnerApiController.php) + [`EnsureValidApiKey.php`](../../backend/app/Http/Middleware/EnsureValidApiKey.php) (`/api/v1/public/*`) | ✅ Completado |
| **REQ-17** | Auditoría de sesgo político en dataset electoral | 8 | P3 | Prueba $\chi^2$ ($p = 0{,}711 > 0{,}05$, sin sesgo significativo) | ✅ Completado |
| **REQ-18** | Controles de costes de API LLM y circuit breaker | 5 | P3 | [`CircuitBreakerService.php`](../../backend/app/Services/CircuitBreakerService.php) (`$5.00 USD/día`, `50,000 tokens/h`) + rutas `/api/v1/admin/circuit-breaker*` | ✅ Completado |
| **REQ-19** | Recuperación y análisis interlingüe inglés/español | 8 | P3 | [`ServicioInterlingue.php`](../../backend/app/Services/ServicioInterlingue.php) + rutas `/api/v1/verificacion/interlingue/*` + [`InterlingueTest.php`](../../backend/tests/Feature/InterlingueTest.php) | ✅ Completado |
| **REQ-20** | Grafo de credibilidad v3 ampliado a 80 fuentes (4 niveles) | 8 | P3 | [`FuentesSeeder.php`](../../backend/database/seeders/FuentesSeeder.php) (80 fuentes: 20 × 4 niveles) + [`GrafoFuentesTest.php`](../../backend/tests/Feature/GrafoFuentesTest.php) | ✅ Completado |
| **REQ-21** | Pruebas de resiliencia, disponibilidad y rendimiento | 8 | P3 | Fallback LLM local (`LocalLlmDriver`), pruebas HTTP pool y KNN exacto SSD (`10–35 ms`) | ✅ Completado |
| **REQ-22** | Onboarding de primera redacción socia | 5 | P3 | Credencial `socio-espectador` (*El Espectador — Sala Digital*) en `config/dagaz.php` | ✅ Completado |
| **REQ-23** | Sembrado de usuarios de prueba por roles (`adulto`, `editor`, `admin`) | 5 | P3 | [`UsuariosSeeder.php`](../../backend/database/seeders/UsuariosSeeder.php) + módulo Ley 1581 Hábeas Data en [`AuthController.php`](../../backend/app/Http/Controllers/AuthController.php) | ✅ Completado |
| **REQ-24** | Evaluación formal: precisión $\ge 0{,}80$, recall $\ge 0{,}75$ | 5 | P3 | Precisión ponderada **84.6%**, Recall **81.2%** (Superado) | ✅ Completado |
| **REQ-25** | Registro y exportación de auditoría forense para reguladores | 5 | P4 | [`AuditoriaExportService.php`](../../backend/app/Services/AuditoriaExportService.php) + [`AuditoriaController.php`](../../backend/app/Http/Controllers/AuditoriaController.php) (`JSON`, `CSV`, `ClaimReview`) | ✅ Completado |
| **REQ-26** | Onboarding de redacciones socias segunda y tercera | 8 | P4 | *ColombiaCheck* (`socio-colombiacheck`) y *La Silla Vacía* (`socio-lasillavacia`) + guía REQ-26 | ✅ Completado |
| **REQ-27** | Manuales de procedimientos, despliegue y usuario | 8 | P4 | Manuales operativos y de despliegue completos en [`docs/iteracion-final/`](./) | ✅ Completado |
| **REQ-28** | Retrospectiva final del proyecto y acta de traspaso | 3 | P4 | [`Retrospectiva_Final_y_Acta_Traspaso_REQ28.md`](Retrospectiva_Final_y_Acta_Traspaso_REQ28.md) | ✅ Completado |
| **SUBTOTAL MVP** | **28 Requisitos Base Implementados** | **157** | **P1–P4** | *(+53 pts de Actividades ACT-01..11 = 210 pts hasta Sprint 7)* | **✅ 100%** |

### 2.1 Evolución Evolutiva Post-MVP (`v1.1.0` → `v1.4.1`: `REQ-29` a `REQ-35`, 39 Puntos)

| ID Requisito | Descripción Evolutiva | Pts | Versión | Evidencia Técnica Real | Estado |
| :---: | :--- | :---: | :---: | :--- | :---: |
| **REQ-29** | Migración primaria a PostgreSQL 18 + `pgvector` en Neon.tech y tabla vectorial `documentos` (`vector(384)`) | 8 | `1.1.0` | `BusquedaSemanticaService` (KNN SSD sin HNSW), `2026_09_24_214547_create_personal_access_tokens_table.php`, [`database/Raiden.sql`](../../database/Raiden.sql) | ✅ Completado |
| **REQ-30** | Consejo de Verificación Multi-Modelo concurrente con ponderación y protección *anti-downgrade* | 8 | `1.3.0`–`1.4.1` | [`MultiModeloHarnessService.php`](../../backend/app/Services/MultiModeloHarnessService.php) (`Http::pool`: GPT-4o, Claude, Gemini, DeepSeek-R1, Qwen/OpenRouter) | ✅ Completado |
| **REQ-31** | Driver OpenRouter / LLM con soporte de modelos de razonamiento extendido y normalización ES | 5 | `1.3.1`–`1.4.1` | [`OpenAiDriver.php`](../../backend/app/Adapters/Ia/OpenAiDriver.php), `normalizarSinTildes()` y [`OpenAiDriverTest.php`](../../backend/tests/Unit/OpenAiDriverTest.php) | ✅ Completado |
| **REQ-32** | Ingesta continua RSS y corpus vectorial colombiano en lote | 5 | `1.1.0`–`1.3.2` | [`ActualizarNoticiasCommand.php`](../../backend/app/Console/Commands/ActualizarNoticiasCommand.php), `NoticiasColombiaSeeder.php`, [`ingesta_noticias_colombia.py`](../../ingesta_noticias_colombia.py) | ✅ Completado |
| **REQ-33** | Contenedor móvil híbrido Android/iOS (`mobile/`) y verificación Capacitor en CI | 5 | `1.1.0` | [`mobile/`](../../mobile/) (`@capacitor/core ^6.0.0`) + job `mobile-container` en `.github/workflows/ci.yml` | ✅ Completado |
| **REQ-34** | Aplicación móvil nativa iOS/Android con Expo SDK 58 y React Native 0.88 | 5 | `1.4.0` | [`mobile-expo/`](../../mobile-expo/) (5 pantallas, modo lectura `A / A+`, `expo-image-picker`) | ✅ Completado |
| **REQ-35** | Empaquetado CI automatizado de binario `DAGAZ.ipa` para Sideloadly en macOS | 3 | `1.4.0` | [`.github/workflows/build-ios-ipa.yml`](../../.github/workflows/build-ios-ipa.yml) (`DAGAZ-iOS-Sideloadly-IPA`) | ✅ Completado |
| **TOTAL CONSOLIDADO** | **35 Requisitos (`157 + 39 = 196 pts`) + `53 pts` Actividades Base (`ACT-01..11`)** | **249 SP** | **`v1.4.1`** | **Cobertura completa Web, Backend, Microservicios, BD Vectorial y Móvil** | **✅ 100%** |

---

## 3. Arquitectura del Sistema y Separación Limpia de Carpetas

La arquitectura física del repositorio refleja una división modular por responsabilidades:

```text
ISI-2026-2-G06/
├── backend/                      # API REST Laravel 13.17 / PHP 8.3–8.4 + Sanctum 4.3
│   ├── app/                      # Controladores, Servicios (MultiModeloHarness, BusquedaSemantica), Adaptadores IA
│   ├── config/view.php           # Resuelve plantillas desde base_path('../frontend/views')
│   ├── database/                 # Migraciones (10 tablas + Sanctum UUID) y Seeders (80 fuentes)
│   ├── tests/                    # 142 Pruebas PHPUnit (15 archivos: 4 Unit + 11 Feature; 2.322 aserciones)
│   └── Microservicios_Python/    # Microservicios FastAPI desacoplados OCR (:5001) y STT (:5002) + 4 pruebas unittest
├── frontend/                     # Capa Web Responsiva y Accesible (Mobile-First, Blade + Tailwind v4)
│   ├── dist/                     # Artefactos compilados de producción (Vite 8)
│   ├── public/                   # Recursos públicos webmanifest e iconos
│   ├── src/                      # CSS tokens y módulos JS ES6 por página
│   ├── tests/                    # 8 Suites de pruebas unitarias/aceptación en Vitest 5.0 (23 tests)
│   └── views/                    # Plantillas Blade SSR (layouts, auth, verificacion, historial, fuentes, perfil)
├── mobile/                       # Contenedor móvil híbrido Android/iOS (Capacitor 6, webDir: ../frontend/dist)
├── mobile-expo/                  # App móvil nativa Expo SDK 58 / React Native 0.88.1 (iOS/Android)
├── database/                     # Script DDL oficial Raiden.sql (PostgreSQL 18 pgvector), Diagrama-ER.md y docs
├── docs/                         # Iteraciones 01, 02, 03 y Final + 10 Mockups interactivos de alta fidelidad
├── .github/workflows/            # Pipelines CI/CD: ci.yml (backend, frontend, mobile) + build-ios-ipa.yml (macOS IPA)
├── ingesta_noticias_colombia.py  # Scraper y vectorizador batch hacia Neon PostgreSQL (sentence-transformers)
└── ingesta_noticias_colombia.ipynb
```

---

## 4. Batería de Pruebas Automatizadas y Calidad (QA)

Ejecución verificada al corte **`v1.4.1`** (`09 de Noviembre de 2026`):

| Entorno de Pruebas | Framework / Herramienta | Suites / Archivos | Pruebas Totales | Aserciones Verificadas | Tasa de Éxito |
| :--- | :--- | :---: | :---: | :---: | :---: |
| **Backend — Unit** | PHPUnit 12.5.12 (PHP 8.4) | 4 archivos | 27 tests | 94 aserciones | **100% (0 fallos)** |
| **Backend — Feature** | PHPUnit 12.5.12 (PHP 8.4) | 11 archivos | 115 tests | 2.228 aserciones | **100% (0 fallos)** |
| **Frontend Web** | Vitest 5.0.3 (Happy-DOM) | 8 suites | 23 tests | Cobertura DOM/UX/XSS | **100% (0 fallos)** |
| **Microservicios Python** | Python `unittest` + FastAPI `TestClient` | 1 suite (`test_services.py`) | 4 tests | Validación OCR/STT | **100% (0 fallos)** |
| **TOTAL CONSOLIDADO** | | **24 archivos / suites** | **169 tests** | **2.322+ aserciones** | **100% Aprobado** |

---

## 5. Índice de Documentos Entregables

1. [**Manual de Usuario para Adultos (+40 Años)**](Manual_Usuario_Adultos.md): Ergonomía visual, tipografía base 18px, selector `A / A+`, botones ≥ 48px y guía ciudadana multicanal Web/Móvil.
2. [**Manual de Administrador y Revisor Humano Editorial**](Manual_Administrador_Revisor.md): Protocolo de intervención editorial (REQ-13), moderación comunitaria, Circuit Breaker (REQ-18) y auditoría forense (REQ-25).
3. [**Manual de Despliegue e Infraestructura**](Manual_Despliegue_Infraestructura.md): Guía técnica de despliegue con Neon PostgreSQL 18 `pgvector`, Laravel 13, microservicios Python, Nginx, CI/CD, Capacitor 6 y Expo `.ipa`.
4. [**Onboarding e Integración de Redacciones Socias**](Onboarding_Redacciones_Socias_REQ26.md): Contratos `/api/v1/public/*` con `X-API-KEY` para *El Espectador*, *ColombiaCheck* y *La Silla Vacía* (REQ-22 / REQ-26).
5. [**Informe del Benchmark de 500 Afirmaciones y Auditoría de Sesgo**](Evaluacion_Benchmark_500_Afirmaciones_y_Auditoria_Sesgo.md): Certificación de Precisión ($84{,}6\%$), Recall ($81{,}2\%$), $\chi^2$ ($p = 0{,}711$) y calibración post-MVP.
6. [**Retrospectiva Final y Acta de Traspaso**](Retrospectiva_Final_y_Acta_Traspaso_REQ28.md): Balance Scrum de entregas, lecciones aprendidas, traspaso institucional y addendum `v1.0.0` → `v1.4.1`.
