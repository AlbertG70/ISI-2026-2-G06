# Diagrama de Secuencia del Sistema
## CU 5.3.2.6 — Verificar Contenidos

---

```mermaid
sequenceDiagram
    autonumber

    actor Adulto Mayor

    box rgb(180,180,180) Sistema — Aplicación Móvil
        participant VRC as VRevisionContenido
        participant CV  as ControladorVerificacion
        participant AM  as AdaptadorMulticanal
        participant AI  as AdaptadorAPI_IA
        participant BD  as BDVerificacion
    end

    %% ══════════════════════════════════════════════════════════
    Note over Adulto Mayor,BD: FLUJO BÁSICO — Contenido textual
    %% ══════════════════════════════════════════════════════════

    Adulto Mayor->>VRC: solicitarVerificacion(Contenido)
    VRC->>CV: procesarContenido(Contenido)

    CV->>AM: procesarEntrada("texto", datos)
    AM-->>CV: Contenido (cuerpoTexto)

    CV->>BD: consultarFuentesRelevantes(cuerpoTexto)
    BD-->>CV: List<Fuente> (nombre, urlFuente, credibilidad)

    CV->>CV: contrastarFuentes(cuerpoTexto)
    CV->>AI: verificarCoherencia(cuerpoTexto)
    AI-->>CV: coherencia validada

    CV->>CV: calcularConfiabilidad(List<Fuente>)
    CV->>CV: emitirVeredicto(Contenido, List<Fuente>)

    CV-->>VRC: mostrarVeredicto(Veredicto)
    VRC-->>Adulto Mayor: mostrar veredicto y lista de fuentes utilizadas

    %% ══════════════════════════════════════════════════════════
    Note over Adulto Mayor,BD: EXTENSIÓN A — El contenido es un link a un video
    %% ══════════════════════════════════════════════════════════

    Adulto Mayor->>VRC: solicitarVerificacion(urlVideo)
    VRC->>CV: procesarContenido(urlVideo)

    CV->>AM: escucharRedSocial(urlVideo)
    AM->>AM: sintetizarVoris(audio) [TTS → texto]
    AM->>AM: extraerTextoOCR(imagen) [OCR → texto]
    AM->>AI: interpretarPrompt(gráficas del video)
    AI-->>AM: análisis de gráficas en texto

    AM-->>CV: Contenido (cuerpoTexto consolidado)
    Note over CV,BD: ↩ Regresa al flujo básico:<br/>contrastarFuentes → calcularConfiabilidad → emitirVeredicto

    %% ══════════════════════════════════════════════════════════
    Note over Adulto Mayor,BD: EXTENSIÓN B — El contenido es un link a un comentario
    %% ══════════════════════════════════════════════════════════

    Adulto Mayor->>VRC: solicitarVerificacion(urlComentario)
    VRC->>CV: procesarContenido(urlComentario)

    CV->>AM: escucharRedSocial(urlComentario)
    AM-->>CV: Contenido (texto del comentario)
    Note over CV,BD: ↩ Regresa al flujo básico:<br/>contrastarFuentes → calcularConfiabilidad → emitirVeredicto

    %% ══════════════════════════════════════════════════════════
    Note over Adulto Mayor,BD: EXTENSIÓN C — El contenido es una imagen
    %% ══════════════════════════════════════════════════════════

    Adulto Mayor->>VRC: solicitarVerificacion(imagen)
    VRC->>CV: procesarContenido("imagen", datos)

    CV->>AM: extraerTextoOCR(imagen) [OCR]
    AM-->>CV: Contenido (texto extraído de la imagen)
    Note over CV,BD: ↩ Regresa al flujo básico:<br/>contrastarFuentes → calcularConfiabilidad → emitirVeredicto

    %% ══════════════════════════════════════════════════════════
    Note over Adulto Mayor,BD: EXTENSIÓN D — El contenido es un audio
    %% ══════════════════════════════════════════════════════════

    Adulto Mayor->>VRC: solicitarVerificacion(audio)
    VRC->>CV: procesarContenido("audio", datos)

    CV->>AM: transcribirAudio(audio) [TTS]
    AM-->>CV: Contenido (texto transcrito del audio)
    Note over CV,BD: ↩ Regresa al flujo básico:<br/>contrastarFuentes → calcularConfiabilidad → emitirVeredicto

    %% ══════════════════════════════════════════════════════════
    Note over Adulto Mayor,BD: EXTENSIÓN E — Contenido sin respaldo de fuentes
    %% ══════════════════════════════════════════════════════════

    CV->>BD: consultarFuentesRelevantes(cuerpoTexto)
    BD-->>CV: [] (ninguna fuente encontrada)

    CV-->>VRC: mostrarError("Sin fuentes disponibles")
    VRC-->>Adulto Mayor: mostrar mensaje especial

    CV->>AI: interpretarPrompt(cuerpoTexto) [descomposición por segmentos]
    AI-->>CV: aproximación analítica en texto

    CV->>CV: emitirVeredicto("No verificado", aproximacion)
    CV-->>VRC: mostrarVeredicto(Veredicto)
    VRC-->>Adulto Mayor: veredicto "No verificado" con aproximación

    %% ══════════════════════════════════════════════════════════
    Note over Adulto Mayor,BD: EXTENSIÓN F — Contenido inválido o ilegible
    %% ══════════════════════════════════════════════════════════

    CV->>AM: procesarEntrada(tipo, datos)
    AM-->>CV: error (contenido ilegible / formato no soportado)

    CV->>AI: analizarNaturaleza(datos)
    AI-->>CV: confirmación — contenido inválido

    CV->>CV: emitirVeredicto("No verificado", null)
    CV-->>VRC: mostrarError("Contenido inválido o ilegible")
    VRC-->>Adulto Mayor: mensaje especial + veredicto "No verificado"
```

