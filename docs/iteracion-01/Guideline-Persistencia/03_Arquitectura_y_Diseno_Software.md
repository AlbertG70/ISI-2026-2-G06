# DAGAZ — Arquitectura y Diseño de Software

> **Documento de Especificación Arquitectónica, Patrones y Diagramas de Interacción (Fase 0)**  
> **Proyecto:** DAGAZ  
> **Stack Técnico:** Laravel 13 (PHP 8.4), PostgreSQL 18 + `pgvector` (compatibilidad MariaDB), Python (FastAPI / Ingesta), Blade / Tailwind CSS v4, Capacitor 6 & Expo SDK 58  
> **Ubicación de DCD:** [`docs/iteracion-01/Diagramas/Clases de Diseno/Diagrama_Clases_Diseno.md`](../Diagramas/Clases%20de%20Diseno/Diagrama_Clases_Diseno.md)  
> **Ubicación de DSS (G):** [`docs/iteracion-01/Diagramas/DSS (Caja Gris)/`](../Diagramas/DSS%20%28Caja%20Gris%29/)  
> **Nota Evolutiva:** La especificación original de Fase 0 modelada con MariaDB evolucionó hacia PostgreSQL 18 + `pgvector`, `MultiModeloHarnessService` y clientes móviles duales; véase la versión actualizada en [`docs/iteracion-02/Guideline-Persistencia/03_Arquitectura_y_Diseno_Software.md`](../../iteracion-02/Guideline-Persistencia/03_Arquitectura_y_Diseno_Software.md).

---

## 1. Visión General de la Arquitectura

El sistema DAGAZ está concebido bajo el principio de **Backend-First**: el núcleo de verificación, reglas de negocio, persistencia y adaptadores externos debe estar 100% operativo y probado antes de acoplar las interfaces de usuario.

```text
┌───────────────────────────────────────────────────────────┐
│                 CAPA DE PRESENTACIÓN (UI)                 │
│      Laravel Blade Views (100% Responsivo Móvil)          │
│   (VRevisionContenido, VSesion, VFuentes, VHistorial)     │
└─────────────────────────────┬─────────────────────────────┘
                              │ HTTP / JSON API
┌─────────────────────────────▼─────────────────────────────┐
│                 CAPA DE APLICACIÓN / CONTROL              │
│                  ControladorVerificacion                  │
│   (Orquestación, Evaluación de Reglas, Ponderación)       │
└───────────────┬───────────────────────────┬───────────────┘
                │                           │
┌───────────────▼─────────────┐   ┌─────────▼───────────────┐
│    CAPA DE INFRAESTRUCTURA  │   │  SERVICIOS EXTERNOS / IA│
│       BDVerificacion        │   │   AdaptadorAPI_IA       │
│  (Eloquent ORM / PG-MariaDB)│   │  (Patrón Strategy/LLM)  │
│                             │   │   AdaptadorMulticanal   │
│                             │   │  (OCR, TTS/STT, APIs)   │
└─────────────────────────────┘   └─────────────────────────┘
```

### 1.1 Principios Arquitectónicos
1. **Agnosticismo de Modelos de Lenguaje (Patrón Strategy / Adapter):**  
   El consumo de Inteligencia Artificial para análisis de sesgo, descomposición de afirmaciones y verificación de coherencia semántica no se ata a un proveedor específico. Se define un contrato (`AdaptadorAPI_IA`) que permite conmutar dinámicamente entre OpenAI, OpenRouter, Google Gemini, Anthropic o modelos abiertos locales, integrando presupuestos de API y *circuit breaker*.
2. **Abstracción Multicanal de Entrada:**  
   Todo medio de entrada (texto directo, imagen con texto, nota de voz, URL de YouTube/TikTok/X) se normaliza a través del `AdaptadorMulticanal` hacia una estructura canónica `Contenido`.
