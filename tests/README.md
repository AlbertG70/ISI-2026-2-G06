# Pruebas y Aseguramiento de Calidad (QA) — DAGAZ

Esta carpeta centraliza las evidencias, métricas y referencia de las suites automatizadas de verificación sobre el ecosistema **DAGAZ** (**190 pruebas totales pasando al 100%**).

---

## 1. Resumen Global de Calidad

| Capa / Entorno | Framework | Suites / Archivos | Tests Totales | Aserciones | Resultado |
| :--- | :--- | :---: | :---: | :---: | :---: |
| **Backend (Laravel 13 / PHP 8.4)** | PHPUnit 12.5 + SQLite `:memory:` | 18 archivos | **163** | **2.464** | ✅ 100% Pasando |
| **Frontend Web (Vite / Tailwind v4)** | Vitest 5.0 + Happy-DOM | 8 archivos | **23** | **68** | ✅ 100% Pasando |
| **Microservicios Multimedia Python** | `unittest` + FastAPI TestClient | 1 archivo | **4** | **12** | ✅ 100% Pasando |
| **TOTAL ECOSISTEMA** | — | **27 archivos** | **190 tests** | **2.544** | ✅ **100% Certificado** |

---

## 2. Pruebas del Backend (`Laravel 13 / PHPUnit 12.5`)

Las pruebas automatizadas del backend residen en [`../backend/tests/`](../backend/tests/) y se ejecutan de manera determinista sobre **SQLite `:memory:`** con `DAGAZ_MULTI_MODELO_HABILITADO=false` configurado en [`../backend/phpunit.xml`](../backend/phpunit.xml).

### Ejecución de Pruebas del Backend

```bash
cd backend
php artisan test
# O directamente con PHPUnit:
.\vendor\bin\phpunit.bat
```

### Cobertura Verificada del Backend

| Métrica | Valor |
| :--- | :--- |
| **Tests totales** | **163** |
| **Aserciones** | **2.464** |
| **Resultado** | ✅ **163 / 163 pasando (100%)** |
| **Motor de pruebas** | PHPUnit 12.5 + Laravel 13 + SQLite `:memory:` |

### Suites Destacadas del Backend

| Suite de Prueba | Archivo | Funcionalidad / Requisitos Validados |
| :--- | :--- | :--- |
| **Búsqueda Semántica & Blindaje RAG** | `BusquedaSemanticaTest.php` | Similitud de coseno, filtrado temático por metadatos, exclusión HTML/imágenes, fallback de hechos 2026 y blindaje para menciones aisladas (`SIN_EVIDENCIA` $\rightarrow$ 0.0%). |
| **Búsqueda Web en Tiempo Real** | `BusquedaWebServiceTest.php` | Integración de resultados web en vivo para actualidad económica y eventos del día. |
| **Consejo Multi-Modelo & Grounding** | `MultiModeloHarnessTest.php` | Quórum dinámico de agentes IA, calibración ponderada y extracción de `groundingChunks` de Google Search. |
| **Integración Gemini & Fallback** | `GeminiDriverTest.php` | Cadena de modelos de respaldo ante 404/429/503, filtrado de bloques `<think>` y parseo JSON resiliente. |
| **Canal Reactivo SSE & E2E** | `VerificacionFeatureTest.php` | Streaming reactivo de verificación en tiempo real (`/api/v1/verificacion/stream`) con eventos SSE y entrada multicanal. |
| **Integración OpenRouter e IA** | `OpenAiDriverTest.php` | Normalización de payloads, parsing robusto de respuestas JSON y manejo de errores de OpenRouter. |
| **Pipeline de Verificación E2E** | `VerificacionServiceTest.php` | Ejecución de las 14 fases, ponderación de fuentes, cálculo de confianza (0–100%) y calibración de veredictos. |
| **Requisitos E2E del Sistema** | `VerificacionesBackendFeatureTest.php` | Validación integral de entrada multicanal (texto, URL, OCR, audio), hechos 2026 y citas documentales. |
| **Autenticación y Hábeas Data** | `AuthTest.php` | Tokens Bearer Sanctum, roles (`adulto`, `editor`), consentimiento Ley 1581 y revocación de acceso. |
| **Grafo de 80 Fuentes** | `GrafoFuentesTest.php` | Categorización en 4 niveles IFCN, ponderación de credibilidad y filtros por reputación. |
| **API Pública Socias (M2M)** | `PublicPartnerApiTest.php` | Autenticación con API Key (`EnsureValidApiKey`), rate limiting y esquema ClaimReview. |
| **Archive.org & Resiliencia** | `ArchiveOrgTest.php` | Conector Wayback Machine para detección de descontextualización histórica. |
| **Recuperación Interlingüe** | `InterlingueTest.php` | Detección de idioma ES-EN, normalización de afirmaciones foráneas y contraste bilingüe. |
| **Exportación Forense** | `AuditoriaExportTest.php` | Generación de reportes forenses en JSON, CSV y ClaimReview Schema.org para reguladores. |