---

### Leyenda de participantes

| Participante | Clase del diagrama de clases | Rol |
|---|---|---|
| **Adulto Mayor** | Actor externo (`Usuario` con rol Adulto Mayor) | Entrega el contenido a verificar |
| **VRevisionContenido** | `VRevisionContenido` | Vista — `solicitarVerificacion()`, `mostrarVeredicto()`, `mostrarError()` |
| **ControladorVerificacion** | `ControladorVerificacion` | Orquesta todo el proceso de verificación |
| **AdaptadorMulticanal** | `AdaptadorMulticanal` | Extrae texto de video, imagen, audio y redes sociales |
| **AdaptadorAPI_IA** | `AdaptadorAPI_IA` | Análisis de coherencia, gráficas y aproximación por IA |
| **BDVerificacion** | `BDVerificacion` | Persistencia — fuentes, contenido y veredicto |

### Métodos del diagrama de clases usados por extensión

| Extensión | Participante | Método |
|---|---|---|
| Texto (básico) | `ControladorVerificacion` | `contrastarFuentes()`, `calcularConfiabilidad()`, `emitirVeredicto()` |
| Video (A) | `AdaptadorMulticanal` | `escucharRedSocial()`, `sintetizarVoris()`, `extraerTextoOCR()` |
| Video (A) | `AdaptadorAPI_IA` | `interpretarPrompt()` |
| Comentario (B) | `AdaptadorMulticanal` | `escucharRedSocial()` |
| Imagen (C) | `AdaptadorMulticanal` | `extraerTextoOCR()` |
| Audio (D) | `AdaptadorMulticanal` | `transcribirAudio()` |
| Sin fuentes (E) | `AdaptadorAPI_IA` | `interpretarPrompt()` (descomposición) |
| Inválido (F) | `AdaptadorAPI_IA` | `analizarNaturaleza()` |

### Notas de diseño
- El **sistema** (caja gris) encapsula **cinco** participantes, el más complejo de todos los diagramas de la serie.
- Las extensiones A–D **convergen al flujo básico** una vez extraído el texto, siguiendo el principio DRY: un solo pipeline de verificación independientemente del formato de entrada.
- Las extensiones E y F terminan siempre con veredicto `"No verificado"`, diferenciándose en si hay aproximación analítica (E) o no (F).
- Los temas abiertos (lista de fuentes externas, APIs usadas, reglas de análisis) quedan representados como parámetros genéricos en `consultarFuentesRelevantes()`, `interpretarPrompt()` y `escucharRedSocial()`.
