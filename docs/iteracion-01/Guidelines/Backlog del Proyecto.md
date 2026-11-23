# Product Backlog del Proyecto — DAGAZ

> **Documento Oficial del Product Backlog (PBL) — Trazabilidad Ágil y Control de Incrementos**  
> **Proyecto:** DAGAZ — Plataforma de Verificación de Información y Desinformación en Colombia  
> **Asignatura:** Ingeniería de Servicios de Internet — 2026-2 — Grupo 06  
> **Equipo Scrum:**  
> • **Alberto Mario Galvis Echeverria** (`0222410070`) — Product Owner / Desarrollador FullStack  
> • **Camilo Isaac Acevedo Rueda** (`0222410049`) — Scrum Master / QA Lead / Desarrollador FullStack  
> **Versión del Backlog:** `v1.4.1` (Actualizado: Noviembre 2026)  
> **Estado General:** **100% de Requisitos Base Completados (157 / 157 SP) + 7 Requisitos de Evolución Post-MVP (39 SP) = 196 Puntos de Historia en Requisitos (+ 53 SP Fundacionales = 249 SP Totales)**  
> **DoD Global:** **190 pruebas automatizadas aprobadas** (**163 PHPUnit** [**2.464 aserciones**] + **23 Vitest** + **4 Python `unittest`**), cumplimiento WCAG 2.1 AA para adultos (+40), contratos OpenAPI 3.1, streaming reactivo SSE, búsqueda web en vivo, Consejo Multi-Modelo, persistencia PostgreSQL 18 con `pgvector` en Neon.tech (`Raiden.sql`) y ecosistema móvil dual (**Capacitor 6** + **Expo SDK 58**).

---

## 1. Resumen Ejecutivo y Métricas de Ejecución del Backlog

El presente Product Backlog consolida la totalidad de requisitos funcionales y no funcionales del sistema DAGAZ, estructurados inicialmente en 4 fases de prioridad (`REQ-01` a `REQ-28`, 157 SP), complementadas con la **Fase Evolutiva Post-MVP** (`REQ-29` a `REQ-35`: Neon PostgreSQL, Fallback Semántico, Mockups AF, OpenRouter, Consejo Multi-Modelo, Clientes Móviles `Capacitor 6` / `Expo SDK 58` con CI `.ipa`, y Normalización PostgreSQL / Contraste Electoral 2026), además de las 11 acciones fundacionales de ingeniería y modelado.

### 📊 Balance Consolidado por Fases y Puntos de Historia (SP)

| Prioridad / Fase | Nombre de la Fase | Total Requisitos | Puntos Estimados | Puntos Completados | Sprints Asignados | Estado de la Fase |
| :--- | :--- | :---: | :---: | :---: | :---: | :---: |
| **Prioridad 1** | Esqueleto Funcional *(MVP Core)* | 8 | 45 | 45 | Sprint 1, Sprint 2 | ✅ **100% Completado** |
| **Prioridad 2** | Demo Utilizable *(Multi-fuente y Roles)* | 8 | 44 | 44 | Sprint 1, Sprint 3, Sprint 4, Sprint 5 | ✅ **100% Completado** |
| **Prioridad 3** | Listo para Pruebas Piloto *(QA y Escala)* | 8 | 44 | 44 | Sprint 4, Sprint 6 | ✅ **100% Completado** |
| **Prioridad 4** | Pulir y Traspaso *(DevOps y Handover)* | 4 | 24 | 24 | Sprint 4, Sprint 7 | ✅ **100% Completado** |
| **Subtotal Requisitos Base** | *Línea Base MVP del Proyecto* | **28** | **157** | **157** | **Sprints 1 al 7** | ✅ **100% Completado** |
| **Fase Evolutiva 1 (v1.1–v1.2)** | Cloud DB, Semántica, Mockups AF y OpenRouter | 4 | 21 | 21 | Post-Sprint 7 / Hardening | ✅ **100% Completado** |
| **Fase Evolutiva 2 (v1.3–v1.4.1)** | Multi-Modelo, Móvil Dual (.ipa) y Normalización 2026 | 3 | 18 | 18 | Post-MVP (`Noviembre 2026`) | ✅ **100% Completado** |
| **Fase Fundacional** | Ingeniería de Requisitos y Modelado (Acciones 1–11) | 11 | 53 | 53 | Sprint 1, Sprint 2 | ✅ **100% Completado** |
| **TOTAL GENERAL** | **Ciclo de Vida Integral DAGAZ** | **46 Ítems** | **249** | **249** | **7 Sprints + Evolutivo** | ✅ **100% Entregado** |

