"""
========================================================================================
DAGAZ — INGESTA RAG MASIVA: 50,000 NOTICIAS EXCLUSIVAS DE COLOMBIA EN NEON.TECH (V2)
========================================================================================
- Supera los 23,576 registros actuales y alcanza los 50,000.
- Desbloquea la totalidad de los sub-sitemaps históricos sin límite artificial de 15.
- Agrega 15 nuevos portales y agencias colombianas de alto volumen.
- Silencia la barra de HuggingFace (que se quedaba congelada en 85 MB).
- Deduplicación estricta: continúa desde 23,576 sin repetir ninguna noticia.
========================================================================================
"""

import os
import re
import json
import time
import random
import hashlib
import asyncio
import warnings
from datetime import datetime, timezone
from typing import List, Dict, Any, Optional, Tuple, Set
import xml.etree.ElementTree as ET
import logging

# Silenciar advertencias, trafilatura y barra visual de HuggingFace
warnings.filterwarnings("ignore")
os.environ["PYTHONWARNINGS"] = "ignore"
os.environ["TOKENIZERS_PARALLELISM"] = "false"
os.environ["HF_HUB_DISABLE_PROGRESS_BARS"] = "1"
os.environ["HF_HUB_DISABLE_SYMLINKS_WARNING"] = "1"
logging.getLogger("trafilatura").setLevel(logging.CRITICAL)

from tqdm.auto import tqdm
import torch
from curl_cffi.requests import AsyncSession
import trafilatura
from sentence_transformers import SentenceTransformer
from langchain_text_splitters import RecursiveCharacterTextSplitter

import psycopg2
from psycopg2.extras import execute_values, Json
from pgvector.psycopg2 import register_vector
import nest_asyncio
nest_asyncio.apply()

# =====================================================================
# 1. CONFIGURACIÓN DEL PIPELINE
# =====================================================================
def _resolver_database_url() -> str:
    """Obtiene la URL de la base de datos desde entorno o desde backend/.env."""
    url = os.getenv("DB_URL") or os.getenv("DATABASE_URL")
    if url:
        return url

    base_dir = os.path.dirname(os.path.abspath(__file__)) if "__file__" in globals() else os.getcwd()
    candidatos = [
        os.path.join(base_dir, "backend", ".env"),
        os.path.join(base_dir, ".env"),
    ]
    for ruta in candidatos:
        if os.path.isfile(ruta):
            try:
                with open(ruta, "r", encoding="utf-8") as f:
                    for linea in f:
                        linea = linea.strip()
                        if not linea or linea.startswith("#"):
                            continue
                        if "=" in linea:
                            k, v = linea.split("=", 1)
                            k = k.strip()
                            v = v.strip().strip('"').strip("'")
                            if k in ("DB_URL", "DATABASE_URL") and v:
                                return v
            except Exception:
                pass
    return ""

class ConfigCol:
    DATABASE_URL = _resolver_database_url()
    
    MODEL_NAME = "all-MiniLM-L6-v2"
    EMBEDDING_DIM = 384
    DEVICE = "cuda" if torch.cuda.is_available() else "cpu"
    
    TARGET_TOTAL_DOCS = 50000
    CONCURRENT_REQUESTS = 25       # Concurrencia balanceada
    REQUEST_TIMEOUT = 10
    MAX_RETRIES = 2
    BATCH_SIZE = 300
    
    # Cuota amplia para extraer archivos históricos profundos
    MAX_URLS_POR_FUENTE = 4500     
    
    CHUNK_SIZE = 1800
    CHUNK_OVERLAP = 150

def get_db_connection():
    db_url = ConfigCol.DATABASE_URL or _resolver_database_url()
    if not db_url:
        raise ValueError(
            "DATABASE_URL / DB_URL no encontrada. "
            "Defínela como variable de entorno o en backend/.env (DB_URL=...)"
        )
    conn = psycopg2.connect(db_url)
    conn.autocommit = True
    register_vector(conn)
    return conn

