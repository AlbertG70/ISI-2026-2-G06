# DAGAZ — Contexto Maestro del Proyecto (Master Context)

> **Documento Central de Trabajo y Referencia Técnica Unificada (Fase 0)**  
> **Sistema:** DAGAZ (Plataforma Multicanal de Detección de Desinformación y Verificación de Contenidos)  
> **Organización:** ISI-CORP (2026-II)  
> **Fecha de Consolidación:** Septiembre 2026 *(Referencia histórica de Fase 0)*

> [!NOTE]
> **Nota de Evolución Arquitectónica (`v0.0.25` – `v1.4.1`):** Este documento conserva el contexto inicial de la Fase 0 (septiembre 2026, cuando la persistencia se planteó inicialmente sobre MariaDB). A partir de la versión `0.0.25`, la arquitectura vigente migró su motor principal a **PostgreSQL 18 + `pgvector` (Neon.tech)** manteniendo compatibilidad dual con MariaDB, opera sobre **Laravel 13**, incorpora el **Consejo Multi-Modelo** (`MultiModeloHarnessService`) y ofrece clientes móviles en **Capacitor 6** y **Expo SDK 58**. Para el contexto maestro vigente, véase [`docs/iteracion-02/Guideline-Persistencia/00_MASTER_CONTEXT_DAGAZ.md`](../../iteracion-02/Guideline-Persistencia/00_MASTER_CONTEXT_DAGAZ.md).

---

## 1. Visión Ejecutiva del Sistema

DAGAZ es un sistema integral concebido para contrarrestar la viralización de desinformación, noticias manipuladas y amarillismo sensacionalista, con especial énfasis en el contexto informativo y electoral de **Colombia**.

La plataforma permite a los usuarios —principalmente **Adultos (+40)** propensos a recibir cadenas en WhatsApp o redes sociales— ingresar contenidos mediante múltiples canales (texto directo, enlaces a publicaciones/videos, imágenes con capturas de pantalla o notas de voz). Mediante una arquitectura orientada a servicios, adaptadores de IA agnósticos y un grafo jerarquizado de credibilidad de medios, DAGAZ aísla afirmaciones atómicas, las contrasta con fuentes confiables y emite un veredicto explicable con trazabilidad completa de su procedencia.

---

## 2. Mapa Documental de Referencia

| Documento | Ubicación | Descripción |
| :--- | :--- | :--- |
| **00. Master Context (Vigente)** | [`../../iteracion-02/Guideline-Persistencia/00_MASTER_CONTEXT_DAGAZ.md`](../../iteracion-02/Guideline-Persistencia/00_MASTER_CONTEXT_DAGAZ.md) | Guía unificada de contexto y reglas de desarrollo vigentes. |
| **01. Contexto y Metodología** | [`01_Contexto_y_Metodologia_Dagaz.md`](01_Contexto_y_Metodologia_Dagaz.md) | Problema, justificación, Canvas de Negocio, Scrum y presupuesto. |
| **02. Casos de Uso** | [`02_Especificacion_Casos_de_Uso.md`](02_Especificacion_Casos_de_Uso.md) | Especificación exhaustiva de CU01 a CU07, precondiciones, flujos básicos y alternativos. |
| **03. Arquitectura y Software** | [`03_Arquitectura_y_Diseno_Software.md`](03_Arquitectura_y_Diseno_Software.md) | Arquitectura por capas, DCD (clases, atributos, métodos) y diagramas de secuencia (caja gris). |
| **04. Base de Datos (Vigente)** | [`../../iteracion-02/Guideline-Persistencia/04_Diseno_Base_de_Datos.md`](../../iteracion-02/Guideline-Persistencia/04_Diseno_Base_de_Datos.md) | Diccionario de datos oficial de 10 tablas en PostgreSQL 18 + `pgvector`. |
| **Plan de Gestión e Implementación** | [`../Guidelines/Plan_Gestion_Desarrollo_Implementacion.md`](../Guidelines/Plan_Gestion_Desarrollo_Implementacion.md) | Plan ágil Backend-First en 7 Sprints, tareas granulares, QA PHPUnit y CI/CD. |
| **Historial de Control y Decisiones** | [`../Guidelines/Historial_de_Control.md`](../Guidelines/Historial_de_Control.md) | Bitácora oficial de cambios y esquema de versiones (`v0.0.1` a `v1.4.1`). |
| **Esquema SQL Oficial Raiden** | [`../../../database/Raiden.sql`](../../../database/Raiden.sql) | Script DDL oficial de creación del esquema completo y semillas base. |
| **Mockups de Servilleta** | [`../Mockups/`](../Mockups/) | Bocetos iniciales de interfaz gráfica para cada uno de los casos de uso (CU01 a CU07). |
| **Mockups de Alta Fidelidad** | [`../../mockups-alta-fidelidad/`](../../mockups-alta-fidelidad/) | Galería interactiva WCAG 2.1 AA para adultos (+40). |
| **Diagramas de Arquitectura** | [`../Diagramas/`](../Diagramas/) | Diagramas originales exportados de clases, secuencias, DER, canvas y dominio. |

