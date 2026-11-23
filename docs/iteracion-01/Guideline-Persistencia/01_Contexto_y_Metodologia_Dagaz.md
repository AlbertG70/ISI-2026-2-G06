# DAGAZ — Contexto General, Metodología y Modelo de Negocio

> **Documento de Referencia Técnica y Estratégica**  
> **Proyecto:** DAGAZ (Sistema de Detección y Verificación de Desinformación)  
> **Organización:** ISI-CORP (2026-II)  
> **Autores Originales:** Alberto Mario Galvis Echeverria, Camilo Isaac Acevedo Rueda  
> **Fuente:** Análisis en profundidad de `Dagaz`

---

## 1. Naturaleza del Proyecto

### 1.1 Misión y Contexto
La desinformación digital representa un fallo crítico en la infraestructura informativa contemporánea. La conjunción de Inteligencia Artificial Generativa, plataformas sociales con difusión algorítmica sin fricción y el debilitamiento de la confianza en las instituciones tradicionales ha reducido la brecha entre la publicación de una noticia falsa y su viralización masiva: lo que antes tomaba días, hoy ocurre en minutos.

En el contexto de **Colombia**, esta problemática es especialmente aguda. Los ciclos electorales, las reformas legislativas, los acuerdos de paz y las campañas de salud pública han demostrado cómo afirmaciones falsas y sesgadas logran penetrar rápidamente a través de canales de mensajería (WhatsApp, Telegram) y redes sociales, alcanzando a cientos de miles de ciudadanos mucho antes de que los desmentidos oficiales o las unidades de fact-checking manual puedan reaccionar.

### 1.2 Definición Operativa del Dominio
El proyecto adopta una distinción taxonómica rigurosa:
* **Desinformación (*Disinformation*):** Información verificable que es deliberadamente falsa y se difunde con la intención intencional de engañar, manipular o causar daño.
* **Información errónea (*Misinformation*):** Falsedades o imprecisiones compartidas por usuarios sin intención de engañar (por desconocimiento o sesgo cognitivo).
* **Información maliciosa (*Malinformation*):** Información verídica descontextualizada o filtrada con el propósito específico de infligir daño personal, social o institucional.
* **Sesgos Cognitivos (*Cognitive Biases*):** Atajo mental automático que usa el cerebro para procesar información con rapidez, lo que lleva a errores en el juicio

### 1.3 Los Tres Fallos del Fact-Checking Tradicional
1. **Velocidad y Latencia:** La verificación manual tarda habitualmente entre 4 y 24 horas desde que una afirmación surge hasta que se publica un veredicto. Para entonces, el impacto en la opinión pública ya es irreversible.
2. **Asimetría de Cobertura:** Crear afirmaciones falsas es trivial, económico y escalable de forma masiva; verificarlas es costoso, secuencial y limitado al ancho de banda de equipos humanos. El periodismo de verificación suele concentrarse únicamente en temas de altísimo perfil, desatendiendo narrativas hiperlocales o rumores en comunidades vulnerables.
3. **Opacidad y Falta de Trazabilidad Máquina:** Los veredictos convencionales se presentan en formatos narrativos rígidos que no exponen sistemáticamente el corpus completo de fuentes consultadas, la ponderación de contradicciones ni permiten auditorías automatizadas de sesgo.

---

## 2. Objetivos del Proyecto

### 2.1 Objetivo General
Construir un sistema integral para la detección y mitigación de desinformación apoyándose en tecnologías de la Internet, análisis automatizado de fuentes y modelos de inteligencia artificial.

### 2.2 Objetivos Específicos
1. **Identificación de Requisitos:** Formalizar los requisitos funcionales, no funcionales y el modelo de datos para un sistema de revisión multicanal capaz de emitir veredictos basados en fuentes verificadas.
2. **Diseño de Arquitectura:** Diseñar la arquitectura por capas y esquemas del sistema (ingesta multicanal, descomposición de afirmaciones, verificación cruzada con ponderación de credibilidad y generación de veredictos explicables).
3. **Desarrollo del Sistema:** Implementar la plataforma tecnológica combinando un backend robusto en **Laravel (PHP 8.4)** con **MariaDB**, microservicios de soporte en **Python** (procesamiento de lenguaje, OCR/TTS y análisis de datos) y una interfaz móvil responsiva basada en **Blade**.
4. **Validación y Pruebas:** Ejecutar pruebas de usabilidad, pruebas unitarias e integración (PHPUnit, cobertura ≥80%) y auditorías de confiabilidad/sesgo para garantizar la veracidad, rendimiento y apego normativo.

---

## 3. Metodología de Desarrollo: Scrum

