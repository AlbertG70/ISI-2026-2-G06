# DAGAZ — Contexto General, Metodología y Modelo de Negocio

> **Documento de Referencia Técnica y Estratégica**  
> **Proyecto:** DAGAZ (Sistema de Detección y Verificación de Desinformación)  
> **Organización:** ISI-CORP (2026-II)  
> **Autores Originales:** Alberto Mario Galvis Echeverria, Camilo Isaac Acevedo Rueda  
> **Actualización de Contexto:** Noviembre 2026 (`v1.4.1`)

---

## 1. Naturaleza del Proyecto

### 1.1 Misión y Contexto
La desinformación digital representa un fallo crítico en la infraestructura informativa contemporánea. La conjunción de Inteligencia Artificial Generativa, plataformas sociales con difusión algorítmica sin fricción y el debilitamiento de la confianza en las instituciones tradicionales ha reducido la brecha entre la publicación de una noticia falsa y su viralización masiva: lo que antes tomaba días, hoy ocurre en minutos.

En el contexto de **Colombia**, esta problemática es especialmente aguda. Los ciclos electorales (incluyendo el ciclo informativo 2026), las reformas legislativas, los acuerdos de paz y las campañas de salud pública han demostrado cómo afirmaciones falsas y sesgadas penetran rápidamente a través de canales de mensajería (WhatsApp, Telegram) y redes sociales, alcanzando a cientos de miles de ciudadanos antes de que las unidades de fact-checking manual puedan reaccionar.

### 1.2 Definición Operativa del Dominio
El proyecto adopta una distinción taxonómica rigurosa:
* **Desinformación (*Disinformation*):** Información verificable que es deliberadamente falsa y se difunde con la intención de engañar, manipular o causar daño.
* **Información errónea (*Misinformation*):** Falsedades o imprecisiones compartidas sin intención deliberada de engañar.
* **Información maliciosa (*Malinformation*):** Información verídica descontextualizada o filtrada con el propósito específico de infligir daño institucional o personal.
* **Sesgos Cognitivos (*Cognitive Biases*):** Atajos mentales automáticos utilizados al procesar información con rapidez, explotados por cadenas alarmistas.

### 1.3 Los Tres Fallos del Fact-Checking Tradicional
1. **Velocidad y Latencia:** La verificación manual tarda habitualmente entre 4 y 24 horas desde que surge una afirmación hasta la publicación de un veredicto.
2. **Asimetría de Cobertura:** Crear afirmaciones falsas es trivial y escalable; verificarlas manualmente es costoso y se concentra en temas de altísimo perfil, descuidando rumores regionales.
3. **Opacidad y Falta de Trazabilidad Máquina:** Muchos veredictos carecen de exportación estructurada (ClaimReview / JSON forense) y exposición explícita de fuentes ponderadas.

---

## 2. Objetivos del Proyecto

### 2.1 Objetivo General
Construir un sistema integral para la detección y mitigación de desinformación apoyándose en tecnologías de Internet, análisis automatizado de fuentes, modelos de IA en consenso multi-agente y estándares abiertos de trazabilidad.

### 2.2 Objetivos Específicos
1. **Identificación de Requisitos:** Formalizar requisitos funcionales, no funcionales y el modelo relacional-vectorial para verificación multicanal.
2. **Diseño de Arquitectura:** Diseñar la arquitectura por capas desacoplada (ingesta multicanal, atomización, verificación cruzada multi-modelo, ponderación de credibilidad y veredictos explicables).
3. **Desarrollo del Sistema:** Implementar el backend en **Laravel 13 (PHP 8.3–8.4)** sobre **PostgreSQL 18 con `pgvector` en Neon.tech** (con compatibilidad MariaDB/SQLite), microservicios **Python FastAPI** (OCR/STT), interfaz Web **Vite 8 + Tailwind CSS v4 + Blade** y clientes móviles **Capacitor 6** y **React Native / Expo SDK 58**.
4. **Validación y Pruebas:** Ejecutar suites automatizadas de extremo a extremo (**169 pruebas pasando al 100%** entre PHPUnit, Vitest y Python `unittest`), benchmark de 500 afirmaciones ($\kappa = 0{,}794$, $F_1 = 0{,}828$), auditoría estadística de sesgo ($\chi^2 = 0{,}682$) y cumplimiento Ley 1581 de 2012.

---

## 3. Metodología de Desarrollo: Scrum

El proyecto se rigió por **Scrum** en 7 Sprints quincenales para el MVP, extendidos posteriormente mediante ciclos evolutivos incrementales (`v1.1.0` a `v1.4.1`).

