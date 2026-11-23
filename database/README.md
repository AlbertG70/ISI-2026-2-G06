# Base de Datos — DAGAZ (PostgreSQL 18 + pgvector en Neon.tech)

Esta carpeta contiene los archivos de diseño y scripts de base de datos del proyecto **DAGAZ** (con soporte primario en PostgreSQL 18 + `pgvector` y compatibilidad de migración con MariaDB / SQLite).

- [x] Diagrama Entidad-Relación Actualizado (`Diagrama-ER.md`)
- [x] Documentación completa de la base de datos (`Documentacion-Raiden.md`), tablas, índices estratégicos y política de rendimiento
- [x] Índices B-Tree Relacionales
- [x] Presupuesto de Almacenamiento (MVP y Corpus Colombia 2026)

## Entregables

- **`Raiden.sql`**: Script DDL oficial completo de creación del esquema relacional y vectorial en PostgreSQL 18 con la extensión `pgvector` (10 tablas, relaciones, claves foráneas, índices B-Tree y semillas iniciales del Grafo de Credibilidad).
- **`Documentacion-Raiden.md`**: Especificación exhaustiva del modelo relacional, diccionario de datos completo de las 10 tablas, política de rendimiento vectorial (búsqueda KNN exacta en SSD sin índices HNSW/IVFFlat para el Free Tier de Neon), presupuesto de almacenamiento y métricas auditadas.
- **`Diagrama-ER.md`**: Diagrama Entidad-Relación (DER en Mermaid) visualizando la estructura de las 10 tablas, claves primarias/foráneas y cardinalidades del sistema.

## Esquema de Tablas (10 Tablas Oficiales + Sanctum UUID)

| Tabla | Tipo | Descripción |
|:--|:--|:--|
| `usuarios` | Relacional | Cuentas de usuario con roles (`lector`, `adulto`, `admin`, `editor`). |
| `fuentes` | Relacional | Grafo de credibilidad con 80 fuentes oficiales y sus índices de confiabilidad. |
| `documentos` | **Vectorial** | **★ Núcleo Semántico**: Artículos indexados con embeddings de 384 dimensiones (`vector(384)`, `all-MiniLM-L6-v2`) para búsqueda semántica KNN exacta y normalización SQL sin tildes. |
| `contenidos` | Relacional | Entradas a verificar enviadas por usuarios o redacciones (`TEXTO`, `URL`, `IMAGEN_OCR`, `RED_SOCIAL`). |
| `procesos_verificacion` | Relacional | Trazabilidad del pipeline de verificación, telemetría y scores algorítmicos. |
| `veredictos` | Relacional | Veredictos emitidos (`VERDADERO`, `FALSO`, `ENGAÑOSO`, `PARCIAL`, `NO_VERIFICABLE`). |
| `veredicto_fuentes` | Relacional (Pivote) | Relación N:M entre veredictos y fuentes contrastadas con peso y cita de contraste. |
| `notas` | Relacional | Notas complementarias colaborativas (*Community Notes*) y calificaciones. |
| `historial` | Relacional | Bitácora de eventos y acciones de usuarios. |
| `logs_comunicador_api` | Relacional | Telemetría y auditoría de llamadas a servicios externos, Consejo Multi-Modelo e IA. |
| `personal_access_tokens` | Migración Laravel | Tabla de tokens Bearer Sanctum adaptada con `tokenable_id` tipo `UUID` para interoperabilidad nativa con `usuarios` en PostgreSQL Neon (`2026_09_24_214547_create_personal_access_tokens_table.php`). |

## Estrategia Vectorial (`pgvector`) e Ingesta Continua

- **Dimensión:** 384 dimensiones (`vector(384)`).
- **Modelo:** `all-MiniLM-L6-v2` (SentenceTransformers).
- **Indexación Vectorial:** Búsqueda KNN exacta directa en disco SSD NVMe (`ORDER BY embedding <=> $1::vector(384) LIMIT 10`), evitando índices HNSW/IVFFlat que agotarían la memoria RAM (2 GB) del Free Tier de Neon.tech.
- **Pipeline de Alimentación de Corpus:**
  - **Ingesta RSS continua en Laravel:** comando Artisan `php artisan dagaz:actualizar-noticias` y seeders [`NoticiasColombiaSeeder.php`](../backend/database/seeders/NoticiasColombiaSeeder.php) / [`DocumentosNoticiasSeeder.php`](../backend/database/seeders/DocumentosNoticiasSeeder.php).
  - **Ingesta masiva Python/Colab:** scripts [`ingesta_noticias_colombia.py`](../ingesta_noticias_colombia.py) e [`ingesta_noticias_colombia.ipynb`](../ingesta_noticias_colombia.ipynb) para extracción y vectorización en lote de medios colombianos hacia `documentos`.
- **Latencia de Consulta:** 10 ms a 35 ms por consulta para el corpus indexado.
