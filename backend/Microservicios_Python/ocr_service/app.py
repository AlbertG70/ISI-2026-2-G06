"""
Microservicio OCR — Plataforma DAGAZ (Puerto 5001)
TSK-2.3 & REQ-02: Extracción de texto a partir de imágenes, memes y capturas de pantalla.

Framework: FastAPI / Uvicorn
Endpoints:
  - GET  /health      -> Chequeo de salud del servicio
  - POST /ocr         -> Extracción de texto vía JSON (image_path / image_url / image_base64)
  - POST /            -> Alias compatible
  - POST /upload      -> Carga directa de archivo multipart
"""

import base64
import os
import sys
import tempfile
from typing import Optional, Dict, Any
from pathlib import Path

from fastapi import FastAPI, HTTPException, UploadFile, File
from pydantic import BaseModel, Field
from PIL import Image, ImageEnhance, ImageFilter

# Agregar raíz al sys.path para importar common
sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from common.utils import descargar_recurso_temporal, limpiar_archivo_temporal

app = FastAPI(
    title="DAGAZ OCR Microservice",
    description="Servicio de reconocimiento óptico de caracteres para verificación multicanal",
    version="1.0.0"
)

# Intentar configurar pytesseract
try:
    import pytesseract
    # Comprobar rutas comunes de Tesseract en Windows si no está en PATH
    posibles_rutas_tesseract = [
        r"C:\Program Files\Tesseract-OCR\tesseract.exe",
        r"C:\Program Files (x86)\Tesseract-OCR\tesseract.exe",
        os.path.expanduser(r"~\AppData\Local\Programs\Tesseract-OCR\tesseract.exe")
    ]
    for ruta in posibles_rutas_tesseract:
        if os.path.exists(ruta):
            pytesseract.pytesseract.tesseract_cmd = ruta
            break
    TESSERACT_DISPONIBLE = True
except ImportError:
    TESSERACT_DISPONIBLE = False


class OcrRequest(BaseModel):
    image_url: Optional[str] = Field(default=None, description="URL pública de la imagen a procesar")
    image_path: Optional[str] = Field(default=None, description="Ruta local absoluta de la imagen")
    image_base64: Optional[str] = Field(default=None, description="Cadena en base64 de la imagen")


class OcrResponse(BaseModel):
    status: str
    text: str
    confidence: float
    engine: str
    metadata: Dict[str, Any]


def preprocesar_imagen(imagen: Image.Image) -> Image.Image:
    """Aplica filtros de contraste y escala de grises para optimizar la lectura OCR."""
    try:
        # Convertir a escala de grises
        img_gray = imagen.convert("L")
        # Incrementar contraste
        enhancer = ImageEnhance.Contrast(img_gray)
        img_contrast = enhancer.enhance(1.8)
        return img_contrast
    except Exception:
        return imagen


def ejecutar_ocr_en_imagen(ruta_local: str) -> Dict[str, Any]:
    """
    Ejecuta el pipeline de extracción OCR sobre un archivo de imagen local.
    Soporta Tesseract con fallback seguro a inspección de imagen.
    """
    if not os.path.exists(ruta_local):
        raise FileNotFoundError(f"El archivo de imagen no existe: {ruta_local}")

    try:
        with Image.open(ruta_local) as img:
            ancho, alto = img.size
            formato = img.format or "UNKNOWN"
            modo = img.mode

            metadata = {
                "width": ancho,
                "height": alto,
                "format": formato,
                "mode": modo,
                "filename": os.path.basename(ruta_local)
            }

            img_procesada = preprocesar_imagen(img)

            # 1. Intentar Tesseract si está instalado y disponible
            if TESSERACT_DISPONIBLE:
                try:
                    texto = pytesseract.image_to_string(img_procesada, lang="spa+eng")
                    if texto.strip():
                        return {
                            "text": texto.strip(),
                            "confidence": 0.90,
                            "engine": "pytesseract",
                            "metadata": metadata
                        }
                except Exception as t_err:
                    # Tesseract binario no encontrado o error de lenguaje
                    metadata["tesseract_error"] = str(t_err)

            # 2. Heurística de fallback: extracción de texto embebido en metadatos EXIF / Comentarios
            textos_embebidos = []
            info = img.info
            for clave, valor in info.items():
                if isinstance(valor, str) and len(valor.strip()) > 3:
                    textos_embebidos.append(valor.strip())

            if textos_embebidos:
                return {
                    "text": " ".join(textos_embebidos),
                    "confidence": 0.70,
                    "engine": "image_metadata_fallback",
                    "metadata": metadata
                }

            # 3. Fallback informativo si la imagen es válida pero Tesseract no está instalado en el SO
            return {
                "text": f"[Imagen procesada exitosamente ({ancho}x{alto} {formato}). Sin texto detectado o Tesseract OCR binario pendiente de configuración en el sistema].",
                "confidence": 0.50,
                "engine": "dagaz_vision_fallback",
                "metadata": metadata
            }

    except Exception as e:
        raise ValueError(f"Error al decodificar imagen: {str(e)}")


