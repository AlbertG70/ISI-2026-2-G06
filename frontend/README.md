# DAGAZ — Frontend y Capa de Presentación

> **Ingeniería de Servicios de Internet — 2026-2 — Grupo 06**  
> **Plataforma:** DAGAZ — Fact-Checking Ciudadano y Verificación Multicanal  
> **Tecnologías:** Laravel Blade (SSR) · Tailwind CSS v4 · JavaScript ES Modules · Vite 8 · WAI-ARIA (WCAG 2.1 AA)

---

## 1. Visión General de la Interfaz

La capa de presentación de **DAGAZ** está diseñada bajo el principio **Mobile-First Responsivo y Accesible**, orientada principalmente a **Adultos (+40 años)** y ciudadanos propensos a recibir cadenas desinformativas en redes sociales y aplicaciones de mensajería (WhatsApp, Telegram, Facebook).

La interfaz replica la ergonomía de los mockups de baja fidelidad ([`docs/iteracion-01/Mockups/`](../docs/iteracion-01/Mockups/)) y los **10 Mockups Interactivos de Alta Fidelidad** ([`docs/mockups-alta-fidelidad/`](../docs/mockups-alta-fidelidad/)), consumiendo la API REST del backend (`/api/v1`) de forma asíncrona mediante tokens Bearer de **Laravel Sanctum**. Además, su compilado en `frontend/dist` alimenta directamente el contenedor móvil híbrido Android/iOS en [`mobile/`](../mobile/).

---

## 2. Arquitectura de Archivos y Componentes

Todo el código fuente de la capa de presentación y vistas Blade reside en **`frontend/`**:

```text
frontend/
├── package.json                     # Scripts y dependencias Vite 8 + Tailwind CSS v4 + Vitest 5.0
├── vite.config.js                   # Configuración de empaquetado para frontend/dist
├── public/
│   └── manifest.webmanifest         # Manifiesto PWA para acceso móvil directo en pantalla de inicio
├── src/
│   ├── css/
│   │   └── app.css                  # Sistema de diseño Tailwind v4: variables de color semafórico,
│   │                                # escala fluida base 18px y tap-targets >= 48px.
│   └── js/
│       ├── app.js                   # Punto de entrada de Vite con cargador dinámico de páginas.
│       └── dagaz/
│           ├── api.js               # Cliente fetch para /api/v1 con Bearer tokens y errores 422.
│           ├── sesion.js            # Gestión de almacenamiento seguro (sessionStorage/localStorage).
│           ├── ui.js                # Utilidades DOM seguras (textContent), medidor SVG y badges.
│           └── paginas/
│               ├── login.js         # Lógica de inicio de sesión (CU05).
│               ├── registro.js      # Lógica de registro con autorización Ley 1581 (CU05).
│               ├── verificar.js     # Entrada multicanal y barra de progreso (CU06).
│               ├── resultado.js     # Veredicto, fuentes, notas, consejo IA y guardado (CU06, CU01–CU03).
│               ├── historial.js     # Consulta y filtrado de noticias guardadas (CU04).
│               ├── fuentes.js       # Catálogo del Grafo de Credibilidad y valoración (CU07).
│               └── perfil.js        # Derechos ARCO y derecho al olvido (Ley 1581 de 2012).
├── tests/                           # 8 archivos de pruebas automatizadas (23 tests Vitest + Happy-DOM)
└── views/                           # Vistas Blade del frontend (cargadas vía backend/config/view.php)
    ├── layouts/
    │   ├── app.blade.php            # Layout principal móvil con navegación táctil fija y región de avisos.
    │   └── auth.blade.php           # Layout centrado para pantallas de acceso.
    ├── components/
    │   ├── logo.blade.php           # Imagotipo SVG de la runa Dagaz (ᛞ).
    │   └── barra-navegacion.blade.php # Barra inferior fija con iconos táctiles de 48px.
    ├── partials/
    │   └── head.blade.php           # Meta-tags de viewport fluido (viewport-fit=cover) y assets.
    ├── auth/
    │   ├── login.blade.php          # Vista de inicio de sesión (CU05).
    │   └── register.blade.php       # Vista de creación de cuenta (CU05).
    ├── verificacion/
    │   ├── index.blade.php          # Vista de entrada multicanal y progreso (CU06 Fase 1).
    │   └── resultado.blade.php      # Vista de veredicto, fuentes, consejo multi-modelo y notas (CU06 Fase 2).
    ├── historial/
    │   └── index.blade.php          # Vista de historial cronológico (CU04).
    ├── fuentes/
    │   └── index.blade.php          # Vista de catálogo de fuentes (CU07).
    └── perfil/
        └── index.blade.php          # Vista de perfil y garantías Ley 1581 de 2012.
```

---

## 3. Matriz de Casos de Uso vs. Vistas y Mockups

