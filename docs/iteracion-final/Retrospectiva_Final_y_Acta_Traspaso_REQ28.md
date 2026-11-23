# DAGAZ — Retrospectiva Final del Proyecto y Acta de Cierre / Traspaso Formal (REQ-28 / TSK-7.4)
## Balance de Desarrollo, Lecciones Aprendidas, Entrega Institucional y Evolución `v1.4.1`

> **Proyecto:** DAGAZ — Plataforma de Verificación de Información y Desinformación en Colombia  
> **Asignatura:** Ingeniería de Servicios de Internet — 2026-2 — Grupo 06  
> **Requisito Asociado:** REQ-28 (Retrospectiva Final y Cierre de Transición), TSK-7.4  
> **Equipo de Desarrollo:** Alberto Mario Galvis Echeverria (`0222410070`) & Camilo Isaac Acevedo Rueda (`0222410049`)  
> **Entidad Receptora:** Facultad de Ingeniería de Tecnologías de Información y Comunicaciones — Universidad Pontificia Bolivariana (UPB)

---

## 1. Balance General de Alcance y Desempeño

El proyecto **DAGAZ** ejecutó su ciclo MVP siguiendo la metodología ágil Scrum a lo largo de 7 Sprints (Octubre de 2026, **28/28 requisitos MVP** / `157 pts`, más `53 pts` de actividades base) y amplió posteriormente sus capacidades hasta la versión consolidada **`v1.4.1`** (Noviembre de 2026):

