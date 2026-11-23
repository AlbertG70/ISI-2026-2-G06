# DAGAZ — Diseño de la Base de Datos (PostgreSQL 18 + pgvector en Neon.tech)

> **Documento de Especificación del Modelo Relacional y Diccionario de Datos**  
> ***Motor de Base de Datos:*** PostgreSQL 18 con extensión `pgvector`  
> **Entorno de Despliegue:** Neon.tech Free Tier (Serverless Postgres con autoscaling: 0.5 GB almacenamiento, 2 CU / 2 GB RAM)  
> ***Estrategia Vectorial:*** Embeddings de 384 dimensiones (`vector(384)`, modelo `all-MiniLM-L6-v2`)  
> **Estrategia de Indexación Vectorial:** **SIN índices HNSW/IVFFlat**. Búsqueda semántica KNN exacta desde disco SSD para evitar agotamiento de memoria RAM en el tier gratuito.  
> **Codificación:** UTF-8 nativo  
> **Tipos de Identificador:** `UUID` nativo (16 bytes) vía `gen_random_uuid()`  

---

## 1. Diagrama Entidad-Relación (DER)

Se encuentra en [`Diagrama-ER.md`](Diagrama-ER.md).

## 2. Diccionario de Datos Exhaustivo (10 Tablas)

### 2.1 Tabla `usuarios`
Almacena credenciales y perfiles de usuarios (adultos mayores, verificadores y administradores).
| Campo | Tipo | Nulo | Por Defecto | Descripción y Reglas de Negocio |
| :--- | :--- | :---: | :---: | :--- |
| `id_usuario` | `UUID` | NO | `gen_random_uuid()` | Identificador único (Clave Primaria, 16 bytes). |
| `email` | `VARCHAR(255)` | NO | - | Correo electrónico único (`uk_usuarios_email`). |
| `password_hash` | `VARCHAR(255)` | NO | - | Contraseña cifrada (Argon2id / Bcrypt). |
| `rol` | `TEXT` | NO | `'adulto'` | Rol del usuario: `'lector'`, `'adulto'`, `'admin'`, `'editor'`. Validado por CHECK constraint. |
| `fecha_creacion`| `TIMESTAMPTZ` | NO | `NOW()` | Marca temporal con zona horaria UTC. |

### 2.2 Tabla `fuentes`
Grafo de credibilidad y catálogo de medios, agencias y verificadores oficiales (80 fuentes target).
| Campo | Tipo | Nulo | Por Defecto | Descripción y Reglas de Negocio |
| :--- | :--- | :---: | :---: | :--- |
| `id_fuente` | `UUID` | NO | `gen_random_uuid()` | Identificador único de la fuente (Clave Primaria). |
| `nombre` | `VARCHAR(200)` | NO | - | Nombre único de la entidad/medio (`uk_fuentes_nombre`). |
| `url_sitio` | `VARCHAR(2048)` | NO | - | Dominio web raíz o endpoint de la fuente. |
| `tipo_fuente` | `TEXT` | NO | `'nacional_n3'` | Categorización IFCN / tipo de fuente en migración y catálogo (`OFICIAL`, `PERIODISTICO`, `ACADEMICO`, `RED_SOCIAL`, `OTRO` en tabla operativa; jerarquía de 4 niveles en grafo). |
| `indice_credibilidad` | `DECIMAL(5,2)` | NO | 50.00 | Puntuación algorítmica y editorial (0.00 a 100.00). |
| `fecha_registro` | `TIMESTAMPTZ` | NO | `NOW()` | Fecha de alta en el sistema. |

### 2.3 Tabla `documentos` (★ Núcleo de Búsqueda Semántica Vectorial)
Almacena noticias, artículos y fragmentos atómicos indexados provenientes de las 80 fuentes de información, enriquecidos con representaciones vectoriales densas para recuperación semántica.
| Campo | Tipo | Nulo | Por Defecto | Descripción y Reglas de Negocio |
| :--- | :--- | :---: | :---: | :--- |
| `id_documento` | `UUID` | NO | `gen_random_uuid()` | Identificador único del fragmento/artículo (Clave Primaria). |
| `fuente_id` | `UUID` | NO | - | FK hacia `fuentes.id_fuente` (`ON DELETE RESTRICT`). |
| `categoria` | `VARCHAR(100)` | NO | `'Gobierno y Política'` | Categoría de metadata: `'Finanzas y Economía'`, `'Gobierno y Política'`, `'Deportes'`, `'Entretenimiento y Farándula'`, `'Estilo de Vida y Sociedad'`, `'Educación y Empleo'`. |
| `titulo` | `VARCHAR(500)` | NO | - | Titular o encabezado original del artículo/noticia. |
| `fragmento` | `VARCHAR(2000)` | NO | - | Fragmento textual depurado (chunk) para contexto de IA. Acotado para optimizar los 0.5 GB de Neon. |
| `url_articulo` | `VARCHAR(2048)` | NO | - | Enlace canónico al artículo original (**UNIQUE** vía índice `uk_documentos_url`). |
| `embedding` | `vector(384)` | SÍ | NULL | Vector denso de 384 dimensiones (`all-MiniLM-L6-v2`). Ocupa exactamente 1,536 bytes por fila. |
| `fecha_publicacion` | `TIMESTAMPTZ` | SÍ | NULL | Fecha de publicación reportada por el medio. |
| `fecha_indexacion` | `TIMESTAMPTZ` | NO | `NOW()` | Fecha y hora en que fue indexado por el scraper/pipeline. |

