# Backlog del Proyecto — DAGAZ

> **Descripción general:** Product Backlog priorizado por fases para el desarrollo del sistema de verificación y generación de veredictos, contrastado contra los requerimientos formales del proyecto, las 11 acciones fundacionales de ingeniería y modelado, y los incrementos evolutivos post-MVP (`v1.1.0` a `v1.4.1`).  
> **Actualización:** 09 de Noviembre de 2026 | **Versión:** `v1.4.1`

---

## Resumen de Fases y Estimaciones

| Prioridad / Fase | Nombre de la Fase | Total Requisitos | Puntos Canónicos (Backlog Base) | Suma Aritmética Detallada | Estado |
| :--- | :--- | :---: | :---: | :---: | :---: |
| **Prioridad 1** | Esqueleto funcional *(MVP Core)* | 8 | 45 pts | **53 pts** | ✅ 100% Completado |
| **Prioridad 2** | Demo utilizable *(Multi-fuente y Roles)* | 8 | 44 pts | **52 pts** | ✅ 100% Completado |
| **Prioridad 3** | Listo para pruebas piloto *(QA y Escala)* | 8 | 44 pts | **47 pts** | ✅ 100% Completado |
| **Prioridad 4** | Pulir y Traspaso *(DevOps y Handover)* | 4 | 24 pts | **24 pts** | ✅ 100% Completado |
| **Subtotal Línea Base MVP** | **Catálogo Original de 28 Requisitos** | **28** | **157 pts** | **176 pts** | **✅ 100% Completado** |
| **Fase Evolutiva Post-MVP** | Cloud Neon, Mockups AF, Multi-Modelo y Móvil Dual (`v1.1.0`–`v1.4.1`) | 7 | **39 pts** | **39 pts** | ✅ 100% Completado |
| **Subtotal Requisitos Totales** | **Catálogo Extendido (REQ-01 a REQ-35)** | **35** | **196 pts** | **215 pts** | **✅ 100% Completado** |
| **Fase Fundacional** | Ingeniería de Requisitos y Modelado (Acciones 1–11) | 11 | **53 pts** | **53 pts** | ✅ 100% Completado |
| **TOTAL GENERAL** | **Ciclo de Vida Integral DAGAZ** | **46 Ítems** | **249 pts** | **268 pts** | **✅ 100% Completado** |

> **Nota Metodológica de Puntos:**  
> El baseline contractual original del curso registraba **157 puntos** para los 28 requisitos iniciales (`45 + 44 + 44 + 24`), consolidados a **249 pts** al sumar los **39 pts** evolutivos post-MVP (`REQ-29` a `REQ-35`) y los **53 pts** fundacionales (`ACT-01` a `ACT-11`). En la tabla granular de la Iteración 02, la suma aritmética ítem por ítem de los 28 requisitos base asciende a `176 pts` (`215 pts` con evolutivo; `268 pts` con acciones fundacionales), y a nivel de capacidad de equipo y tareas técnicas de infraestructura, pruebas y CI/CD en los 7 Sprints se ejecutaron **281 puntos de esfuerzo ágil** más **39 puntos** post-MVP (**320 pts ejecutados**).

---

## Prioridad 1 — Esqueleto funcional
*El sistema de extremo a extremo más pequeño que genera un veredicto*

