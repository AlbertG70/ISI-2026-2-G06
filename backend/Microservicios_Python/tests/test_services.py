"""
Suite de Pruebas Automatizadas — Microservicios DAGAZ (OCR & STT)
TSK-2.3 & REQ-02
"""

import sys
import os
import unittest
from pathlib import Path
from PIL import Image
import wave
import struct

# Asegurar path de importación
BASE_DIR = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(BASE_DIR))

from ocr_service.app import app as ocr_app, ejecutar_ocr_en_imagen
from stt_service.app import app as stt_app, transcribir_archivo_audio
from common.utils import descargar_recurso_temporal, limpiar_archivo_temporal

try:
    from fastapi.testclient import TestClient
    TESTCLIENT_AVAILABLE = True
except ImportError:
    TESTCLIENT_AVAILABLE = False


class TestMicroserviciosDagaz(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.test_dir = BASE_DIR / "tests" / "fixtures"
        cls.test_dir.mkdir(parents=True, exist_ok=True)
        
        # 1. Crear imagen de prueba sintética (PNG con texto o patrón)
        cls.sample_image = str(cls.test_dir / "sample.png")
        img = Image.new("RGB", (300, 100), color=(255, 255, 255))
        img.save(cls.sample_image)

        # 2. Crear archivo WAV de prueba sintético (1 segundo de silencio/tono a 16kHz mono)
        cls.sample_wav = str(cls.test_dir / "sample.wav")
        sample_rate = 16000
        duration = 1  # segundo
        with wave.open(cls.sample_wav, 'w') as wf:
            wf.setnchannels(1)
            wf.setsampwidth(2)
            wf.setframerate(sample_rate)
            # Escribir 1 segundo de silencio (amplitud 0)
            data = struct.pack(f"<{sample_rate}h", *([0] * sample_rate))
            wf.writeframes(data)

    @classmethod
    def tearDownClass(cls):
        # Limpieza de fixtures
        if os.path.exists(cls.sample_image):
            os.remove(cls.sample_image)
        if os.path.exists(cls.sample_wav):
            os.remove(cls.sample_wav)
        if cls.test_dir.exists():
            try:
                cls.test_dir.rmdir()
            except Exception:
                pass

    def test_ocr_local_execution(self):
        """Verifica la ejecución directa de la función OCR con fallback seguro."""
        resultado = ejecutar_ocr_en_imagen(self.sample_image)
        self.assertIn("text", resultado)
        self.assertIn("confidence", resultado)
        self.assertIn("metadata", resultado)
        self.assertGreater(resultado["confidence"], 0)

    def test_stt_local_execution(self):
        """Verifica la ejecución directa de la función STT con fallback seguro."""
        resultado = transcribir_archivo_audio(self.sample_wav, idioma="es-CO")
        self.assertIn("transcription", resultado)
        self.assertIn("confidence", resultado)
        self.assertIn("metadata", resultado)
        self.assertEqual(resultado["metadata"]["channels"], 1)
        self.assertEqual(resultado["metadata"]["sample_rate"], 16000)

    def test_ocr_api_endpoints(self):
        """Prueba los endpoints HTTP de FastAPI para OCR."""
        if not TESTCLIENT_AVAILABLE:
            self.skipTest("fastapi.testclient (httpx) no disponible en el entorno")

        client = TestClient(ocr_app)
        
        # Health check
        res_health = client.get("/health")
        self.assertEqual(res_health.status_code, 200)
        self.assertEqual(res_health.json()["status"], "online")
        self.assertEqual(res_health.json()["port"], 5001)

        # POST /ocr con image_path
        res_ocr = client.post("/ocr", json={"image_path": self.sample_image})
        self.assertEqual(res_ocr.status_code, 200)
        datos = res_ocr.json()
        self.assertEqual(datos["status"], "success")
        self.assertIn("text", datos)

        # POST /ocr/ocr (compatibilidad URL)
        res_ocr_sub = client.post("/ocr/ocr", json={"image_path": self.sample_image})
        self.assertEqual(res_ocr_sub.status_code, 200)

    def test_stt_api_endpoints(self):
        """Prueba los endpoints HTTP de FastAPI para STT."""
        if not TESTCLIENT_AVAILABLE:
            self.skipTest("fastapi.testclient (httpx) no disponible en el entorno")

        client = TestClient(stt_app)

        # Health check
        res_health = client.get("/health")
        self.assertEqual(res_health.status_code, 200)
        self.assertEqual(res_health.json()["status"], "online")
        self.assertEqual(res_health.json()["port"], 5002)

        # POST /transcribe con audio_path
        res_stt = client.post("/transcribe", json={"audio_path": self.sample_wav, "idioma": "es-CO"})
        self.assertEqual(res_stt.status_code, 200)
        datos = res_stt.json()
        self.assertEqual(datos["status"], "success")
        self.assertIn("transcription", datos)

        # POST /stt/transcribe (compatibilidad URL)
        res_stt_sub = client.post("/stt/transcribe", json={"audio_path": self.sample_wav})
        self.assertEqual(res_stt_sub.status_code, 200)


if __name__ == "__main__":
    unittest.main()
