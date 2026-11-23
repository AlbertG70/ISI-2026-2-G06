# DAGAZ — Contexto Maestro del Proyecto (Master Context)

> **Documento Central de Trabajo y Referencia Técnica Unificada**  
> **Sistema:** DAGAZ (Plataforma Multicanal de Detección de Desinformación y Verificación de Contenidos)  
> **Organización:** ISI-CORP (2026-II)  
> **Fecha de Consolidación Vigente:** 09 de Noviembre de 2026 (`v1.4.1`)

---

## 1. Visión Ejecutiva del Sistema

DAGAZ es un sistema integral concebido para contrarrestar la viralización de desinformación, noticias manipuladas y amarillismo sensacionalista, con especial énfasis en el contexto informativo y electoral de **Colombia** (incluyendo contraste factual actualizado al ciclo 2026).

La plataforma permite a los usuarios —principalmente **Adultos (+40)** propensos a recibir cadenas en WhatsApp o redes sociales— ingresar contenidos mediante múltiples canales (texto directo, enlaces web/redes, imágenes con capturas de pantalla o notas de voz) desde la web responsiva, la app híbrida **Capacitor 6** o la aplicación nativa **Expo SDK 58**. Mediante adaptadores de IA agnósticos (con proveedor **OpenRouter**), un **Consejo de Verificación Multi-Modelo en paralelo** y un grafo jerarquizado de 80 fuentes IFCN, DAGAZ aísla afirmaciones atómicas, las contrasta con evidencia documental y emite un veredicto explicable y auditable.

---

## 2. Mapa Documental de Referencia

| Documento | Ubicación | Descripción |
| :--- | :--- | :--- |
| **00. Master Context** | [`00_MASTER_CONTEXT_DAGAZ.md`](00_MASTER_CONTEXT_DAGAZ.md) | Guía unificada de contexto, reglas de arquitectura y estado vigente del sistema. |
| **01. Contexto y Metodología** | [`01_Contexto_y_Metodologia_Dagaz.md`](01_Contexto_y_Metodologia_Dagaz.md) | Problema, justificación, Canvas de Negocio, Scrum, Product Backlog extendido y presupuesto. |
| **02. Casos de Uso** | [`02_Especificacion_Casos_de_Uso.md`](02_Especificacion_Casos_de_Uso.md) | Especificación de CU01 a CU07, precondiciones, flujos básicos y alternativos. |
| **03. Arquitectura y Software** | [`03_Arquitectura_y_Diseno_Software.md`](03_Arquitectura_y_Diseno_Software.md) | Arquitectura por capas actual (`Laravel 13`, Consejo Multi-Modelo, Web + Móvil Dual), DCD y DSS. |
| **04. Base de Datos** | [`04_Diseno_Base_de_Datos.md`](04_Diseno_Base_de_Datos.md) | Diccionario de datos PostgreSQL 18 + `pgvector` (Neon.tech), embeddings 384d, KNN exacto y Hábeas Data. |
| **05. Presentación Ejecutiva** | [`05_Presentacion_Ejecutiva_Proyecto_DAGAZ.md`](05_Presentacion_Ejecutiva_Proyecto_DAGAZ.md) | Resumen ejecutivo, objetivos, arquitectura y balance completo hasta **`v1.4.1`**. |
| **Plan de Gestión e Implementación** | [`Plan_Gestion_Desarrollo_Implementacion.md`](../Guidelines/Plan_Gestion_Desarrollo_Implementacion.md) | Plan ágil Backend-First (7 Sprints + Evolución `v1.1.0–v1.4.1`), QA y pipelines CI/CD. |
| **Diagrama de Gantt y Cronograma** | [`Diagrama_de_Gantt.md`](../Guidelines/Diagrama_de_Gantt.md) | Cronograma oficial (Gates 0 a 5), matriz WBS y ruta crítica. |
| **Historial de Control y Decisiones** | [`Historial_de_Control.md`](../Guidelines/Historial_de_Control.md) | Bitácora oficial cronológica desde `v0.0.1` hasta **`v1.4.1`**. |
| **Registro de Cambios (Changelog)** | [`CHANGELOG.md`](../../../CHANGELOG.md) | Registro estándar Keep a Changelog en la raíz del repositorio. |
| **Script DDL PostgreSQL Raiden** | [`database/Raiden.sql`](../../../database/Raiden.sql) | Script DDL oficial PostgreSQL 18 + `pgvector` para ejecución directa (10 tablas de dominio). |

---

## 3. Directrices y Reglas de Desarrollo (Reglas de Oro)

1. **Estrategia «Backend-First»:**  
   El núcleo del backend (**Laravel 13 / PHP 8.3–8.4**, **PostgreSQL 18 + `pgvector`** con compatibilidad MariaDB/SQLite) define contratos REST v1 y OpenAPI 3.1 estables antes de integrarlos en frontend Web (`Vite 8` + `Tailwind v4` + `Blade`), [`mobile/`](../../../mobile/) (Capacitor 6) y [`mobile-expo/`](../../../mobile-expo/) (Expo SDK 58).