---

## 2. Asignación de Requisitos por Sprints y Cronograma Scrum

La ejecución del Backlog siguió el plan de 7 Sprints quincenales bajo el principio **Backend-First**, extendido con las fases evolutivas post-MVP:

```text
[Sprint 1: Core Backend & DB] ──────────► REQ-08, REQ-12, CU05 (Acciones 1-6)
[Sprint 2: Pipeline IA & Veredicto] ────► REQ-01, REQ-02, REQ-03, REQ-04, REQ-05, REQ-06, REQ-07, CU06 (Acciones 7-11)
[Sprint 3: Monitoreo & Comunidad] ──────► REQ-09, REQ-10, REQ-13, REQ-14, CU01, CU02, CU03, CU04, CU07
[Sprint 4: API Pública & Auditoría] ────► REQ-16, REQ-18, REQ-19, REQ-20, REQ-25 (GATE 1: Backend Freeze)
[Sprint 5: Frontend Móvil Blade] ───────► REQ-15, CU05 UI, CU06 UI, CU04 UI (Accesibilidad +40)
[Sprint 6: Benchmark & Piloto] ─────────► REQ-11, REQ-17, REQ-21, REQ-22, REQ-23, REQ-24
[Sprint 7: CI/CD & Traspaso MVP] ───────► REQ-26, REQ-27, REQ-28
[Evolutivo 1 (v1.1.0 - v1.2.0)] ────────► REQ-29 (Neon UUID), REQ-30 (Semántica), REQ-31 (Mockups AF), REQ-32 (OpenRouter)
[Evolutivo 2 (v1.3.0 - v1.4.1)] ────────► REQ-33 (Multi-Modelo & Ingesta), REQ-34 (Móvil Capacitor+Expo .ipa), REQ-35 (Normalización PG 2026)
```

---

## 3. Detalle de Requisitos por Fase de Prioridad

---

### Prioridad 1 — Esqueleto Funcional
> *El sistema de extremo a extremo más pequeño que genera un veredicto estructurado y auditable.*  
> **Subtotal:** 8 Requisitos | **45 Puntos de Historia** | **Estado:** 100% Completado

| # | Requisito / Historia de Usuario | Puntos | Sprint | Caso de Uso | Criterio de Aceptación (DoD) | Evidencia Técnica Real / Entregable | Estado |
| :-: | :--- | :-: | :-: | :-: | :--- | :--- | :-: |
| **REQ-01** | **Extractor de afirmaciones:** Aislar la afirmación verificable de un titular o párrafo de entrada con objetivo $F_1 \ge 0{,}75$. | 8 | Sprint 2 | CU06 | Reconocimiento léxico y sintáctico sin sesgo; $F_1 \ge 0{,}75$ sobre titulares etiquetados. | `VerificacionService.php` + `VerificacionServiceTest.php` / $F_1 = 0{,}82$ en pruebas. | ✅ Done |
| **REQ-02** | **OCR / STT y análisis multimodal:** Extraer texto de capturas y audios usando microservicios dedicados y visión/lenguaje. | 5 | Sprint 2 | CU06 | Extracción con precisión $\ge 90\%$ en texto legible y fallback seguro. | `backend/Microservicios_Python/` (`ocr_service`, `stt_service`) + `AdaptadorMulticanal.php`. | ✅ Done |
| **REQ-03** | **Agente de descomposición atómica:** Desglosar afirmaciones compuestas en proposiciones simples contrastables individualmente. | 8 | Sprint 2 | CU06 | Descomposición estructurada con asociación de sujetos, predicados y temporalidad. | `VerificacionService.php` (descomposición interna) + `VerificacionServiceTest.php`. | ✅ Done |
| **REQ-04** | **Agente de verificación cruzada v1:** Contrastar afirmaciones contra evidencias recuperadas; ponderar por nivel de fuente. | 8 | Sprint 2 | CU06 | Algoritmo determinista de scoring $0\text{ a }100$ con ponderación por fuente. | `VerificacionService.php` con pesos configurables en `backend/config/dagaz.php`. | ✅ Done |
| **REQ-05** | **Agente de recuperación de evidencia v1:** Búsqueda semántica/léxica que recupera fuentes candidatas por afirmación. | 8 | Sprint 2 | CU06 | Búsqueda semántica vectorial KNN ($d=384$) + coincidencia de palabras clave y categoría. | `BusquedaSemanticaService.php` (`buscarEvidenciaPorTexto()`, `buscarNoticiasPorFuenteYMetadata()`) + `BusquedaSemanticaTest.php`. | ✅ Done |
| **REQ-06** | **Agente de síntesis del veredicto:** Emisión de etiqueta (`VERDADERO`, `FALSO`, `ENGAÑOSO`, `PARCIAL`, `NO_VERIFICABLE`) + puntaje + trazabilidad. | 8 | Sprint 2 | CU06 | Veredicto con confianza porcentual, citas textuales y justificación narrativa transparente. | `VerificacionService.php` (`determinarVeredicto`) + auditoría en `veredictos` y `veredicto_fuentes`. | ✅ Done |
| **REQ-07** | **Demo de esqueleto funcional:** Prueba de concepto end-to-end sobre afirmación electoral usando fuentes Nivel 1. | 3 | Sprint 2 | CU06 | Verificación de extremo a extremo validada con trazabilidad en log. | Endpoint `POST /api/v1/verificacion` validado en `VerificacionesBackendFeatureTest.php` y CI. | ✅ Done |
| **REQ-08** | **Grafo de Credibilidad v1 (Fuentes Nivel 1):** Registro de las principales fuentes internacionales (Reuters, AP, AFP, BBC, OMS, ONU, etc.). | 5 | Sprint 1 | CU07 | Tabla `fuentes` con índice de credibilidad y tipos normalizados. | `FuentesSeeder.php` y migración `2026_09_01_000000_create_dagaz_tables.php`. | ✅ Done |