# =====================================================================
# 2. CATÁLOGO EXPANDIDO DE MEDIOS Y FUENTES EXCLUSIVAS DE COLOMBIA
# =====================================================================
FUENTES_COLOMBIA = [
    # --- PRENSA NACIONAL ---
    ("El Tiempo", "https://www.eltiempo.com", "nacional_n3", 87.00, [
        "https://www.eltiempo.com/sitemap.xml",
        "https://www.eltiempo.com/sitemap-google-news.xml"
    ]),
    ("El Espectador", "https://www.elespectador.com", "nacional_n3", 88.00, [
        "https://www.elespectador.com/arc/outboundfeeds/sitemap-index/",
        "https://www.elespectador.com/arc/outboundfeeds/sitemap/"
    ]),
    ("Revista Semana", "https://www.semana.com", "nacional_n3", 79.00, [
        "https://www.semana.com/sitemap.xml",
        "https://www.semana.com/sitemap-news.xml"
    ]),
    ("Portafolio", "https://www.portafolio.co", "nacional_n3", 86.00, [
        "https://www.portafolio.co/sitemap.xml",
        "https://www.portafolio.co/sitemap-google-news.xml"
    ]),
    ("La República", "https://www.larepublica.co", "nacional_n3", 86.00, [
        "https://www.larepublica.co/sitemap.xml"
    ]),
    ("Noticias Caracol", "https://noticias.caracoltv.com", "nacional_n3", 84.00, [
        "https://noticias.caracoltv.com/sitemap.xml"
    ]),
    ("Caracol Radio", "https://caracol.com.co", "nacional_n3", 85.00, [
        "https://caracol.com.co/arc/outboundfeeds/sitemap-index/"
    ]),
    ("W Radio Colombia", "https://www.wradio.com.co", "nacional_n3", 85.00, [
        "https://www.wradio.com.co/arc/outboundfeeds/sitemap-index/"
    ]),
    ("Blu Radio", "https://www.bluradio.com", "nacional_n3", 83.00, [
        "https://www.bluradio.com/sitemap.xml"
    ]),
    ("Noticias RCN", "https://noticias.canalrcn.com", "nacional_n3", 82.00, [
        "https://noticias.canalrcn.com/sitemap.xml"
    ]),
    ("RCN Radio (Cadena)", "https://www.rcnradio.com", "nacional_n3", 82.00, [
        "https://www.rcnradio.com/sitemap.xml"
    ]),
    ("Pulzo", "https://www.pulzo.com", "nacional_n3", 78.00, [
        "https://www.pulzo.com/sitemap.xml"
    ]),
    ("Revista Cambio", "https://cambiocolombia.com", "nacional_n3", 84.00, [
        "https://cambiocolombia.com/sitemap.xml"
    ]),
    ("Valora Analitik", "https://www.valoraanalitik.com", "nacional_n3", 85.00, [
        "https://www.valoraanalitik.com/sitemap_index.xml"
    ]),
    ("Cuestión Pública", "https://cuestionpublica.com", "nacional_n3", 87.00, [
        "https://cuestionpublica.com/sitemap.xml"
    ]),
    ("Vorágine", "https://voragine.co", "nacional_n3", 86.00, [
        "https://voragine.co/sitemap.xml"
    ]),
    ("Las2Orillas", "https://www.las2orillas.co", "nacional_n3", 80.00, [
        "https://www.las2orillas.co/sitemap.xml"
    ]),
    ("Red+ Noticias", "https://redmas.com.co", "nacional_n3", 80.00, [
        "https://redmas.com.co/sitemap.xml"
    ]),
    ("La FM", "https://www.lafm.com.co", "nacional_n3", 81.00, [
        "https://www.lafm.com.co/sitemap.xml"
    ]),
    ("Publimetro Colombia", "https://www.publimetro.co", "nacional_n3", 78.00, [
        "https://www.publimetro.co/sitemap.xml"
    ]),
    ("KienyKe", "https://www.kienyke.com", "nacional_n3", 78.00, [
        "https://www.kienyke.com/sitemap.xml"
    ]),
    ("Alerta Bogotá", "https://www.alertabogota.com", "nacional_n3", 74.00, [
        "https://www.alertabogota.com/sitemap.xml"
    ]),
    ("RTVC Noticias", "https://rtvcnoticias.com", "nacional_n3", 83.00, [
        "https://rtvcnoticias.com/sitemap.xml"
    ]),

    # --- PRENSA REGIONAL DE ALTO VOLUMEN ---
    ("El Colombiano (Antioquia)", "https://www.elcolombiano.com", "regional_n2", 82.00, [
        "https://www.elcolombiano.com/sitemap.xml",
        "https://www.elcolombiano.com/sitemap-news.xml"
    ]),
    ("El País Cali (Valle)", "https://www.elpais.com.co", "regional_n2", 80.00, [
        "https://www.elpais.com.co/sitemap.xml"
    ]),
    ("El Heraldo (Caribe/Barranquilla)", "https://www.elheraldo.co", "regional_n2", 79.00, [
        "https://www.elheraldo.co/sitemap.xml"
    ]),
    ("Vanguardia (Santander/Bucaramanga)", "https://www.vanguardia.com", "regional_n2", 78.00, [
        "https://www.vanguardia.com/sitemap.xml"
    ]),
    ("El Universal (Cartagena/Bolívar)", "https://www.eluniversal.com.co", "regional_n2", 77.00, [
        "https://www.eluniversal.com.co/sitemap.xml"
    ]),
    ("La Patria (Manizales/Caldas)", "https://www.lapatria.com", "regional_n2", 76.00, [
        "https://www.lapatria.com/sitemap.xml"
    ]),
    ("La Opinión (Cúcuta/Norte Santander)", "https://www.laopinion.com.co", "regional_n2", 75.00, [
        "https://www.laopinion.com.co/sitemap.xml"
    ]),
    ("El Nuevo Día (Tolima/Ibagué)", "https://www.elnuevodia.com.co", "regional_n2", 74.00, [
        "https://www.elnuevodia.com.co/sitemap.xml"
    ]),
    ("Diario del Huila (Neiva)", "https://diariodelhuila.com", "regional_n2", 73.00, [
        "https://diariodelhuila.com/sitemap.xml"
    ]),
    ("El Pilón (Valledupar/Cesar)", "https://elpilon.com.co", "regional_n2", 74.00, [
        "https://elpilon.com.co/sitemap.xml"
    ]),
    ("Boyacá 7 Días", "https://boyaca7dias.com.co", "regional_n2", 75.00, [
        "https://boyaca7dias.com.co/sitemap.xml"
    ]),
    ("Q'hubo Bogotá", "https://qhubobogota.com", "regional_n2", 70.00, [
        "https://qhubobogota.com/sitemap.xml"
    ]),
    ("Zona Cero (Barranquilla)", "https://zonacero.com", "regional_n2", 77.00, [
        "https://zonacero.com/sitemap.xml"
    ]),
    ("Seguimiento.co (Santa Marta)", "https://seguimiento.co", "regional_n2", 73.00, [
        "https://seguimiento.co/sitemap.xml"
    ]),
    ("Telemedellín", "https://telemedellin.tv", "regional_n2", 78.00, [
        "https://telemedellin.tv/sitemap_index.xml"
    ]),
    ("Teleantioquia", "https://teleantioquia.co", "regional_n2", 77.00, [
        "https://teleantioquia.co/sitemap.xml"
    ]),

    # --- FACT-CHECKERS, ACADEMIA E INSTITUCIONES ---
    ("ColombiaCheck", "https://colombiacheck.com", "factchecker_n4", 98.00, [
        "https://colombiacheck.com/sitemap.xml"
    ]),
    ("La Silla Vacía (Detector de Mentiras)", "https://www.lasillavacia.com", "factchecker_n4", 97.50, [
        "https://www.lasillavacia.com/sitemap_index.xml"
    ]),
    ("Agencia de Noticias UNAL", "https://agenciadenoticias.unal.edu.co", "factchecker_n4", 96.00, [
        "https://agenciadenoticias.unal.edu.co/sitemap.xml"
    ]),
    ("El País América - Colombia", "https://elpais.com/america-colombia", "internacional_n1", 95.00, [
        "https://elpais.com/sitemaps/seccion/america-colombia/news.xml"
    ])
]