---

## 3. Pruebas del Frontend y Aceptación (`Vitest 5.0 + Happy-DOM`)

Las pruebas unitarias y de aceptación de la capa cliente residen en [`../frontend/tests/`](../frontend/tests/) y se ejecutan con **Vitest 5.0** simulando el DOM del navegador.

### Ejecución de Pruebas del Frontend

```bash
cd frontend
npm test -- --run
```

### Cobertura Verificada Frontend

| Métrica | Valor |
| :--- | :--- |
| **Archivos de prueba** | **8** |
| **Tests totales** | **23** |
| **Resultado** | ✅ **23 / 23 pasando (100%)** |
| **Motor de pruebas** | Vitest 5.0 + Happy-DOM |

### Suites de Pruebas del Frontend

| Suite | Archivo | Casos de Uso / Requisito |
| :--- | :--- | :--- |
| **Cliente API** | [`api.test.js`](../frontend/tests/api.test.js) | Conexión con `/api/v1`, tokens Bearer Sanctum, mapeo de errores 422 |
| **Sesión y Auth** | [`sesion.test.js`](../frontend/tests/sesion.test.js) | Almacenamiento seguro, verificación de roles (`editor`/`adulto`), cierre de sesión |
| **Seguridad y DOM** | [`ui.test.js`](../frontend/tests/ui.test.js) | Mitigación XSS (inserción forzada `textContent`), medidor circular SVG, anonimización Hábeas Data |
| **Panel Editorial** | [`editorial.test.js`](../frontend/tests/editorial.test.js) | **REQ-13 / TSK-3.7**: Control de acceso por rol (`editor`/`admin`), validación de longitudes |
| **Accesibilidad +40** | [`accesibilidad.test.js`](../frontend/tests/accesibilidad.test.js) | Conmutador de fuente (`A / A+`), persistencia en `localStorage`, contraste WCAG 2.1 AA |
| **Historial** | [`historial.test.js`](../frontend/tests/historial.test.js) | **CU04**: Filtrado de noticias guardadas, búsqueda instantánea, clasificación por veredicto |
| **Notas y Votos** | [`notas.test.js`](../frontend/tests/notas.test.js) | **CU01 / CU02**: Sanitización de caracteres prohibidos (`< > { } \ |`), barra de utilidad |
| **Entrada Multicanal** | [`verificar.test.js`](../frontend/tests/verificar.test.js) | **CU06**: Longitud mínima de texto (10 car.), validación de URLs y límite de archivo (20 MB) |

---

## 4. Pruebas de Microservicios Python (`unittest` + FastAPI TestClient)

Las pruebas unitarias y de endpoints FastAPI de los microservicios OCR (`:5001`) y STT (`:5002`) se encuentran en [`../backend/Microservicios_Python/tests/test_services.py`](../backend/Microservicios_Python/tests/test_services.py).

### Ejecución Microservicios

```bash
cd backend/Microservicios_Python
python -m unittest discover -s tests -v
```

### Cobertura Verificada Microservicios

| Métrica | Valor |
| :--- | :--- |
| **Tests totales** | **4** (`test_ocr_api_endpoints`, `test_ocr_local_execution`, `test_stt_api_endpoints`, `test_stt_local_execution`) |
| **Resultado** | ✅ **4 / 4 pasando (100%)** |
| **Motor de pruebas** | Python `unittest` + `fastapi.testclient` |
