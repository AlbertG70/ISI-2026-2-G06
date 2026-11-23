# DAGAZ — Onboarding e Integración de Redacciones Socias (REQ-26 / TSK-7.2)
## Guía de Integración Máquina a Máquina (M2M), Credenciales y Auditoría para Medios Aliados

> **Proyecto:** DAGAZ — Plataforma de Verificación de Información y Desinformación en Colombia  
> **Versión:** `v1.4.1`  
> **Asignatura:** Ingeniería de Servicios de Internet — 2026-2 — Grupo 06  
> **Requisito Asociado:** REQ-22, REQ-25, REQ-26 (Redacciones Socias), TSK-4.2, TSK-7.2  
> **Audiencia:** Directores de tecnología, jefes de redacción y desarrolladores de medios aliados.

---

## 1. Visión General del Programa de Socios Periodísticos

El programa de redacciones aliadas de **DAGAZ** permite a medios de comunicación y verificadores certificados consumir el motor de verificación directamente desde sus salas de redacción, sistemas CMS y flujos automatizados mediante la API pública REST `/api/v1/public/*`.

Bajo los requisitos **REQ-22** (primera redacción en producción) y **REQ-26** (incorporación de segunda y tercera redacción), [`config/dagaz.php`](../../backend/config/dagaz.php) y [`PartnerApiService.php`](../../backend/app/Services/PartnerApiService.php) aprovisionan soporte M2M multisede para:
* **Socio 1 (Medio Nacional de Referencia):** *El Espectador — Sala Digital* (`socio-espectador`)
* **Socio 2 (Verificador Certificado IFCN):** *ColombiaCheck — Redacción Central* (`socio-colombiacheck`)
* **Socio 3 (Medio de Investigación Política):** *La Silla Vacía — Detector de Mentiras* (`socio-lasillavacia`)
* **Ambiente Sandbox Piloto:** *Redacción de Prueba Piloto* (`socio-test`)

---

## 2. Aprovisionamiento de Credenciales y Parámetros Operativos

Las credenciales están definidas en `config('dagaz.partners.keys')` (sobrescribibles vía variables de entorno `DAGAZ_PARTNER_KEY_*` y `DAGAZ_PARTNER_RATE_LIMIT`) con cuotas diferenciadas mediante el rate limiter `throttle:partners`:

| Identificador de Socio (`id_socio`) | Organización | Cuota (`rate_limit_minute`) | Permisos Activos | Contacto Asociado |
| :--- | :--- | :---: | :--- | :--- |
| `socio-espectador` | *El Espectador — Sala Digital* | 60 req/min | `verificar`, `consultar`, `fuentes`, `auditoria` | `redaccion@elespectador.com` |
| `socio-colombiacheck` | *ColombiaCheck — Redacción Central* | 120 req/min | `verificar`, `consultar`, `fuentes`, `auditoria` | `contacto@colombiacheck.com` |
| `socio-lasillavacia` | *La Silla Vacía — Detector de Mentiras* | 90 req/min | `verificar`, `consultar`, `fuentes`, `auditoria` | `detector@lasillavacia.com` |
| `socio-test` | *Redacción de Prueba Piloto* | 60 req/min | `verificar`, `consultar`, `fuentes`, `auditoria` | `piloto@dagaz.test` |

> ⚠️ **Nota de Seguridad en Producción:** Las llaves por defecto incluidas en `config/dagaz.php` aplican únicamente a entornos locales o de desarrollo; en despliegue productivo deben asignarse valores aleatorios únicos mediante las variables `DAGAZ_PARTNER_KEY_ESPECTADOR`, `DAGAZ_PARTNER_KEY_COLOMBIACHECK` y `DAGAZ_PARTNER_KEY_SILLAVACIA` en `.env`.

---

## 3. Mecanismo de Autenticación M2M

Todas las solicitudes hacia `/api/v1/public/*` pasan por el middleware [`EnsureValidApiKey.php`](../../backend/app/Http/Middleware/EnsureValidApiKey.php), que admite los siguientes esquemas de entrega de credencial:

### Opción A: Cabecera HTTP `X-API-KEY` (Recomendada)
```http
POST /api/v1/public/verificar HTTP/1.1
Host: api.dagaz.com.co
X-API-KEY: <SU_API_KEY_ASIGNADA>
Content-Type: application/json
Accept: application/json
```

### Opción B: Cabecera `Authorization` o Query String (solo `GET`)
```http
Authorization: Api-Key <SU_API_KEY_ASIGNADA>
```
*(También se admiten prefijos `ApiKey` y `Key`, y el parámetro `?api_key=...` en peticiones `GET`).*

> 🔒 **Trazabilidad Automática:** Cada respuesta exitosa inyecta las cabeceras HTTP `X-Partner-Id` y `X-Partner-Name`, aprovisiona de forma idempotente el usuario `editor` asociado en la base de datos y registra la operación en `logs_comunicador_api` bajo el servicio `PartnerAPI:<id_socio>`.

---

## 4. Contrato Real de Endpoints (`/api/v1/public/*`)

Implementado en [`PublicPartnerApiController.php`](../../backend/app/Http/Controllers/PublicPartnerApiController.php), [`AuditoriaController.php`](../../backend/app/Http/Controllers/AuditoriaController.php) y [`routes/api.php`](../../backend/routes/api.php):