### 2.4 Tabla `contenidos`
Registra los titulares, textos, capturas de pantalla, audios o URLs que los usuarios someten a análisis.
| Campo | Tipo | Nulo | Por Defecto | Descripción y Reglas de Negocio |
| :--- | :--- | :---: | :---: | :--- |
| `id_contenido` | `UUID` | NO | `gen_random_uuid()` | Identificador único del contenido (Clave Primaria). |
| `id_usuario` | `UUID` | SÍ | NULL | FK hacia `usuarios.id_usuario` (`ON DELETE SET NULL`). |
| `tipo_entrada` | `TEXT` | NO | `'TEXTO'` | `'TEXTO'`, `'URL'`, `'IMAGEN_OCR'`, `'RED_SOCIAL'`. |
| `contenido_original` | `TEXT` | NO | - | Texto sin procesar provisto por el usuario o transcrito por OCR/STT. |
| `url_origen` | `VARCHAR(2048)` | SÍ | NULL | Enlace HTTP(S) original si la entrada fue extraída de la web o red social. |
| `estado` | `TEXT` | NO | `'PENDIENTE'` | `'PENDIENTE'`, `'PROCESANDO'`, `'VERIFICADO'`, `'ERROR'`. |
| `fecha_subida` | `TIMESTAMPTZ` | NO | `NOW()` | Momento en que el contenido ingresó al sistema. |

### 2.5 Tabla `procesos_verificacion`
Contiene la telemetría, descomposición atómica y evaluación semántica del motor de IA.
| Campo | Tipo | Nulo | Por Defecto | Descripción y Reglas de Negocio |
| :--- | :--- | :---: | :---: | :--- |
| `id_proceso` | `UUID` | NO | `gen_random_uuid()` | Identificador único del proceso (Clave Primaria). |
| `id_contenido` | `UUID` | NO | - | FK hacia `contenidos.id_contenido` (`ON DELETE CASCADE`). |
| `score_confiabilidad` | `DECIMAL(5,2)` | NO | 0.00 | Puntuación algorítmica calculada (rango 0.00 a 100.00). |
| `analisis_lenguaje` | `JSONB` | SÍ | NULL | JSON estructurado con sesgo sintáctico, emotividad y tono sensacionalista. |
| `contraste_temas` | `JSONB` | SÍ | NULL | Desglose atómico de afirmaciones contrastadas contra evidencia documental y votos del Consejo Multi-Modelo. |
| `fecha_analisis` | `TIMESTAMPTZ` | NO | `NOW()` | Marca temporal de culminación del proceso analítico. |

### 2.6 Tabla `veredictos`
Resultado formal de la verificación entregado al usuario final y redacciones aliadas.
| Campo | Tipo | Nulo | Por Defecto | Descripción y Reglas de Negocio |
| :--- | :--- | :---: | :---: | :--- |
| `id_veredicto` | `UUID` | NO | `gen_random_uuid()` | Identificador único del veredicto (Clave Primaria). |
| `id_contenido` | `UUID` | NO | - | FK hacia `contenidos.id_contenido` (`ON DELETE CASCADE`). **UNIQUE** (un solo veredicto vigente por contenido). |
| `id_proceso` | `UUID` | NO | - | FK hacia `procesos_verificacion.id_proceso` (`ON DELETE CASCADE`). |
| `tipo_veredicto` | `TEXT` | NO | - | `'VERDADERO'`, `'FALSO'`, `'ENGAÑOSO'`, `'PARCIAL'`, `'NO_VERIFICABLE'`. |
| `nivel_confiabilidad` | `DECIMAL(5,2)` | NO | 0.00 | Certeza cuantitativa (0.00 a 100.00). |
| `explicacion` | `TEXT` | NO | - | Justificación narrativa legible para usuarios adultos. |
| `fecha_emision` | `TIMESTAMPTZ` | NO | `NOW()` | Fecha y hora de emisión del veredicto. |