---

### Prioridad 2 — Demo Utilizable
> *Ampliación multicanal, histórico de versiones, panel editorial y API para salas de prensa.*  
> **Subtotal:** 8 Requisitos | **44 Puntos de Historia** | **Estado:** 100% Completado

| # | Requisito / Historia de Usuario | Puntos | Sprint | Caso de Uso | Criterio de Aceptación (DoD) | Evidencia Técnica Real / Entregable | Estado |
| :-: | :--- | :-: | :-: | :-: | :--- | :--- | :-: |
| **REQ-09** | **Monitor de redes sociales:** Ingesta y normalización de frases y cadenas virales mediante adaptadores multicanal. | 8 | Sprint 3 | CU06 | Soporte para entradas `TEXTO`, `URL`, `IMAGEN_OCR` y `RED_SOCIAL` con sanitización. | `AdaptadorMulticanal.php` + ingesta RSS `ActualizarNoticiasCommand.php`. | ✅ Done |
| **REQ-10** | **Conector Archive.org / Wayback Machine:** Recuperación de versiones históricas para detectar descontextualización temporal. | 5 | Sprint 3 | CU06 | Detección de fechas originales de publicación y alerta de reciclaje de noticias antiguas. | `ArchiveOrgService.php` + endpoints `/api/v1/verificacion/archive/*` + `ArchiveOrgTest.php`. | ✅ Done |
| **REQ-11** | **Dataset de referencia de 500 afirmaciones etiquetadas:** Corpus de prueba electoral validado por acuerdos inter-evaluadores. | 8 | Sprint 6 | CU06 | Corpus balanceado con acuerdo Cohen's Kappa $\kappa \ge 0{,}75$ ($\kappa = 0{,}794$ alcanzado). | Informe formal en [`docs/iteracion-final/Evaluacion_Benchmark_500_Afirmaciones_y_Auditoria_Sesgo.md`](../../iteracion-final/Evaluacion_Benchmark_500_Afirmaciones_y_Auditoria_Sesgo.md). | ✅ Done |
| **REQ-12** | **Diseño de Base de Datos y Entidades Core:** Modelo relacional completo para usuarios, afirmaciones, notas comunitarias y calificaciones. | 8 | Sprint 1 | CU01-05 | Esquema relacional en 3FN con UUIDs nativos, llaves foráneas, restricciones CHECK y soporte dual. | Migraciones Laravel + Script DDL [`database/Raiden.sql`](../../../database/Raiden.sql). | ✅ Done |
| **REQ-13** | **Panel de intervención editorial para revisores:** Entorno administrativo para auditar y recalibrar veredictos automatizados. | 8 | Sprint 3 | REQ-13 | Interfaz con control de roles (`editor`, `admin`) y bloqueo de cambios sin justificación. | Endpoint `POST /api/v1/veredictos/{idVeredicto}/intervenir` (`middleware rol:admin,editor`) + `editorial.test.js`. | ✅ Done |
| **REQ-14** | **Grafo de Credibilidad v2 (Prensa Regional y Nacional):** Expansión con medios colombianos (El Tiempo, El Espectador, etc.). | 5 | Sprint 3 | CU07 | Clasificación jerárquica en 4 niveles de credibilidad y ponderaciones diferenciadas. | `FuentesSeeder.php` ampliado con medios nacionales y regionales de Colombia. | ✅ Done |
| **REQ-15** | **Interfaz de Tarjeta de Veredicto Accesible:** Visualización Mobile-First para adultos (+40) con semáforo y lenguaje comprensible. | 5 | Sprint 5 | CU06 | Cumplimiento WCAG 2.1 AA (contraste $\ge 4{,}5:1$, fuente $\ge 18\text{px}$, botones $\ge 48\text{px}$). | Vista [`frontend/views/verificacion/resultado.blade.php`](../../../frontend/views/verificacion/resultado.blade.php) y suite `ui.test.js`. | ✅ Done |
| **REQ-16** | **API Pública v1 para Salas de Prensa Socias:** Endpoint seguro con autenticación API Key para consumo programático. | 5 | Sprint 4 | REQ-16 | Documentación OpenAPI 3.1, `throttle:partners` y telemetría por socio. | `PublicPartnerApiController.php`, `EnsureValidApiKey.php`, rutas `/api/v1/public/*` y `PublicPartnerApiTest.php`. | ✅ Done |

