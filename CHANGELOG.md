# Registro de Cambios (Changelog) — DAGAZ

Todos los cambios notables en el proyecto **DAGAZ** están documentados en este archivo.  
El formato se basa en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y este proyecto sigue [Semantic Versioning](https://semver.org/lang/es/).

> **Criterio de Versionamiento:**  
> Versiones preliminares, de diseño, arquitectura y prototipado inician en `v0.0.x`.  
> La versión `v0.1.0` marcó el cierre funcional Pre-Alpha; `v1.0.0` representa la liberación oficial del **Producto Mínimo Viable (MVP)**, y las versiones `v1.1.x`–`v1.4.x` documentan la evolución multi-agente, despliegue móvil dual e ingesta masiva continua.

---

## [1.4.1] - 2026-11-09
### Añadido / Corregido
- **Resiliencia de Búsqueda PostgreSQL, Contraste Electoral Colombia 2026 y Parser OpenRouter (`REQ-35`)**:
  - Implementación de `normalizarSinTildes()` en [`backend/app/Services/BusquedaSemanticaService.php`](backend/app/Services/BusquedaSemanticaService.php) y corrección de búsqueda por palabras clave y metadatos sobre PostgreSQL 18 (Neon.tech).
  - Mejoras en [`backend/app/Adapters/Ia/OpenAiDriver.php`](backend/app/Adapters/Ia/OpenAiDriver.php): limpieza de bloques de razonamiento `<think>...</think>`, respaldo desde el campo `reasoning` cuando `content` llega vacío (modelos de razonamiento vía OpenRouter) y soporte tolerante de la clave JSON `justificación` ([`OpenAiDriverTest.php`](backend/tests/Unit/OpenAiDriverTest.php)).
  - Salvaguarda anti-degradación en [`backend/app/Services/MultiModeloHarnessService.php`](backend/app/Services/MultiModeloHarnessService.php) para evitar que una contradicción explícita (`FALSO`) sea degradada erróneamente a `NO_VERIFICABLE`.
  - Actualización de reglas de contraste factual electoral de Colombia 2026 y ampliación de la suite verificada a **169 pruebas automatizadas**: **142 PHPUnit** (27 Unit / 94 aserciones + 115 Feature / 2.228 aserciones = **2.322 aserciones**), **23 Vitest** (8 archivos) y **4 Python `unittest`**.
- **Alineación Documental Integral (`Laravel 13` / `PHPUnit 12.5` / `Expo SDK 58` / `Capacitor 6`)**:
  - Sincronización de `CHANGELOG.md`, bitácoras de control (`iteracion-01` e `iteracion-02`), READMEs de módulos, backlog extendido y rutas REST v1 vigentes (`/api/v1/verificacion`, `/api/v1/public/*`).

---

## [1.4.0] - 2026-11-06 a 2026-11-09
### Añadido / Modificado
- **Arquitectura Móvil Dual (`mobile/` Capacitor 6 + `mobile-expo/` React Native Expo SDK 58) (`REQ-34`)**:
  - Restauración del contenedor híbrido Android **Capacitor 6 (`^6.0.0`)** en [`mobile/`](mobile/) (`com.isicorp.dagaz`, `webDir: ../frontend/dist`), validado por el job `mobile-container` de [`.github/workflows/ci.yml`](.github/workflows/ci.yml).
  - Reubicación y consolidación de la aplicación móvil nativa multiplataforma en [`mobile-expo/`](mobile-expo/) sobre **Expo SDK 58** (`expo ~58.0.23`, `react-native 0.88.1`, `react 19.3.0`) con vistas accesibles para Adultos (+40) (`Verificar`, `Resultado`, `Fuentes`, `Historial`, `Perfil`).
- **Compilación CI de iOS para Sideloadly y Timeout Extendido Multi-Modelo**:
  - Adición del workflow [`.github/workflows/build-ios-ipa.yml`](.github/workflows/build-ios-ipa.yml) en runner `macos-latest` para compilar `DAGAZ.ipa` sin firma (`DAGAZ-iOS-Sideloadly-IPA`) y perfil en [`mobile-expo/eas.json`](mobile-expo/eas.json).
  - Ampliación del timeout cliente en [`mobile-expo/src/services/api.js`](mobile-expo/src/services/api.js) a **120 segundos** para absorber la latencia del Consejo Multi-Modelo y alineación de consumo con REST v1.

---

## [1.3.0] - 2026-11-03
### Añadido
- **Consejo de Verificación Multi-Modelo en Paralelo (`MultiModeloHarnessService`) (`REQ-33`)**:
  - Evaluación concurrente multi-agente vía `Http::pool` en [`backend/app/Services/MultiModeloHarnessService.php`](backend/app/Services/MultiModeloHarnessService.php) integrando GPT-4o / OpenRouter, Claude 3.5 Sonnet, Gemini 2.0 Flash, DeepSeek-R1 y Qwen activable (`DAGAZ_COUNCIL_QWEN_ACTIVO`), con configuración centralizada en [`backend/config/dagaz.php`](backend/config/dagaz.php).
- **Ingesta Masiva Colombia y Determinismo en CI**:
  - Incorporación de scripts de ingesta y vectorización [`ingesta_noticias_colombia.py`](ingesta_noticias_colombia.py) e [`ingesta_noticias_colombia.ipynb`](ingesta_noticias_colombia.ipynb).
  - Configuración de `DAGAZ_MULTI_MODELO_HABILITADO=false` en [`backend/phpunit.xml`](backend/phpunit.xml) para ejecución determinista en pruebas y salvaguardas ante veredictos con puntuación cero o sin fuentes.

---

## [1.2.0] - 2026-10-17
### Añadido
- **Suite Completa de Mockups de Alta Fidelidad WCAG 2.1 AA (`REQ-31`)**:
  - Entrega de 10 prototipos HTML interactivos accesibles en [`docs/mockups-alta-fidelidad/`](docs/mockups-alta-fidelidad/) cubriendo `CU01`–`CU07`, el panel editorial `REQ13` e índice navegable para Adultos (+40).
- **Product Backlog Evolutivo**:
  - Actualización formal del backlog extendido en [`docs/iteracion-01/Guidelines/Backlog del Proyecto.md`](docs/iteracion-01/Guidelines/Backlog%20del%20Proyecto.md) integrando requisitos post-MVP (`REQ-29` a `REQ-32`, ampliado posteriormente a `REQ-35`).

---

## [1.1.0] - 2026-10-15
### Modificado
- **Infraestructura Cloud Neon SSL, OpenRouter y Fallback Semántico (`REQ-29`, `REQ-30`, `REQ-32`)**:
  - Compatibilidad dual PostgreSQL 18 Serverless (Neon.tech con SSL obligatorio) y MariaDB local configurable desde [`backend/.env.example`](backend/.env.example).
  - Integración de llaves **OpenRouter** (`OPENROUTER_API_KEY`, `DAGAZ_IA_PROVEEDOR=openrouter`) en [`OpenAiDriver.php`](backend/app/Adapters/Ia/OpenAiDriver.php).
  - Fallback pedagógico semántico en [`BusquedaSemanticaService.php`](backend/app/Services/BusquedaSemanticaService.php) y [`VerificacionService.php`](backend/app/Services/VerificacionService.php) cuando la consulta no arroja fuentes coincidentes.

---

## [1.0.1] - 2026-10-12
### Corregido
- **Estabilización de Rutas Frontend Web y Pipeline CI**:
  - Restauración de rutas SSR en [`backend/routes/web.php`](backend/routes/web.php) validadas por [`FrontendRoutesTest.php`](backend/tests/Feature/FrontendRoutesTest.php).
  - Guarda defensiva de explicación en [`VerificacionService.php`](backend/app/Services/VerificacionService.php) y fijación de `actions/download-artifact@v4.1.3` en [`.github/workflows/ci.yml`](.github/workflows/ci.yml).

---

## [1.0.0] - 2026-10-10
### Añadido
- **Liberación Oficial del Producto Mínimo Viable (MVP) DAGAZ**:
  - Cierre formal de los **7 Sprints** del ciclo Scrum, completando el 100% de los **28 requisitos funcionales y no funcionales originales** (`REQ-01` a `REQ-28`, 157 SP) y los 7 Casos de Uso (`CU01`–`CU07`).
  - Entrega de los tres manuales operativos ([`Manual_Usuario_Adultos.md`](docs/iteracion-final/Manual_Usuario_Adultos.md), [`Manual_Administrador_Revisor.md`](docs/iteracion-final/Manual_Administrador_Revisor.md), [`Manual_Despliegue_Infraestructura.md`](docs/iteracion-final/Manual_Despliegue_Infraestructura.md)) y acta de traspaso en [`docs/iteracion-final/`](docs/iteracion-final/).

---

## [0.1.0] - 2026-10-06
### Añadido
- **Hito Pre-Alpha (`v0.1.0-pre-alpha`)**:
  - Consolidación end-to-end de backend Laravel 13, microservicios FastAPI Python, frontend modular Vite + Tailwind CSS v4 (Vitest), grafo de 80 fuentes IFCN y API REST v1 (`/api/v1/*`) lista para aseguramiento QA pre-MVP.

---

## [0.0.34] - 2026-10-06
### Añadido
- **Consolidación Académica y Forense de Iteración Final**:
  - Ejecución formal del benchmark de 500 afirmaciones ($\kappa = 0{,}794$, Precisión $84{,}6\%$, Exhaustividad $81{,}2\%$) y auditoría de sesgo político ($\chi^2 = 0{,}682$, $p > 0{,}05$) en [`Evaluacion_Benchmark_500_Afirmaciones_y_Auditoria_Sesgo.md`](docs/iteracion-final/Evaluacion_Benchmark_500_Afirmaciones_y_Auditoria_Sesgo.md).
  - Protocolo de onboarding para redacciones socias (`El Espectador`, `ColombiaCheck`, `La Silla Vacía`) en [`Onboarding_Redacciones_Socias_REQ26.md`](docs/iteracion-final/Onboarding_Redacciones_Socias_REQ26.md) y retrospectiva ágil en [`Retrospectiva_Final_y_Acta_Traspaso_REQ28.md`](docs/iteracion-final/Retrospectiva_Final_y_Acta_Traspaso_REQ28.md).

---

## [0.0.33] - 2026-10-05
### Añadido
- **Frontend Modular Vite + Tailwind CSS v4, Pruebas Vitest y Pipeline CI/CD**:
  - Reorganización de vistas Blade y módulos JavaScript en `frontend/views/` y `frontend/src/` (PWA `manifest.webmanifest`) junto con **23 pruebas de interfaz en Vitest** (Happy-DOM).
  - Orquestación automática en [`.github/workflows/ci.yml`](.github/workflows/ci.yml) y elaboración de manuales operativos (`REQ-27`).

---

## [0.0.32] - 2026-10-05
### Añadido
- **Ingesta RSS Continua, Seeder Colombia y Tokens Sanctum UUID en Neon**:
  - Creación de [`NoticiasColombiaSeeder.php`](backend/database/seeders/NoticiasColombiaSeeder.php) y del comando Artisan `dagaz:actualizar-noticias` ([`ActualizarNoticiasCommand.php`](backend/app/Console/Commands/ActualizarNoticiasCommand.php)).
  - Adaptación de `personal_access_tokens.tokenable_id` a tipo `UUID` para compatibilidad completa con PostgreSQL 18 en Neon.tech.

---

## [0.1.6] - 2026-11-14
### Añadido
- **Streaming Reactivo SSE y Consola de Pensamiento CoT (TSK-6.7)**:
  - Implementación de canal Server-Sent Events en `POST /api/v1/verificacion/stream` (`VerificacionController.php`, `VerificacionService.php`) con emisión híbrida A+B (hitos de progreso y streaming palabra por palabra de explicaciones preliminares).
  - Transformación de la vista de carga hacia la **Consola de Pensamiento (Chain-of-Thought)** en terminal oscura (`bg-slate-950`) en `frontend/views/verificacion/index.blade.php`, `backend/resources/views/verificacion/index.blade.php` y `mobile-expo/src/screens/VerificarScreen.js`, sustituyendo los checklists estáticos con íconos.
  - Mecanismo defensivo de auto-inyección en el cliente JavaScript (`verificar.js`) para sobreescribir la UI ante cachés persistentes en navegadores o WebViews.
- **Blindaje Heurístico en Búsqueda Semántica**:
  - Modificación de `BusquedaSemanticaService.php` para clasificar afirmaciones con menciones aisladas de entidades sin refrendación como `SIN_EVIDENCIA` ($0.0\%$), forzando veredictos estrictos de `FALSO` (0.0%).
- **Modo Autónomo Local en APK Android Capacitor**:
  - Pantalla inicial en `mobile/android/app/src/main/assets/public/index.html` con selector de servidor y red local Wi-Fi/Emulador con verificación de ping reactiva.
  - Configuración de `usesCleartextTraffic="true"` en `AndroidManifest.xml` y fijación de entorno de compilación Gradle a OpenJDK 17 LTS (`jbr-17.0.14`).
  - Generación exitosa de `app-debug.apk` funcional para pruebas locales y en dispositivo.
- **Hito de Calidad: Segunda Revisión Formal de Supervisor (16-Oct)**:
  - Certificación académica de avance y arquitectura por el supervisor docente con calificación máxima de **5.0 / 5.0 (5/5)**.
- **Expansión de la Suite de Pruebas**:
  - Incorporación de pruebas en `VerificacionFeatureTest.php`, `BusquedaWebServiceTest.php` y `BusquedaSemanticaTest.php`, alcanzando **149 tests PHPUnit (2.357 aserciones)** al 100% (176 tests automatizados en el ecosistema).

---

## [0.1.5] - 2026-11-09
### Añadido
- **Optimización de Búsqueda Semántica y Hechos Electorales 2026 (PR #25 - `53c4c48`, `9a2ca9d`)**:
  - Corrección de consultas SQL de búsqueda híbrida full-text y embeddings vectoriales para PostgreSQL en `backend/app/Services/BusquedaSemanticaService.php`.
  - Integración de hechos electorales colombianos 2026 y calibración de veredictos en `backend/app/Services/VerificacionService.php`.
  - Soporte de OpenRouter y robustecimiento de parser en `backend/app/Adapters/Ia/OpenAiDriver.php`.
  - Expansión de la suite de pruebas unitarias y de integración de backend a **142 tests PHPUnit** (2.322 aserciones) incorporando pruebas dedicadas en `BusquedaSemanticaTest.php`, `OpenAiDriverTest.php`, `VerificacionServiceTest.php` y `VerificacionesBackendFeatureTest.php`.
- **Ampliación de Resiliencia Móvil y Timeout de 120s (PR #24 - `f07586e`, `161ce15`)**:
  - Ampliación del timeout de red de la aplicación móvil Expo a 120 segundos para soportar verificaciones complejas multietapa y multi-modelo.
  - Sincronización y normalización de endpoints REST v1 en `mobile-expo/src/services/api.js` y pantallas (`FuentesScreen.js`, `HistorialScreen.js`, `PerfilScreen.js`, `ResultadoScreen.js`).
- **Pipeline CI/CD macOS para Generación de `DAGAZ.ipa` (PR #23 - `09b342c`, `185bc2f`)**:
  - Automatización en GitHub Actions ([`.github/workflows/build-ios-ipa.yml`](.github/workflows/build-ios-ipa.yml)) en runners macOS para compilar el binario nativo iOS sin firma para Sideloadly vía `expo prebuild` y `xcodebuild`.
  - Configuración de perfiles de empaquetado `eas.json` en `mobile-expo/`.

---

## [0.1.4] - 2026-11-06
### Añadido
- **Aplicación Móvil React Native Expo y Restauración de Contenedor Android Capacitor (PR #22 - `5e8f877`, `7815c31`)**:
  - Creación de la aplicación móvil multiplataforma completa en [`mobile-expo/`](mobile-expo/) (Expo SDK 52+, React Navigation, WCAG 2.1 AA) con pantallas dedicadas para verificación multicanal (CU06), resultado con indicador circular SVG y veredictos semafóricos (CU03/CU06), grafo de 80 fuentes IFCN (CU07), notas comunitarias (CU01/CU02), historial cronológico (CU04) y perfil con Hábeas Data Ley 1581 (CU05).
  - Selector tipográfico de accesibilidad `A / A+` (+25%) para adultos mayores (+40 años).
  - Restauración del contenedor nativo Android Capacitor en [`mobile/`](mobile/) para pruebas de empaquetado encapsulado y sincronización en CI.

---

## [0.1.3] - 2026-11-03
### Añadido
- **Consejo de Verificación Multi-Modelo y Galería de Mockups de Alta Fidelidad (PR #19 - `c42eb3e`, `35f3ce3`, `5fe57c1`)**:
  - Implementación de [`backend/app/Services/MultiModeloHarnessService.php`](backend/app/Services/MultiModeloHarnessService.php) para evaluación paralela y consenso entre LLMs de frontera (GPT-4o, Claude 3.5 Sonnet, Gemini 2.0 Flash) y modelos abiertos (DeepSeek-R1, Qwen 2.5) vía HTTP Pool asíncrono.
  - Incorporación de 10 mockups interactivos de alta fidelidad en [`docs/mockups-alta-fidelidad/`](docs/mockups-alta-fidelidad/) (CU01 al CU07 y panel editorial REQ13).
  - Ingesta masiva RAG de noticias colombianas hacia Neon.tech ([`ingesta_noticias_colombia.py`](ingesta_noticias_colombia.py) e [`.ipynb`](ingesta_noticias_colombia.ipynb)).
  - Ajuste de CI para multi-modelo en testing y corrección de fallback defensivo de fuentes con score cero.

---

## [0.1.2] - 2026-10-12
### Modificado
- **Mantenimiento y Estabilidad de Entorno CI (PR #15 - `1e88d09`, `0870a00`, `8b195df`)**:
  - Fijación de acción `download-artifact` a la versión parcheada `v4.1.3` en el workflow de CI.
  - Restauración de rutas frontend y salvaguarda condicional para la explicación de veredictos sin justificación externa.

---

## [0.1.1] - 2026-10-08
### Modificado
- **Refactorización de Capa Frontend y Reestructuración de Historial (PR #13 - `331edec`, `033404b`)**:
  - Consolidación del módulo Front-Dagaz y estandarización del control de versiones.

---

## [0.1.0] - 2026-10-06
### Añadido
- **Consolidación de la Versión Pre-Alpha del Proyecto (v0.1.0-pre-alpha)**:
  - Culminación de los 7 Sprints planificados (281 pts al 100%), 28 requisitos del Backlog y 7 Casos de Uso.
  - Suite de 149 pruebas iniciales (122 PHPUnit, 23 Vitest, 4 Python).
  - Benchmark de 500 afirmaciones electorales ($\kappa = 0{,}794$, $F_1 = 0{,}828$) y auditoría de sesgo político ($\chi^2 = 0{,}682$, $p > 0{,}05$).
  - Manuales operativos formales (Usuario Adultos, Administrador Revisor, Despliegue e Infraestructura) y acta de traspaso REQ-28.

---

## [0.0.34] - 2026-10-06
### Añadido
- Benchmark de 500 afirmaciones y auditoría formal de sesgo político electoral.

---

## [0.0.33] - 2026-10-05
### Añadido
- Modularización de frontend con Vite y Tailwind CSS v4 accesible; suite de 23 pruebas Vitest.

---

## [0.0.32] - 2026-10-05
### Añadido
- Comando de ingesta periódica RSS `dagaz:actualizar-noticias` y compatibilidad con UUIDs en tokens Sanctum en Neon.tech.

---

## [0.0.31] - 2026-10-04
### Añadido
- **Diagrama de Gantt Oficial y Cronograma de 7 Sprints**: Incorporación del cronograma WBS maestro de 14 semanas con dependencias, hitos de control (Backend Freeze, Frontend Freeze, MVP) y ruta crítica en [`docs/iteracion-02/Guidelines/Diagrama_de_Gantt.md`](docs/iteracion-02/Guidelines/Diagrama_de_Gantt.md).
- Visualización de flujo de dependencias temporales y ruta crítica de sprints en Mermaid.

### Modificado
- Actualización integral de toda la documentación del repositorio (`README.md`, `backend/README.md`, `frontend/README.md`, `database/README.md`, `tests/README.md`, `mobile/README.md`, y documentos de las iteraciones 01, 02, 03 y final) para reflejar la arquitectura viva del proyecto.
- Sincronización del Historial de Control y Bitácora de Decisiones en [`docs/iteracion-02/Guidelines/Historial_de_Control.md`](docs/iteracion-02/Guidelines/Historial_de_Control.md) y [`docs/iteracion-01/Guidelines/Historial_de_Control.md`](docs/iteracion-01/Guidelines/Historial_de_Control.md).

---

## [0.0.30] - 2026-10-04
### Añadido
- **Capa Frontend Móvil Responsiva y Desacoplada (`frontend/`)**:
  - `frontend/index.html`: Flujo principal de verificación (CU06) con diseño accesible para Adultos (+40) bajo norma **WCAG 2.1 AA** (tipografía $\ge 18\text{px}$, botones $\ge 48\text{px}$, alto contraste). Soporte de entrada multicanal (texto directo, URL, archivo/foto con drag-and-drop, grabación de notas de voz en navegador). Animación multi-etapa del pipeline y tarjeta de resultado con semáforo y medidor circular SVG de confianza (0-100%).
  - `frontend/fuentes.html`: Catálogo y explorador interactivo del Grafo de Credibilidad (CU07) de 80 fuentes periodísticas con filtros por 4 niveles IFCN y búsqueda reactiva en tiempo real.
  - `frontend/resultado.html`: Vista de detalle individual de veredicto con citas de contraste y enlaces a fuentes originales.
  - `frontend/acerca.html`: Documentación institucional, metodología de verificación y políticas de cumplimiento de la Ley 1581 de 2012 (Hábeas Data).
- **Servidor Ligero Desacoplado (`backend/api_server.php`)**:
  - Servidor de desarrollo zero-dependency con enrutamiento REST, soporte CORS nativo y pipeline de verificación multicanal para pruebas rápidas.
- **Configuración CORS (`backend/config/cors.php`)**:
  - Habilitación de orígenes cruzados para consumo desde clientes web locales y remotos.
- **Seeder de Documentos de Noticias (`backend/database/seeders/DocumentosNoticiasSeeder.php`)**:
  - Generación de artículos estructurados en 6 metadatas temáticas (Finanzas, Política, Deportes, Estilo de Vida, Educación) vinculados al grafo de fuentes.
- **Suite de Pruebas de Requisitos de Usuario (`backend/tests/Feature/VerificacionesBackendFeatureTest.php`)**:
  - Pruebas de extremo a extremo que validan los requerimientos de recepción multicanal, consulta a base de datos por metadata, contraste de hechos y emisión de veredicto con citas.

### Modificado
- `backend/app/Services/BusquedaSemanticaService.php`: Búsqueda híbrida enriquecida con filtrado temático por metadata y cálculo de similitud de coseno en memoria como fallback determinista ante entornos sin pgvector.
- `backend/app/Services/VerificacionService.php`: Habilitación de flujo público de verificación sin requerir autenticación obligatoria para usuarios móviles libres.
- `backend/app/Adapters/AdaptadorMulticanal.php`: Modo dual con soporte para microservicios FastAPI y simulación heurística defensiva.

---

## [0.0.29] - 2026-10-03
### Añadido
- **Microservicios Python Multicanal de Ingesta (`backend/Microservicios_Python/`)**:
  - Microservicio OCR (`ocr_service/app.py`, puerto `5001`): FastAPI + Uvicorn, procesamiento de imágenes con Pillow y Tesseract OCR (`pytesseract`, `lang='spa'`).
  - Microservicio STT (`stt_service/app.py`, puerto `5002`): FastAPI + Uvicorn y transcripción con `SpeechRecognition` más manejo heurístico en ausencia de backend remoto.
  - Script de arranque unificado `run_services.py` y suite de pruebas `tests/test_services.py` (`unittest`).
- Integración en Laravel mediante `AdaptadorMulticanal.php` con timeouts defensivos y reconexión automática.

---

## [0.0.28] - 2026-10-02
### Añadido
- **Hito de Control Backend Freeze — Implementación Completa de Servicios Core**:
  - Pipeline de verificación de 14 etapas en `VerificacionService.php` (extracción de afirmaciones, búsqueda semántica, contraste y síntesis).
  - Drivers de IA desacoplados bajo patrón Strategy (`OpenAiDriver`, `GeminiDriver`, `LocalLlmDriver`) en `backend/app/Adapters/Ia/`.
  - Circuit Breaker de control presupuestal y de fallos en llamadas a LLMs (`CircuitBreakerService.php`).
  - Detección de descontextualización histórica mediante conector Wayback Machine (`ArchiveOrgService.php`).
  - Módulo de recuperación interlingüe ES-EN (`ServicioInterlingue.php`).
  - API pública para redacciones socias (`PublicPartnerApiController.php`) con autenticación API Key (`EnsureValidApiKey`) y rate limiting `throttle:partners`.
  - Exportador forense para reguladores y medios (`AuditoriaExportService.php`) en JSON, CSV y ClaimReview Schema.org.
  - Suite de 107 pruebas automatizadas PHPUnit pasando al 100% con 2.107 aserciones (`backend/tests/`).

---

## [0.0.27] - 2026-10-01
### Añadido
- **Consolidación de Persistencia y Esquema Oficial Raiden (`database/Raiden.sql`)**:
  - Script DDL completo de PostgreSQL 18 con extensión `pgvector` para las 10 tablas del sistema con UUIDs nativos.
  - Documentación del diccionario de datos y estrategia de índices B-Tree en [`database/Documentacion-Raiden.md`](database/Documentacion-Raiden.md).
  - Diagrama Entidad-Relación actualizado en [`database/Diagrama-ER.md`](database/Diagrama-ER.md).

---

## [0.0.26] - 2026-09-30
### Añadido
- **Poblado del Grafo de Credibilidad de 80 Fuentes**:
  - Semillas completas de 80 fuentes categorizadas en 4 niveles IFCN (internacionales N1, regionales N2, nacionales N3, fact-checkers N4).
  - Ingesta masiva de 8.000+ noticias reales vectorizadas con embeddings densos (`vector(384)`, `all-MiniLM-L6-v2`) en 6 categorías de metadatos.
  - Consumo real de almacenamiento optimizado a ~22 MB en Neon.tech.

---

## [0.0.25] - 2026-09-30
### Modificado
- **Migración Arquitectónica de Persistencia a PostgreSQL 18 + pgvector en Neon.tech (Free Tier)**:
  - Reemplazo de MariaDB como motor principal de persistencia.
  - Definición de política KNN exacta en disco SSD sin índices HNSW/IVFFlat para operar dentro de los 2 GB de RAM del tier gratuito.
  - Optimización de tipos de datos (`UUID` nativo de 16 bytes, `JSONB`, `VARCHAR(2048)`).

---

## [0.0.1] a [0.0.24] - 2026-08-23 a 2026-09-19
### Histórico
- Definición del problema, justificación y Canvas del Modelo de Negocio.
- Definición de los 7 Casos de Uso (CU01 a CU07) y Product Backlog de 28 requisitos (157 puntos).
- Aprobación formal del proyecto por el supervisor docente con calificación **4.0 / 5.0**.
- Diseño de diagramas de secuencia (DSS de caja negra y caja gris) y mockups de baja fidelidad.
- Elaboración de lineamientos metodológicos Scrum y arquitectura inicial Backend-First.
