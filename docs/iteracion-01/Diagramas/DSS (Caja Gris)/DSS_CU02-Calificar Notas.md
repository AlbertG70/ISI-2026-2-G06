# Diagrama de Secuencia del Sistema
## CU 5.3.2.2 — Calificar Notas Complementarias

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

    Adulto Mayor->>VRC: seleccionarNoticia(idContenido)
    VRC->>CV: obtenerContenido(idContenido)
    CV->>BD: consultarContenido(idContenido)
    BD-->>CV: Contenido (cuerpoTexto, urlReferencia)
    CV->>BD: consultarNotasPorContenido(idContenido)
    BD-->>CV: List<Nota> (comentario, calificacion)
    CV-->>VRC: contenido y notas recuperados
    VRC-->>Adulto Mayor: mostrar noticia y notas de otros Adultos

    Note over Adulto Mayor: El Adulto Mayor lee las notas\ny decide si son útiles

    Adulto Mayor->>VRC: calificarNota(idNota, calificacion)
    VRC->>CV: procesarCalificacion(idNota, calificacion)

    CV->>CV: validarCalificacion(calificacion)

    alt Calificación válida
        CV->>BD: guardarNota(Nota{idNota, calificacion})
        BD-->>CV: true (calificación guardada)
        CV->>BD: consultarCalificacion(idNota)
        BD-->>CV: Nota{calificacion actualizada}
        CV-->>VRC: calificacionRegistrada(Nota)
        VRC-->>Adulto Mayor: mostrar calificación actualizada\nde la nota

    else Calificación fuera de rango / inválida
        Note over CV,VRC: El sistema detecta un valor\nde calificación no permitido
        CV-->>VRC: mostrarError("Calificación no válida")
        VRC-->>Adulto Mayor: mostrar mensaje de error\ny solicitar calificación válida
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

### Objetos creados/consultados durante la ejecución

| Clase | Atributos relevantes usados |
|---|---|
| `Nota` | `idNota`, `calificacion`, `comentario`, `getCalificacion()` |
| `Contenido` | `idContenido`, `cuerpoTexto`, `urlReferencia` |

### Notas de diseño
- El **sistema** (caja gris) encapsula las tres capas: Vista → Controlador → Base de datos.
- Las notas listadas corresponden a objetos `Nota` vinculados al `Contenido` seleccionado (relación `califica` del diagrama de clases).
- Tras guardar, el sistema consulta la calificación actualizada para reflejarla inmediatamente en la UI (`VRC` muestra el resultado al Adulto Mayor).
- La extensión de **calificación inválida** cubre el tema abierto sobre el sistema de calificación: cualquier valor fuera del rango definido es rechazado por `ControladorVerificacion` antes de persistir.
