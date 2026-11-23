# DAGAZ — Manual de Administrador y Revisor Humano Editorial
## Guía de Gestión, Intervención Humana (REQ-13), Moderación y Auditoría Forense (REQ-25)

> **Proyecto:** DAGAZ — Plataforma de Verificación de Información y Desinformación en Colombia  
> **Versión:** `v1.4.1`  
> **Asignatura:** Ingeniería de Servicios de Internet — 2026-2 — Grupo 06  
> **Requisitos Asociados:** REQ-13 (Intervención y Rectificación Humana), REQ-18 (Circuit Breaker), REQ-25 (Auditoría Forense), REQ-27 (Manuales)  
> **Destinatarios:** Editores de salas de prensa aliadas (`editor`), revisores de fact-checking y administradores del sistema (`admin`).

---

## 1. Introducción al Rol Editorial y Revisor Humano

El sistema **DAGAZ** opera bajo un modelo de **Inteligencia Aumentada**: aunque el motor automatizado (búsqueda semántica `pgvector` sobre 80 fuentes, análisis Archive.org/interlingüe y **Consejo Multi-Modelo de IA**) genera un dictamen preliminar, el criterio humano de periodistas y editores certificados tiene **supremacía editorial y trazabilidad forense**.

De acuerdo con **REQ-13** y [`VerificacionController.php`](../../backend/app/Http/Controllers/VerificacionController.php), los usuarios autenticados con rol **`editor`** o **`admin`** pueden intervenir cualquier veredicto para:
* Rectificar clasificaciones automatizadas imprecisas o desactualizadas.
* Ajustar el nivel de confiabilidad (`0` a `100%`) basado en evidencia primaria reciente.
* Registrar una explicación editorial sustentada y dejar constancia inmutable del cambio en la bitácora de auditoría.

---

## 2. Panel de Intervención Humana Editorial (Paso a Paso)

### 2.1 Identificación y Acceso
1. Inicie sesión en DAGAZ (`/login`) con una cuenta que tenga rol **`editor`** o **`admin`**.
2. Navegue hacia cualquier veredicto emitido (desde el apartado **Historial** en `/historial` o mediante la ruta directa `/verificar/{id}`).
3. En la sección inferior de [`resultado.blade.php`](../../frontend/views/verificacion/resultado.blade.php), la interfaz habilitará automáticamente el **Panel de Intervención Editorial Humana (REQ-13)** cuando detecte el rol autorizado en la sesión activa.

### 2.2 Campos del Formulario de Rectificación
El panel solicita los siguientes cuatro parámetros obligatorios validados tanto en cliente (`frontend/src/js/pages/resultado.js`) como en servidor (`VerificacionController::intervenirVeredicto`):
* **Nuevo Veredicto Rectificado (`tipo_veredicto`):** Valores oficiales del esquema de base de datos:
  * `VERDADERO` (*✅ Verdadero*)
  * `PARCIAL` (*⚠️ Parcialmente cierto*)
  * `ENGAÑOSO` (*🟠 Engañoso*) — *Nota técnica: el `<select>` HTML normaliza la selección al enviar hacia el endpoint conforme al contrato oficial `config/dagaz.php`*.
  * `FALSO` (*❌ Falso*)
  * `NO_VERIFICABLE` (*❔ No verificable*)
* **Nivel de Confianza Asignado (`nivel_confiabilidad`):** Valor numérico entre `0` y `100` (%).
* **Motivo de la Intervención (`motivo_intervencion`):** Justificación concisa para auditoría forense (entre **10 y 1.000 caracteres**).
* **Explicación Ciudadana Sustentada (`explicacion`):** Redacción clara y pedagógica dirigida a la ciudadanía (entre **20 y 5.000 caracteres**). Al persistirse, el backend antepone el sello `[Revisión Editorial por <email>]: ...`.

### 2.3 Aplicación y Persistencia de la Rectificación
1. Pulse **"✍️ Registrar Intervención Editorial"**.
2. El frontend enviará una petición `POST` autenticada con token Bearer Sanctum a:
   ```http
   POST /api/v1/veredictos/{idVeredicto}/intervenir
   ```
   protegida por los middlewares `auth:sanctum` y `rol:admin,editor`.