```text
┌────────────────────────────────────────────────────────────────────────────┐
│            BALANCE CUANTITATIVO DEL PROYECTO (CIERRE v1.5.1)               │
├────────────────────────────────────────────────────────────────────────────┤
│  • Puntos de Historia Planificados y Completados (Requisitos MVP + Post):  │
│      - Requisitos MVP (REQ-01 a REQ-28):          157 pts (100%)           │
│      - Evolución Post-MVP (REQ-29 a REQ-35):       39 pts (100%)           │
│      - Actividades Base Ingeniería (ACT-01..11):   53 pts (100%)           │
│      - Total Consolidado Backlog del Proyecto:    249 SP  (100%)           │
│  • Casos de Uso del Negocio (CU01–CU07):           7 de 7 implementados    │
│  • Pruebas Automatizadas Backend (PHPUnit):      163 pasando (2.464 aserc.)│
│  • Pruebas Automatizadas Frontend (Vitest):       23 pasando (8 suites)    │
│  • Pruebas Microservicios Python (unittest):       4 pasando (1 suite)     │
│  • Total Pruebas Automatizadas del Sistema:      190 pasando (0 fallos)    │
│  • Deuda Técnica Crítica / Bloqueante:             0 incidencias           │
└────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Resumen de Fases y Despliegue por Sprint

| Fase / Sprint | Enfoque Principal | Puntos | Estado | Principales Entregables |
| :--- | :--- | :---: | :---: | :--- |
| **Actividades Base (`ACT-01..11`)** | Fundamentos e Ingeniería | 53 | ✅ 100% | Propuesta, objetivos, metodología, alcance, especificación CU01–CU07 y mockups baja fidelidad. |
| **Prioridad 1 (Sprints 1–2)** | Esqueleto Funcional | 45 | ✅ 100% | Pipeline IA desacoplado (`VerificacionService`), microservicios OCR/STT FastAPI, fuentes Nivel 1. |
| **Prioridad 2 (Sprints 3–4)** | Demo Utilizable | 44 | ✅ 100% | Archive.org, panel editorial REQ-13, API `/api/v1/public/*`, esquema relacional `Raiden`. |
| **Prioridad 3 (Sprints 5–6)** | Listo para Piloto | 44 | ✅ 100% | Frontend Blade en `frontend/`, notas comunitarias, benchmark 500 afirmaciones ($\kappa = 0{,}794$). |
| **Prioridad 4 (Sprint 7 — MVP `v1.0.0`)** | Pulir y Traspaso | 24 | ✅ 100% | Auditoría forense, onboarding socios 2/3, manuales, CI/CD, liberación MVP `v1.0.0`. |
| **Post-MVP (`v1.1.0` – `v1.4.1`)** | Vectorización, Multi-Modelo & Móvil | 39 | ✅ 100% | PostgreSQL 18 `pgvector` en Neon, Consejo Multi-Modelo, ingesta RSS/vectorial, `mobile/` Capacitor 6, `mobile-expo/` Expo SDK 58 + `.ipa` CI. |
| **TOTAL CONSOLIDADO** | | **249** | **100%** | **Plataforma integral lista para operación Web, M2M y Móvil (`v1.4.1`)** |

---

## 3. Retrospectiva del Equipo de Desarrollo (Scrum Retrospective)

### 3.1 ¿Qué funcionó excepcionalmente bien?
1. **Estrategia Backend-First y Contratos Claros:** Diseñar primero los modelos, migraciones con restricciones estrictas y contratos OpenAPI v1 permitió evolucionar hacia Neon `pgvector`, streaming SSE, búsqueda web en vivo, clientes móviles (`mobile/`, `mobile-expo/`) y socios M2M sin reescribir la lógica central.
2. **Desacoplamiento Modular (`backend/`, `frontend/`, `mobile/`, `mobile-expo/`):** Separar vistas Blade, compilado Vite 8 + Tailwind v4 y aplicaciones móviles permitió reutilizar una única API REST Sanctum para web responsiva, contenedor híbrido Capacitor 6 y app nativa React Native Expo.
3. **Ergonomía y Accesibilidad para Adultos (+40 años):** La tipografía base de 18px, objetivos táctiles de ≥ 48px y el alternador `A / A+` persistente se replicaron coherentemente tanto en Web como en la aplicación móvil nativa.
4. **Cobertura QA Multi-Capa:** Escalar la batería hasta **190 pruebas automatizadas** (**163 PHPUnit** con 2.464 aserciones + **23 Vitest** + **4 Python `unittest`**) respaldó cambios complejos en OpenRouter, GeminiDriver, búsqueda web, streaming SSE, normalización diacrítica y el Consejo Multi-Modelo sin regresiones.

### 3.2 Lecciones Aprendidas y Desafíos Técnicos Superados
1. **Búsqueda Vectorial en Tier Gratuito Serverless (`Neon.tech`):** Al detectar que los índices `HNSW`/`IVFFlat` amenazaban el límite de 2 GB de RAM, se adoptó búsqueda KNN exacta desde disco SSD NVMe con pre-filtrado relacional por `fuente_id` y `categoria`, manteniendo latencias de `10–35 ms`.
2. **Consenso y Protección *Anti-Downgrade* en Modelos de Razonamiento:** Modelos externos con bloques `<think>` o cautela ante fechas posteriores a su corte tendían a degradar veredictos `FALSO` a `NO_VERIFICABLE`; se corrigió mediante parsing dedicado en `OpenAiDriver` y la salvaguarda de confianza en `MultiModeloHarnessService`.
3. **Mitigación de Inyecciones y Hábeas Data:** El filtrado estricto en Notas Comunitarias (`VerificacionService::analizarNota`) junto con los endpoints `GET /api/v1/auth/datos-personales` y `DELETE /api/v1/auth/cuenta` blindaron el cumplimiento de la Ley 1581 de 2012.

---

## 4. Acta Formal de Entrega y Traspaso de la Plataforma (Cierre MVP e Integración `v1.4.1`)

En la ciudad de Bucaramanga / Medellín, con cierre inicial del ciclo MVP en octubre de 2026 y consolidación técnica de la versión **`v1.4.1`** al **09 de noviembre de 2026**, el equipo desarrollador de **DAGAZ** formaliza el traspaso técnico del repositorio y sus evidencias a la Universidad Pontificia Bolivariana.

### 4.1 Declaración de Bienes y Entregables Transferidos
1. **Repositorio de Código Fuente Completo:**
   - [`backend/`](../../backend/) (`Laravel 13.17`, PHP `8.3–8.4`, Sanctum `4.3`, PHPUnit `12.5`) y [`backend/Microservicios_Python/`](../../backend/Microservicios_Python/) (FastAPI OCR/STT).
   - [`frontend/`](../../frontend/) (Vite 8, Tailwind CSS v4, Vitest 5.0, vistas Blade accesibles).
   - [`mobile/`](../../mobile/) (Contenedor híbrido Capacitor 6 Android/iOS) y [`mobile-expo/`](../../mobile-expo/) (App nativa Expo SDK 58 / React Native 0.88).
2. **Base de Datos y Pipelines de Ingesta:** Script DDL [`database/Raiden.sql`](../../database/Raiden.sql) (PostgreSQL 18 + `pgvector`), migraciones compatibilizadas (PostgreSQL/MariaDB/SQLite), `FuentesSeeder` (80 fuentes) e ingestores [`ingesta_noticias_colombia.py`](../../ingesta_noticias_colombia.py) y `php artisan dagaz:actualizar-noticias`.
3. **Pipelines Automatizados de CI/CD en GitHub Actions:**
   - [`.github/workflows/ci.yml`](../../.github/workflows/ci.yml) (Backend, Frontend y Mobile Capacitor Sync).
   - [`.github/workflows/build-ios-ipa.yml`](../../.github/workflows/build-ios-ipa.yml) (Compilación automatizada de `DAGAZ.ipa` para Sideloadly en `macos-latest`).
4. **Conjunto Completo de Manuales, Informes y Mockups:**
   - [Manual de Usuario para Adultos (+40 años)](Manual_Usuario_Adultos.md)
   - [Manual de Administrador y Revisor Humano](Manual_Administrador_Revisor.md)
   - [Manual de Despliegue e Infraestructura](Manual_Despliegue_Infraestructura.md)
   - [Guía de Onboarding de Redacciones Socias](Onboarding_Redacciones_Socias_REQ26.md)
   - [Informe del Benchmark de 500 Afirmaciones y Auditoría de Sesgo](Evaluacion_Benchmark_500_Afirmaciones_y_Auditoria_Sesgo.md)
   - [Documentación Consolidada de la Iteración Final](Documentacion_Consolidada_Iteracion_Final.md) y [10 Mockups Interactivos de Alta Fidelidad](../mockups-alta-fidelidad/index.html)

---

## 5. Addendum de Evolución y Cierre Técnico Post-MVP (`v1.0.0` → `v1.4.1`, Noviembre 2026)

Tras el congelamiento funcional MVP de octubre de 2026 (`v1.0.0`), el repositorio incorporó siete capacidades productivas (`REQ-29` a `REQ-35`, documentadas en detalle en [`CHANGELOG.md`](../../CHANGELOG.md) y [`Historial_de_Control.md`](../iteracion-02/Guidelines/Historial_de_Control.md)):
1. **Base Vectorial Productiva (`v1.1.0`):** Transición a PostgreSQL 18 + `pgvector` en Neon.tech (`vector(384)` KNN exacto) e ingesta continua de noticias colombianas (`php artisan dagaz:actualizar-noticias` e `ingesta_noticias_colombia.py`).
2. **Mockups Alta Fidelidad + Consejo Multi-Modelo (`v1.2.0` – `v1.3.0`):** Creación de 10 pantallas interactivas en `docs/mockups-alta-fidelidad/` e implementación de `MultiModeloHarnessService` (`Http::pool` concurrente con votación ponderada).
3. **Calibración Diacrítica y OpenRouter (`v1.3.1` – `v1.3.2`):** Soporte de razonamiento extendido (`OpenAiDriver`) y `normalizarSinTildes()` en `BusquedaSemanticaService`.
4. **Expansión Móvil Nativa iOS/Android e Integración Final (`v1.4.0` – `v1.4.1`):** Desarrollo de `mobile-expo/`, compilación cloud `DAGAZ.ipa` vía `build-ios-ipa.yml`, endurecimiento *anti-downgrade* en el Consejo Multi-Modelo y alineación documental completa.

---

### Firmas de Conformidad

```text
_____________________________________________         _____________________________________________
      Alberto Mario Galvis Echeverria                        Camilo Isaac Acevedo Rueda
             C.C. / ID: 0222410070                                  C.C. / ID: 0222410049
   Analista de Requerimientos / FullStack                 Analista de Calidad (QA) / FullStack
                 Grupo 06                                               Grupo 06
```