| # | Requisito | Puntos | Sprint | Estado | Componente / Evidencia |
| :-: | :--- | :-: | :-: | :-: | :--- |
| **REQ-01** | Extractor de afirmaciones: aislar la afirmación verificable de un titular (objetivo $F_1 \ge 0{,}75$ sobre 100 afirmaciones etiquetadas manualmente) | 8 | **Sprint 2** | ✅ Completado | [`VerificacionService.php`](../../../backend/app/Services/VerificacionService.php) + [`VerificacionServiceTest.php`](../../../backend/tests/Unit/VerificacionServiceTest.php) |
| **REQ-02** | OCR y capturas de pantalla: extraer texto de imágenes/capturas y audio vía microservicios multicanal | 5 | **Sprint 2** | ✅ Completado | [`backend/Microservicios_Python/`](../../../backend/Microservicios_Python/) (`:5001`, `:5002`) + [`AdaptadorMulticanal.php`](../../../backend/app/Adapters/AdaptadorMulticanal.php) |
| **REQ-03** | Agente de descomposición: dividir afirmaciones compuestas en unidades atómicas verificables | 8 | **Sprint 2** | ✅ Completado | [`VerificacionService.php`](../../../backend/app/Services/VerificacionService.php) (Fase 3: Atomización) |
| **REQ-04** | Agente de verificación cruzada v1: comparar la evidencia recuperada con la afirmación; ponderar según el nivel de la fuente | 8 | **Sprint 2** | ✅ Completado | [`VerificacionService.php`](../../../backend/app/Services/VerificacionService.php) + [`dagaz.php`](../../../backend/config/dagaz.php) |
| **REQ-05** | Agente de recuperación de evidencia v1: búsqueda semántica y documental que recupera $\ge 5$ fuentes candidatas por afirmación | 8 | **Sprint 2** | ✅ Completado | [`BusquedaSemanticaService.php`](../../../backend/app/Services/BusquedaSemanticaService.php) + `pgvector` en Neon |
| **REQ-06** | Agente de síntesis del veredicto: genera la etiqueta del veredicto (`VERDADERO` / `FALSO` / `ENGAÑOSO` / `PARCIAL` / `NO_VERIFICABLE`) + confianza + trazabilidad | 8 | **Sprint 2** | ✅ Completado | [`VerificacionService.php`](../../../backend/app/Services/VerificacionService.php) + [`Veredicto.php`](../../../backend/app/Models/Veredicto.php) |
| **REQ-07** | Demo de «esqueleto funcional»: veredicto integral sobre afirmación electoral utilizando fuentes de nivel 1 | 3 | **Sprint 2** | ✅ Completado | [`VerificacionesBackendFeatureTest.php`](../../../backend/tests/Feature/VerificacionesBackendFeatureTest.php) + `POST /api/v1/verificacion` |
| **REQ-08** | Grafo de credibilidad v1: registrar las 10 fuentes internacionales iniciales de nivel 1 (Reuters, AP, AFP, BBC, OMS, ONU, OCDE, etc.) | 5 | **Sprint 1** | ✅ Completado | [`FuentesSeeder.php`](../../../backend/database/seeders/FuentesSeeder.php) + [`Raiden.sql`](../../../database/Raiden.sql) |

**Subtotal Prioridad 1:** **53 puntos** en suma detallada (`45 pts` en baseline canónico) — **100% Completado**.

---

## Prioridad 2 — Demo utilizable
*El sistema de extremo a extremo ampliado con persistencia, redes históricas, panel editorial y contratos de redacción*

| # | Requisito | Puntos | Sprint | Estado | Componente / Evidencia |
| :-: | :--- | :-: | :-: | :-: | :--- |
| **REQ-09** | Monitor de redes sociales y canales: extraer e ingestar cadenas virales mediante adaptadores aprobados y RSS | 8 | **Sprint 3** | ✅ Completado | [`AdaptadorMulticanal.php`](../../../backend/app/Adapters/AdaptadorMulticanal.php) + [`ActualizarNoticiasCommand.php`](../../../backend/app/Console/Commands/ActualizarNoticiasCommand.php) |
| **REQ-10** | Herramienta de consulta de Archive.org: recuperación de versiones históricas de fuentes para detección de descontextualización | 5 | **Sprint 3** | ✅ Completado | [`ArchiveOrgService.php`](../../../backend/app/Services/ArchiveOrgService.php) + [`ArchiveOrgTest.php`](../../../backend/tests/Feature/ArchiveOrgTest.php) |
| **REQ-11** | Conjunto de referencia de 500 afirmaciones etiquetadas manualmente; acuerdo inter-evaluador $\ge 0{,}75$ | 8 | **Sprint 6** | ✅ Completado | Dataset evaluado con $\kappa = 0{,}794$ en [`Evaluacion_Benchmark_500_Afirmaciones_y_Auditoria_Sesgo.md`](../../iteracion-final/Evaluacion_Benchmark_500_Afirmaciones_y_Auditoria_Sesgo.md) |
| **REQ-12** | Diseño de Base de Datos: Usuarios (`lector`, `adulto`, `editor`, `admin`), afirmaciones, veredictos y notas de revisión | 8 | **Sprint 1** | ✅ Completado | [`Raiden.sql`](../../../database/Raiden.sql) y migraciones Laravel 13 (10 tablas de dominio + `personal_access_tokens` UUID) |
| **REQ-13** | Entorno de trabajo para revisores humanos: intervención editorial para revisar veredictos de baja confianza | 8 | **Sprint 3** | ✅ Completado | [`VerificacionController.php`](../../../backend/app/Http/Controllers/VerificacionController.php) + [`Manual_Administrador_Revisor.md`](../../iteracion-final/Manual_Administrador_Revisor.md) |
| **REQ-14** | Grafo de credibilidad v2: ampliar a 40 fuentes (prensa regional Nivel 2 y prensa nacional colombiana Nivel 3) | 5 | **Sprint 3** | ✅ Completado | [`FuentesSeeder.php`](../../../backend/database/seeders/FuentesSeeder.php) (ampliado y superado a 80 fuentes) |
| **REQ-15** | Interfaz de la tarjeta de veredicto: vista accesible con veredicto, confianza, fuentes y explicación | 5 | **Sprint 5** | ✅ Completado | [`frontend/views/verificacion/resultado.blade.php`](../../../frontend/views/verificacion/resultado.blade.php) y [`frontend/resultado.html`](../../../frontend/resultado.html) |
| **REQ-16** | API pública v1: endpoints REST M2M para integración de salas de redacción socias | 5 | **Sprint 4** | ✅ Completado | [`PublicPartnerApiController.php`](../../../backend/app/Http/Controllers/PublicPartnerApiController.php) (`/api/v1/public/*`) + OpenAPI 3.1 |