---

### Prioridad 3 — Listo para Pruebas Piloto
> *Control de sesgo algorítmico, recuperación interlingüe, pruebas de estabilidad y validación estadística.*  
> **Subtotal:** 8 Requisitos | **44 Puntos de Historia** | **Estado:** 100% Completado

| # | Requisito / Historia de Usuario | Puntos | Sprint | Caso de Uso | Criterio de Aceptación (DoD) | Evidencia Técnica Real / Entregable | Estado |
| :-: | :--- | :-: | :-: | :-: | :--- | :--- | :-: |
| **REQ-17** | **Auditoría de sesgo político:** Evaluación estadística para asegurar imparcialidad entre tendencias políticas en época electoral. | 8 | Sprint 6 | REQ-17 | Prueba Chi-cuadrado $\chi^2 = 0{,}682$ con $p > 0{,}05$ (sin diferencia estadísticamente significativa). | [`Evaluacion_Benchmark_500_Afirmaciones_y_Auditoria_Sesgo.md`](../../iteracion-final/Evaluacion_Benchmark_500_Afirmaciones_y_Auditoria_Sesgo.md). | ✅ Done |
| **REQ-18** | **Circuit Breaker y controles de costo:** Monitoreo presupuestal de tokens de inferencia con corte automático ante anomalías. | 5 | Sprint 4 | REQ-18 | Límite horario de tokens y presupuesto diario en USD; degradación elegante si se supera el tope. | `CircuitBreakerService.php` + endpoints `/api/v1/admin/circuit-breaker*` + `BackendAdvancedFeatureTest.php`. | ✅ Done |
| **REQ-19** | **Recuperación interlingüe (ES-EN):** Capacidad de contrastar afirmaciones en español con fuentes primarias anglosajonas. | 8 | Sprint 4 | CU06 | Detección de idioma, traducción bidireccional y análisis de distorsión ES-EN. | `ServicioInterlingue.php` + `/api/v1/verificacion/interlingue/*` + `InterlingueTest.php`. | ✅ Done |
| **REQ-20** | **Grafo de Credibilidad v3 (80 Fuentes y Fact-Checkers):** Red completa con signatarios IFCN (ColombiaCheck, Chequeado, AFP Factual, etc.). | 8 | Sprint 3 | CU07 | Catálogo consolidado de 80 medios con categorización temática. | `FuentesSeeder.php` (80 fuentes) + `DocumentosNoticiasSeeder.php` + `GrafoFuentesTest.php`. | ✅ Done |
| **REQ-21** | **Prueba de carga y estabilidad continua:** Simulación de flujo sostenido con disponibilidad $\ge 99\%$ y actualización RSS. | 8 | Sprint 6 | REQ-21 | Operación estable bajo consultas recurrentes en Neon.tech sin agotar cuotas. | Comando `dagaz:actualizar-noticias` (`ActualizarNoticiasCommand.php`) + pruebas de integración. | ✅ Done |
| **REQ-22** | **Onboarding técnico de redacción piloto:** Primer socio de prensa consumiendo veredictos en producción. | 5 | Sprint 6 | REQ-26 | Generación de credenciales y consumo M2M auditado. | Protocolo documentado en [`Onboarding_Redacciones_Socias_REQ26.md`](../../iteracion-final/Onboarding_Redacciones_Socias_REQ26.md). | ✅ Done |
| **REQ-23** | **Módulo de Usuarios y Consentimiento Informado:** Registro seguro con encriptación Bcrypt y política de datos Ley 1581 de 2012. | 5 | Sprint 1 | CU05 | Consentimiento explícito, descarga ARCO y supresión de cuenta (derecho al olvido). | `AuthController.php`, tokens Sanctum UUID y `AuthTest.php`. | ✅ Done |
| **REQ-24** | **Evaluación formal de precisión y exhaustividad:** Métricas sobre el conjunto de 500 casos ($\text{Precision} \ge 0{,}80$, $\text{Recall} \ge 0{,}75$). | 5 | Sprint 6 | REQ-24 | Precisión alcanzada: **84.6%**, Exhaustividad: **81.2%**. | Resultados certificados en [`Evaluacion_Benchmark_500_Afirmaciones_y_Auditoria_Sesgo.md`](../../iteracion-final/Evaluacion_Benchmark_500_Afirmaciones_y_Auditoria_Sesgo.md). | ✅ Done |