def sincronizar_fuentes_colombia():
    conn = get_db_connection()
    mapa = {}
    with conn.cursor() as cur:
        for f_nom, f_url, f_tip, f_cred, sitemaps in FUENTES_COLOMBIA:
            cur.execute("""
                INSERT INTO fuentes (nombre, url_sitio, tipo_fuente, indice_credibilidad)
                VALUES (%s, %s, %s, %s)
                ON CONFLICT (nombre) DO UPDATE SET
                    url_sitio = EXCLUDED.url_sitio,
                    tipo_fuente = EXCLUDED.tipo_fuente,
                    indice_credibilidad = EXCLUDED.indice_credibilidad
                RETURNING id_fuente::text, nombre;
            """, (f_nom, f_url, f_tip, f_cred))
            row = cur.fetchone()
            mapa[row[1]] = (row[0], f_tip, sitemaps)
    conn.close()
    return mapa

MAPA_COLOMBIA = sincronizar_fuentes_colombia()

def cargar_urls_existentes() -> Set[str]:
    conn = get_db_connection()
    with conn.cursor() as cur:
        cur.execute("SELECT url_articulo FROM documentos;")
        urls = {r[0] for r in cur.fetchall()}
    conn.close()
    return urls

URLS_REGISTRADAS = cargar_urls_existentes()