### 4.1 Verificar Contenido (`POST /api/v1/public/verificar`)
* **Entrada admitida (`JSON` o `multipart/form-data`):**
  * `tipo_entrada` (**requerido**): `TEXTO`, `URL`, `IMAGEN_OCR`, `RED_SOCIAL` (también acepta minúsculas o `imagen`/`audio`/`video`).
  * `contenido_original` (mínimo 10, máximo 10.000 caracteres — requerido si no se envían `url_origen` ni `archivo`).
  * `url_origen` (URL válida de hasta 2.048 caracteres — opcional/alternativo).
  * `archivo` (imagen/audio de hasta 20 MB — opcional/alternativo).

```json
{
  "tipo_entrada": "TEXTO",
  "contenido_original": "El ministro de hacienda confirmó que el impuesto al valor agregado aumentará al 22% a partir del próximo mes.",
  "url_origen": "https://twitter.com/cadena_viral/status/123456"
}
```

* **Respuesta Exitosa (`200 OK`):**
```json
{
  "version_api": "v1.0",
  "socio": {
    "id_socio": "socio-colombiacheck",
    "nombre": "ColombiaCheck — Redacción Central"
  },
  "solicitud": {
    "id_contenido": "9d8f4a21-5b42-4c11-9e1a-8a3b7c2d1e0f",
    "id_proceso": "1a2b3c4d-7e8f-4901-abcd-1234567890ab",
    "tipo_entrada": "TEXTO",
    "estado": "VERIFICADO",
    "timestamp": "2026-11-09T20:15:00-05:00"
  },
  "veredicto": {
    "tipo_veredicto": "FALSO",
    "nivel_confiabilidad": 92.5,
    "explicacion": "La afirmación no cuenta con respaldo en las fuentes oficiales consultadas..."
  },
  "fuentes_contraste": [
    {
      "nombre": "Ministerio de Hacienda y Crédito Público",
      "url_sitio": "https://www.minhacienda.gov.co",
      "tipo_fuente": "OFICIAL",
      "indice_credibilidad": 98.0
    }
  ],
  "analisis_avanzado": {
    "interlingue": null,
    "archive_org": null,
    "multi_modelo_evaluacion": {
      "habilitado": true,
      "veredicto_consenso": "FALSO",
      "porcentaje_consenso": 100.0
    }
  }
}
```

### 4.2 Consultar Veredicto Existente (`GET /api/v1/public/veredictos/{idContenido}`)
* Recibe el UUID `idContenido` y retorna los metadatos de `contenido`, `veredicto` vigente (incluyendo rectificaciones humanas editoriales de REQ-13) y el detalle de `fuentes` contrastadas con `nivel`, `nivel_etiqueta` y `cita_contraste`.

### 4.3 Consultar Grafo de Credibilidad (`GET /api/v1/public/fuentes`)
* Retorna el catálogo completo (80 fuentes iniciales en 4 niveles) con filtros opcionales por query string: `?tipo_fuente=OFICIAL`, `?nivel=1..4` y `?buscar=<texto>`.

### 4.4 Diagnóstico del Servicio (`GET /api/v1/public/health`)
* Retorna `{ status: "operational", servicio: "DAGAZ Public Partner API v1", socio_activo, timestamp, version: "1.0.0", rate_limit, sla: "99.9%" }`.

### 4.5 Auditoría Forense y Schema.org ClaimReview (`REQ-25`)
* `GET /api/v1/public/auditoria/{idContenido}?formato=json|json-ld|csv` — Descarga el expediente completo de trazabilidad con firma **SHA-256**, logs externos e intervenciones editoriales.
* `GET /api/v1/public/claim-review/{idContenido}` — Entrega el documento **Schema.org `ClaimReview` (`application/ld+json`)** listo para indexación directa en Google Fact Check Tools e IFCN (enlace público web canónico: `https://<dominio-dagaz>/verificar/{idContenido}`).

---

## 5. Patrón de Integración Síncrona y Evolutiva (Webhooks)

En la versión actual (`v1.4.1`), el consumo de `/api/v1/public/verificar` se ejecuta de forma **síncrona** en una sola transacción REST (incluyendo RAG semántico y Consejo Multi-Modelo concurrente vía `Http::pool`). Para verificar si un veredicto fue posteriormente rectificado por un editor humano (REQ-13), los CMS aliados pueden consultar periódicamente (`polling`) `GET /api/v1/public/veredictos/{idContenido}` o `GET /api/v1/public/auditoria/{idContenido}`.

> 📌 **Evolución Planificada (Roadmap M2M):** En futuras versiones con colas asíncronas (`QUEUE_CONNECTION=redis`), se contempla el despacho de eventos *push* vía Webhook (`veredicto.completado` y `veredicto.intervenido`) sobre las mismas estructuras JSON de respuesta.

---

## 6. Manejo de Errores y Códigos HTTP

| Código HTTP | Causa | Respuesta / Solución Recomendada |
| :---: | :--- | :--- |
| `401 Unauthorized` | Cabecera `X-API-KEY` faltante o vacía | Incluir `X-API-KEY` o `Authorization: Api-Key <token>`. |
| `403 Forbidden` | Clave inválida, revocada o sin permiso requerido | Validar la clave asignada en configuración del entorno. |
| `404 Not Found` | UUID de `idContenido` inexistente o ruta inválida | Verificar que `idContenido` sea un UUID válido emitido por DAGAZ. |
| `422 Unprocessable Entity` | `tipo_entrada` inválido o `contenido_original` < 10 chars | Enviar alguno de `contenido_original` (≥ 10 chars), `url_origen` o `archivo`. |
| `429 Too Many Requests` | Límite `throttle:partners` excedido | Implementar reintentos exponenciales (*exponential backoff*). |
| `500 Internal Error` | Excepción interna no recuperable | Revisar conectividad de BD/proveedores y reintentar. |