### 2.7 Tabla `veredicto_fuentes`
Tabla asociativa (pivote N:M) que garantiza la trazabilidad y procedencia de cada fuente utilizada para sustentar el veredicto.
| Campo | Tipo | Nulo | Por Defecto | Descripción y Reglas de Negocio |
| :--- | :--- | :---: | :---: | :--- |
| `id_veredicto` | `UUID` | NO | - | FK hacia `veredictos.id_veredicto` (`ON DELETE CASCADE`). |
| `id_fuente` | `UUID` | NO | - | FK hacia `fuentes.id_fuente` (`ON DELETE RESTRICT` para proteger evidencia histórica). |
| `peso_en_veredicto` | `DECIMAL(5,2)` | NO | 1.00 | Ponderación relativa que tuvo la fuente en la decisión. |
| `cita_contraste` | `TEXT` | SÍ | NULL | Extracto textual específico que refuta o valida la afirmación. |
| **PK compuesta** | `(id_veredicto, id_fuente)` | | | Clave primaria compuesta para unicidad de la relación. |

### 2.8 Tabla `notas`
Notas complementarias elaboradas por la comunidad (*Community Notes*).
| Campo | Tipo | Nulo | Por Defecto | Descripción y Reglas de Negocio |
| :--- | :--- | :---: | :---: | :--- |
| `id_nota` | `UUID` | NO | `gen_random_uuid()` | Identificador único de la nota (Clave Primaria). |
| `id_usuario` | `UUID` | NO | - | FK hacia `usuarios.id_usuario` (`ON DELETE CASCADE`). |
| `id_contenido` | `UUID` | NO | - | FK hacia `contenidos.id_contenido` (`ON DELETE CASCADE`). |
| `contenido_nota` | `TEXT` | NO | - | Texto de la aclaración o enlace complementario. |
| `calificacion` | `DECIMAL(3,1)` | NO | 0.0 | Calificación promedio comunitaria (-1.0 a 5.0). |
| `fecha_creacion` | `TIMESTAMPTZ` | NO | `NOW()` | Fecha de redacción. |

### 2.9 Tabla `historial`
Registro cronológico de actividad de los usuarios.
| Campo | Tipo | Nulo | Por Defecto | Descripción y Reglas de Negocio |
| :--- | :--- | :---: | :---: | :--- |
| `id_historial` | `UUID` | NO | `gen_random_uuid()` | Identificador único del evento (Clave Primaria). |
| `id_usuario` | `UUID` | NO | - | FK hacia `usuarios.id_usuario` (`ON DELETE CASCADE`). |
| `id_contenido` | `UUID` | NO | - | FK hacia `contenidos.id_contenido` (`ON DELETE CASCADE`). |
| `accion` | `VARCHAR(100)` | NO | - | `'verificado'`, `'guardado'`, `'valorado'`, `'eliminado'`. |
| `fecha_evento` | `TIMESTAMPTZ` | NO | `NOW()` | Marca temporal del evento. |

### 2.10 Tabla `logs_comunicador_api`
Auditoría y control de consumo de APIs externas (LLMs, OpenRouter, OCR, scrapers).
| Campo | Tipo | Nulo | Por Defecto | Descripción y Reglas de Negocio |
| :--- | :--- | :---: | :---: | :--- |
| `id_log` | `UUID` | NO | `gen_random_uuid()` | Identificador único del log (Clave Primaria). |
| `id_proceso` | `UUID` | SÍ | NULL | FK hacia `procesos_verificacion.id_proceso` (`ON DELETE SET NULL`). |
| `servicio` | `VARCHAR(100)` | NO | - | Proveedor consultado (ej. `'OpenAI'`, `'OpenRouter'`, `'Gemini'`, `'Anthropic'`, `'Scraper'`, `'Archive.org'`). |
| `tipo_operacion` | `VARCHAR(50)` | NO | - | `'descomposicion'`, `'ocr'`, `'busqueda_fuentes'`, `'evaluacion'`. |
| `codigo_respuesta`| `INT` | SÍ | NULL | Código HTTP (200, 429, 500). |
| `detalle_error` | `TEXT` | SÍ | NULL | Traza de error para diagnóstico. |
| `fecha_registro` | `TIMESTAMPTZ` | NO | `NOW()` | Marca temporal de la petición. |

---

## 3. Índices Estratégicos y Política de Rendimiento