# =====================================================================
# 3. FILTRO DE CONTENIDO LOCAL Y CATEGORIZACIÓN
# =====================================================================
PATRONES_EXCLUSION_MUNDO = [
    "/mundo/", "/internacional/", "/global/", "/estados-unidos/", 
    "/europa/", "/oriente-medio/", "/asia/", "/guerra-rusia", "/gaza",
    "/mexico/", "/argentina/", "/espana/", "/chile/", "/venezuela/"
]

def es_noticia_colombiana(url: str, titulo: str) -> bool:
    u_lower = url.lower()
    for p in PATRONES_EXCLUSION_MUNDO:
        if p in u_lower:
            return False
    return True

CATEGORIAS_OFICIALES = {
    "Finanzas y Economía": ["dolar", "trm", "renta", "subsidio", "iva", "salario", "gasolina", "acpm", "inflacion", "ipc", "banco", "precio", "pib"],
    "Gobierno y Política": ["reforma", "congreso", "senado", "cedula", "soat", "runt", "dian", "paz", "petro", "eleccion", "corte", "ley", "alcalde"],
    "Deportes": ["seleccion", "colombia", "partido", "copa", "betplay", "futbol", "james", "diaz", "gol", "liga", "ciclista"],
    "Entretenimiento y Farándula": ["concierto", "karol", "feid", "musica", "desafio", "masterchef", "reality", "estreno", "artista"],
    "Estilo de Vida y Sociedad": ["loteria", "baloto", "chance", "clima", "ideam", "lluvia", "sismo", "temblor", "salud", "accidente"],
    "Educación y Empleo": ["sena", "icfes", "saber", "convocatoria", "empleo", "vacante", "trabajo", "universidad", "estudio"]
}