---

### Prioridad 4 — Pulir y Traspaso
> *Auditoría regulatoria, manuales operativos, incorporación de socios secundarios y cierre de transición.*  
> **Subtotal:** 4 Requisitos | **24 Puntos de Historia** | **Estado:** 100% Completado

| # | Requisito / Historia de Usuario | Puntos | Sprint | Caso de Uso | Criterio de Aceptación (DoD) | Evidencia Técnica Real / Entregable | Estado |
| :-: | :--- | :-: | :-: | :-: | :--- | :--- | :-: |
| **REQ-25** | **Registro de auditoría inmutable (Compliance):** Trazabilidad forense exportable en JSON, CSV y `ClaimReview` (Schema.org). | 5 | Sprint 4 | REQ-25 | Trazabilidad de inferencia, fuente utilizada y telemetría en logs. | `AuditoriaExportService.php`, `AuditoriaController.php`, tabla `logs_comunicador_api` y `AuditoriaExportTest.php`. | ✅ Done |
| **REQ-26** | **Incorporación de redacciones socias secundarias:** Protocolo escalable para sumar múltiples medios aliados. | 8 | Sprint 7 | REQ-26 | Credenciales API Key (`config/dagaz.php`), rate limits dedicados y guías de integración. | [`Onboarding_Redacciones_Socias_REQ26.md`](../../iteracion-final/Onboarding_Redacciones_Socias_REQ26.md). | ✅ Done |
| **REQ-27** | **Manuales de procedimientos y guía de Adulto Mayor:** Documentación integral de despliegue y manual con diseño inclusivo. | 8 | Sprint 7 | REQ-27 | 3 manuales publicados: Usuario Adulto (+40), Administrador/Revisor y Despliegue e Infraestructura. | Carpeta [`docs/iteracion-final/`](../../iteracion-final/) con los tres manuales formalizados. | ✅ Done |
| **REQ-28** | **Retrospectiva final y acta formal de traspaso:** Evaluación del proceso Scrum, lecciones aprendidas y transferencia. | 3 | Sprint 7 | REQ-28 | Acta formal documentada con inventario de código y matriz de riesgos. | [`Retrospectiva_Final_y_Acta_Traspaso_REQ28.md`](../../iteracion-final/Retrospectiva_Final_y_Acta_Traspaso_REQ28.md). | ✅ Done |

---

### Fase Fundacional — Análisis de Requisitos y Modelado del Sistema (Sprints 1 y 2)
> *Las 11 acciones fundacionales de ingeniería del software, análisis de negocio y modelado.*  
> **Subtotal:** 11 Acciones | **53 Puntos de Esfuerzo** | **Estado:** 100% Completado