3. El controlador actualizará `veredictos` y registrará una entrada inmutable de auditoría en `logs_comunicador_api`.

---

## 3. Moderación Comunitaria (CU01 / CU02)

Las notas comunitarias publicadas en `POST /api/v1/contenidos/{idContenido}/notas` y calificadas en `PATCH /api/v1/notas/{idNota}/calificar` aplican automáticamente protecciones integradas:
* **Prevención XSS / SQLi (`VerificacionService::analizarNota`):** Rechaza etiquetas `<script>`, `javascript:`, cargas maliciosas SQL y caracteres estructurales inseguros en notas.
* **Rango Normalizado de Calificación (`VerificacionService::validarCalificacion`):** Controla votaciones en escala `0.0` a `10.0` y bloquea votos fuera de rango.
* **Enmascaramiento de Autor:** En respuestas públicas (`listarNotas`), el correo del ciudadano se entrega enmascarado según la Ley 1581 de 2012.

---

## 4. Gestión del Grafo de Credibilidad de Fuentes (CU07)

En la sección web **Fuentes Confiables (`/fuentes`)** o vía API REST con rol **`admin`**:
* **Reclasificación de Fuentes Existentes:** `PATCH /api/v1/fuentes/{idFuente}/clasificar` permite actualizar `tipo_fuente` (`OFICIAL`, `PERIODISTICO`, `ACADEMICO`, `RED_SOCIAL`, `OTRO`), `indice_credibilidad` (`0` a `100`), `nombre` y `url_sitio`.
* **Registro de Nuevas Fuentes:** `POST /api/v1/fuentes` incorpora nuevos medios nacionales, regionales o agencias al Grafo de Credibilidad (80 fuentes iniciales en `FuentesSeeder.php`).

---

## 5. Auditoría Forense, Circuit Breaker y Telemetría (REQ-18 / REQ-25)

### 5.1 Registro de Auditoría en Intervenciones (`logs_comunicador_api`)
Cada rectificación editorial almacena automáticamente un registro trazable con:
* `id_proceso`: UUID del proceso de verificación intervenido.
* `servicio`: `'Panel Editorial Humano'`
* `tipo_operacion`: `'intervencion_editorial'`
* `codigo_respuesta`: `200`
* `detalle_error` (payload JSON): `editor`, `id_editor`, `motivo_intervencion` y `estado_anterior` (`tipo_veredicto`, `nivel_confiabilidad`, `explicacion`).
* `fecha_registro`: Timestamp exacto del evento.

### 5.2 Exportación Forense para Reguladores y ClaimReview (`AuditoriaController`)
Editores y administradores pueden descargar evidencias con hash de integridad **SHA-256** mediante:
* `GET /api/v1/auditoria/veredictos/{idContenido}?formato=json|json-ld|csv` — Expediente forense individual (incluyendo intervenciones editoriales e historial de logs).
* `GET /api/v1/auditoria/expedientes?desde=YYYY-MM-DD&hasta=YYYY-MM-DD&formato=json|csv` — Exportación masiva por lote (hasta 500 registros).
* `GET /api/v1/auditoria/claim-review/{idContenido}` — Esquema oficial **Schema.org `ClaimReview` JSON-LD** compatible con Google Fact Check Tools e IFCN.

### 5.3 Supervisión de Costes LLM y Logs Externos (Solo `admin`)
Los administradores cuentan adicionalmente con tres endpoints administrativos:
* `GET /api/v1/admin/circuit-breaker` — Estado en tiempo real del disyuntor de costes LLM (tokens/hora y presupuesto diario USD).
* `POST /api/v1/admin/circuit-breaker/reset` — Restablecimiento manual del circuito tras validación presupuestal.
* `GET /api/v1/admin/logs-api?servicio=...&codigo_respuesta=...` — Bitácora paginada de llamadas a LLMs, OCR/STT, Archive.org y Redacciones Socias.
