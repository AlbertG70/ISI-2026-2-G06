# DAGAZ — Manual de Despliegue e Infraestructura
## Guía de Configuración de Servidores, Neon PostgreSQL (`pgvector`), Microservicios Python, CI/CD y Clientes Móviles

> **Proyecto:** DAGAZ — Plataforma de Verificación de Información y Desinformación en Colombia  
> **Versión:** `v1.4.1`  
> **Asignatura:** Ingeniería de Servicios de Internet — 2026-2 — Grupo 06  
> **Requisito Asociado:** REQ-27 (Manuales de Despliegue y Procedimientos), REQ-30 a REQ-35 (Multi-Modelo y Clientes Móviles), TSK-7.1, TSK-7.3  
> **Destinatarios:** Ingenieros DevOps, administradores de sistemas y desarrolladores.

---

## 1. Arquitectura de Despliegue y Topología

La plataforma **DAGAZ** integra cuatro capas desacopladas en producción y desarrollo:

```text
┌─────────────────────────────────────────────────────────────────────────┐
│                    CLIENTES WEB, MÓVILES Y M2M                         │
│  • Web Responsiva Accesible (Desktop & Mobile — Blade SSR + Vite 8)    │
│  • Contenedor Híbrido Android/iOS (mobile/ — Capacitor 6)              │
│  • App Móvil Nativa iOS/Android (mobile-expo/ — Expo SDK 58 / RN 0.88) │
│  • Salas de Redacción Aliadas (API REST /api/v1/public/* — REQ-26)     │
└───────────────────────────────────┬─────────────────────────────────────┘
                                    │ HTTP(S) + Sanctum Bearer / X-API-KEY
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│             FRONTEND WEB (Vite 8 + Tailwind CSS v4 + Vitest 5)         │
│  • Directorio: frontend/                                                │
│  • Plantillas: frontend/views/ (resueltas por backend/config/view.php) │
│  • Compilación: frontend/dist/ (enlace en backend/public/build)        │
└───────────────────────────────────┬─────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                 BACKEND CORE (Laravel 13.17 / PHP 8.3–8.4)             │
│  • Directorio: backend/                                                 │
│  • BD Primaria: PostgreSQL 18 + pgvector (Neon.tech Serverless SSL)    │
│  • BD Alternativa / CI: MariaDB 10.11+ / SQLite en memoria (phpunit)   │
│  • Autenticación: Laravel Sanctum 4.3 (Bearer Tokens UUID)             │
│  • Consejo Multi-Modelo: MultiModeloHarnessService (Http::pool paralelo)│
└──────────────────────────┬────────────────────────┬─────────────────────┘
                           │ HTTP Interno           │ HTTPS Proveedores
                           ▼                        ▼
┌─────────────────────────────────┐  ┌────────────────────────────────────┐
│  MICROSERVICIOS PYTHON (FastAPI)│  │   PROVEEDORES IA & CONECTORES      │
│  • backend/Microservicios_Python│  │  • OpenAI GPT-4o / OpenRouter Free │
│  • OCR (:5001) — Tesseract/PIL  │  │  • Google Gemini 2.0 Flash         │
│  • STT (:5002) — SpeechRecogn.  │  │  • Anthropic Claude 3.5 Sonnet     │
└─────────────────────────────────┘  │  • Ollama Local (Contingencia)     │
                                     │  • Archive.org Wayback / CDX API   │
                                     └────────────────────────────────────┘
```

---

## 2. Requisitos de Infraestructura y Servidor

