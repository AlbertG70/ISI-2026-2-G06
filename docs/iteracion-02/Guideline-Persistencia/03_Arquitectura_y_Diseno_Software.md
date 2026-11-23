# DAGAZ — Arquitectura y Diseño de Software

> **Documento de Especificación Arquitectónica, Patrones y Diagramas de Interacción**  
> **Proyecto:** DAGAZ  
> **Stack Técnico Vigente (`v1.4.1`):** **Laravel 13 (`^13.17`)** / PHP `^8.3`–`8.4`, PostgreSQL 18 + `pgvector` en Neon.tech (compatibilidad MariaDB/SQLite), Python FastAPI (OCR/STT), Vite 8 + Tailwind CSS v4 + Blade, **Capacitor 6** (`mobile/`) y **React Native / Expo SDK 58** (`mobile-expo/`).  
> **Ubicación de DCD:** [`Diagrama_Clases_Diseno.md`](../../iteracion-01/Diagramas/Clases%20de%20Diseno/Diagrama_Clases_Diseno.md)  
> **Ubicación de DSS (Caja Gris):** [`DSS (Caja Gris)/`](../../iteracion-01/Diagramas/DSS%20%28Caja%20Gris%29/)

---

## 1. Visión General de la Arquitectura

El sistema DAGAZ sigue un diseño modular **Backend-First** con separación estricta entre clientes (Web Blade/Vite, Capacitor 6, Expo SDK 58, API pública M2M para redacciones) y el núcleo de dominio en Laravel 13:

```text
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                        CAPA DE PRESENTACIÓN / CLIENTES (UI & M2M)                      │
│  - Web Móvil SSR/PWA (+40 WCAG 2.1 AA): Vite 8 + Tailwind CSS v4 + Blade               │
│    (frontend/views/* e index.html, fuentes.html, resultado.html, acerca.html)          │
│  - App Híbrida Android/iOS: mobile/ (Capacitor 6, webDir: ../frontend/dist)            │
│  - App Nativa Multiplataforma: mobile-expo/ (Expo SDK 58, timeout 120s, CI .ipa)       │
│  - Redacciones Socias M2M: Endpoints /api/v1/public/* con X-API-KEY                    │
└───────────────────────────────┬────────────────────────────┬───────────────────────────┘
                                │ HTTP / REST (JSON)         │ CORS / X-API-KEY / Bearer
                                ▼                            ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                        CAPA DE SEGURIDAD, ENRUTAMIENTO Y ACCESO                        │
│  - Middleware auth:sanctum (Tokens Bearer UUID en personal_access_tokens)              │
│  - Middleware EnsureValidApiKey ('api.key') + 'throttle:partners'                      │
│  - Middleware VerificarRol ('rol:admin,editor') + CORS + Servidor backend/api_server   │
└───────────────────────────────────────────┬────────────────────────────────────────────┘
                                            │
                                            ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                              CONTROLADORES DE APLICACIÓN REST                          │
│   AuthController │ VerificacionController │ PublicPartnerApiController │ AuditoriaCtrl │
└───────────────────────────────────────────┬────────────────────────────────────────────┘
                                            │
               ┌────────────────────────────┴────────────────────────────┐
               ▼                                                         ▼
┌──────────────────────────────────────────────┐  ┌──────────────────────────────────────┐
│        SERVICIOS DE DOMINIO Y CORE           │  │       SERVICIOS DE IA Y SOPORTE      │
│ - VerificacionService (Pipeline 14 etapas)   │  │ - MultiModeloHarnessService (Pool)   │
│ - BusquedaSemanticaService (KNN + Léxico +   │  │   (GPT-4o, Claude, Gemini, DeepSeek, │
│   normalizarSinTildes + Contraste 2026)      │  │    Qwen; salvaguarda anti-downgrade) │
│ - ArchiveOrgService (Wayback / CDX 365d)     │  │ - AuditoriaExportService (ClaimReview│
│ - ServicioInterlingue (Contraste ES <-> EN)  │  │   JSON Forense SHA-256, CSV BOM)     │
│ - CircuitBreakerService (Costes/Tokens LLM)  │  │ - PartnerApiService (M2M Socios)     │
└──────────────────────┬───────────────────────┘  └──────────────────────────────────────┘
                       │
        ┌──────────────┴─────────────────────────┐
        ▼                                        ▼
┌─────────────────────────────────┐   ┌──────────────────────────────────────────────────┐
│  ADAPTADORES DE INFRAESTRUCTURA │   │             CAPA DE PERSISTENCIA Y DATOS         │
│ - IaDriverFactory / Drivers IA  │   │  PostgreSQL 18 + pgvector en Neon.tech (SSL)     │
│   (OpenAI, OpenRouter <think>/  │   │  - 10 Tablas de dominio UUID + tokens Sanctum    │
│    reasoning, Gemini, Local LLM)│   │  - Tabla documentos: vector(384) KNN en SSD      │
│ - AdaptadorMulticanal (HTTP)    │   │  - Grafo de 80 Fuentes categorizadas IFCN        │
│   - Microservicio OCR (:5001)   │   │  - Entorno local compatible: MariaDB / SQLite    │
│   - Microservicio STT (:5002)   │   │  - Ingesta: ActualizarNoticiasCommand / Python   │
└─────────────────────────────────┘   └──────────────────────────────────────────────────┘
```