class MetadataEnricherCol:
    @staticmethod
    def generar_sha256(url: str, contenido: str) -> str:
        return hashlib.sha256(f"{url.strip().lower()}::{contenido[:400]}".encode()).hexdigest()

    @classmethod
    def clasificar(cls, texto: str) -> str:
        t = texto.lower()
        scores = {cat: sum(1 for kw in kws if kw in t) for cat, kws in CATEGORIAS_OFICIALES.items()}
        m = max(scores, key=scores.get)
        return m if scores[m] > 0 else "Gobierno y Política"

    @staticmethod
    def estimar_sentimiento(texto: str) -> Tuple[str, float]:
        pos = sum(1 for kw in ["exito", "crecimiento", "avance", "victoria", "positivo", "acuerdo"] if kw in texto.lower())
        neg = sum(1 for kw in ["crisis", "muerte", "caida", "violencia", "fraude", "conflicto"] if kw in texto.lower())
        tot = pos + neg
        if tot == 0: return "Neutro", 0.0
        sc = (pos - neg) / tot
        lb = "Positivo" if sc > 0.15 else ("Negativo" if sc < -0.15 else "Neutro")
        return lb, round(sc, 2)

    @staticmethod
    def extraer_entidades(texto: str) -> str:
        raw = re.findall(r'\b[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)*\b', texto[:2500])
        stops = {"El", "La", "Los", "Las", "Un", "Una", "En", "Por", "Para", "Con", "De", "Del", "Al", "Según"}
        unicas = list(dict.fromkeys([e for e in raw if e not in stops and len(e) > 3]))[:10]
        return ", ".join(unicas) if unicas else "Colombia"

# =====================================================================
# 4. EXTRACCIÓN DE SITEMAPS PROFUNDOS (SIN TOPE DE 15)
# =====================================================================
class AsyncScraperColombia:
    def __init__(self, limit: int = 25):
        self.semaphore = asyncio.Semaphore(limit)

    async def fetch_url(self, session: AsyncSession, url: str) -> Optional[str]:
        for attempt in range(ConfigCol.MAX_RETRIES):
            try:
                async with self.semaphore:
                    r = await session.get(url, impersonate="chrome120", timeout=ConfigCol.REQUEST_TIMEOUT)
                    if r.status_code == 200 and r.content:
                        return r.text
                    elif r.status_code in [403, 429]:
                        await asyncio.sleep(0.8 * (2 ** attempt))
                    else:
                        return None
            except Exception:
                await asyncio.sleep(0.5)
        return None

    async def extraer_urls_sitemap_profundo(self, session: AsyncSession, sitemap_url: str) -> List[str]:
        """Recorre TODOS los sub-sitemaps hasta llenar la cuota sin límite arbitrario de 15."""
        urls = []
        xml_text = await self.fetch_url(session, sitemap_url)
        if not xml_text: return urls
        
        try:
            root = ET.fromstring(xml_text.encode('utf-8'))
            subs = [c.text.strip() for elem in root.iter() if elem.tag.endswith('sitemap') for c in elem if c.tag.endswith('loc') and c.text]
            
            if subs:
                # Recorre sub-sitemaps hasta alcanzar la cuota por fuente
                for sub in subs:
                    if len(urls) >= ConfigCol.MAX_URLS_POR_FUENTE:
                        break
                    sub_xml = await self.fetch_url(session, sub)
                    if sub_xml:
                        try:
                            sroot = ET.fromstring(sub_xml.encode('utf-8'))
                            for item in sroot.iter():
                                if item.tag.endswith('loc') and item.text:
                                    link = item.text.strip()
                                    if link.startswith('http') and not link.endswith(('.xml', '.jpg', '.png', '.pdf')):
                                        if es_noticia_colombiana(link, ""):
                                            urls.append(link)
                                            if len(urls) >= ConfigCol.MAX_URLS_POR_FUENTE: 
                                                break
                        except Exception:
                            continue
            else:
                for elem in root.iter():
                    if elem.tag.endswith('loc') and elem.text:
                        link = elem.text.strip()
                        if link.startswith('http') and not link.endswith(('.xml', '.jpg', '.png', '.pdf')):
                            if es_noticia_colombiana(link, ""):
                                urls.append(link)
                                if len(urls) >= ConfigCol.MAX_URLS_POR_FUENTE: 
                                    break
        except Exception:
            pass
        return list(set(urls))

    async def extraer_articulo(self, session: AsyncSession, url: str, nombre_fuente: str, fuente_uuid: str, tipo_fuente: str) -> Optional[Dict[str, Any]]:
        if url in URLS_REGISTRADAS:
            return None

        try:
            html = await self.fetch_url(session, url)
            if not html: return None

            data = trafilatura.bare_extraction(html, include_comments=False, with_metadata=True, include_tables=False)
            if not data: return None

            body = data.get("text", "") or ""
            if len(body.split()) < 45: return None

            title = (data.get("title") or "").strip()
            if len(title) < 5:
                slug = url.rstrip('/').split('/')[-1].replace('-', ' ').replace('_', ' ')
                title = slug.capitalize() if len(slug) > 10 else f"Noticia de {nombre_fuente}"

            if not es_noticia_colombiana(url, title):
                return None

            doc_id = MetadataEnricherCol.generar_sha256(url, body)

            pub_date = data.get("date")
            if pub_date:
                try:
                    dt = datetime.fromisoformat(str(pub_date).replace("Z", "+00:00"))
                    pub_date_iso = dt.isoformat()
                except Exception:
                    pub_date_iso = datetime.now(timezone.utc).isoformat()
            else:
                pub_date_iso = datetime.now(timezone.utc).isoformat()

            categoria = MetadataEnricherCol.clasificar(f"{title} {body[:400]}")
            sent_label, sent_score = MetadataEnricherCol.estimar_sentimiento(body)
            entidades = MetadataEnricherCol.extraer_entidades(f"{title}. {body[:2000]}")
            resumen = (data.get("description") or body[:350] or "").strip()
            word_count = len(body.split())

            metadata_dict = {
                "doc_id": doc_id,
                "title": title[:500],
                "url": url,
                "source_name": nombre_fuente,
                "domain_authority_type": tipo_fuente,
                "publication_date": pub_date_iso,
                "ingestion_timestamp": datetime.now(timezone.utc).isoformat(),
                "category": categoria,
                "named_entities": entidades,
                "sentiment": sent_label,
                "sentiment_score": sent_score,
                "language": "es",
                "summary": resumen[:1000],
                "word_count": word_count
            }

            return {
                "metadata": metadata_dict,
                "fuente_id": fuente_uuid,
                "body": body
            }
        except Exception:
            return None

