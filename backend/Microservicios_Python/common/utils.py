"""
Módulo de Utilidades Comunes para los Microservicios DAGAZ (Python).

Maneja descarga segura de URLs a archivos temporales, validación de formatos,
limpieza de texto y manejo homogéneo de respuestas de error.
"""

import os
import tempfile
import urllib.parse
from typing import Optional, Tuple
import requests


def es_url_valida(url: str) -> bool:
    """Verifica si una cadena corresponde a una URL HTTP/HTTPS bien formada."""
    try:
        resultado = urllib.parse.urlparse(url)
        return all([resultado.scheme in ('http', 'https'), resultado.netloc])
    except Exception:
        return False


def descargar_recurso_temporal(url: str, sufijo: str = ".tmp", timeout: int = 30) -> Tuple[Optional[str], Optional[str]]:
    """
    Descarga un recurso web a un archivo temporal local.
    
    Retorna:
        Tuple[ruta_archivo_temporal, mensaje_error]
    """
    if not es_url_valida(url):
        return None, f"URL no válida o esquema no soportado: {url}"

    headers = {
        "User-Agent": "Mozilla/5.0 (DAGAZ-Verification-Bot/1.0; Python-Requests)"
    }

    try:
        with requests.get(url, headers=headers, stream=True, timeout=timeout) as r:
            r.raise_for_status()
            
            with tempfile.NamedTemporaryFile(delete=False, suffix=sufijo) as temp_file:
                for chunk in r.iter_content(chunk_size=8192):
                    if chunk:
                        temp_file.write(chunk)
                return temp_file.name, None

    except requests.exceptions.RequestException as e:
        return None, f"Fallo al descargar recurso de {url}: {str(e)}"
    except Exception as e:
        return None, f"Error inesperado al almacenar archivo temporal: {str(e)}"


def limpiar_archivo_temporal(ruta: Optional[str]) -> None:
    """Elimina de forma segura un archivo temporal creado."""
    if ruta and os.path.exists(ruta):
        try:
            os.remove(ruta)
        except OSError:
            pass