| # | Acción de Ingeniería / Modelado | Sprint | Complejidad | Estado |
| :-: | :--- | :---: | :---: | :---: |
| **1** | Matriz de ideación/identificación de problemáticas | **S1** | **3 pts** | ✅ Hecho |
| **2** | Elección de metodología | **S1** | **5 pts** | ✅ Hecho |
| **3** | Análisis de negocio | **S1** | **3 pts** | ✅ Hecho |
| **4** | Modelado del negocio | **S1** | **5 pts** | ✅ Hecho |
| **5** | Análisis de requisitos | **S1** | **3 pts** | ✅ Hecho |
| **6** | Modelado de requisitos | **S1** | **3 pts** | ✅ Hecho |
| **7** | Análisis de casos de uso | **S2** | **5 pts** | ✅ Hecho |
| **8** | Descripción de casos de uso | **S2** | **5 pts** | ✅ Hecho |
| **9** | Modelado de casos de uso | **S2** | **8 pts** | ✅ Hecho |
| **10** | Modelado de secuencias de los casos de uso | **S2** | **5 pts** | ✅ Hecho |
| **11** | Propuesta de mockups de la UI del servicio | **S2** | **8 pts** | ✅ Hecho |

---

### Fase Evolutiva 1 — Cloud DB, Semántica, Mockups AF y OpenRouter (`v1.1.0`–`v1.2.0`)
> **Subtotal:** 4 Requisitos | **21 Puntos de Historia** | **Estado:** 100% Completado

| # | Requisito / Historia de Usuario | Puntos | Versión | Caso de Uso | Criterio de Aceptación (DoD) | Evidencia Técnica Real / Entregable | Estado |
| :-: | :--- | :-: | :-: | :-: | :--- | :--- | :-: |
| **REQ-29** | **Soporte Cloud Serverless PostgreSQL Neon:** Conexión dinámica SSL y tipos UUID nativos en `personal_access_tokens`. | 5 | `1.1.0` | Infra | Compatibilidad total de migraciones en PostgreSQL 18 Neon sin romper compatibilidad con MariaDB. | Migración `2026_09_24_214547_create_personal_access_tokens_table.php` y `.env.example`. | ✅ Done |
| **REQ-30** | **Búsqueda Semántica Resiliente y Fallback Pedagógico:** Manejo amigable sin errores 500 ante ausencia de evidencia coincidente. | 5 | `1.1.0` | CU06 | Explicación clara indicando que ningún organismo oficial se ha pronunciado aún y salvaguardas de explicación. | `BusquedaSemanticaService.php` y `VerificacionService.php`. | ✅ Done |
| **REQ-31** | **Galería de Mockups de Alta Fidelidad Interactiva:** 10 vistas HTML5/Tailwind con selector de pantallas y viewport adaptable. | 6 | `1.2.0` | UI/UX | Prototipos navegables de CU01 a CU07 + REQ-13 con ergonomía para adultos (+40). | Directorio [`docs/mockups-alta-fidelidad/`](../../mockups-alta-fidelidad/) con `index.html` y 9 vistas. | ✅ Done |
| **REQ-32** | **Integración de Inferencia vía OpenRouter:** Soporte transparente de múltiples modelos externos en `OpenAiDriver` bajo Circuit Breaker. | 5 | `1.1.0` | Backend | Conexión unificada a OpenRouter mediante `OPENROUTER_API_KEY` y `DAGAZ_IA_PROVEEDOR=openrouter`. | `OpenAiDriver.php` y `backend/config/dagaz.php`. | ✅ Done |

---

### Fase Evolutiva 2 — Consejo Multi-Modelo, Móvil Dual y Normalización 2026 (`v1.3.0`–`v1.4.1`)
> **Subtotal:** 3 Requisitos | **18 Puntos de Historia** | **Estado:** 100% Completado

