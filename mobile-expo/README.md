# DAGAZ — Aplicación Móvil Nativa iOS / Android (React Native + Expo SDK 58)

> **Ingeniería de Servicios de Internet — 2026-2 — Grupo 06**  
> **Plataforma:** DAGAZ — Fact-Checking Ciudadano y Verificación Multicanal  
> **Tecnologías:** React Native (`0.88.1`) · React (`19.3.0`) · Expo SDK 58 (`~58.0.23`) · React Navigation · WCAG 2.1 AA (Accesible Adultos +40)

---

## 1. Visión General de la App Móvil

Esta aplicación móvil adapta con **fidelidad 1:1** la arquitectura, casos de uso y ergonomía visual de la plataforma DAGAZ para dispositivos móviles (iOS y Android), especialmente optimizada para **adultos y adultos mayores (+40 años)**, consumiendo directamente la API REST v1 del backend Laravel 13 (`/api/v1/*`) con **timeout extendido de 120 segundos** para absorber la evaluación paralela del Consejo Multi-Modelo y búsqueda semántica.

> Para el contenedor híbrido Android/iOS conectado a `frontend/dist`, consulte [`../mobile/README.md`](../mobile/README.md).

1. **CU06: Verificación Multicanal:**
   - **Texto:** Pegado directo de cadenas de WhatsApp, tweets o rumores (validación mínima 10 caracteres).
   - **Enlace:** Validación de URLs seguras (`https://`).
   - **Imagen:** Selección de capturas desde la galería o toma de foto directa con la cámara (`expo-image-picker`) para procesamiento OCR.
   - **Consola de Pensamiento Fáctico (Chain-of-Thought):** Transmisión y visualización en tiempo real del razonamiento del sistema y deliberación multi-agente (`[SISTEMA]`, `[INGESTA]`, `[ATÓMICO]`, `[RAG_GRAFO]`, `[CONSEJO_IA]`, `[VEREDICTO]`) en terminal oscura, con cronómetro fáctico en segundos y barra de progreso porcentual interactiva.
2. **CU06 / CU03: Resultado de Veredicto y Certeza:**
   - Veredictos semafóricos accesibles (**Verdadero**, **Parcialmente cierto**, **Engañoso**, **Falso**, **No verificable**).
   - Medidor circular de porcentaje de certeza (0 - 100%) y despliegue de evaluaciones del **Consejo Multi-Modelo** cuando aplica.
   - Justificación explicativa accesible (*¿Por qué este veredicto?*).
   - Alerta preventiva en veredictos con baja confianza (<60%).
   - Opción para **Guardar en el Historial** personal y **Compartir** el veredicto por WhatsApp/mensajería.
3. **CU07: Grafo de Credibilidad y Fuentes de Contraste:**
   - Despliegue de citas de contraste y enlaces a medios oficiales.
   - Catálogo completo de las 80 fuentes de DAGAZ con buscador, filtros y valoración ciudadana (`PATCH /api/v1/fuentes/{id}/valorar`).
4. **CU01 & CU02: Notas Comunitarias Ciudadanas:**
   - Formulario para aportar contexto verificable (`POST /api/v1/contenidos/{id}/notas`).
   - Barra porcentual de utilidad comunitaria y calificación ciudadana (`PATCH /api/v1/notas/{id}/calificar`).
5. **CU04: Historial de Contenidos:**
   - Consulta cronológica paginada de verificaciones previas (`GET /api/v1/historial`) con buscador en tiempo real y filtros de veredicto.
6. **CU05 & Ley 1581 de 2012 (Hábeas Data):**
   - Inicio de sesión y registro seguro con token Bearer de Laravel Sanctum persistido en `AsyncStorage`.
   - Consentimiento expreso de tratamiento de datos personales conforme a la normativa colombiana.
   - Ejercicio de **Derechos ARCO** (Exportar expediente de datos y derecho al olvido/supresión).
7. **Accesibilidad (+40 años):**
   - Switcher en cabecera `A` / `A+` para incrementar el tamaño de texto en toda la interfaz.
   - Controles y áreas táctiles con un mínimo de **48 × 48 px**.
   - Colores con ratios de contraste superiores a **4.5:1** (WCAG 2.1 AA).

---

## 2. Cómo Probar la Aplicación en tu iPhone o Android (Paso a Paso)

### Paso 1: Instalar Expo Go
1. Abre la **App Store** (iPhone) o **Google Play Store** (Android).
2. Busca e instala **Expo Go** (es gratuita).

### Paso 2: Iniciar la Aplicación en tu Computadora
1. Abre una terminal en la carpeta `mobile-expo`:
   ```powershell
   cd mobile-expo
   npm install
   npx expo start
   ```
2. Aparecerá un **código QR** en la consola y la dirección de Metro Bundler (ej. `http://localhost:8081`).

### Paso 3: Abrir la App en el Dispositivo
1. Asegúrate de que el teléfono y tu PC estén conectados a la **misma red Wi-Fi**.
2. Escanea el código QR (Cámara en iPhone / Expo Go en Android).
3. Si la red Wi-Fi tiene aislamiento entre clientes, ejecuta `npx expo start --tunnel`.

---

## 3. Conectar la App al Backend de DAGAZ

1. Inicia el backend Laravel 13 escuchando en todas las interfaces de red:
   ```powershell
   cd backend
   php artisan serve --host=0.0.0.0 --port=8000
   ```
2. Obtén la IP local de tu PC (`ipconfig` en Windows / `ip a` en Linux).
3. En la app móvil, ve a **"Mi cuenta"** > **"⚙️ Conexión del Backend (API REST)"**.
4. Configura `http://<TU_IP_LOCAL>:8000` (el método `probarConexionApi()` consulta `/api/health`) y pulsa **"Guardar Dirección del Servidor"**.

---

## 4. Generar el archivo `DAGAZ.ipa` para Sideloadly (Sin Mac)

No es posible compilar un binario `.ipa` directamente en Windows porque Xcode y el SDK de iOS requieren macOS. Además, Expo EAS Build requiere una cuenta Apple Developer de pago (\$99/año) para emitir un `.ipa`.

### Opción A: Vía GitHub Actions (Recomendada para Sideloadly)
El repositorio incluye el flujo automatizado [`../.github/workflows/build-ios-ipa.yml`](../.github/workflows/build-ios-ipa.yml) en runner `macos-latest` que compila el binario nativo sin firma corporativa:

1. Sube tus cambios a GitHub (`git push`) o abre la pestaña **Actions** en el repositorio.
2. Selecciona **"Build iOS IPA (Sideloadly)"** y pulsa **"Run workflow"**.
3. Al finalizar, descarga el artefacto **`DAGAZ-iOS-Sideloadly-IPA`** y descomprímelo para obtener **`DAGAZ.ipa`**.
4. Abre **Sideloadly** en Windows, conecta tu iPhone por USB, arrastra `DAGAZ.ipa`, ingresa tu Apple ID gratuito y presiona **Start**.
5. En el iPhone: habilita la confianza del perfil en **Ajustes > General > Administración de VPN y dispositivos** y activa el **Modo Desarrollador** en *Ajustes > Privacidad y seguridad*.

### Opción B: Vía Expo EAS Build ([`eas.json`](eas.json))
1. Instala el CLI de EAS e inicia sesión:
   ```bash
   npm install -g eas-cli
   eas login
   ```
2. Ejecuta la compilación con perfil `preview` o `production`:
   ```bash
   eas build --platform ios --profile preview
   ```