3. **Ponderación Jerárquica de Fuentes (Ámbito Colombia e Internacional):**  
   El motor de veredictos no asigna igual peso a cualquier resultado de búsqueda. Pondera según el nivel de jerarquía:
   * **Nivel 1:** Agencias internacionales y organismos multilaterales (Reuters, AP, AFP, BBC, OMS, ONU, OCDE, EFE).
   * **Nivel 2:** Prensa regional colombiana validada.
   * **Nivel 3:** Prensa nacional de Colombia.
   * **Nivel 4:** Fact-checkers certificados por la IFCN (*International Fact-Checking Network*) como ColombiaCheck, AFP Factual, Chequeado.

---

## 2. Diagrama de Clases de Diseño (DCD)

El modelo de clases formaliza las responsabilidades y dependencias entre las entidades del dominio, las interfaces de usuario, el controlador de aplicación y los adaptadores de infraestructura.

```mermaid
classDiagram
    direction TB

    %% Modelos de Dominio
    class Usuario {
        +String idUser
        +String email
        +String passwordHash
        +String rol
        +validarSesion() boolean
        +esAdministrador() boolean
    }

    class Contenido {
        +String idContenido
        +String canalOrigen
        +String cuerpoTexto
        +String urlReferencia
        +DateTime fechaRecepcion
        +getCuerpoTexto() String
    }

    class Veredicto {
        +String idVeredicto
        +float nivelConfiabilidad
        +String clasificacion
        +String justificacion
        +DateTime fechaEmision
        +esConfiable() boolean
    }

    class Fuente {
        +String idFuente
        +String nombre
        +String urlFuente
        +float credibilidad
        +String clasificacion
        +getClasificacion() String
        +setClasificacion(String c) void
        +getCredibilidad() float
    }

    class Nota {
        +String idNota
        +String comentario
        +int calificacion
        +getCalificacion() int
        +setCalificacion(int c) void
    }

    class RegistroHistorial {
        +String idRegistro
        +DateTime fechaHora
        +getResumen() String
    }

    %% Capa de Control
    class ControladorVerificacion {
        +int timeoutSegundos
        +validarCredenciales(String email, String passHash) boolean
        +crearUsuario(String email, String passHash, String rol) boolean
        +procesarContenido(Contenido c) Veredicto
        +contrastarFuentes(String cuerpo) List
        +procesarNota(String comentario, String idContenido) boolean
        +analizarNota(String comentario) boolean
        +procesarCalificacion(String idNota, int calificacion) boolean
        +validarCalificacion(int calificacion) boolean
        +obtenerFuentes(String idUser) List
        +verificarRol(String rol) String
        +procesarValoracion(String idFuente, float calificacion) boolean
        +actualizarClasificacion(String idFuente, String nuevaClasif) boolean
        +consultarHistorialUsuario(String idUser) List
        +obtenerContenido(String idContenido) Contenido
    }

    %% Capa de Vistas / Boundary
    class VSesion {
        +boolean estadoSesion
        +iniciarApp() void
        +identificarse(String email, String pass) void
        +registrarCredenciales(String email, String pass, String rol) void
    }

    class VRevisionContenido {
        +String idSesion
        +solicitarVerificacion(Contenido c) void
        +mostrarVeredicto(Veredicto v) void
        +agregarNota(String comentario) void
    }

    class VFuentes {
        +String filtroActivo
        +solicitarListaFuentes() void
        +valorarFuente(String idFuente, float calif) void
        +cambiarClasificacion(String idFuente, String clasif) void
    }

    class VHistorial {
        +String idUsuario
        +solicitarHistorial(String idUser) void
        +mostrarHistorial(List items) void
        +seleccionarNoticia(String idContenido) void
    }

    %% Capa de Adaptadores e Infraestructura
    class BDVerificacion {
        +String cadenaConexion
        +consultarUsuario(String email) Usuario
        +guardarUsuario(Usuario u) boolean
        +consultarContenido(String idContenido) Contenido
        +guardarContenido(Contenido c) boolean
        +guardarVeredicto(Veredicto v) boolean
        +consultarNotasPorContenido(String idContenido) List
        +consultarCalificacion(String idNota) Nota
        +guardarNota(Nota n) boolean
        +consultarFuentesRelevantes(String tema) List
        +guardarFuente(Fuente f) boolean
        +guardarRegistroHistorial(RegistroHistorial r) boolean
        +consultarHistorialUsuario(String idUser) List
    }

    class AdaptadorMulticanal {
        +List canalesHabilitados
        +procesarEntrada(String tipo, byte[] datos) Contenido
        +extraerTextoOCR(byte[] img) String
        +transcribirAudio(byte[] audio) String
        +sintetizarVozTTS(String texto) byte[]
        +escucharRedSocial(String url) Contenido
    }

    class AdaptadorAPI_IA {
        +String endpointAPI
        +String apiKey
        +analizarNaturaleza(String texto) String
        +detectarSesgoLenguaje(String texto) float
        +verificarCoherencia(String texto) boolean
        +interpretarPrompt(String consulta) String
    }

    %% Relaciones de Dominio
    Usuario "1" -- "0..*" RegistroHistorial : posee
    Usuario "1" -- "0..*" Contenido : envia
    Usuario "1" -- "0..*" Nota : redacta
    Contenido "1" -- "1" Veredicto : evaluado_en
    Contenido "1" -- "0..*" Nota : anotado_con
    RegistroHistorial "0..*" -- "1" Contenido : referencia
    Veredicto "1" -- "1..*" Fuente : sustenta_con

    %% Relaciones de Uso (Vistas hacia Controlador)
    VSesion ..> ControladorVerificacion : use
    VRevisionContenido ..> ControladorVerificacion : use
    VFuentes ..> ControladorVerificacion : use
    VHistorial ..> ControladorVerificacion : use

    %% Relaciones de Uso (Controlador hacia Adaptadores)
    ControladorVerificacion ..> BDVerificacion : use
    ControladorVerificacion ..> AdaptadorMulticanal : use
    ControladorVerificacion ..> AdaptadorAPI_IA : use
```

