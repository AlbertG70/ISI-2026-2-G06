# Diagrama de Secuencia del Sistema
## CU 5.3.2.7 — Asignar Confiabilidad a las Fuentes

---

```mermaid
sequenceDiagram
    autonumber

    actor Adulto Mayor
    actor Admin as Administrador

    box rgb(180,180,180) Sistema — Aplicación Móvil
        participant VRC as VRevisionContenido
        participant CV  as ControladorVerificacion
        participant BD  as BDVerificacion
    end

    %% ══════════════════════════════════════════════════════════
    Note over Adulto Mayor,BD: PRECONDICIÓN: El Adulto Mayor tiene sesión iniciada<br/>y el sistema muestra las fuentes registradas
    %% ══════════════════════════════════════════════════════════

    %% ── FLUJO BÁSICO ──────────────────────────────────────────
    Note over Adulto Mayor,BD: FLUJO BÁSICO — Adulto Mayor natural valora una fuente

    Adulto Mayor->>VRC: solicitarListaFuentes()
    VRC->>CV: obtenerFuentes(idUsuario)

    CV->>CV: verificarRol(Usuario.rol) → "Adulto Mayor"

    CV->>BD: consultarFuentesRelevantes("todas")
    BD-->>CV: List<Fuente> (nombre, urlFuente, credibilidad)
    CV-->>VRC: lista de fuentes recuperada
    VRC-->>Adulto Mayor: mostrar lista de fuentes\ncon calificación actual

    Adulto Mayor->>VRC: seleccionarFuente(idFuente)
    VRC-->>Adulto Mayor: mostrar detalle de la fuente

    Adulto Mayor->>VRC: valorarFuente(idFuente, calificacion)
    VRC->>CV: procesarValoracion(idFuente, calificacion)

    CV->>CV: calcularConfiabilidad(calificacion)
    CV->>BD: guardarFuente(Fuente{credibilidad actualizada})
    BD-->>CV: true

    CV-->>VRC: valoracionGuardada()
    VRC-->>Adulto Mayor: mostrar calificación actualizada\nde la fuente

    %% ── EXTENSIÓN: rol = administrador ───────────────────────
    Note over Admin,BD: EXTENSIÓN — El sistema identifica al Adulto Mayor como Administrador

    Admin->>VRC: solicitarListaFuentes()
    VRC->>CV: obtenerFuentes(idUsuario)

    CV->>CV: verificarRol(Usuario.rol) → "administrador"

    CV->>BD: consultarFuentesRelevantes("todas")
    BD-->>CV: List<Fuente> (nombre, urlFuente, credibilidad, clasificacion)
    CV-->>VRC: lista con calificaciones y clasificaciones
    VRC-->>Admin: mostrar calificación y clasificación\nde cada fuente

    Admin->>VRC: cambiarClasificacion(idFuente, nuevaClasificacion)
    VRC->>CV: actualizarClasificacion(idFuente, nuevaClasificacion)

    CV->>BD: guardarFuente(Fuente{clasificacion actualizada})
    BD-->>CV: true

    CV-->>VRC: clasificacionActualizada()
    VRC-->>Admin: mostrar clasificación actualizada\nde la fuente
```

---

### Leyenda de participantes

| Participante | Clase del diagrama de clases | Rol |
|---|---|---|
| **Adulto Mayor** | `Usuario` con `rol = "Adulto Mayor"` | Valora la credibilidad de una fuente |
| **Administrador** | `Usuario` con `rol = "administrador"` | Cambia la clasificación de una fuente |
| **VRevisionContenido** | `VRevisionContenido` | Vista — muestra lista de fuentes, calificaciones y clasificaciones |
| **ControladorVerificacion** | `ControladorVerificacion` | Orquesta la lógica de roles y actualización de fuentes |
| **BDVerificacion** | `BDVerificacion` | Persistencia — `consultarFuentesRelevantes()`, `guardarFuente()` |

### Objetos usados durante la ejecución

| Clase | Atributos relevantes |
|---|---|
| `Usuario` | `rol: String` — bifurca el flujo entre "Adulto Mayor" y "administrador" |
| `Fuente` | `idFuente`, `nombre`, `urlFuente`, `credibilidad: float`, `getCredibilidad()` |

### Notas de diseño
- El **sistema** (caja gris) encapsula tres participantes; la diferenciación de comportamiento se delega al atributo `rol` de `Usuario`, que `ControladorVerificacion` inspecciona con `verificarRol()`.
- El **flujo básico** actualiza `Fuente.credibilidad` mediante `calcularConfiabilidad()`, método disponible en `ControladorVerificacion` del diagrama de clases.
- La **extensión** de Administrador añade la capacidad de cambiar la **clasificación** de la fuente, un campo complementario a `credibilidad` que permite una categorización editorial (ej. "oficial", "satírica", "no verificada").
- Los temas abiertos (reglas para ajustar confiabilidad y forma de mostrar calificaciones) quedan encapsulados en `calcularConfiabilidad()` y en la vista de `VRC`, cuya implementación visual es una decisión pendiente.
- Ambos actores comparten el mismo punto de entrada (`solicitarListaFuentes()`); la bifurcación ocurre **dentro del sistema**, manteniendo una interfaz uniforme para el usuario.
