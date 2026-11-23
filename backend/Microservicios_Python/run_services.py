"""
Orquestador de Microservicios Python — DAGAZ
Inicia concurrentemente los servicios OCR (puerto 5001) y STT (puerto 5002).

Uso:
  python run_services.py              # Inicia ambos servicios
  python run_services.py --ocr-only   # Inicia solo el servicio OCR
  python run_services.py --stt-only   # Inicia solo el servicio STT
"""

import argparse
import os
import signal
import subprocess
import sys
import time

RUTA_BASE = os.path.dirname(os.path.abspath(__file__))
RUTA_OCR = os.path.join(RUTA_BASE, "ocr_service", "app.py")
RUTA_STT = os.path.join(RUTA_BASE, "stt_service", "app.py")


def iniciar_servicio(nombre: str, script_path: str, puerto: int) -> subprocess.Popen:
    """Inicia un microservicio en un subproceso independiente."""
    cmd = [
        sys.executable,
        "-m", "uvicorn",
        "app:app",
        "--host", "0.0.0.0",
        "--port", str(puerto),
        "--log-level", "info"
    ]
    cwd = os.path.dirname(script_path)
    print(f"[*] Iniciando {nombre} en http://localhost:{puerto} (Directorio: {cwd})...")
    
    proceso = subprocess.Popen(
        cmd,
        cwd=cwd,
        stdout=sys.stdout,
        stderr=sys.stderr
    )
    return proceso


def main():
    parser = argparse.ArgumentParser(description="Orquestador de Microservicios DAGAZ")
    parser.add_argument("--ocr-only", action="store_true", help="Iniciar únicamente el servicio OCR (5001)")
    parser.add_argument("--stt-only", action="store_true", help="Iniciar únicamente el servicio STT (5002)")
    args = parser.parse_args()

    procesos = []

    try:
        if args.ocr_only:
            procesos.append(iniciar_servicio("Servicio OCR", RUTA_OCR, 5001))
        elif args.stt_only:
            procesos.append(iniciar_servicio("Servicio STT", RUTA_STT, 5002))
        else:
            procesos.append(iniciar_servicio("Servicio OCR", RUTA_OCR, 5001))
            procesos.append(iniciar_servicio("Servicio STT", RUTA_STT, 5002))

        print("\n" + "=" * 60)
        print("  DAGAZ — Microservicios Multicanal Operativos")
        print("  - OCR Service: http://localhost:5001 (Docs: /docs)")
        print("  - STT Service: http://localhost:5002 (Docs: /docs)")
        print("  Presione Ctrl + C para detener todos los servicios.")
        print("=" * 60 + "\n")

        while True:
            time.sleep(1)
            # Verificar si algún subproceso terminó inesperadamente
            for p in procesos:
                if p.poll() is not None:
                    print(f"[!] Un subproceso se detuvo con código {p.returncode}")
                    return

    except KeyboardInterrupt:
        print("\n[*] Deteniendo microservicios DAGAZ...")
        for p in procesos:
            p.terminate()
            try:
                p.wait(timeout=5)
            except subprocess.TimeoutExpired:
                p.kill()
        print("[✓] Microservicios detenidos correctamente.")


if __name__ == "__main__":
    main()