---

## 3. Directrices y Reglas de Desarrollo Fundacionales (Reglas de Oro)

1. **Estrategia «Backend-First» Innegociable:**  
   El desarrollo del Backend (**Laravel 13** / PHP 8.4) tiene prioridad absoluta. La lógica de negocio, migraciones, controladores, adaptadores de IA y pruebas unitarias (PHPUnit) deben estar 100% funcionales con contratos de API documentados antes de construir o integrar las vistas móviles.
2. **Restricción Geográfica y Fuentes de Información:**  
   Todo el análisis, búsqueda de fuentes y datos de contraste debe focalizarse estrictamente en el ecosistema informativo de **Colombia** y agencias internacionales de Nivel 1 (Reuters, AP, AFP, BBC, OMS, ONU).
3. **Patrón Adapter / Strategy para Modelos de IA:**  
   La arquitectura se desacopla del proveedor del LLM (`AdaptadorApiIaInterface`) permitiendo alternar dinámicamente entre OpenAI, OpenRouter, Google Gemini o modelos locales Ollama, además del Consejo Multi-Modelo (`MultiModeloHarnessService`).
4. **Persistencia Inicial (Fase 0) y Evolución Dual:**  
   En Fase 0 se partió de MariaDB 10.11+ LTS con TablePlus; a partir de `v0.0.25` se adoptó **PostgreSQL 18 + `pgvector` en Neon.tech** como motor principal para búsqueda semántica KNN exacta, conservando compatibilidad de migraciones Laravel con MariaDB y SQLite.
5. **Responsividad Móvil y Usabilidad para Adultos (+40):**  
   Las interfaces web y clientes móviles deben ser 100% responsivas, con tipografía de alto contraste (WCAG 2.1 AA), botones de interacción evidentes ($\ge 48\text{ px}$) y flujos claros sin fricciones innecesarias.

---

## 4. Matriz de Trazabilidad: Casos de Uso vs. Clases vs. Tablas

| CU | Nombre del Caso de Uso | Vista / Boundary | Métodos Clave del Controlador | Tablas Afectadas |
| :---: | :--- | :--- | :--- | :--- |
| **CU01** | Asignar notas complementarias | `VRevisionContenido` | `procesarNota()`, `analizarNota()` | `notas`, `contenidos` |
| **CU02** | Calificar notas complementarias | `VRevisionContenido` | `procesarCalificacion()`, `validarCalificacion()` | `notas` |
| **CU03** | Guardar contenidos | `VRevisionContenido` | `coordinarGuardado()` | `contenidos`, `veredictos`, `historial` |
| **CU04** | Mostrar historial de contenidos | `VHistorial` | `consultarHistorialUsuario()`, `obtenerContenido()` | `historial`, `contenidos`, `veredictos` |
| **CU05** | Inicio de sesión / Credenciales | `VSesion` | `validarCredenciales()`, `crearUsuario()` | `usuarios` |
| **CU06** | Verificar contenidos | `VRevisionContenido` | `procesarContenido()`, `contrastarFuentes()`, `emitirVeredicto()` | `contenidos`, `procesos_verificacion`, `veredictos`, `veredicto_fuentes`, `logs_comunicador_api` |
| **CU07** | Asignar confiabilidad a fuentes | `VFuentes` | `obtenerFuentes()`, `procesarValoracion()`, `actualizarClasificacion()` | `fuentes` |

---

## 5. Implementación Consolidada en el Repositorio

La implementación ejecutada en el repositorio se alinea con la estructura final verificada en:
1. [`backend/app/Models/`](../../../backend/app/Models/) — Modelos Eloquent con UUIDs para las 10 tablas oficiales y tokens Sanctum.
2. [`database/Raiden.sql`](../../../database/Raiden.sql) y [`backend/database/migrations/`](../../../backend/database/migrations/) — Esquema PostgreSQL 18 (`pgvector`) y compatibilidad dual.
3. [`backend/app/Services/`](../../../backend/app/Services/) y [`backend/app/Adapters/`](../../../backend/app/Adapters/) — Orquestación de pipeline de 14 etapas, `MultiModeloHarnessService`, `BusquedaSemanticaService`, `ArchiveOrgService`, `ServicioInterlingue`, `CircuitBreakerService` y `AuditoriaExportService`.
4. [`backend/tests/`](../../../backend/tests/) — Suite de 142 pruebas PHPUnit (100% exitosas).
