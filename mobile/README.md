# DAGAZ — Contenedor Móvil Híbrido (Capacitor 6)

> **Ingeniería de Servicios de Internet — 2026-2 — Grupo 06**  
> **Plataforma:** DAGAZ — Fact-Checking Ciudadano y Verificación Multicanal  
> **Tecnologías:** Capacitor 6 (`^6.0.0`) · Android SDK / Gradle · Xcode iOS · WebView Responsiva (`frontend/dist`)  
> **ID de Aplicación:** `com.isicorp.dagaz`

---

## 1. Arquitectura Dual Móvil de DAGAZ

El proyecto **DAGAZ** mantiene dos clientes móviles complementarios para acceso multicanal optimizado para adultos (+40 años) bajo **WCAG 2.1 AA**:

1. **Contenedor Híbrido Android / iOS ([`mobile/`](.)):**  
   Empaqueta el compilado web accesible de [`../frontend/dist`](../frontend/dist) dentro de una aplicación nativa gestionada con **Capacitor 6** (`@capacitor/core`, `@capacitor/android`, `@capacitor/ios`, `@capacitor/cli` `^6.0.0`). Está integrado al pipeline automatizado de CI en el job `mobile-container` de [`../.github/workflows/ci.yml`](../.github/workflows/ci.yml).
2. **Aplicación Nativa Multiplataforma ([`../mobile-expo/`](../mobile-expo/)):**  
   Aplicación escrita en **React Native `0.88.1` + Expo SDK 58** con consumo directo de la API REST v1 (incluyendo streaming de razonamiento en tiempo real), pruebas en iPhone/Android con Expo Go y compilación `.ipa` para Sideloadly mediante GitHub Actions (`build-ios-ipa.yml`). Para el cliente nativo, consulte [`../mobile-expo/README.md`](../mobile-expo/README.md).

---

## 2. Estructura de este Directorio (`mobile/`)

```text
mobile/
├── android/                   # Proyecto nativo Android (Gradle, MainActivity, AndroidManifest, iconos y splash)
├── ios/                       # Proyecto nativo iOS (AppIcon.appiconset y configuración Xcode)
├── scripts/                   # Scripts auxiliares (generate_icons.ps1 para generación de iconos/splash)
├── capacitor.config.json      # Configuración Capacitor (appId: com.isicorp.dagaz, webDir: ../frontend/dist)
├── package.json               # Dependencias @capacitor/* ^6.0.0 y scripts cap:*
└── README.md                  # Esta guía técnica
```

---

## 3. Sincronización y Ejecución Local

Antes de sincronizar el contenedor Android/iOS, compile los assets web en `frontend/`:

```bash
# 1. Compilar assets del frontend accesible
cd frontend
npm install
npm run build
cd ..

# 2. Instalar dependencias Capacitor y sincronizar plataformas
cd mobile
npm install
npx cap sync android
```

### Scripts disponibles (`mobile/package.json`)
| Comando | Descripción |
| :--- | :--- |
| `npm run cap:sync` | Copia los assets compilados (`../frontend/dist`) y plugins hacia los proyectos nativos. |
| `npm run cap:open:android` | Abre el proyecto [`android/`](android/) en Android Studio. |
| `npm run cap:run:android` | Compila y ejecuta en emulador o dispositivo conectado por ADB (`server.url: http://10.0.2.2:8000`). |
| `npm run cap:open:ios` | Abre el proyecto iOS en Xcode *(requiere macOS)*. |
| `npm run cap:run:ios` | Ejecuta en simulador iOS *(requiere macOS)*. |