El proyecto se rige por un marco de trabajo ágil iterativo e incremental basado en **Scrum**, con Sprints de **2 semanas de duración**.

### 3.1 Dinámicas y Reglas
* **Principio de Entrega Continua:** Al culminar cada Sprint, el equipo debe desplegar un *Incremento de Producto* potencialmente desplegable y demostrable en vivo.
* **Inmutabilidad del Sprint Goal:** Una vez iniciado el Sprint, no se alteran las metas que comprometan el objetivo acordado.
* **Calidad No Negociable:** La cobertura de pruebas, las directrices de seguridad y los criterios de aceptación no se degradan por restricciones de tiempo.

### 3.2 Eventos del Sprint
| Evento | Frecuencia / Duración | Participantes | Propósito Central |
| :--- | :--- | :--- | :--- |
| **Sprint Planning** | Inicio de Sprint (máx. 2h) | Scrum Team completo | Definir el Sprint Goal y seleccionar ítems del Product Backlog desglosados en tareas técnicas. |
| **Daily Scrum** | Diario (15 minutos) | Desarrolladores (SM opcional) | Sincronización técnica: ¿Qué se logró ayer? ¿Qué se hará hoy? ¿Qué impedimentos existen? |
| **Sprint Review** | Fin de Sprint (1h) | Scrum Team + Stakeholders | Demostración en vivo del software funcional y recopilación de retroalimentación. |
| **Sprint Retrospective** | Fin de Sprint (45 min) | Scrum Team exclusivamente | Inspección y adaptación interna del equipo: personas, relaciones, procesos y herramientas. |

---

## 4. Product Backlog Priorizado

El backlog está segmentado en 4 prioridades estratégicas, sumando un total de **28 requisitos y 157 puntos de historia**.

### 4.1 Prioridad 1: Esqueleto Funcional (45 Puntos)
*Objetivo: El sistema de extremo a extremo más pequeño que genera un veredicto.*
* **REQ-01:** Extractor de afirmaciones: aislar la afirmación verificable de un titular (F1 ≥ 0.75 sobre 100 afirmaciones etiquetadas). *(8 pts)*
* **REQ-02:** OCR y capturas de pantalla: extraer texto de imágenes/memes con modelo de visión-lenguaje. *(5 pts)*
* **REQ-03:** Agente de descomposición: dividir afirmaciones compuestas en unidades atómicas verificables. *(8 pts)*
* **REQ-04:** Agente de verificación cruzada v1: comparar la evidencia recuperada ponderando según el nivel de la fuente. *(8 pts)*
* **REQ-05:** Agente de recuperación de evidencia v1: herramienta de búsqueda web que recupera ≥5 fuentes candidatas por afirmación atómica. *(8 pts)*
* **REQ-06:** Agente de síntesis del veredicto: genera etiqueta (Verdadero / Falso / Engañoso / No verificable) + confianza + trazabilidad. *(8 pts)*
* **REQ-07:** Demo de «esqueleto funcional»: veredicto integral sobre afirmación electoral con fuentes de nivel 1. *(3 pts)*
* **REQ-08:** Grafo de credibilidad v1: registrar 10 fuentes internacionales nivel 1 (Reuters, AP, AFP, BBC, OMS, ONU, OCDE, The Guardian, El País, EFE) con metadatos. *(5 pts)*

### 4.2 Prioridad 2: Demo Utilizable (44 Puntos)
* **REQ-09:** Monitor de redes sociales: extraer frases virales a través de APIs aprobadas. *(8 pts)*
* **REQ-10:** Herramienta de consulta Archive.org: recuperación de versiones históricas para detectar descontextualización. *(5 pts)*
* **REQ-11:** Conjunto de referencia de 500 afirmaciones etiquetadas manualmente (acuerdo inter-evaluador ≥ 0.75). *(8 pts)*
* **REQ-12:** Diseño de Base de Datos: usuarios (Adultos, Administradores), afirmaciones, veredictos, fuentes y notas. *(8 pts)*
* **REQ-13:** Entorno de trabajo para revisores humanos: panel de edición para veredictos de baja confianza. *(8 pts)*
* **REQ-14:** Grafo de credibilidad v2: ampliar a 40 fuentes (prensa regional nivel 2 y prensa nacional colombiana nivel 3). *(5 pts)*
* **REQ-15:** Interfaz de tarjeta de veredicto: vista responsiva con veredicto, confianza, fuentes y explicación. *(5 pts)*
* **REQ-16:** API pública v1: endpoint REST para integración de redacciones aliadas. *(5 pts)*

