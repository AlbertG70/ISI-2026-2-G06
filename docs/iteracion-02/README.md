# Iteración 02 — Implementación del Backend, Persistencia, Capa Frontend y Evolución

Esta carpeta contiene los documentos técnicos, bitácoras de gestión y evidencias correspondientes a la segunda iteración de **DAGAZ** (núcleo backend Laravel 13, microservicios Python, frontend modular y trazabilidad de ingeniería hasta `v1.5.1`).

---

## Entregables Principales

- [x] **[Documentación Funcional y Técnica del Backend (6 Tareas + Evolución)](Documentacion_Funcional_Tecnica_Backend_6_Tareas.md)**:
  - **Tarea 1**: Ingesta Multicanal con Microservicios Python FastAPI OCR y STT (`TSK-2.3` & `REQ-02`).
  - **Tarea 2**: Conector Archive.org / Wayback Machine y Detección de Descontextualización Histórica (`TSK-3.2` & `REQ-10`).
  - **Tarea 3**: Recuperación Interlingüe y Detección de Distorsión Cross-Lingual ES-EN (`TSK-4.4` & `REQ-19`).
  - **Tarea 4**: Grafo de Credibilidad de 80 Fuentes en 4 Niveles IFCN (`TSK-3.1`, `TSK-4.1` / `REQ-14`, `REQ-20`).
  - **Tarea 5**: Autenticación por API Key (`EnsureValidApiKey`) y Rate Limiting para Redacciones Socias M2M (`TSK-4.2` & `REQ-16`).
  - **Tarea 6**: Exportador de Auditoría Forense y ClaimReview Schema.org para Reguladores (`TSK-4.6` & `REQ-25`).
  - **Tarea 7**: Integración Frontend Móvil Responsivo (+40 WCAG 2.1 AA), Servidor Ligero `api_server.php` y CORS.
  - **Evolución Post-MVP (`v1.1.0`–`v1.5.1`)**: Consejo de Verificación Multi-Modelo (`MultiModeloHarnessService`), streaming reactivo SSE (`/api/v1/verificacion/stream`), búsqueda web en vivo (`BusquedaWebService`), cadena de respaldo `GeminiDriver`, soporte Neon Serverless SSL + Sanctum UUID, Docker multi-stage / PaaS, normalización SQL sin tildes (`normalizarSinTildes()`), blindaje `SIN_EVIDENCIA`, parser OpenRouter (`<think>` / `reasoning`) y ecosistema móvil dual (`Capacitor 6` + `Expo SDK 58`).
- [x] **Pruebas Automatizadas de la Solución**: **190 pruebas automatizadas pasando al 100%** (**163 PHPUnit** [**2.464 aserciones**] en [`backend/tests/`](../../backend/tests/), **23 Vitest** en [`frontend/tests/`](../../frontend/tests/) y **4 Python `unittest`** en [`backend/Microservicios_Python/tests/`](../../backend/Microservicios_Python/tests/)).
- [x] **Código Fuente del Backend (Laravel 13 / PHP 8.4)**: Ubicado en [`backend/`](../../backend/).
- [x] **Microservicios de Procesamiento**: OCR y STT en Python FastAPI ubicados en [`backend/Microservicios_Python/`](../../backend/Microservicios_Python/).
- [x] **Capa Frontend Móvil Responsiva**: Vistas Blade + Vite 8 + Tailwind CSS v4 en [`frontend/`](../../frontend/) (`views/`, `src/`, `index.html`, `fuentes.html`, `resultado.html`, `acerca.html`), 10 mockups de baja fidelidad en [`Mockups/MockUps-Baja Fidelidad/`](Mockups/MockUps-Baja%20Fidelidad/) y prototipos de alta fidelidad en [`docs/mockups-alta-fidelidad/`](../mockups-alta-fidelidad/).
- [x] **Clientes Móviles Duales (`REQ-34`)**: Contenedor híbrido **Capacitor 6** (APK Android) en [`mobile/`](../../mobile/) y aplicación nativa **React Native / Expo SDK 58** (con compilación `.ipa` en GitHub Actions y consola CoT en tiempo real) en [`mobile-expo/`](../../mobile-expo/).

---

## Guidelines y Persistencia del Proyecto

- [x] **[Product Backlog Actualizado](Guidelines/Backlog%20del%20Proyecto.md)**: **35 requisitos** (28 de línea base en 4 fases = **157 SP** + 7 post-MVP = **39 SP**, más **11 acciones fundacionales** = **249 SP totales**).
- [x] **[Sprint Backlogs Realizados](Guidelines/Sprint_Backlog_Realizados.md)**: Detalle granular de los Sprint Backlogs (Sprints 1 a 8 y bloque evolutivo `v1.1.0`–`v1.5.1`), metas, tareas WBS, puntos y evidencias de código.
- [x] **[Plan de Gestión, Desarrollo e Implementación](Guidelines/Plan_Gestion_Desarrollo_Implementacion.md)**: Estrategia Backend-First en 8 Sprints + evolución, matriz de riesgos, Hábeas Data (Ley 1581) y pipelines CI/CD (`ci.yml`, `build-ios-ipa.yml`).
- [x] **[Cronograma Maestro y Diagrama de Gantt](Guidelines/Diagrama_de_Gantt.md)**: Cronograma WBS de 16 semanas (S1 a S8 + post-MVP), hitos de control (Gates 0 a 5, incluyendo Gate Supervisor 5/5), exportación visual HTML (`Diagrama_de_Gantt_Visual.html`) y flujo de ruta crítica en Mermaid.
- [x] **[Historial de Control y Bitácora de Decisiones](Guidelines/Historial_de_Control.md)**: Trazabilidad cronológica desde `v0.0.1` hasta `v1.5.1`.
- [x] **[Changelog Oficial del Repositorio](../../CHANGELOG.md)**: Registro en formato Keep a Changelog en la raíz del proyecto.

---

## Integrantes

| Integrante | Código | Rol |
|:--|:--:|:--|
| **Alberto Mario Galvis Echeverria** | `0222410070` | Analista de Requerimientos / Desarrollador FullStack |
| **Camilo Isaac Acevedo Rueda** | `0222410049` | Analista de Calidad / Desarrollador FullStack |

---

## Estado de la Entrega

- [x] Documento técnico y funcional del backend y evolución multi-agente actualizado
- [x] Código fuente del backend Laravel 13 estructurado con arquitectura de servicios, streaming SSE y contratos OpenAPI 3.1
- [x] **190 pruebas automatizadas pasando al 100%** (163 PHPUnit [`2.464` aserciones] + 23 Vitest + 4 Python `unittest`)
- [x] Interfaz de usuario frontend móvil accesible (Blade + Vite + Tailwind v4) y clientes Capacitor 6 (APK) / Expo SDK 58 (IPA) integrados
- [x] Cronograma de Gantt (incluyendo versión visual HTML), Sprint Backlogs e Historial de Control actualizados a `v1.5.1`
- [x] Evidencias técnicas, modelos de datos y endpoints REST v1 auditados