### 3.1 Dinámicas y Reglas
* **Principio de Entrega Continua:** Al culminar cada Sprint se entregó un incremento demostrable y probado automáticamente.
* **Backend-First:** Estabilidad de BD, servicios e API OpenAPI 3.1 prioritaria frente a las capas clientes.
* **Calidad No Negociable:** Ningún cambio degrada cobertura de pruebas, validaciones CORS/Sanctum/API Key ni pautas WCAG 2.1 AA.

### 3.2 Eventos del Sprint
| Evento | Frecuencia / Duración | Participantes | Propósito Central |
| :--- | :--- | :--- | :--- |
| **Sprint Planning** | Inicio de Sprint (máx. 2h) | Scrum Team completo | Definir el Sprint Goal y desglosar ítems en tareas técnicas WBS. |
| **Daily Scrum** | Diario (15 minutos) | Desarrolladores | Sincronización diaria y detección de bloqueos. |
| **Sprint Review** | Fin de Sprint (1h) | Scrum Team + Stakeholders | Demostración en vivo del software funcional y revisión de Quality Gates. |
| **Sprint Retrospective** | Fin de Sprint (45 min) | Scrum Team exclusivamente | Inspección y mejora continua de procesos, arquitectura y automatización QA. |

---

## 4. Product Backlog Priorizado (Línea Base + Evolución `v1.4.1`)

El backlog original comprendió **4 prioridades estratégicas (28 requisitos / 157 puntos de historia canónicos)** más **11 acciones fundacionales (`53` pts)**, ampliado post-MVP con **7 requisitos evolutivos (`REQ-29` a `REQ-35`, `39` pts)** para un total de **35 requisitos (`196` SP en requisitos; `249` SP totales)**. El detalle granular se encuentra en [`Backlog del Proyecto.md`](../Guidelines/Backlog%20del%20Proyecto.md).

### 4.1 Prioridad 1: Esqueleto Funcional (45 Puntos Canónicos) — ✅ Completado
* **REQ-01:** Extractor de afirmaciones verificables de un titular ($F_1 \ge 0{,}75$). *(8 pts)*
* **REQ-02:** OCR y extracción multimedia (capturas de pantalla y audio) vía microservicios. *(5 pts)*
* **REQ-03:** Agente de descomposición en unidades atómicas verificables. *(8 pts)*
* **REQ-04:** Agente de verificación cruzada v1 ponderada por nivel de fuente. *(8 pts)*
* **REQ-05:** Agente de recuperación de evidencia v1 ($\ge 5$ fuentes candidatas). *(8 pts)*
* **REQ-06:** Agente de síntesis del veredicto (`VERDADERO`, `FALSO`, `ENGAÑOSO`, `PARCIAL`, `NO_VERIFICABLE`) + confianza + trazabilidad. *(8 pts)*
* **REQ-07:** Demo de esqueleto funcional sobre afirmación electoral. *(3 pts)*
* **REQ-08:** Grafo de credibilidad v1 con fuentes internacionales Nivel 1. *(5 pts)*

### 4.2 Prioridad 2: Demo Utilizable (44 Puntos Canónicos) — ✅ Completado
* **REQ-09:** Monitor e ingesta multicanal/RSS de frases virales. *(8 pts)*
* **REQ-10:** Conector Archive.org / Wayback Machine para descontextualización temporal. *(5 pts)*
* **REQ-11:** Conjunto de referencia de 500 afirmaciones etiquetadas ($\kappa = 0{,}794 \ge 0{,}75$). *(8 pts)*
* **REQ-12:** Diseño de Base de Datos relacional y vectorial (10 tablas de dominio + Sanctum UUID). *(8 pts)*
* **REQ-13:** Panel e intervención editorial para revisores humanos. *(8 pts)*
* **REQ-14:** Grafo de credibilidad v2 ampliado a prensa regional N2 y nacional colombiana N3. *(5 pts)*
* **REQ-15:** Interfaz accesible de tarjeta de veredicto para Adultos (+40). *(5 pts)*
* **REQ-16:** API pública v1 (`/api/v1/public/*`) para salas de redacción aliadas. *(5 pts)*

