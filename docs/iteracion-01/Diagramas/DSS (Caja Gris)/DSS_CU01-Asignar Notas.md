# Diagrama de Secuencia del Sistema
## CU 5.3.2.1 — Asignar Notas Complementarias

---

```mermaid
sequenceDiagram
    autonumber

    actor Adulto Mayor

    box rgb(180,180,180) Sistema — Aplicación Móvil
        participant VRC as VRevisionContenido
        participant CV as ControladorVerificacion
        participant BD as BDVerificacion
    end

    %% ── FLUJO BÁSICO ──────────────────────────────────────────
    Note over Adulto Mayor,BD: FLUJO BÁSICO

    Adulto Mayor->>VRC: ingresarApp()
    VRC->>CV: validarSesion(idSesion)
    CV-->>VRC: sesión válida
    VRC-->>Adulto Mayor: mostrar pantalla principal

    Adulto Mayor->>VRC: seleccionarNoticia(idContenido)
    VRC->>CV: obtenerContenido(idContenido)
    CV->>BD: consultarContenido(idContenido)
    BD-->>CV: Contenido (cuerpoTexto, urlReferencia)
    CV-->>VRC: contenido recuperado
    VRC-->>Adulto Mayor: mostrar noticia

    Adulto Mayor->>VRC: agregarNota(comentario)
    VRC->>CV: procesarNota(comentario, idContenido)

    CV->>CV: analizarNota(comentario)

    alt Nota válida (texto no vacío y sin caracteres inválidos)
        CV->>BD: guardarNota(Nota{comentario, idContenido})
        BD-->>CV: true (nota guardada)
        CV-->>VRC: notaRegistrada()
        VRC-->>Adulto Mayor: mostrar confirmación\n"Nota publicada exitosamente"

    %% ── EXTENSIÓN A: nota en blanco ──────────────────────────
    else Extensión A — Nota en blanco
        Note over CV,VRC: El sistema detecta que no hay\nsuficiente información
        CV-->>VRC: mostrarError("La nota no puede estar vacía")
        VRC-->>Adulto Mayor: mostrar mensaje de error\ny solicitar información válida

    %% ── EXTENSIÓN B: caracteres inválidos ────────────────────
    else Extensión B — Caracteres inválidos
        Note over CV,VRC: El sistema detecta caracteres\ninválidos en la nota
        CV-->>VRC: mostrarError("Caracteres inválidos en la nota")
        VRC-->>Adulto Mayor: mostrar mensaje de error\ny solicitar caracteres válidos
    end
```

---

### Leyenda de participantes

| Participante | Clase del diagrama de clases | Rol |
|---|---|---|
| **Adulto Mayor** | Actor externo (`Usuario` con rol Adulto Mayor) | Inicia el caso de uso |
| **VRevisionContenido** | `VRevisionContenido` | Vista / interfaz de la app móvil |
| **ControladorVerificacion** | `ControladorVerificacion` | Lógica de negocio y validación |
| **BDVerificacion** | `BDVerificacion` | Persistencia — `guardarNota(Nota n): boolean` |

### Objetos creados durante la ejecución

| Clase | Atributos relevantes usados |
|---|---|
| `Nota` | `idNota`, `comentario`, `calificacion` |
| `Contenido` | `idContenido`, `cuerpoTexto`, `urlReferencia` |

### Notas de diseño
- El **sistema** (caja gris) encapsula las tres capas: Vista → Controlador → Base de datos.
- Las extensiones **A** y **B** se manejan íntegramente dentro del sistema; el Adulto Mayor solo recibe el mensaje de error y la solicitud de corrección.
- Tras el flujo básico exitoso, la nota queda disponible para que otros Adulto Mayors la califiquen (`Nota.getCalificacion()`).