---

## 3. Diagramas de Secuencia Detallados (Caja Gris)

### 3.1 CU06: Verificar Contenidos (Flujo Principal)
```mermaid
sequenceDiagram
    autonumber
    actor Adulto
    participant VRevisionContenido
    participant ControladorVerificacion
    participant AdaptadorMulticanal
    participant BDVerificacion
    participant AdaptadorAPI_IA

    Adulto->>VRevisionContenido: solicitarVerificacion(Contenido)
    VRevisionContenido->>ControladorVerificacion: procesarContenido(Contenido)
    ControladorVerificacion->>AdaptadorMulticanal: procesarEntrada("texto", datos)
    AdaptadorMulticanal-->>ControladorVerificacion: Contenido(cuerpoTexto)
    ControladorVerificacion->>BDVerificacion: consultarFuentesRelevantes(cuerpoTexto)
    BDVerificacion-->>ControladorVerificacion: List<Fuente>(nombre, urlFuente, credibilidad)
    ControladorVerificacion->>ControladorVerificacion: contrastarFuentes(cuerpoTexto)
    ControladorVerificacion->>AdaptadorAPI_IA: verificarCoherencia(cuerpoTexto)
    AdaptadorAPI_IA-->>ControladorVerificacion: coherencia validada (score, justificación)
    ControladorVerificacion->>ControladorVerificacion: calcularConfiabilidad(List<Fuente>)
    ControladorVerificacion->>ControladorVerificacion: emitirVeredicto(Contenido, List<Fuente>)
    ControladorVerificacion-->>VRevisionContenido: mostrarVeredicto(Veredicto)
    VRevisionContenido-->>Adulto: mostrar veredicto y lista de fuentes utilizadas
```

### 3.2 CU06: Flujos Alternativos de Entrada Multicanal
1. **Entrada de Video (FA1):**  
   `ControladorVerificacion` invoca `AdaptadorMulticanal.extraerTextoOCR(frames)` y `AdaptadorMulticanal.transcribirAudio(audioStream)`. Los textos combinados se envían como `cuerpoTexto` al flujo principal.
2. **Entrada de Comentario / Red Social (FA2):**  
   `AdaptadorMulticanal.escucharRedSocial(url)` extrae el texto del tweet/post, metadatos del autor y fecha, enviándolos normalizados a verificación.