### 4.3 Prioridad 3: Listo para Pruebas Piloto (44 Puntos Canónicos) — ✅ Completado
* **REQ-17:** Auditoría estadística de sesgo político electoral ($\chi^2 = 0{,}682$, $p > 0{,}05$). *(8 pts)*
* **REQ-18:** Controles de costes LLM con Circuit Breaker (`CLOSED`, `OPEN`, `HALF_OPEN`). *(5 pts)*
* **REQ-19:** Recuperación interlingüe y análisis de distorsión ES-EN. *(8 pts)*
* **REQ-20:** Grafo de credibilidad v3 ampliado a 80 fuentes con fact-checkers IFCN N4. *(8 pts)*
* **REQ-21:** Pruebas de carga, ingesta continua y disponibilidad $\ge 99\%$. *(8 pts)*
* **REQ-22:** Incorporación piloto de primera redacción (*El Espectador*, 50 veredictos). *(5 pts)*
* **REQ-23:** Usuarios de prueba, roles y derechos ARCO / Ley 1581. *(5 pts)*
* **REQ-24:** Evaluación formal: Precisión **84.6%** ($\ge 0{,}80$), Exhaustividad **81.2%** ($\ge 0{,}75$). *(5 pts)*

### 4.4 Prioridad 4: Pulir y Traspaso (24 Puntos Canónicos) — ✅ Completado
* **REQ-25:** Exportación forense de auditoría (`JSON SHA-256`, `CSV BOM`, `ClaimReview`). *(5 pts)*
* **REQ-26:** Onboarding de segunda y tercera redacción (*ColombiaCheck*, *La Silla Vacía*). *(8 pts)*
* **REQ-27:** Manuales de procedimientos, despliegue y usuario (+40 / revisor). *(8 pts)*
* **REQ-28:** Retrospectiva final y acta formal de traspaso. *(3 pts)*

### 4.5 Fase Evolutiva Post-MVP (`REQ-29` a `REQ-35`, 39 Puntos) — ✅ Completado
* **REQ-29:** Neon PostgreSQL SSL Serverless + tokens Sanctum UUID (`v1.1.0`). *(5 pts)*
* **REQ-30:** Fallback semántico pedagógico sin errores ante ausencia de evidencia (`v1.1.0`). *(5 pts)*
* **REQ-31:** Suite de 10 Mockups de Alta Fidelidad interactivos WCAG 2.1 AA (`v1.2.0`). *(6 pts)*
* **REQ-32:** Proveedor unificado OpenRouter en `OpenAiDriver` (`v1.1.0`). *(5 pts)*
* **REQ-33:** Consejo Multi-Modelo paralelo (`MultiModeloHarnessService` con GPT-4o, Claude 3.5, Gemini 2.0, DeepSeek-R1 y Qwen) + ingesta masiva Colombia (`v1.3.0`). *(6 pts)*
* **REQ-34:** Clientes móviles duales (**Capacitor 6** en `mobile/` + **Expo SDK 58** en `mobile-expo/` con CI macOS `.ipa`, timeout 120s) (`v1.4.0`). *(7 pts)*
* **REQ-35:** Normalización sin tildes PostgreSQL (`normalizarSinTildes`), contraste electoral 2026, parser OpenRouter (`<think>` / `reasoning` / `justificación`), salvaguarda anti-degradación `FALSO` -> `NO_VERIFICABLE` y 169 pruebas verdes (`v1.4.1`). *(5 pts)*

---

## 5. Modelo de Negocio Canvas (Resumen Estratégico)

* **Propuesta de Valor:** Detección de desinformación en tiempo real mediante consejo multi-modelo; contraste con 80 fuentes jerarquizadas y auditables; explicación clara adaptada a adultos (+40).
* **Segmento de Clientes:** Ciudadanos adultos (+40), redacciones periodísticas, fact-checkers y organizaciones de la sociedad civil.
* **Canales:** Web móvil responsiva/PWA (`frontend/`), app híbrida Android/iOS (`mobile/` Capacitor 6), app nativa multiplataforma (`mobile-expo/` Expo SDK 58 + `.ipa` Sideloadly) y API REST M2M para salas de prensa.
* **Relaciones con Clientes:** Interfaz de alto contraste (`A / A+`), notas comunitarias y calificación entre pares, historial personal y transparencia forense.
* **Fuentes de Ingreso:** Licenciamiento de API REST B2B para medios (`/api/v1/public/*`), auditorías de trazabilidad y análisis institucional.
* **Recursos Clave:** Pipeline Laravel 13, corpus vectorizado en Neon `pgvector`, grafo de 80 fuentes IFCN, microservicios Python FastAPI y Consejo Multi-Modelo.
* **Aliados Clave:** Medios y verificadores certificados IFCN (*ColombiaCheck*, *La Silla Vacía*, *El Espectador*), universidades y proveedores LLM/OpenRouter.
* **Estructura de Costos:** Talento humano, infraestructura cloud (Neon Serverless, hosting API), consumo controlado de tokens IA mediante Circuit Breaker y licenciamiento.

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