* **Sistema Operativo:** Ubuntu 24.04 LTS / Debian 12 / Windows 11 (entorno local) / macOS (build `.ipa`).
* **PHP:** Versión **`8.3` o `8.4`** con extensiones `pdo`, `pdo_pgsql`, `pgsql`, `pdo_sqlite`, `pdo_mysql`, `mbstring`, `xml`, `curl`, `openssl`, `bcmath`, `tokenizer`.
* **Composer:** Versión `2.x`.
* **Node.js y npm:** Versión **`20.x` o `22.x` LTS** y `npm 10+`.
* **Python:** Versión **`3.11+`** y binario de sistema **Tesseract-OCR** (`tesseract-ocr`, `tesseract-ocr-spa`) para el servicio OCR.
* **Base de Datos Primaria:** **PostgreSQL 18 con extensión `pgvector`** (Neon.tech Free Tier con `?sslmode=require`, búsqueda KNN exacta sobre `vector(384)` sin índices HNSW/IVFFlat para respetar el límite de 2 GB de RAM). *(Compatible también con MariaDB 10.11+ / MySQL 8.0+ como opción local).*
* **Servidor Web en Producción:** Nginx 1.24+ con PHP-FPM 8.4 y certificados SSL/TLS (Let's Encrypt / Certbot).

---

## 3. Procedimiento de Despliegue Paso a Paso

### 3.1 Clonación del Repositorio
```bash
git clone https://github.com/ISI-CORP/ISI-2026-2-G06.git
cd ISI-2026-2-G06
```

### 3.2 Compilación y Pruebas del Frontend Web (`frontend/`)
```bash
cd frontend
npm ci
npm test -- --run     # Ejecuta las 8 suites Vitest (23 pruebas)
npm run build         # Genera artefactos de producción en frontend/dist
cd ..
```

### 3.3 Despliegue del Backend Laravel (`backend/`)
```bash
cd backend
composer install --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Configurar las variables principales en [`backend/.env.example`](../../backend/.env.example) (usando siempre credenciales propias del entorno de despliegue):

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://dagaz.com.co

# Opción Primaria: PostgreSQL 18 + pgvector (Neon.tech SSL)
DB_CONNECTION=pgsql
DB_URL="postgresql://USUARIO:CONTRASEÑA@HOST/BASE_DE_DATOS?sslmode=require"
DB_SSLMODE=require

# Adaptadores IA y Consejo Multi-Modelo
DAGAZ_IA_PROVEEDOR=openrouter
OPENROUTER_API_KEY=
OPENAI_API_KEY=
GEMINI_API_KEY=
ANTHROPIC_API_KEY=
DAGAZ_MULTI_MODELO_HABILITADO=true
DAGAZ_MULTI_MODELO_TIMEOUT=20
DAGAZ_MULTI_MODELO_CALIBRAR=true

# Microservicios Python (OCR / STT)
OCR_SERVICE_URL=http://localhost:5001/ocr
STT_SERVICE_URL=http://localhost:5002/stt
```

Ejecutar migraciones, sembrado del Grafo de Credibilidad (80 fuentes) y caché productiva:
```bash
php artisan migrate --force
php artisan db:seed --class=FuentesSeeder --force
php artisan db:seed --class=UsuariosSeeder --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan test        # Verificación local SQLite en memoria (142 pruebas PHPUnit)
cd ..
```

### 3.4 Despliegue de Microservicios Python (OCR :5001 y STT :5002)
```bash
cd backend/Microservicios_Python
python -m venv .venv
# Activar .venv según sistema operativo y luego instalar dependencias:
pip install -r requirements.txt
python -m unittest discover -s tests -v   # Valida 4 pruebas FastAPI OCR/STT
python run_services.py                    # Orquesta Uvicorn en puertos 5001 y 5002
```
*(En servidores Linux productivos, ejecutar `run_services.py` bajo `systemd` o `supervisor`).*

### 3.5 Ingesta Periódica de Noticias e Índice Semántico (`pgvector`)
Para mantener actualizado el corpus documental en PostgreSQL:
* **Comando Artisan RSS en Laravel:**
  ```bash
  cd backend
  php artisan dagaz:actualizar-noticias
  ```
  Programable vía `cron` cada hora para incorporar titulares de medios colombianos e internacionales.
* **Ingesta Vectorial Masiva Python (`vector(384)`):** Ejecutar [`ingesta_noticias_colombia.py`](../../ingesta_noticias_colombia.py) o el cuaderno [`ingesta_noticias_colombia.ipynb`](../../ingesta_noticias_colombia.ipynb) configurando `DATABASE_URL` por variable de entorno (sin almacenar credenciales en el repositorio).

### 3.6 Despliegue de Clientes Móviles (`mobile/` y `mobile-expo/`)
1. **Contenedor Híbrido Android (`mobile/`, Capacitor 6):**
   ```bash
   cd frontend && npm run build && cd ../mobile
   npm ci
   npx cap sync android
   ```
   Para emulador Android local, [`capacitor.config.json`](../../mobile/capacitor.config.json) apunta a `http://10.0.2.2:8000`; en producción actualizar `server.url` al dominio HTTPS o empaquetar `frontend/dist`.
2. **App Nativa React Native (`mobile-expo/`, Expo SDK 58):**
   ```bash
   cd mobile-expo
   npm ci
   npx expo start
   ```
   La configuración base de la API móvil reside en [`mobile-expo/src/services/api.js`](../../mobile-expo/src/services/api.js) (definir variable de entorno pública / URL del backend productivo). El paquete **`DAGAZ.ipa`** sin firma para instalación mediante Sideloadly se compila en la nube mediante el workflow de GitHub Actions `Build iOS IPA (Sideloadly)`.

---

## 4. Configuración del Servidor Web Nginx

Archivo de configuración recomendado en `/etc/nginx/sites-available/dagaz.conf`:

```nginx
server {
    listen 80;
    server_name dagaz.com.co www.dagaz.com.co;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name dagaz.com.co www.dagaz.com.co;

    ssl_certificate /etc/letsencrypt/live/dagaz.com.co/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/dagaz.com.co/privkey.pem;

    root /var/www/dagaz/backend/public;
    index index.php index.html;

    client_max_body_size 25M;

    # Encabezados de Seguridad y Hábeas Data
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header Referrer-Policy "strict-origin-when-cross-origin";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Bloqueo de acceso a archivos sensibles (.env, .git)
    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

---

## 5. Pipelines de Integración Continua (CI/CD) en GitHub Actions

El repositorio automatiza la verificación continua mediante dos workflows declarativos:

1. **[`.github/workflows/ci.yml`](../../.github/workflows/ci.yml) (`DAGAZ CI/CD Pipeline`):**
   * **`backend-tests` (`ubuntu-latest`, PHP 8.4):** Instala dependencias Composer y ejecuta `php artisan test` sobre SQLite en memoria (**163 pruebas** entre Unit y Feature, validando API v1, streaming reactivo SSE, búsqueda semántica, búsqueda web en vivo, Consejo Multi-Modelo y microservicios).
   * **`frontend-tests` (`ubuntu-latest`, Node 22):** Instala dependencias npm, ejecuta `npm test -- --run` (**23 pruebas Vitest** en 8 suites), compila Vite + Tailwind v4 (`npm run build`) y publica el artefacto `frontend-dist`.
   * **`mobile-container` (`ubuntu-latest`, Node 22):** Descarga `frontend-dist` y valida `npx cap sync android` en `mobile/`.
2. **[`.github/workflows/build-ios-ipa.yml`](../../.github/workflows/build-ios-ipa.yml) (`Build iOS IPA (Sideloadly)`):**
   * **`build-ipa` (`macos-latest`, Node 22):** Ejecuta `npx expo prebuild --platform ios --clean`, compila el binario iOS Release sin firma con `xcodebuild`, empaqueta `Payload/*.app` en `mobile-expo/DAGAZ.ipa` y publica el artefacto **`DAGAZ-iOS-Sideloadly-IPA`** (retención de 14 días).
