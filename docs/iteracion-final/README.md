# Iteración Final — Despliegue CI/CD, Manuales, Liberación MVP `v1.0.0` y Consolidado `v1.5.1`

Esta carpeta contiene los documentos formales y evidencias de cierre correspondientes a la entrega final y evolución post-MVP de la plataforma **DAGAZ** (`v1.5.1`, corte al `17 de Noviembre de 2026`).

---

## Alcance de la Iteración Final (Sprint 7) y Evolución Post-MVP

De acuerdo con el [Plan de Gestión](../iteracion-02/Guidelines/Plan_Gestion_Desarrollo_Implementacion.md), el [Diagrama de Gantt](../iteracion-02/Guidelines/Diagrama_de_Gantt.md) y el [Sprint Backlog Realizado](../iteracion-02/Guidelines/Sprint_Backlog_Realizados.md), la Iteración Final y su cierre consolidado comprenden:

1. **Pipelines de Integración y Despliegue Continuo CI/CD (REQ-27 / REQ-35):**
   - Automatización en GitHub Actions ([`.github/workflows/ci.yml`](../../.github/workflows/ci.yml)): **163 pruebas PHPUnit** (`2.464` aserciones), **23 pruebas Vitest** (8 suites), compilación `frontend/dist` y verificación `npx cap sync android` en `mobile/`.
   - Compilación automatizada iOS sin firma para Sideloadly ([`.github/workflows/build-ios-ipa.yml`](../../.github/workflows/build-ios-ipa.yml)): generación de `DAGAZ.ipa` desde `mobile-expo/` en `macos-latest`.
2. **Incorporación de Redacciones Socias y Auditoría Forense (REQ-22, REQ-25, REQ-26):**
   - API pública M2M bajo `/api/v1/public/*` con `X-API-KEY` para *El Espectador*, *ColombiaCheck* y *La Silla Vacía*, exportación forense SHA-256 y `ClaimReview` JSON-LD.
3. **Manuales de Procedimiento, Despliegue y Operación (REQ-27):**
   - [Manual de Despliegue e Infraestructura](Manual_Despliegue_Infraestructura.md) (Neon PostgreSQL 18 `pgvector`, Laravel 13, Docker multi-stage / PaaS, FastAPI Python, Capacitor 6 y Expo SDK 58).
   - [Manual de Usuario para Adultos (+40)](Manual_Usuario_Adultos.md) y [Manual de Administrador y Revisor Humano](Manual_Administrador_Revisor.md).
4. **Evaluación Formal, Retrospectiva y Evolución `v1.5.1` (REQ-11, REQ-17, REQ-24, REQ-28, REQ-29..35):**
   - Certificación del Benchmark de 500 afirmaciones ($\kappa = 0{,}794$, Precisión $84{,}6\%$, Recall $81{,}2\%$, $\chi^2 p = 0{,}711$), Consejo Multi-Modelo (`MultiModeloHarnessService`), streaming reactivo SSE (`/api/v1/verificacion/stream`), búsqueda web en vivo (`BusquedaWebService`), fallback `GeminiDriver`, ingesta colombiana y traspaso formal.

---

## Entregables Completados

- [x] [**Documentación Consolidada de la Iteración Final**](Documentacion_Consolidada_Iteracion_Final.md) — Memoria técnica completa, cobertura de requisitos (`REQ-01`..`REQ-35`) y matriz QA de `190` pruebas.
- [x] [**Manual de Despliegue e Infraestructura**](Manual_Despliegue_Infraestructura.md) — Topología Neon `pgvector`, Laravel 13, Docker multi-stage, PaaS, microservicios FastAPI, Nginx, CI/CD y clientes móviles.
- [x] [**Manual de Usuario para Adultos (+40)**](Manual_Usuario_Adultos.md) — Guía accesible WCAG 2.1 AA, selector `A / A+`, verificación ciudadana y Ley 1581 de 2012.
- [x] [**Manual de Administrador y Revisor Humano**](Manual_Administrador_Revisor.md) — Protocolo de intervención editorial (REQ-13), moderación, Circuit Breaker y auditoría forense (REQ-25).
- [x] [**Onboarding de Redacciones Socias**](Onboarding_Redacciones_Socias_REQ26.md) — Especificación real `/api/v1/public/*` con `X-API-KEY`, cuotas y `ClaimReview` (REQ-26).
- [x] [**Evaluación Benchmark 500 Afirmaciones y Auditoría de Sesgo**](Evaluacion_Benchmark_500_Afirmaciones_y_Auditoria_Sesgo.md) — Resultados empíricos (REQ-11, REQ-17, REQ-24) y calibración post-MVP.
- [x] [**Retrospectiva Final y Acta de Traspaso**](Retrospectiva_Final_y_Acta_Traspaso_REQ28.md) — Balance Scrum, lecciones aprendidas, firma de traspaso y addendum evolutivo `v1.5.1` (REQ-28).
- [x] **Pipelines CI/CD operativos** ([`ci.yml`](../../.github/workflows/ci.yml) y [`build-ios-ipa.yml`](../../.github/workflows/build-ios-ipa.yml)) — Con `190` pruebas automatizadas (`163` PHPUnit + `23` Vitest + `4` Python `unittest`) al `100%`.

---

## Integrantes del Proyecto

| Integrante | Código | Rol |
| :--- | :---: | :--- |
| **Alberto Mario Galvis Echeverria** | `0222410070` | Analista de Requerimientos / Desarrollador FullStack |
| **Camilo Isaac Acevedo Rueda** | `0222410049` | Analista de Calidad (QA) / Desarrollador FullStack |

---

## Estado de la Entrega

- [x] Documento de la iteración final y memoria técnica consolidada
- [x] Evidencias complementarias, suites de pruebas (`190` tests al 100%) y releases móvil / web / Docker