| Caso de Uso | Mockup de Referencia (Baja / Alta Fidelidad) | Vista Blade (`frontend/views/`) | Ruta Web |
| :--- | :--- | :--- | :--- |
| **CU05: Inicio de Sesión** | `CU05_Mockup[_Alta_Fidelidad]_Inicio_Sesion.html` | `auth/login.blade.php` | `/login` |
| **CU05: Registro de Credenciales** | `CU05_Mockup[_Alta_Fidelidad]_Inicio_Sesion.html` | `auth/register.blade.php` | `/registro` |
| **CU06: Entrada de Verificación** | `CU06_Mockup[_Alta_Fidelidad]_Verificar_Entrada.html` | `verificacion/index.blade.php` | `/` · `/verificar` |
| **CU06: Resultado de Veredicto** | `CU06_Mockup[_Alta_Fidelidad]_Verificar_Resultado.html` | `verificacion/resultado.blade.php` | `/verificar/{id}` |
| **CU03: Guardar Contenidos** | `CU03_Mockup[_Alta_Fidelidad]_Guardar_Contenido.html` | `verificacion/resultado.blade.php` | Botón en `/verificar/{id}` |
| **CU04: Historial de Contenidos** | `CU04_Mockup[_Alta_Fidelidad]_Historial_Noticias.html` | `historial/index.blade.php` | `/historial` |
| **CU01: Asignar Notas** | `CU01_Mockup[_Alta_Fidelidad]_Asignar_Notas.html` | `verificacion/resultado.blade.php` | Formulario en `/verificar/{id}` |
| **CU02: Calificar Notas** | `CU02_Mockup[_Alta_Fidelidad]_Calificar_Notas.html` | `verificacion/resultado.blade.php` | Votación en `/verificar/{id}` |
| **CU07: Confiabilidad de Fuentes** | `CU07_Mockup[_Alta_Fidelidad]_Confiabilidad_Fuentes.html` | `fuentes/index.blade.php` | `/fuentes` |
| **Gobernanza: Ley 1581 Hábeas Data** | N/A (Normativo Colombia) | `perfil/index.blade.php` | `/perfil` |

---

## 4. Estándares de Accesibilidad y Responsividad

1. **Tipografía Fluida y Switcher Accesible:** Escala base calculada a 18px con funciones `clamp()`, facilitando la lectura sin necesidad de zoom en dispositivos móviles. Incluye un interruptor en cabecera (`A` / `A+`) que escala la interfaz dinámicamente hasta 130%, persistido en `localStorage`.
2. **PWA Standalone (Progressive Web App):** Configurado con [`manifest.webmanifest`](public/manifest.webmanifest) y soporte para pantalla completa sin barras del navegador.
3. **Áreas Táctiles Mínimas:** Todos los botones, enlaces de la barra inferior y controles de pestañas superan los **48 × 48 px**.
4. **Contraste de Color:** Ratios de contraste superiores a **4.5:1** (estándar WCAG 2.1 AA) para todos los textos y distintivos semafóricos de veredictos (`✅ Verdadero`, `⚠️ Parcial`, `🟠 Engañoso`, `❌ Falso`, `❓ No verificable`).
5. **Notas Comunitarias (CU01 / CU02):** Sanitización de caracteres (`< > { } \ |`), barra de utilidad porcentual (`dz-barra-progreso`), filtros (*Todas, 👍 Útiles, 👎 No útiles*) y confirmación visual instantánea.
6. **Historial Interactivo (CU04):** Acordeón desplegable en línea (`👁 Ver detalle`) y remoción animada suave (`🗑 Quitar`).
7. **Protección XSS e Inserción Segura:** Todo dato proveniente del usuario o de las APIs externas se renderiza estrictamente mediante `textContent` en el DOM.

---

## 5. Instrucciones de Ejecución

### Modo A: Compilación en `frontend/`
```bash
cd frontend
npm install
npm run build
```

### Modo B: Servir la aplicación con Laravel Blade
```bash
cd backend
php artisan serve
```

La aplicación estará accesible en **`http://127.0.0.1:8000`** (sirviendo las vistas Blade ubicadas en `frontend/views/`).

---

## 6. Pruebas Automatizadas del Frontend (Vitest)

El frontend cuenta con una suite de pruebas unitarias y de componentes ejecutada sobre **Vitest 5.0** y **Happy-DOM**:

```bash
cd frontend
npm test -- --run
```

### Cobertura de la Suite Frontend (8 Suites / 23 Tests — 100% Pasando)

| Suite de Prueba | Archivo | Casos de Uso / Aspecto Verificado |
| :--- | :--- | :--- |
| **Accesibilidad** | `tests/accesibilidad.test.js` | Contraste WCAG 2.1 AA (≥4.5:1), persistencia `A/A+` y tap-targets ≥ 48px. |
| **Cliente API** | `tests/api.test.js` | Inyección de Bearer tokens, errores 422 Unprocessable Entity y sanitización. |
| **Control Editorial** | `tests/editorial.test.js` | Panel de revisión humana (REQ-13), justificaciones editoriales y veredictos. |
| **Historial** | `tests/historial.test.js` | Tarjetas cronológicas (CU04), acordeón (`👁 Ver detalle`) y eliminación animada (`🗑 Quitar`). |
| **Notas Comunitarias** | `tests/notas.test.js` | Sanitización (`< > { } \ |`), barra de utilidad y filtros (CU01, CU02). |
| **Sesión y Autenticación** | `tests/sesion.test.js` | Persistencia en `sessionStorage`/`localStorage`, roles y cierre de sesión (CU05). |
| **Utilidades UI** | `tests/ui.test.js` | Renderizado seguro vía `textContent`, mitigación XSS, medidor circular SVG y badges. |
| **Verificación Multicanal** | `tests/verificar.test.js` | Pestañas WAI-ARIA (Texto, URL, Imagen, Audio), progreso y validación de entrada (CU06). |