**Subtotal Prioridad 2:** **52 puntos** en suma detallada (`44 pts` en baseline canónico) — **100% Completado**.

---

## Prioridad 3 — Listo para pruebas piloto
*Resiliencia de costes, contraste interlingüe ES-EN, grafo de 80 fuentes IFCN y certificación formal*

| # | Requisito | Puntos | Sprint | Estado | Componente / Evidencia |
| :-: | :--- | :-: | :-: | :-: | :--- |
| **REQ-17** | Auditoría de sesgo v1: evaluación estructurada del sesgo político en el conjunto de referencia electoral | 8 | **Sprint 6** | ✅ Completado | Informe estadístico ($\chi^2 = 0{,}682$, $p = 0{,}711 > 0{,}05$) sin sesgo significativo |
| **REQ-18** | Controles de costes: presupuestos de API de LLM por hora/día con interrupción automática del circuito | 5 | **Sprint 4** | ✅ Completado | [`CircuitBreakerService.php`](../../../backend/app/Services/CircuitBreakerService.php) (`CLOSED`, `OPEN`, `HALF_OPEN`) |
| **REQ-19** | Recuperación interlingüe: gestionar afirmaciones de origen inglés que circulan en Colombia | 8 | **Sprint 4** | ✅ Completado | [`ServicioInterlingue.php`](../../../backend/app/Services/ServicioInterlingue.php) + [`InterlingueTest.php`](../../../backend/tests/Feature/InterlingueTest.php) |
| **REQ-20** | Grafo de credibilidad v3: ampliación a 80 fuentes con fact-checkers Nivel 4 certificados IFCN y control estatal | 8 | **Sprint 3** | ✅ Completado | 80 fuentes oficiales en [`FuentesSeeder.php`](../../../backend/database/seeders/FuentesSeeder.php) + [`GrafoFuentesTest.php`](../../../backend/tests/Feature/GrafoFuentesTest.php) |
| **REQ-21** | Prueba de carga y flujo continuo: actualización continua y disponibilidad $\ge 99\%$ | 8 | **Sprint 6** | ✅ Completado | [`ActualizarNoticiasCommand.php`](../../../backend/app/Console/Commands/ActualizarNoticiasCommand.php) + resiliencia comprobada |
| **REQ-22** | Incorporación de socios: primera redacción en producción (*El Espectador*), 50 veredictos consumidos | 5 | **Sprint 6** | ✅ Completado | 50 verificaciones M2M certificadas vía `/api/v1/public/verificar` |
| **REQ-23** | Incorporación de Base de Datos y Hábeas Data: primeros usuarios de prueba y roles | 5 | **Sprint 1** | ✅ Completado | [`UsuariosSeeder.php`](../../../backend/database/seeders/UsuariosSeeder.php) (roles `lector`, `adulto`, `admin`, `editor`) + [`AuthTest.php`](../../../backend/tests/Feature/AuthTest.php) |
| **REQ-24** | Evaluación formal: precisión $\ge 0{,}80$, exhaustividad $\ge 0{,}75$ en conjunto de referencia de 500 afirmaciones | 5 | **Sprint 6** | ✅ Completado | Precisión: **84.6%**, Exhaustividad: **81.2%**, $F_1 = 0{,}828$ |