# =====================================================================
# 5. VECTORIZACIÓN E INSERCIÓN EN NEON.TECH
# =====================================================================
class PipelineColombia:
    def __init__(self):
        print(f"🧠 Inicializando SentenceTransformer ({ConfigCol.MODEL_NAME}) en {ConfigCol.DEVICE.upper()}...")
        self.model = SentenceTransformer(ConfigCol.MODEL_NAME, device=ConfigCol.DEVICE)
        self.splitter = RecursiveCharacterTextSplitter(
            chunk_size=ConfigCol.CHUNK_SIZE,
            chunk_overlap=ConfigCol.CHUNK_OVERLAP,
            separators=["\n\n", "\n", ". ", " ", ""]
        )
        print("✅ Modelo cargado correctamente en memoria GPU.")

    def persistir_lote(self, articulos: List[Dict[str, Any]]) -> int:
        if not articulos: return 0

        filas_insercion = []
        textos_a_vectorizar = []
        metadata_records = []

        for art in articulos:
            meta = art["metadata"]
            fuente_id = art["fuente_id"]
            body = art["body"]

            chunks = self.splitter.split_text(body)
            total_chunks = len(chunks)

            for idx, chunk_text in enumerate(chunks):
                chunk_url = meta["url"] if idx == 0 else f"{meta['url']}#chunk-{idx}"

                c_meta = meta.copy()
                c_meta["chunk_index"] = idx
                c_meta["total_chunks"] = total_chunks

                texto_vector = f"{meta['title']}. {chunk_text[:1200]}"
                textos_a_vectorizar.append(texto_vector)

                metadata_records.append((
                    fuente_id,
                    meta["category"],
                    meta["title"][:490],
                    chunk_text[:1990],
                    chunk_url[:2040],
                    Json(c_meta),
                    meta["publication_date"],
                    meta["url"]
                ))

        if not textos_a_vectorizar: return 0

        embeddings = self.model.encode(
            textos_a_vectorizar,
            batch_size=128,
            show_progress_bar=False,
            normalize_embeddings=True
        )

        filas_sql = []
        for rec, emb in zip(metadata_records, embeddings):
            filas_sql.append((
                rec[0], rec[1], rec[2], rec[3], rec[4], emb.tolist(), rec[5], rec[6]
            ))

        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                query = """
                    INSERT INTO documentos (
                        fuente_id, categoria, titulo, fragmento, 
                        url_articulo, embedding, metadata, fecha_publicacion
                    )
                    VALUES %s
                    ON CONFLICT (url_articulo) DO NOTHING;
                """
                execute_values(cur, query, filas_sql)
        finally:
            conn.close()

        for rec in metadata_records:
            URLS_REGISTRADAS.add(rec[7])

        return len(articulos)