### 4.3 Prioridad 3: Listo para Pruebas Piloto (44 Puntos)
* **REQ-17:** Auditoría de sesgo v1: evaluación estructurada de sesgo político en el conjunto de referencia electoral. *(8 pts)*
* **REQ-18:** Controles de costes: presupuestos de API de LLM por agente con circuit breaker automático. *(5 pts)*
* **REQ-19:** Recuperación interlingüe: gestionar afirmaciones de origen inglés que circulan en Colombia. *(8 pts)*
* **REQ-20:** Grafo de credibilidad v3: ampliación a 80 fuentes (incorporar fact-checkers IFCN: ColombiaCheck, AFP Factual, Chequeado, Snopes). *(8 pts)*
* **REQ-21:** Prueba de carga: ejecución continua de 30 días con flujo de reclamaciones y disponibilidad ≥99%. *(8 pts)*
* **REQ-22:** Incorporación de socios: primera redacción en producción, 50 veredictos consumidos. *(5 pts)*
* **REQ-23:** Incorporación de Base de Datos: primeros Adultos de prueba en base de datos productiva. *(5 pts)*
* **REQ-24:** Evaluación formal: precisión ≥ 0.80, exhaustividad ≥ 0.75 en benchmark de 500 afirmaciones. *(5 pts)*

### 4.4 Prioridad 4: Pulir y Traspaso (24 Puntos)
* **REQ-25:** Archivo de registro de auditoría (auditable para reguladores). *(5 pts)*
* **REQ-26:** Incorporación de las redacciones de los socios segundo y tercero. *(8 pts)*
* **REQ-27:** Manual de procedimientos de despliegue + manuales de usuario (Adulto y Administrador). *(8 pts)*
* **REQ-28:** Retrospectiva final y cierre de transición. *(3 pts)*

---

## 5. Modelo de Negocio Canvas (Resumen Estratégico)

* **Propuesta de Valor:** Detección de desinformación en tiempo real; contraste con fuentes jerarquizadas y auditables; detección de amarillismo y sensacionalismo; explicación clara y transparente de cada veredicto.
* **Segmento de Clientes:** Ciudadanos adultos (enfoque primordial en adultos mayores de 40 años propensos a cadenas de mensajería), redacciones periodísticas y organizaciones de la sociedad civil.
* **Canales:** Aplicación web móvil responsiva (Blade), APIs para salas de redacción, extensiones para navegadores (futuro) y bots de mensajería (futuro).
* **Relaciones con Clientes:** Interfaz intuitiva y legible, notas comunitarias tipo Twitter/X Community Notes, historial personal de consultas, explicaciones detalladas.
* **Fuentes de Ingreso:** Licenciamiento de la API REST para medios de comunicación, servicios de auditoría y análisis de desinformación para organizaciones, y publicidad no invasiva dentro de la aplicación.
* **Recursos Clave:** Algoritmos de extracción y descomposición, base de datos de fuentes confiables con índices de credibilidad, infraestructura en la nube y modelos LLM.
* **Aliados Clave:** Medios de comunicación y verificadores de datos certificados (IFCN), centros de investigación universitaria, proveedores de cloud services y APIs de LLM.
* **Estructura de Costos:** Talento humano (desarrollo y análisis), infraestructura de servidores y bases de datos en la nube, consumo de tokens de LLM y licenciamiento de software.

---

## 6. Presupuesto Base del Proyecto

| Ítem | Categoría | Descripción | Cant. | Unidad | Valor Unitario (COP) | Total (COP) |
| :--- | :--- | :--- | :---: | :---: | :---: | :---: |
| **Desarrollador Full Stack Junior** | Talento Humano | Desarrollo Full Stack - 16 semanas (Remoto) | 1 | Contrato Mes | $4.100.000 | $16.400.000 |
| **Analista de Requerimientos Junior** | Talento Humano | Identificación y documentación - 16 semanas (Remoto) | 1 | Contrato Mes | $3.200.000 | $12.800.000 |
| **Enterprise Architect (Corporate)** | Software / Licencias | Licencia para modelado UML de software | 1 | Licencia | $916.000 | $916.000 |
| **TablePlus (Single License)** | Software / Licencias | Licencia para administración de bases de datos | 1 | Licencia | $236.000 | $236.000 |
| **GitHub Team (2 usuarios)** | Software / Licencias | Suscripción por 4 meses (21 USD/mes/usuario) | 8 | Mes-Usuario | $66.000 | $520.000 |
| **Fondo de Imprevistos (5%)** | Contingencias | Reserva para cambios de alcance o emergencias | 1 | Global | $1.543.600 | $1.543.600 |
| **TOTAL ESTIMADO** | | | | | | **$32.415.600 COP** |