### 1.1 Principios Arquitectónicos Clave
1. **Agnosticismo Strategy + Consejo Multi-Modelo en Paralelo:**  
   `AdaptadorApiIaInterface` desacopla el motor de inferencia (`OpenAiDriver` compatible con OpenAI y OpenRouter —limpiando `<think>` y leyendo `reasoning`—, `GeminiDriver`, `LocalLlmDriver`). Además, [`MultiModeloHarnessService`](../../../backend/app/Services/MultiModeloHarnessService.php) ejecuta validaciones paralelas vía `Http::pool` con calibración configurable y salvaguarda explícita que evita degradar `FALSO` a `NO_VERIFICABLE`.
2. **Abstracción Multicanal de Entrada:**  
   Texto plano, URLs, imágenes (vía OCR `ocr_service/app.py` con Pillow y `pytesseract` en `:5001`) y audios (vía STT `stt_service/app.py` con `SpeechRecognition` en `:5002`) se normalizan hacia `Contenido`.
3. **Ponderación Jerárquica en 4 Niveles IFCN (80 Fuentes):**  
   * **Nivel 1:** Agencias internacionales y multilaterales (Reuters, AP, AFP, BBC, OMS, ONU, OCDE, EFE).
   * **Nivel 2:** Prensa regional colombiana validada.
   * **Nivel 3:** Prensa nacional de Colombia.
   * **Nivel 4:** Fact-checkers certificados IFCN (ColombiaCheck, La Silla Vacía, AFP Factual, Chequeado, Snopes) y organismos oficiales de control (Registraduría, CNE, DANE, BanRep).

---

## 2. Diagrama de Clases de Diseño (DCD)

El modelo de clases de análisis/diseño que dio origen al esquema relacional y a los servicios Eloquent mantiene la correspondencia dominio-controlador-infraestructura:

```mermaid
classDiagram
    direction TB

    %% Modelos de Dominio
    class Usuario {
        +UUID idUser
        +String email
        +String passwordHash
        +String rol
        +validarSesion() boolean
        +esAdministrador() boolean
    }

    class Contenido {
        +UUID idContenido
        +String canalOrigen
        +String cuerpoTexto
        +String urlReferencia
        +DateTime fechaRecepcion
        +getCuerpoTexto() String
    }

    class Documento {
        +UUID idDocumento
        +UUID fuenteId
        +String titulo
        +String fragmento
        +Vector embedding
        +DateTime fechaIndexacion
        +buscarSimilares(Vector consulta) List
    }

    class ProcesoVerificacion {
        +UUID idProceso
        +UUID idContenido
        +float scoreConfiabilidad
        +JSON analisisLenguaje
        +JSON contrasteTemas
        +DateTime fechaAnalisis
    }

    class Veredicto {
        +UUID idVeredicto
        +UUID idContenido
        +UUID idProceso
        +float nivelConfiabilidad
        +String clasificacion
        +String justificacion
        +DateTime fechaEmision
        +esConfiable() boolean
    }

    class Fuente {
        +UUID idFuente
        +String nombre
        +String urlFuente
        +float credibilidad
        +String clasificacion
        +getClasificacion() String
        +setClasificacion(String c) void
        +getCredibilidad() float
    }

    class Nota {
        +UUID idNota
        +UUID idUsuario
        +UUID idContenido
        +String comentario
        +int calificacion
        +getCalificacion() int
        +setCalificacion(int c) void
    }

    class Historial {
        +UUID idRegistro
        +UUID idUsuario
        +UUID idContenido
        +DateTime fechaHora
        +getResumen() String
    }

    class VeredictoFuente {
        +UUID idVeredicto
        +UUID idFuente
        +float pesoEnVeredicto
        +String citaContraste
    }

    class LogComunicadorApi {
        +UUID idLog
        +UUID idProceso
        +String servicio
        +String tipoOperacion
        +int codigoRespuesta
        +String detalleError
        +DateTime fechaRegistro
    }

    %% Capa de Control y Multi-Agente
    class VerificacionController {
        +verificarContenido() JsonResponse
        +obtenerContenido() JsonResponse
        +listarFuentes() JsonResponse
        +agregarNota() JsonResponse
        +calificarNota() JsonResponse
        +intervenirVeredicto() JsonResponse
    }

    class MultiModeloHarnessService {
        +evaluarConConsejo(afirmacion, veredictoPreliminar, citas) array
    }

    %% Capa de Vistas / Clientes
    class VistasBladeYMovil {
        +index.blade.php / resultado.blade.php
        +VerificarScreen.js / ResultadoScreen.js
        +fuentes / historial / perfil
    }

    %% Capa de Adaptadores e Infraestructura
    class AdaptadorMulticanal {
        +procesarEntrada() Contenido
        +extraerTextoOCR() String
        +transcribirAudio() String
    }

    class AdaptadorApiIaInterface {
        +analizarNaturaleza(String texto) String
        +detectarSesgoLenguaje(String texto) float
        +verificarCoherencia(String texto) array
        +interpretarPrompt(String consulta) String
    }

    %% Relaciones de Dominio
    Usuario "1" -- "0..*" Historial : posee
    Usuario "1" -- "0..*" Contenido : envia
    Usuario "1" -- "0..*" Nota : redacta
    Fuente "1" -- "0..*" Documento : publica
    Contenido "1" -- "0..*" ProcesoVerificacion : analizado_en
    ProcesoVerificacion "1" -- "0..*" LogComunicadorApi : genera
    Contenido "1" -- "1" Veredicto : evaluado_en
    Contenido "1" -- "0..*" Nota : anotado_con
    Historial "0..*" -- "1" Contenido : referencia
    Veredicto "1" -- "1..*" VeredictoFuente : sustentado_por
    Fuente "1" -- "0..*" VeredictoFuente : citada_en

    VistasBladeYMovil ..> VerificacionController : consume REST v1
    VerificacionController ..> MultiModeloHarnessService : usa consejo
    VerificacionController ..> AdaptadorMulticanal : usa
    VerificacionController ..> AdaptadorApiIaInterface : usa
```

---

## 3. Diagramas de Secuencia Detallados (Caja Gris)

### 3.1 CU06: Verificar Contenidos (Flujo Principal con Consejo Multi-Modelo)
```mermaid
sequenceDiagram
    autonumber
    actor Adulto
    participant Cliente as VRevisionContenido / App Móvil
    participant Ctrl as VerificacionController
    participant Serv as VerificacionService
    participant Multi as AdaptadorMulticanal / Python
    participant Sem as BusquedaSemanticaService (Neon pgvector)
    participant IA as AdaptadorApiIa + MultiModeloHarnessService

    Adulto->>Cliente: solicitarVerificacion(Contenido)
    Cliente->>Ctrl: POST /api/v1/verificacion
    Ctrl->>Serv: procesarContenido(Contenido)
    Serv->>Multi: normalizar / extraer OCR (:5001) o STT (:5002)
    Multi-->>Serv: texto normalizado
    Serv->>Sem: buscarEvidenciaPorTexto / buscarPorVector (normalizarSinTildes)
    Sem-->>Serv: List<Documento> y Fuentes (80 fuentes IFCN)
    Serv->>IA: verificarCoherencia + evaluarConConsejo (Http::pool)
    IA-->>Serv: veredicto calibrado (sin degradar FALSO->NO_VERIFICABLE)
    Serv-->>Ctrl: Veredicto + Citas + Explicación + Trazabilidad
    Ctrl-->>Cliente: 200 OK JSON (VERDADERO/FALSO/ENGAÑOSO/PARCIAL/NO_VERIFICABLE)
    Cliente-->>Adulto: Tarjeta de veredicto, semáforo accesible y fuentes
```

### 3.2 CU06: Flujos Alternativos de Entrada Multicanal
1. **Entrada de Imagen / Captura de Pantalla:**  
   `AdaptadorMulticanal` envía el recurso al microservicio OCR (`POST /ocr` en `:5001` con Pillow + `pytesseract`), extrae texto con preprocesamiento en escala de grises y continúa al pipeline semántico.