# =====================================================================
# 6. ORQUESTADOR DE INGESTA EXCLUSIVA COLOMBIA
# =====================================================================
async def ejecutar_ingesta_colombia():
    print("=" * 80)
    print("🇨🇴 REANUDANDO INGESTA: META 50,000 NOTICIAS DE COLOMBIA EN NEON.TECH")
    print("=" * 80)

    scraper = AsyncScraperColombia(limit=ConfigCol.CONCURRENT_REQUESTS)
    pipeline = PipelineColombia()

    total_actual = len(URLS_REGISTRADAS)
    pbar = tqdm(total=ConfigCol.TARGET_TOTAL_DOCS, initial=total_actual, desc="Noticias Colombia", unit="doc")

    if total_actual >= ConfigCol.TARGET_TOTAL_DOCS:
        print(f"🎉 Meta de {ConfigCol.TARGET_TOTAL_DOCS:,} ya alcanzada.")
        return

    buffer_articulos = []

    async with AsyncSession() as session:
        for nombre, (f_uuid, f_tipo, sitemaps) in MAPA_COLOMBIA.items():
            if not sitemaps: continue

            if len(URLS_REGISTRADAS) >= ConfigCol.TARGET_TOTAL_DOCS:
                print("\n🎯 Meta de 50,000 noticias alcanzada exitosamente.")
                break

            print(f"\n📰 Explorando archivos de: {nombre} ({f_tipo})")

            urls_candidatas = []
            for sm in sitemaps:
                encontradas = await scraper.extraer_urls_sitemap_profundo(session, sm)
                filtradas = [u for u in encontradas if u not in URLS_REGISTRADAS]
                urls_candidatas.extend(filtradas)
                if len(urls_candidatas) >= ConfigCol.MAX_URLS_POR_FUENTE:
                    break

            urls_candidatas = list(set(urls_candidatas))
            if not urls_candidatas: 
                print("   ↳ Sin URLs nuevas pendientes en esta fuente. Continuando...")
                continue

            print(f"   ↳ {len(urls_candidatas)} URLs colombianas nuevas detectadas...")

            tasks = [scraper.extraer_articulo(session, u, nombre, f_uuid, f_tipo) for u in urls_candidatas]
            for tarea in asyncio.as_completed(tasks):
                doc = await tarea
                if doc:
                    buffer_articulos.append(doc)

                    if len(buffer_articulos) >= ConfigCol.BATCH_SIZE:
                        insertados = pipeline.persistir_lote(buffer_articulos)
                        pbar.update(insertados)
                        buffer_articulos.clear()
                        print(f"   💾 +{insertados} noticias guardadas en Neon. Total acumulado: {len(URLS_REGISTRADAS):,}")

                        if len(URLS_REGISTRADAS) >= ConfigCol.TARGET_TOTAL_DOCS:
                            break

        if buffer_articulos:
            insertados = pipeline.persistir_lote(buffer_articulos)
            pbar.update(insertados)
            buffer_articulos.clear()

    pbar.close()
    print("=" * 80)
    print(f"🏁 PROCESO FINALIZADO. Total de noticias colombianas en Neon: {len(URLS_REGISTRADAS):,}")
    print("=" * 80)

if __name__ == "__main__":
    loop = asyncio.get_event_loop()
    if loop.is_running():
        asyncio.create_task(ejecutar_ingesta_colombia())
    else:
        asyncio.run(ejecutar_ingesta_colombia())