| # | Requisito / Historia de Usuario | Puntos | Versión | Caso de Uso | Criterio de Aceptación (DoD) | Evidencia Técnica Real / Entregable | Estado |
| :-: | :--- | :-: | :-: | :-: | :--- | :--- | :-: |
| **REQ-33** | **Consejo de Verificación Multi-Modelo en Paralelo e Ingesta Masiva Colombia:** Evaluación concurrente vía `Http::pool` (GPT-4o, Claude 3.5 Sonnet, Gemini 2.0, DeepSeek-R1, Qwen opcional) + ingesta masiva RAG. | 6 | `1.3.0` | CU06 | Latencia paralela acotada, aislamiento determinista en `phpunit.xml` (`DAGAZ_MULTI_MODELO_HABILITADO=false`) y vectorización Colombia. | [`MultiModeloHarnessService.php`](../../../backend/app/Services/MultiModeloHarnessService.php), [`ingesta_noticias_colombia.py`](../../../ingesta_noticias_colombia.py) e `.ipynb`. | ✅ Done |
| **REQ-34** | **Ecosistema Móvil Dual (Capacitor 6 + Expo SDK 58) y Compilación CI `.ipa` Sideloadly:** Contenedor Android/iOS híbrido y app nativa RN con timeout de 120s. | 7 | `1.4.0` | CU01-07 | Job `mobile-container` verde en `ci.yml`, app Expo SDK 58 alineada con REST v1 y artefacto `DAGAZ-iOS-Sideloadly-IPA` en GitHub Actions macOS. | [`mobile/`](../../../mobile/), [`mobile-expo/`](../../../mobile-expo/) y [`.github/workflows/build-ios-ipa.yml`](../../../.github/workflows/build-ios-ipa.yml). | ✅ Done |
| **REQ-35** | **Resiliencia SQL sin Tildes en PostgreSQL, Contraste Electoral 2026 y Parser OpenRouter (`<think>` / `reasoning`):** Eliminación de fallos de búsqueda por acentos y respuestas vacías en modelos DeepSeek-R1. | 5 | `1.4.1` | CU06 / QA | `normalizarSinTildes()`, limpieza `<think>...</think>`, respaldo `reasoning`, soporte clave `justificación`, salvaguarda anti-degradación `FALSO` -> `NO_VERIFICABLE` y 169 pruebas verdes. | `BusquedaSemanticaService.php`, `OpenAiDriver.php`, `OpenAiDriverTest.php` y `MultiModeloHarnessService.php`. | ✅ Done |

---

## 4. Matriz de Trazabilidad: Casos de Uso vs. Requisitos vs. Interfaces

La siguiente matriz conecta los Casos de Uso formales del sistema con sus historias de usuario, prototipos y suites de pruebas reales del repositorio:

| Caso de Uso Oficial | Nombre del Caso de Uso | Requisitos del Backlog Asociados | Pantalla / Mockup de Alta Fidelidad | Tests Automatizados Reales |
| :--- | :--- | :--- | :--- | :--- |
| **CU01** | Asignar Notas a Contenidos | REQ-12, TSK-3.4, TSK-6.1 | [`CU01_Mockup_Alta_Fidelidad_Asignar_Notas.html`](../../mockups-alta-fidelidad/CU01_Mockup_Alta_Fidelidad_Asignar_Notas.html) | `VerificacionFeatureTest.php`, `notas.test.js` |
| **CU02** | Calificar Notas de Contenidos | REQ-12, TSK-3.4, TSK-6.1 | [`CU02_Mockup_Alta_Fidelidad_Calificar_Notas.html`](../../mockups-alta-fidelidad/CU02_Mockup_Alta_Fidelidad_Calificar_Notas.html) | `VerificacionFeatureTest.php`, `notas.test.js` |
| **CU03** | Guardar Contenido en Colección | REQ-12, TSK-3.5 | [`CU03_Mockup_Alta_Fidelidad_Guardar_Contenido.html`](../../mockups-alta-fidelidad/CU03_Mockup_Alta_Fidelidad_Guardar_Contenido.html) | `VerificacionFeatureTest.php`, `historial.test.js` |
| **CU04** | Historial de Consultas Realizadas | REQ-12, TSK-3.5, TSK-5.5 | [`CU04_Mockup_Alta_Fidelidad_Historial_Noticias.html`](../../mockups-alta-fidelidad/CU04_Mockup_Alta_Fidelidad_Historial_Noticias.html) | `VerificacionFeatureTest.php`, `historial.test.js` |
| **CU05** | Inicio de Sesión y Registro Seguro | REQ-08, REQ-12, REQ-23, TSK-1.6 | [`CU05_Mockup_Alta_Fidelidad_Inicio_Sesion.html`](../../mockups-alta-fidelidad/CU05_Mockup_Alta_Fidelidad_Inicio_Sesion.html) | `AuthTest.php`, `sesion.test.js` |
| **CU06** | Verificar Contenido Multicanal | REQ-01 al REQ-07, REQ-09, REQ-10, REQ-15, REQ-19, REQ-30, REQ-33, REQ-35 | [`CU06_Mockup_Alta_Fidelidad_Verificar_Entrada.html`](../../mockups-alta-fidelidad/CU06_Mockup_Alta_Fidelidad_Verificar_Entrada.html) y [`CU06_Mockup_Alta_Fidelidad_Verificar_Resultado.html`](../../mockups-alta-fidelidad/CU06_Mockup_Alta_Fidelidad_Verificar_Resultado.html) | `VerificacionesBackendFeatureTest.php`, `VerificacionServiceTest.php`, `BusquedaSemanticaTest.php`, `OpenAiDriverTest.php`, `verificar.test.js` |
| **CU07** | Asignar Confiabilidad a Fuentes | REQ-08, REQ-14, REQ-20, TSK-3.6, TSK-6.2 | [`CU07_Mockup_Alta_Fidelidad_Confiabilidad_Fuentes.html`](../../mockups-alta-fidelidad/CU07_Mockup_Alta_Fidelidad_Confiabilidad_Fuentes.html) | `GrafoFuentesTest.php`, `FrontendRoutesTest.php`, `accesibilidad.test.js` |
| **REQ-13** | Panel Editorial de Intervención | REQ-13, REQ-25, TSK-3.7 | [`REQ13_Mockup_Alta_Fidelidad_Panel_Editorial.html`](../../mockups-alta-fidelidad/REQ13_Mockup_Alta_Fidelidad_Panel_Editorial.html) | `BackendAdvancedFeatureTest.php`, `AuditoriaExportTest.php`, `editorial.test.js` |