2. **Restricción Geográfica y Grafo de 80 Fuentes IFCN:**  
   Todo análisis prioriza el ecosistema informativo de **Colombia** (prensa regional N2, nacional N3, entidades de control y fact-checkers certificados IFCN N4) junto con agencias internacionales de Nivel 1.
3. **Patrón Adapter / Strategy + Consejo Multi-Modelo:**  
   La inferencia IA es intercambiable vía `AdaptadorApiIaInterface` (`OpenAiDriver` con OpenAI y **OpenRouter**, `GeminiDriver`, `LocalLlmDriver`), enriquecida en evaluación paralela por [`MultiModeloHarnessService`](../../../backend/app/Services/MultiModeloHarnessService.php) (`Http::pool`) bajo protección de [`CircuitBreakerService`](../../../backend/app/Services/CircuitBreakerService.php) y salvaguarda anti-degradación (`FALSO` $\not\rightarrow$ `NO_VERIFICABLE`).
4. **Persistencia Híbrida & Búsqueda Resiliente:**  
   PostgreSQL 18 Serverless (Neon.tech SSL) con `vector(384)` (KNN exacto sin índices vectoriales para optimizar RAM) y tabla `personal_access_tokens` adaptada a **UUID** para Sanctum, además de normalización SQL sin tildes (`normalizarSinTildes()`) y compatibilidad local MariaDB/SQLite.
5. **Accesibilidad Adultos (+40) en Web y Móvil:**  
   Interfaces WCAG 2.1 AA con tipografía $\ge 18\text{px}$, selector persistente `A / A+`, botones táctiles $\ge 48\text{px}$ y tiempo de espera cliente ampliado a **120 segundos** en móvil para consultas Multi-Modelo.

---

## 4. Matriz de Trazabilidad: Casos de Uso vs. Rutas y Módulos Reales

| CU | Nombre del Caso de Uso | Rutas / Controlador Principal | Vistas Blade / Móvil | Tablas Afectadas |
| :---: | :--- | :--- | :--- | :--- |
| **CU01** | Asignar notas complementarias | `POST/GET /api/v1/contenidos/{idContenido}/notas` (`VerificacionController`) | `resultado.blade.php`, `ResultadoScreen.js` | `notas`, `contenidos` |
| **CU02** | Calificar notas complementarias | `PATCH /api/v1/notas/{idNota}/calificar` (`VerificacionController`) | `resultado.blade.php`, `ResultadoScreen.js` | `notas` |
| **CU03** | Guardar contenidos | `POST /api/v1/historial/{idContenido}` (`VerificacionController`) | `resultado.blade.php`, `ResultadoScreen.js` | `contenidos`, `veredictos`, `historial` |
| **CU04** | Mostrar historial de contenidos | `GET /api/v1/historial` (`VerificacionController`) | `historial/index.blade.php`, `HistorialScreen.js` | `historial`, `contenidos`, `veredictos` |
| **CU05** | Inicio de sesión / Credenciales / Ley 1581 | `/api/v1/auth/{register,login,logout,perfil,datos-personales,cuenta}` (`AuthController`) | `auth/login.blade.php`, `perfil/index.blade.php`, `PerfilScreen.js` | `usuarios`, `personal_access_tokens` |
| **CU06** | Verificar contenidos | `POST /api/v1/verificacion`, `/api/v1/public/verificar`, Archive & Interlingüe | `verificacion/index.blade.php`, `VerificarScreen.js` | `contenidos`, `procesos_verificacion`, `documentos`, `veredictos`, `veredicto_fuentes`, `logs_comunicador_api` |
| **CU07** | Asignar confiabilidad a fuentes | `/api/v1/fuentes`, `/valorar`, `/clasificar`, `/api/v1/public/fuentes` | `fuentes/index.blade.php`, `FuentesScreen.js` | `fuentes` |

---

## 5. Estado Operativo Vigente del Proyecto (`v1.4.1`)

- **Cobertura Funcional:** **100% del MVP (`REQ-01` a `REQ-28`) y del Bloque Evolutivo (`REQ-29` a `REQ-35`) completados** (35 requisitos cerrados, 7 Casos de Uso y panel editorial `REQ-13`).
- **Calidad Automatizada Verificada:** **169 pruebas automatizadas pasando al 100%** (**142 PHPUnit** [`27 Unit` con 94 aserciones + `115 Feature` con 2.228 aserciones = **2.322 aserciones**] + **23 Vitest** en 8 archivos + **4 Python `unittest`**).
- **Pipelines CI/CD:** Validación continua en [`.github/workflows/ci.yml`](../../../.github/workflows/ci.yml) (`backend-tests`, `frontend-tests`, `mobile-container`) y compilación nativa macOS iOS `.ipa` para Sideloadly en [`.github/workflows/build-ios-ipa.yml`](../../../.github/workflows/build-ios-ipa.yml).
- **Ingesta y Operación:** Comando `dagaz:actualizar-noticias` (`ActualizarNoticiasCommand.php`), seeder `NoticiasColombiaSeeder` e ingesta masiva Python/Colab en [`ingesta_noticias_colombia.py`](../../../ingesta_noticias_colombia.py) e [`ingesta_noticias_colombia.ipynb`](../../../ingesta_noticias_colombia.ipynb).