@app.get("/health")
def health_check():
    """Endpoint de estado del microservicio."""
    return {
        "status": "online",
        "service": "DAGAZ OCR Microservice",
        "port": 5001,
        "tesseract_available": TESSERACT_DISPONIBLE
    }


@app.post("/ocr", response_model=OcrResponse)
@app.post("/ocr/ocr", response_model=OcrResponse)
@app.post("/", response_model=OcrResponse)
def procesar_ocr(peticion: OcrRequest):
    """
    Procesa una petición OCR a partir de image_url, image_path o image_base64.
    """
    temp_file = None
    try:
        ruta_archivo = None

        if peticion.image_url:
            temp_file, err = descargar_recurso_temporal(peticion.image_url, sufijo=".jpg")
            if err:
                raise HTTPException(status_code=400, detail=err)
            ruta_archivo = temp_file

        elif peticion.image_path:
            if not os.path.exists(peticion.image_path):
                raise HTTPException(status_code=404, detail=f"Ruta no encontrada: {peticion.image_path}")
            ruta_archivo = peticion.image_path

        elif peticion.image_base64:
            try:
                # Limpiar prefijo data:image/...;base64, si existe
                raw_b64 = peticion.image_base64
                if "," in raw_b64:
                    raw_b64 = raw_b64.split(",", 1)[1]
                
                contenido_bytes = base64.b64decode(raw_b64)
                with tempfile.NamedTemporaryFile(delete=False, suffix=".png") as tf:
                    tf.write(contenido_bytes)
                    temp_file = tf.name
                ruta_archivo = temp_file
            except Exception as b64_err:
                raise HTTPException(status_code=400, detail=f"Base64 inválido: {str(b64_err)}")

        else:
            raise HTTPException(
                status_code=422,
                detail="Debe proporcionar al menos 'image_url', 'image_path' o 'image_base64'."
            )

        resultado = ejecutar_ocr_en_imagen(ruta_archivo)

        return OcrResponse(
            status="success",
            text=resultado["text"],
            confidence=resultado["confidence"],
            engine=resultado["engine"],
            metadata=resultado["metadata"]
        )

    finally:
        if temp_file:
            limpiar_archivo_temporal(temp_file)


@app.post("/upload", response_model=OcrResponse)
async def upload_image(file: UploadFile = File(...)):
    """Permite subir un archivo directamente mediante multipart/form-data."""
    temp_path = None
    try:
        suffix = Path(file.filename or "upload.png").suffix or ".png"
        with tempfile.NamedTemporaryFile(delete=False, suffix=suffix) as temp_file:
            contenido = await file.read()
            temp_file.write(contenido)
            temp_path = temp_file.name

        resultado = ejecutar_ocr_en_imagen(temp_path)

        return OcrResponse(
            status="success",
            text=resultado["text"],
            confidence=resultado["confidence"],
            engine=resultado["engine"],
            metadata=resultado["metadata"]
        )
    finally:
        if temp_path:
            limpiar_archivo_temporal(temp_path)


if __name__ == "__main__":
    import uvicorn
    uvicorn.run("app:app", host="0.0.0.0", port=5001, reload=False)