2. **Entrada de Audio / Nota de Voz:**  
   `AdaptadorMulticanal` envía el audio al microservicio STT (`POST /transcribe` en `:5002` con `SpeechRecognition`), obtiene la transcripción y la contrasta con el grafo.
3. **Contenido sin Evidencia Coincidente en BD:**  
   `BusquedaSemanticaService` y `VerificacionService` activan el fallback pedagógico (`REQ-30`), clasificando como `NO_VERIFICABLE` e informando con lenguaje claro que ningún organismo oficial o medio del grafo se ha pronunciado aún.

### 3.3 CU01: Asignar Notas Complementarias
```mermaid
sequenceDiagram
    autonumber
    actor Adulto
    participant Cliente
    participant Ctrl as VerificacionController
    participant BD as PostgreSQL / Eloquent

    Adulto->>Cliente: seleccionar contenido y redactar nota
    Cliente->>Ctrl: POST /api/v1/contenidos/{idContenido}/notas (Bearer Sanctum)
    Ctrl->>Ctrl: analizarNota() [anti-XSS/SQLi + longitud]
    Ctrl->>BD: Nota::create(...)
    BD-->>Ctrl: Nota persistida
    Ctrl-->>Cliente: 201 Created
    Cliente-->>Adulto: Mostrar nota publicada en comunidad
```

### 3.4 CU02: Calificar Notas Complementarias
```mermaid
sequenceDiagram
    autonumber
    actor Adulto
    participant Cliente
    participant Ctrl as VerificacionController
    participant BD as PostgreSQL / Eloquent

    Adulto->>Cliente: votar utilidad de nota
    Cliente->>Ctrl: PATCH /api/v1/notas/{idNota}/calificar
    Ctrl->>Ctrl: validarCalificacion() (0.0 a 10.0)
    Ctrl->>BD: actualizar promedio/votos de Nota
    BD-->>Ctrl: Nota actualizada
    Ctrl-->>Cliente: 200 OK
    Cliente-->>Adulto: Actualizar barra porcentual de utilidad
```

### 3.5 CU03: Guardar Contenidos & CU04: Historial
```mermaid
sequenceDiagram
    autonumber
    actor Adulto
    participant Cliente
    participant Ctrl as VerificacionController
    participant BD as PostgreSQL / Eloquent

    Adulto->>Cliente: guardar noticia verificada (CU03)
    Cliente->>Ctrl: POST /api/v1/historial/{idContenido}
    Ctrl->>BD: Historial::firstOrCreate(...)
    BD-->>Ctrl: registro historial
    Ctrl-->>Cliente: 201 Created
    Adulto->>Cliente: consultar historial (CU04)
    Cliente->>Ctrl: GET /api/v1/historial
    Ctrl->>BD: consultar registros paginados + Contenido + Veredicto
    BD-->>Ctrl: lista paginada
    Ctrl-->>Cliente: 200 OK
```

### 3.6 CU05: Inicio de Sesión & Ley 1581
```mermaid
sequenceDiagram
    autonumber
    actor Adulto
    participant Cliente
    participant Auth as AuthController
    participant BD as PostgreSQL / Sanctum UUID

    Adulto->>Cliente: enviar credenciales (email, password)
    Cliente->>Auth: POST /api/v1/auth/login
    Auth->>BD: consultar Usuario y verificar password_hash (Bcrypt)
    Auth->>BD: crear token UUID en personal_access_tokens
    Auth-->>Cliente: 200 OK (token Bearer + perfil + rol)
    Cliente-->>Adulto: Acceso concedido a funciones autenticadas y Hábeas Data
```

### 3.7 CU07: Asignar Confiabilidad a las Fuentes
```mermaid
sequenceDiagram
    autonumber
    actor Usuario as Adulto / Admin
    participant Cliente
    participant Ctrl as VerificacionController
    participant BD as PostgreSQL / Grafo 80 Fuentes

    Usuario->>Cliente: consultar catálogo de fuentes IFCN
    Cliente->>Ctrl: GET /api/v1/fuentes
    Ctrl->>BD: consultar 80 fuentes ordenadas por nivel y credibilidad
    Ctrl-->>Cliente: 200 OK listado filtrable
    Usuario->>Cliente: valorar o reclasificar fuente
    Cliente->>Ctrl: PATCH /api/v1/fuentes/{idFuente}/valorar (o /clasificar si admin)
    Ctrl->>BD: actualizar indice_credibilidad / tipo_fuente
    Ctrl-->>Cliente: 200 OK calificación actualizada
```