3. **Entrada de Imagen / Captura de Pantalla (FA3):**  
   `AdaptadorMulticanal.extraerTextoOCR(byte[] img)` procesa la imagen con visión artificial para aislar las frases a contrastar.
4. **Contenido sin Evidencia en Fuentes (FA5):**  
   Si `BDVerificacion.consultarFuentesRelevantes` retorna un conjunto vacío o inferior al umbral, el controlador califica como `"No verificado"`, descompone la afirmación en términos clave y sugiere fuentes institucionales recomendadas.

### 3.3 CU01: Asignar Notas Complementarias
```mermaid
sequenceDiagram
    autonumber
    actor Adulto
    participant VRevisionContenido
    participant ControladorVerificacion
    participant BDVerificacion

    Adulto->>VRevisionContenido: ingresarApp()
    VRevisionContenido->>ControladorVerificacion: validarSesion(idSesion)
    ControladorVerificacion-->>VRevisionContenido: sesión válida
    VRevisionContenido-->>Adulto: mostrar pantalla principal
    Adulto->>VRevisionContenido: seleccionarNoticia(idContenido)
    VRevisionContenido->>ControladorVerificacion: obtenerContenido(idContenido)
    ControladorVerificacion->>BDVerificacion: consultarContenido(idContenido)
    BDVerificacion-->>ControladorVerificacion: Contenido(cuerpoTexto, urlReferencia)
    ControladorVerificacion-->>VRevisionContenido: contenido recuperado
    VRevisionContenido-->>Adulto: mostrar noticia
    Adulto->>VRevisionContenido: agregarNota(comentario)
    VRevisionContenido->>ControladorVerificacion: procesarNota(comentario, idContenido)
    ControladorVerificacion->>ControladorVerificacion: analizarNota(comentario)
    ControladorVerificacion->>BDVerificacion: guardarNota(Nota{comentario, idContenido})
    BDVerificacion-->>ControladorVerificacion: true (nota guardada)
    ControladorVerificacion-->>VRevisionContenido: notaRegistrada()
    VRevisionContenido-->>Adulto: mostrar confirmación "Nota publicada exitosamente"
```

### 3.4 CU02: Calificar Notas Complementarias
```mermaid
sequenceDiagram
    autonumber
    actor Adulto
    participant VRevisionContenido
    participant ControladorVerificacion
    participant BDVerificacion

    Adulto->>VRevisionContenido: calificarNota(idNota, calificacion)
    VRevisionContenido->>ControladorVerificacion: procesarCalificacion(idNota, calificacion)
    ControladorVerificacion->>ControladorVerificacion: validarCalificacion(calificacion)
    ControladorVerificacion->>BDVerificacion: guardarNota(Nota{idNota, calificacion})
    BDVerificacion-->>ControladorVerificacion: true (calificación guardada)
    ControladorVerificacion->>BDVerificacion: consultarCalificacion(idNota)
    BDVerificacion-->>ControladorVerificacion: Nota{calificacion actualizada}
    ControladorVerificacion-->>VRevisionContenido: calificacionRegistrada(Nota)
    VRevisionContenido-->>Adulto: mostrar calificacion actualizada de la nota
```

### 3.5 CU03: Guardar Contenidos
```mermaid
sequenceDiagram
    autonumber
    actor Adulto
    participant VRevisionContenido
    participant ControladorVerificacion
    participant BDVerificacion

    Adulto->>VRevisionContenido: pedirGuardarNoticia(idContenido)
    VRevisionContenido->>ControladorVerificacion: coordinarGuardado(Contenido, Veredicto, Usuario)
    ControladorVerificacion->>BDVerificacion: guardarContenido(Contenido)
    BDVerificacion-->>ControladorVerificacion: true
    ControladorVerificacion->>BDVerificacion: guardarVeredicto(Veredicto)
    BDVerificacion-->>ControladorVerificacion: true
    ControladorVerificacion->>BDVerificacion: guardarRegistroHistorial(RegistroHistorial)
    BDVerificacion-->>ControladorVerificacion: true
    ControladorVerificacion-->>VRevisionContenido: guardadoExitoso()
    VRevisionContenido-->>Adulto: mostrar confirmación "Noticia guardada exitosamente"
```

