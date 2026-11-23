# Microservicios de Soporte DAGAZ — Python (OCR & STT)

> **Requerimientos asociados:** TSK-2.3 & REQ-02 (Extracción Multimodal de Texto y Transcripción de Audio)  
> **Arquitectura:** Microservicios desacoplados basados en FastAPI & Uvicorn consumidos por el `AdaptadorMulticanal` de **Laravel 13**.

---

## 1. Descripción General

En el ecosistema **DAGAZ**, los usuarios pueden enviar solicitudes de verificación de noticias y desinformación a través de múltiples formatos:
- **Imágenes / Memes / Capturas de pantalla**: Procesados por el **Microservicio OCR** (Puerto `5001`).
- **Notas de voz / Audios / Videos (WAV, MP3, etc.)**: Procesados por el **Microservicio STT** (Puerto `5002`).

Ambos servicios están construidos con **FastAPI** + **Uvicorn** (`requirements.txt`) y diseñados con principios de **alta tolerancia a fallos** (Fail-Safe), preprocesamiento con `Pillow` / `wave` y degradación heurística controlada ante ausencia de binarios externos o red.

---

## 2. Microservicio OCR (Puerto 5001)

- **Ubicación:** [`ocr_service/app.py`](ocr_service/app.py)
- **Puerto:** `5001`
- **Motor principal:** `pytesseract` + `Pillow` (conversión a escala de grises y realce de contraste al 1.8x, idioma `spa`).
- **Fallback:** Detección de metadatos EXIF/comentarios y respuesta heurística segura (sin excepciones no controladas 500).

### Endpoints

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/health` | Chequeo de liveness y disponibilidad del binario Tesseract |
| `POST` | `/ocr` | Extracción de texto vía JSON |
| `POST` | `/ocr/ocr` | Alias de compatibilidad directa |
| `POST` | `/upload` | Subida directa de imagen multipart (`multipart/form-data`) |

### Estructura de Petición JSON (`POST /ocr`)
```json
{
  "image_url": "https://ejemplo.com/noticia_falsa.jpg",
  "image_path": "C:\\ruta\\a\\captura.png",
  "image_base64": "data:image/png;base64,iVBORw0KGgo..."
}
```
*(Se debe enviar al menos uno de los tres campos).*

### Estructura de Respuesta (`200 OK`)
```json
{
  "status": "success",
  "text": "Texto extraído de la imagen o titular detectado...",
  "confidence": 0.90,
  "engine": "pytesseract",
  "metadata": {
    "width": 1200,
    "height": 630,
    "format": "JPEG",
    "mode": "RGB",
    "filename": "noticia_falsa.jpg"
  }
}
```

---

## 3. Microservicio STT (Speech-to-Text) (Puerto 5002)

- **Ubicación:** [`stt_service/app.py`](stt_service/app.py)
- **Puerto:** `5002`
- **Motor principal:** `SpeechRecognition` (para audio WAV de 16kHz mono).
- **Fallback:** Inspección acústica de cabeceras WAV (canales, sample rate, duración) y respuesta normalizada en ausencia de servicio externo.

### Endpoints

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/health` | Estado del servicio y disponibilidad de `SpeechRecognition` |
| `POST` | `/transcribe` | Transcripción de audio vía JSON |
| `POST` | `/stt` | Alias de compatibilidad |
| `POST` | `/stt/transcribe` | Alias de compatibilidad normalizada |
| `POST` | `/upload` | Subida directa de archivo de audio multipart |

### Estructura de Petición JSON (`POST /transcribe`)
```json
{
  "audio_url": "https://ejemplo.com/audio_whatsapp.wav",
  "audio_path": "C:\\ruta\\a\\audio.wav",
  "audio_base64": "UklGRi...",
  "idioma": "es-CO"
}
```

### Estructura de Respuesta (`200 OK`)
```json
{
  "status": "success",
  "transcription": "Texto transcrito del audio con precisión...",
  "confidence": 0.92,
  "engine": "google_speech_recognition",
  "metadata": {
    "filename": "audio.wav",
    "size_bytes": 32000,
    "language_requested": "es-CO",
    "duration_seconds": 1.0,
    "channels": 1,
    "sample_rate": 16000
  }
}
```

---

## 4. Orquestador de Ejecución (`run_services.py`)

Para levantar ambos microservicios de forma concurrente con una sola orden:

```powershell
python run_services.py
```

### Opciones de ejecución:
- Solo OCR: `python run_services.py --ocr-only`
- Solo STT: `python run_services.py --stt-only`
- Detención: Presionar `Ctrl + C` (detiene ordenadamente ambos subprocesos Uvicorn).

---

## 5. Pruebas Automatizadas

La suite de pruebas unitarias y de integración cubre la lógica de preprocesamiento, fallbacks y endpoints HTTP mediante `unittest` y `fastapi.testclient`:

```powershell
python -m unittest discover -s tests -v
```

Salida esperada:
```text
Ran 4 tests in ...s
OK
```

---

## 6. Integración con Laravel (DAGAZ)

En el backend principal de Laravel:
- **Configuración:** [`../config/dagaz.php`](../config/dagaz.php) y `.env`:
  ```dotenv
  OCR_SERVICE_URL=http://localhost:5001/ocr
  STT_SERVICE_URL=http://localhost:5002/stt
  ```
- **Consumo:** `App\Adapters\AdaptadorMulticanal`:
  - `extraerTextoOCR(string $rutaImagen): string`
  - `transcribirAudio(string $rutaAudio): string`
  - Normaliza automáticamente la URL base y el subpath para evitar duplicidad de segmentos (`/ocr/ocr` o `/stt/transcribe`).