**Subtotal Prioridad 3:** **47 puntos** en suma detallada (`44 pts` en baseline canónico) — **100% Completado**.

---

## Prioridad 4 — Pulir y Traspaso
*Auditoría regulatoria, redacciones aliadas secundarias, manuales operativos y acta de cierre*

| # | Requisito | Puntos | Sprint | Estado | Componente / Evidencia |
| :-: | :--- | :-: | :-: | :-: | :--- |
| **REQ-25** | Archivo de registro de auditoría forense (JSON, CSV, `ClaimReview` Schema.org) | 5 | **Sprint 4** | ✅ Completado | [`AuditoriaExportService.php`](../../../backend/app/Services/AuditoriaExportService.php) + [`AuditoriaExportTest.php`](../../../backend/tests/Feature/AuditoriaExportTest.php) |
| **REQ-26** | Incorporación de las redacciones socias segunda y tercera (*ColombiaCheck*, *La Silla Vacía*) | 8 | **Sprint 7** | ✅ Completado | [`Onboarding_Redacciones_Socias_REQ26.md`](../../iteracion-final/Onboarding_Redacciones_Socias_REQ26.md) |
| **REQ-27** | Manual de procedimientos de despliegue + manuales de Adulto Mayor y Revisor | 8 | **Sprint 7** | ✅ Completado | [`Manual_Usuario_Adultos.md`](../../iteracion-final/Manual_Usuario_Adultos.md), [`Manual_Administrador_Revisor.md`](../../iteracion-final/Manual_Administrador_Revisor.md) y [`Manual_Despliegue_Infraestructura.md`](../../iteracion-final/Manual_Despliegue_Infraestructura.md) |
| **REQ-28** | Retrospectiva final + cierre de la transición | 3 | **Sprint 7** | ✅ Completado | [`Retrospectiva_Final_y_Acta_Traspaso_REQ28.md`](../../iteracion-final/Retrospectiva_Final_y_Acta_Traspaso_REQ28.md) |

**Subtotal Prioridad 4:** **24 puntos** (`24 pts` en baseline canónico) — **100% Completado**.

---

## Fase Evolutiva Post-MVP (`REQ-29` a `REQ-35`, `v1.1.0` a `v1.4.1`)
*Endurecimiento productivo, consejo multi-agente en paralelo y ecosistema móvil dual*

| # | Requisito | Puntos | Versión | Estado | Componente / Evidencia |
| :-: | :--- | :-: | :-: | :-: | :--- |
| **REQ-29** | Soporte Cloud Serverless PostgreSQL Neon SSL y UUID en `personal_access_tokens` | 5 | `v1.1.0` | ✅ Completado | Migración Sanctum UUID y [`backend/.env.example`](../../../backend/.env.example) |
| **REQ-30** | Búsqueda semántica resiliente y fallback pedagógico cuando no hay fuentes coincidentes | 5 | `v1.1.0` | ✅ Completado | [`BusquedaSemanticaService.php`](../../../backend/app/Services/BusquedaSemanticaService.php) y [`VerificacionService.php`](../../../backend/app/Services/VerificacionService.php) |
| **REQ-31** | Galería de 10 Mockups de Alta Fidelidad interactivos WCAG 2.1 AA (`CU01`–`CU07` + `REQ13`) | 6 | `v1.2.0` | ✅ Completado | [`docs/mockups-alta-fidelidad/`](../../mockups-alta-fidelidad/) |
| **REQ-32** | Integración de proveedor unificado **OpenRouter** en `OpenAiDriver` | 5 | `v1.1.0` | ✅ Completado | [`OpenAiDriver.php`](../../../backend/app/Adapters/Ia/OpenAiDriver.php) y [`dagaz.php`](../../../backend/config/dagaz.php) |
| **REQ-33** | Consejo de Verificación Multi-Modelo en paralelo (`Http::pool`) e ingesta masiva Colombia | 6 | `v1.3.0` | ✅ Completado | [`MultiModeloHarnessService.php`](../../../backend/app/Services/MultiModeloHarnessService.php), [`ingesta_noticias_colombia.py`](../../../ingesta_noticias_colombia.py) e `.ipynb` |
| **REQ-34** | Ecosistema móvil dual (**Capacitor 6** en `mobile/` + **Expo SDK 58** en `mobile-expo/`) y CI `DAGAZ.ipa` | 7 | `v1.4.0` | ✅ Completado | [`mobile/`](../../../mobile/), [`mobile-expo/`](../../../mobile-expo/) y [`.github/workflows/build-ios-ipa.yml`](../../../.github/workflows/build-ios-ipa.yml) |
| **REQ-35** | Normalización SQL sin tildes PostgreSQL (`normalizarSinTildes`), contraste electoral 2026, parser OpenRouter (`<think>` / `reasoning` / `justificación`) y salvaguarda anti-degradación | 5 | `v1.4.1` | ✅ Completado | [`BusquedaSemanticaTest.php`](../../../backend/tests/Unit/BusquedaSemanticaTest.php), [`OpenAiDriverTest.php`](../../../backend/tests/Unit/OpenAiDriverTest.php) y [`MultiModeloHarnessService.php`](../../../backend/app/Services/MultiModeloHarnessService.php) |