### 3.6 CU04: Mostrar Historial de Contenidos
```mermaid
sequenceDiagram
    autonumber
    actor Adulto
    participant VHistorial
    participant ControladorVerificacion
    participant BDVerificacion

    Adulto->>VHistorial: solicitarHistorial(idUsuario)
    VHistorial->>ControladorVerificacion: consultarHistorialUsuario(idUsuario)
    ControladorVerificacion->>BDVerificacion: consultarHistorialUsuario(idUsuario)
    BDVerificacion-->>ControladorVerificacion: List<RegistroHistorial>(idRegistro, fechaHora, getResumen())
    ControladorVerificacion-->>VHistorial: historial recuperado
    VHistorial->>VHistorial: mostrarHistorial()
    VHistorial-->>Adulto: mostrar lista de noticias guardadas con resumen y fecha
    Adulto->>VHistorial: seleccionarNoticia(idContenido)
    VHistorial->>ControladorVerificacion: obtenerContenido(idContenido)
    ControladorVerificacion->>BDVerificacion: consultarContenido(idContenido)
    BDVerificacion-->>ControladorVerificacion: Contenido(cuerpoTexto, urlReferencia, fechaRegistro)
    ControladorVerificacion-->>VHistorial: contenido recuperado
    VHistorial-->>Adulto: mostrar detalle de noticia
```

### 3.7 CU05: Inicio de Sesión
```mermaid
sequenceDiagram
    autonumber
    actor Adulto
    participant VRevisionContenido
    participant ControladorVerificacion
    participant BDVerificacion
    participant Usuario

    Adulto->>VRevisionContenido: iniciarApp()
    VRevisionContenido-->>Adulto: mostrar interfaz de credenciales
    Adulto->>VRevisionContenido: identificarse(email, password)
    VRevisionContenido->>ControladorVerificacion: validarCredenciales(email, passwordHash)
    ControladorVerificacion->>BDVerificacion: consultarUsuario(email)
    BDVerificacion-->>ControladorVerificacion: Usuario(idUser, email, passwordHash, rol)
    ControladorVerificacion->>Usuario: validarSesion()
    Usuario-->>ControladorVerificacion: true
    ControladorVerificacion-->>VRevisionContenido: sesionIniciada(idSesion)
    VRevisionContenido-->>Adulto: acceso concedido — mostrar pantalla principal
```

### 3.8 CU07: Asignar Confiabilidad a las Fuentes
```mermaid
sequenceDiagram
    autonumber
    actor Adulto
    participant VRevisionContenido
    participant ControladorVerificacion
    participant BDVerificacion

    Adulto->>VRevisionContenido: solicitarListaFuentes()
    VRevisionContenido->>ControladorVerificacion: obtenerFuentes(idUsuario)
    ControladorVerificacion->>ControladorVerificacion: verificarRol(Usuario.rol) -> "adulto"
    ControladorVerificacion->>BDVerificacion: consultarFuentesRelevantes("todas")
    BDVerificacion-->>ControladorVerificacion: List<Fuente>(nombre, urlFuente, credibilidad)
    ControladorVerificacion-->>VRevisionContenido: lista de fuentes recuperada
    VRevisionContenido-->>Adulto: mostrar lista de fuentes con calificación actual
    Adulto->>VRevisionContenido: seleccionarFuente(idFuente)
    VRevisionContenido-->>Adulto: mostrar detalle de la fuente
    Adulto->>VRevisionContenido: valorarFuente(idFuente, calificacion)
    VRevisionContenido->>ControladorVerificacion: procesarValoracion(idFuente, calificacion)
    ControladorVerificacion->>ControladorVerificacion: calcularConfiabilidad(calificacion)
    ControladorVerificacion->>BDVerificacion: guardarFuente(Fuente{credibilidad actualizada})
    BDVerificacion-->>ControladorVerificacion: true
    ControladorVerificacion-->>VRevisionContenido: valoracionGuardada()
    VRevisionContenido-->>Adulto: mostrar calificación actualizada de la fuente
```
