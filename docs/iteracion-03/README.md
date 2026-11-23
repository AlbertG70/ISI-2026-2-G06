# Iteración 03 — Evaluación Formal, Benchmark y Pruebas Piloto

Esta carpeta documenta la tercera iteración del proyecto **DAGAZ** (evaluación formal sobre 500 afirmaciones, auditoría de sesgo electoral, carga continua, streaming reactivo SSE y piloto con redacciones socias), consolidando sus entregables evaluativos en [`docs/iteracion-final/`](../iteracion-final/) junto con el historial evolutivo hasta **`v1.5.1`**.

---

## Alcance Ejecutado de la Iteración 03 (Sprint 6)

De acuerdo con el [Plan de Gestión](../iteracion-02/Guidelines/Plan_Gestion_Desarrollo_Implementacion.md) y el [Diagrama de Gantt](../iteracion-02/Guidelines/Diagrama_de_Gantt.md), en esta iteración se completaron y certificaron los siguientes objetivos:

1. **Benchmark de 500 Afirmaciones Etiquetadas (`REQ-11`):**
   - Construcción y doble etiquetado ciego por panel de 3 evaluadores sobre 500 afirmaciones colombianas con acuerdo inter-evaluador Fleiss' Kappa $\kappa = 0{,}794$ ($\ge 0{,}75$).
2. **Auditoría de Sesgo Político (`REQ-17`):**
   - Prueba estadística de independencia $\chi^2 = 0{,}682$ ($df = 2$, $p = 0{,}711 > 0{,}05$) sobre el subconjunto electoral, certificando ausencia de sesgo partidista significativo.
3. **Evaluación Formal de Rendimiento (`REQ-24`):**
   - Validación empírica superando las metas contractuales: **Precisión del 84.6%** ($\ge 80\%$), **Exhaustividad (Recall) del 81.2%** ($\ge 75\%$), **Exactitud del 83.4%** y **$F_1$-score de 0.828**.
4. **Prueba de Carga, Flujo Continuo e Ingesta (`REQ-21`):**
   - Operación continua con comando Artisan `dagaz:actualizar-noticias` y fallback heurístico local asegurando disponibilidad $\ge 99\%$ (99.8% registrada) ante caídas de red o límites de cuota.
5. **Prueba Piloto con Redacción Socia (`REQ-22`):**
   - Consumo M2M auditado de 50 veredictos por la primera sala de prensa aliada (*El Espectador — Sala Digital*) vía `/api/v1/public/verificar` (extendido posteriormente a *ColombiaCheck* y *La Silla Vacía* en `REQ-26`).
6. **Streaming Reactivo de Verificación en Tiempo Real (`TSK-6.7` / `REQ-24` / `CU06`):**
   - Implementación de canal Server-Sent Events (`POST /api/v1/verificacion/stream`, `text/event-stream`) en Laravel para transmisión en vivo de las etapas del pipeline (ingesta, contraste de 80 fuentes IFCN, búsqueda web, juicio multi-agente y veredicto) hacia la web y la app móvil mediante la Consola de Pensamiento Fáctico (*Chain-of-Thought*).

---

## Entregables Completados y Ubicación en el Repositorio

- [x] **[Informe Técnico de Evaluación Formal y Auditoría de Sesgo](../iteracion-final/Evaluacion_Benchmark_500_Afirmaciones_y_Auditoria_Sesgo.md)**: Benchmark de 500 afirmaciones ($\kappa = 0{,}794$, Precisión $84{,}6\%$, Recall $81{,}2\%$, $F_1 = 0{,}828$), prueba de imparcialidad $\chi^2 = 0{,}682$ y métricas de carga (99.8% disponibilidad).
- [x] **[Guía de Onboarding y Piloto de Redacciones Socias (`REQ-22` / `REQ-26`)](../iteracion-final/Onboarding_Redacciones_Socias_REQ26.md)**: Autenticación `X-API-KEY`, cuotas `throttle:partners` y endpoints `/api/v1/public/*`.
- [x] **[Sprint Backlogs Realizados (Sprint 6 y Bloque Post-MVP)](../iteracion-02/Guidelines/Sprint_Backlog_Realizados.md)**: Trazabilidad WBS (`TSK-6.1` a `TSK-6.7`) y calibración posterior con `MultiModeloHarnessService` y `BusquedaWebService`.
- [x] **Especificación técnica y canal de Streaming reactivo de verificación (SSE)**: `POST /api/v1/verificacion/stream`, emisión híbrida de hitos con Consola de Pensamiento CoT.
- [x] **[Acta de Traspaso y Retrospectiva (`REQ-28`)](../iteracion-final/Retrospectiva_Final_y_Acta_Traspaso_REQ28.md)**: Evidencia de cierre y evolución continua.
- [x] **Evidencias de Calidad Vigentes (`v1.5.1`)**: **190 pruebas automatizadas aprobadas al 100%** (**163 PHPUnit** [`2.464` aserciones] + **23 Vitest** + **4 Python `unittest`**).

---

## Integrantes

| Integrante | Código | Rol |
|:--|:--:|:--|
| **Alberto Mario Galvis Echeverria** | `0222410070` | Analista de Requerimientos / Desarrollador FullStack |
| **Camilo Isaac Acevedo Rueda** | `0222410049` | Analista de Calidad / Desarrollador FullStack |

---

## Estado de la Entrega

- [x] Documentación y métricas de la Iteración 03 consolidadas y enlazadas
- [x] Evidencias cuantitativas del benchmark de 500 afirmaciones, auditoría $\chi^2$, streaming SSE y piloto M2M certificadas
- [x] Suites automatizadas (**190 pruebas**) pasando al 100% en estado vigente `v1.5.1`