**Subtotal Fase Evolutiva Post-MVP:** **39 puntos** — **100% Completado**.

---

## Fase Fundacional — Análisis de Requisitos y Modelado del Sistema (Sprints 1 y 2)

*Acciones metodológicas, de análisis y modelado de ingeniería de software que originaron y estructuraron los 28 requerimientos iniciales del Backlog.*

| # | Acción de Ingeniería / Modelado | Sprint | Complejidad | Estado | Entregable / Artefacto en Repositorio |
| :-: | :--- | :---: | :---: | :---: | :--- |
| **ACT-01** | **Matriz de ideación/identificación de problemáticas** | **S1** | **3 pts** | ✅ Hecho | [`01_Contexto_y_Metodologia_Dagaz.md`](../../iteracion-01/Guideline-Persistencia/01_Contexto_y_Metodologia_Dagaz.md) |
| **ACT-02** | **Elección de metodología** | **S1** | **5 pts** | ✅ Hecho | Adopción de Scrum con enfoque Backend-First |
| **ACT-03** | **Análisis de negocio** | **S1** | **3 pts** | ✅ Hecho | Objetivos de negocio y valor en `01_Contexto_y_Metodologia_Dagaz.md` |
| **ACT-04** | **Modelado del negocio** | **S1** | **5 pts** | ✅ Hecho | [`Canvas_Modelo_Negocio.png`](../../iteracion-01/Diagramas/Canvas_Modelo_Negocio.png) y [`Modelo_Dominio.png`](../../iteracion-01/Diagramas/Modelo_Dominio.png) |
| **ACT-05** | **Análisis de requisitos** | **S1** | **3 pts** | ✅ Hecho | Estructuración del Product Backlog en 4 fases |
| **ACT-06** | **Modelado de requisitos** | **S1** | **3 pts** | ✅ Hecho | Diagrama taxonómico [`Modelo_Requisitos.png`](../../iteracion-01/Diagramas/Modelo_Requisitos.png) |
| **ACT-07** | **Análisis de casos de uso** | **S2** | **5 pts** | ✅ Hecho | Alcance de CU01 a CU07 en [`02_Especificacion_Casos_de_Uso.md`](../../iteracion-01/Guideline-Persistencia/02_Especificacion_Casos_de_Uso.md) |
| **ACT-08** | **Descripción de casos de uso** | **S2** | **5 pts** | ✅ Hecho | Fichas narrativas formales con flujos alternativos |
| **ACT-09** | **Modelado de casos de uso** | **S2** | **8 pts** | ✅ Hecho | Diagrama UML [`Diagrama_Casos_de_Uso.png`](../../iteracion-01/Diagramas/Diagrama_Casos_de_Uso.png) |
| **ACT-10** | **Modelado de secuencias de los casos de uso** | **S2** | **5 pts** | ✅ Hecho | Diagramas de secuencia [`DSS (Caja Negra)/`](../../iteracion-01/Diagramas/DSS%20%28Caja%20Negra%29/) y [`DSS (Caja Gris)/`](../../iteracion-01/Diagramas/DSS%20%28Caja%20Gris%29/) |
| **ACT-11** | **Propuesta de mockups de la UI del servicio** | **S2** | **8 pts** | ✅ Hecho | Prototipos ergonómicos baja fidelidad en [`Mockups/`](../../iteracion-01/Mockups/) y alta fidelidad en [`mockups-alta-fidelidad/`](../../mockups-alta-fidelidad/) |

**Subtotal Acciones Fundacionales de Ingeniería y Modelado:** **53 puntos** (S1: 22 pts + S2: 31 pts) — **100% Hecho**.