---

## 5. Definición de Hecho (Definition of Done — DoD)

Para que cualquier historia de usuario o requisito del Backlog fuera marcado como **✅ Done (Completado)**, se aplicaron rigurosamente los siguientes criterios de ingeniería:

1. **Código y Persistencia:**
   - Implementado en **PHP 8.4 / Laravel 13** siguiendo estándares PSR-12.
   - Migraciones con integridad referencial, tipos UUID nativos y comprobadas en PostgreSQL Neon y MariaDB/SQLite.
2. **Pruebas Automatizadas (QA):**
   - Suite **PHPUnit 12.5** aprobada al 100% (**142 tests / 2.322 aserciones**), **Vitest 5.0** al 100% (**23 tests** en 8 archivos) y **Python `unittest`** (**4 tests**), totalizando **169 pruebas verificadas**.
3. **Accesibilidad y Ergonomía (Adultos +40):**
   - Cumplimiento de WCAG 2.1 nivel AA: relación de contraste texto $\ge 4{,}5:1$ y áreas táctiles mínimas de $48 \times 48\text{ px}$ tanto en Web (`frontend/`) como en app nativa (`mobile-expo/`).
   - Tipografía base escalable con selector accesible `A / A+`.
4. **Seguridad y Regulación:**
   - Consentimiento previo y expreso bajo la Ley 1581 de 2012 (Hábeas Data).
   - Sanitización de entradas, prevención de SQLi (PDO preparado) y XSS (`textContent`).
   - Trazabilidad de auditoría forense y exportación ClaimReview Schema.org (`REQ-25`).
5. **Documentación Contractual:**
   - Endpoints documentados en OpenAPI 3.1 (`backend/storage/api-docs/openapi.yaml`) y trazados en el Historial de Control y Manuales Técnicos.

---

## 6. Estado de la Deuda Técnica y Conclusiones del Backlog

- **Deuda Técnica en Backend:** `0 horas` (**142 pruebas PHPUnit aprobadas, 2.322 aserciones, 0 fallos**).
- **Deuda Técnica en Frontend y Microservicios:** `0 horas` (**23 pruebas Vitest + 4 pruebas Python `unittest`, 0 fallos**).
- **Cobertura de Requisitos:** **100% alcanzada (35/35 requisitos entregados, 196 SP de requisitos + 53 SP fundacionales = 249 SP cerrados)**.
- **Transición Operativa:** La plataforma opera en versión **`v1.4.1`** con clientes Web/PWA, Capacitor Android y React Native / Expo SDK 58 (incluyendo artefacto `.ipa` Sideloadly), lista para uso ciudadano y redacciones aliadas (*El Espectador*, *ColombiaCheck*, *La Silla Vacía*).