### 3.1 Política Vectorial: Cero Índices HNSW / IVFFlat
En el Free Tier de Neon.tech (2 GB de RAM compartida con el SO y buffer cache), la construcción y persistencia de índices vectoriales `HNSW` consume cantidades significativas de memoria de trabajo, provocando eventos de *Out of Memory* (OOM) o reinicios del compute node.
* **Solución adoptada:** Se utiliza **búsqueda KNN exacta** (`ORDER BY embedding <=> $1::vector(384) LIMIT 10`) directamente leída desde SSD NVMe.
* **Desempeño:** Para un corpus de hasta 120,000 vectores de 384 dimensiones, un sequential scan vectorizado en PostgreSQL toma entre **15 y 45 ms**, lo cual está muy por debajo del SLA de respuesta del sistema (< 2 segundos).

### 3.2 Índices B-Tree Relacionales
Los índices tradicionales B-Tree optimizan los filtros relacionales sin penalizar la memoria:
```sql
-- Unicidad de artículos y aceleración de consultas por categoría y fuente
CREATE UNIQUE INDEX uk_documentos_url ON documentos(url_articulo);
CREATE INDEX idx_documentos_fuente    ON documentos(fuente_id);
CREATE INDEX idx_documentos_categoria ON documentos(categoria);
CREATE INDEX idx_documentos_fecha     ON documentos(fecha_publicacion DESC NULLS LAST);

-- Consultas de usuario y estado del pipeline
CREATE INDEX idx_contenidos_usuario ON contenidos(id_usuario);
CREATE INDEX idx_contenidos_estado  ON contenidos(estado);

-- Joins rápidos de procesos y veredictos
CREATE INDEX idx_procesos_contenido ON procesos_verificacion(id_contenido);
CREATE INDEX idx_veredictos_tipo    ON veredictos(tipo_veredicto);

-- Consultas de notas comunitarias e historial
CREATE INDEX idx_notas_contenido         ON notas(id_contenido);
CREATE INDEX idx_notas_usuario           ON notas(id_usuario);
CREATE INDEX idx_historial_usuario_fecha ON historial(id_usuario, fecha_evento DESC);
CREATE INDEX idx_logs_proceso            ON logs_comunicador_api(id_proceso);
```

### 3.3 Patrón Recomendado de Búsqueda Semántica con Pre-filtrado Relacional
Para consultar fuentes específicas sin escanear toda la tabla:
```sql
WITH candidatos AS (
    SELECT id_documento, fuente_id, titulo, fragmento, url_articulo, embedding
    FROM documentos
    WHERE fuente_id = ANY($2::uuid[])
)
SELECT 
    c.id_documento,
    c.titulo,
    c.fragmento,
    c.url_articulo,
    1 - (c.embedding <=> $1::vector(384)) AS similitud
FROM candidatos c
ORDER BY c.embedding <=> $1::vector(384)
LIMIT 10;
```

---

## 4. Presupuesto de Almacenamiento (Límite 0.5 GB en Neon.tech)

| Componente | Estimación Unitaria | Total para 100,000 Chunks |
| :--- | :--- | :--- |
| `embedding` (`vector(384)`) | 384 × 4 bytes = 1,536 bytes | ~150 MB |
| Metadatos (`titulo`, `fragmento`, `url`, `fechas`, PK/FK) | ~800 bytes promedio | ~80 MB |
| Overhead de Tupla PostgreSQL + Índices B-Tree | ~150 bytes | ~15 MB |
| **Subtotal Tabla `documentos`** | **~2.5 KB / fila** | **~245 MB** |
| Tablas de Soporte (`usuarios`, `contenidos`, `veredictos`, `notas`, etc.) | Variable | ~50 MB |
| **Total Estimado en Operación** | | **~295 MB (59% del límite de 512 MB)** |

> **Directriz de Capacidad:** Mantener el corpus indexado por debajo de **120,000 fragmentos** para garantizar un margen de seguridad del 25% en el disco de Neon.tech.

### 4.1 Métricas Reales Auditadas en Producción (Septiembre – Noviembre 2026)
* **Catálogo de Fuentes:** 80 fuentes de información activas en la tabla `fuentes` (Niveles 1 al 4 completados).
* **Corpus Indexado:** Más de 9.000 artículos reales de medios colombianos e internacionales con URLs navegables activas, categorizados en las 6 metadatas del proyecto y ampliables mediante [`../ingesta_noticias_colombia.py`](../ingesta_noticias_colombia.py), `NoticiasColombiaSeeder` y `php artisan dagaz:actualizar-noticias`.
* **Consumo Real en Disco:** ~22.4 MB (~4.37% del límite de 512 MB en Neon Free Tier).
* **Latencia de Búsqueda KNN Exacta:** 10 ms a 25 ms promedio por consulta sin índices vectoriales, complementada con normalización sin tildes (`normalizarSinTildes`) en `BusquedaSemanticaService` para PostgreSQL.
* **Soporte de Autenticación UUID:** Migración `personal_access_tokens` adaptada a `tokenable_id` tipo `UUID` para tokens Sanctum nativos en PostgreSQL Neon.
