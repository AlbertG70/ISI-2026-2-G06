# Presupuesto de Desarrollo — Proyecto DAGAZ

> **Proyecto:** DAGAZ — Plataforma de Verificación de Información y Desinformación en Colombia  
> **Asignatura:** Ingeniería de Servicios de Internet — 2026-2 — Grupo 06  
> **Vigencia Temporal:** Valores de Referencia a Año Fiscal 2026  
> **Moneda:** Pesos Colombianos (COP) — Tasa de cambio base de referencia: $1 \text{ USD} \approx \$4.000 \text{ COP}$

---

> [!IMPORTANT]
> ### Inciso Legal y Financiero de Referencia (Aclaración de Vigencia 2026)
> El presente documento constituye una **estimación presupuestal técnica y de referencia** calculada a valores proyectados para el año **2026**. 
> 
> Las tarifas de talento humano, los servicios de computación en la nube (Cloud/GPU), el consumo de modelos de lenguaje y el licenciamiento de software aquí expuestos reflejan el alcance del proyecto DAGAZ, integrando su arquitectura actual y su proyección a futuro (incluyendo la orquestación de hasta cuatro agentes de inteligencia artificial de pesos abiertos, indexación vectorial semántica RAG sobre Neon.tech y despliegue web responsivo mobile-first).
> 
> En caso de una **implementación formal por parte de una empresa, entidad pública o medio de comunicación**, este presupuesto **debe ser auditado y actualizado** considerando:
> 1. La fecha efectiva de contratación y ejecución.
> 2. Las fluctuaciones inflacionarias y la variación de la Tasa Representativa del Mercado (TRM USD/COP).
> 3. Ajustes en las políticas de precios de proveedores de infraestructura cloud e inteligencia artificial.
> 4. Variaciones en el alcance, acuerdos de nivel de servicio (SLA) o modificaciones arquitectónicas demandadas por la organización adquirente.

---

## 1. Alcance de los Rubros Presupuestados

Se tienen en cuenta para el presupuesto los siguientes ítems:
* **Talento Humano Especializado:** Desarrollo Full Stack, análisis de requisitos, aseguramiento de la calidad (QA) e ingeniería de MLOps / IA para integración de modelos abiertos y RAG.
* **Servicios de Infraestructura Cloud, IA & RAG:** Almacenamiento vectorial en Neon.tech PostgreSQL (`pgvector`), clúster de inferencia GPU cloud para hasta cuatro (4) agentes de pesos abiertos (*open-weight*), hosting de aplicación web y bolsa de contingencia de APIs LLM.
* **Licencias de Programas y Software:** Modelado UML en Enterprise Architect, gestión de bases de datos relacionales en TablePlus, control de versiones colaborativo y CI/CD en GitHub Team, y suites de monitoreo de API.
* **Recursos Tecnológicos y Entornos de Desarrollo:** Estaciones de trabajo de alto rendimiento para desarrollo y entrenamiento local, conectividad simétrica corporativa y entornos de staging.
* **Fondo de Imprevistos y Contingencias:** Reserva estratégica para mitigar volatilidad cambiaria o sobreconsumos imprevistos de tokens y cómputo.

---

## 2. Tabla Detallada del Presupuesto de Desarrollo

A continuación se detalla la estructura financiera proyectada para un ciclo de desarrollo, despliegue y puesta en marcha de **16 semanas (4 meses)**:

| Ítem | Categoría | Descripción | Cant. | Unidad | Valor Unitario (COP) | Total (COP) |
| :--- | :--- | :--- | :---: | :---: | :---: | :---: |
| **Desarrollador Full Stack Junior / Mid** | Talento Humano | Desarrollo de backend (Laravel 12 / PHP 8.4), frontend accesible (Blade SSR / Tailwind CSS v4), consumo de API REST v1 y lógica de negocio (16 semanas). | 1 | Contrato Mes | $4.500.000 | $18.000.000 |
| **Analista de Requerimientos y QA Junior** | Talento Humano | Especificación de casos de uso (CU01–CU07), diseño de historias de usuario, ejecución de pruebas de accesibilidad WCAG 2.1 AA y auditoría de sesgo (16 semanas). | 1 | Contrato Mes | $3.500.000 | $14.000.000 |
| **Ingeniero MLOps / Especialista en IA** | Talento Humano | Configuración del pipeline RAG con `pgvector`, despliegue y optimización de hasta 4 agentes de pesos abiertos como jueces de veredictos, calibración de prompts y microservicios OCR/STT. | 1 | Contrato Mes | $3.800.000 | $15.200.000 |
| **Neon.tech PostgreSQL (`pgvector` + RAG Storage)** | Infraestructura | Base de datos relacional serverless con extensión `pgvector` para almacenamiento de embeddings semánticos, autoescalado de cómputo y ramas de desarrollo independientes. | 4 | Mes | $180.000 | $720.000 |
| **Clúster GPU Cloud para Agentes de Pesos Abiertos** | Infraestructura | Instancia GPU cloud dedicada/serverless (NVIDIA A10G / L4 vía RunPod o Together AI) para servir concurrentemente hasta 4 agentes de veredicto cuantizados (Llama 3 / Mistral / DeepSeek). | 4 | Mes | $640.000 | $2.560.000 |
| **Hosting Cloud y Servidor de Caché / Colas** | Infraestructura | Servidor VPS en la nube (4 vCPU, 8GB RAM) para el aplicativo Laravel, servidor web Nginx, worker de colas asíncronas y servidor Redis / Upstash para circuit breakers. | 4 | Mes | $200.000 | $800.000 |
| **Bolsa de Consumo APIs LLM Comerciales (Respaldo/Eval)** | Infraestructura | Cuota para llamadas de evaluación cruzada y benchmarking comparativo (OpenAI GPT-4o / Gemini 1.5 Pro) bajo control estricto del *Circuit Breaker* (REQ-18). | 1 | Bolsa Global | $1.800.000 | $1.800.000 |
| **Enterprise Architect (Corporate Edition)** | Licencias de Programas | Licencia comercial para ingeniería de software, diagramado UML formal (casos de uso, secuencia, clases y dominio) y modelado arquitectónico. | 1 | Licencia Anual | $950.000 | $950.000 |
| **TablePlus (Commercial License)** | Licencias de Programas | Herramienta profesional de administración e inspección de bases de datos relacionales y colecciones vectoriales. | 2 | Licencia | $240.000 | $480.000 |
| **GitHub Team (Organización 2 Usuarios)** | Licencias de Programas | Repositorio privado de código, gestión colaborativa de Pull Requests, ejecución de runners CI/CD automatizados (GitHub Actions) y almacenamiento de artefactos. | 8 | Mes-Usuario | $46.000 | $368.000 |
| **Postman Pro & Herramientas de Testing API** | Licencias de Programas | Suite de pruebas automatizadas de integración para contratos de API REST v1 y validación de webhooks con redacciones socias (REQ-16 / REQ-26). | 4 | Mes | $78.000 | $312.000 |
| **Amortización de Estaciones de Trabajo** | Recursos Tecnológicos | Depreciación y mantenimiento de dos (2) computadores de ingeniería de alto rendimiento (RAM $\ge 32\text{ GB}$, almacenamiento NVMe, tarjetas gráficas dedicadas para pruebas locales). | 4 | Mes | $600.000 | $2.400.000 |
| **Conectividad Simétrica Corporativa** | Recursos Tecnológicos | Conexión de fibra óptica simétrica empresarial con IP fija para pruebas de carga prolongadas (REQ-21), sincronización continua RSS y disponibilidad $\ge 99\%$. | 4 | Mes | $200.000 | $800.000 |
| **Fondo de Imprevistos y Contingencias (5%)** | Contingencias | Reserva estratégica para cubrir variaciones en la tasa de cambio USD/COP, picos en el consumo de tokens o requerimientos adicionales de almacenamiento. | 1 | Global | $2.919.500 | $2.919.500 |
| **TOTAL GENERAL ESTIMADO** | | | | | | **$61.309.500 COP** |

---

## 3. Resumen Ejecutivo de la Distribución del Gasto

```
┌──────────────────────────────────────────────┬──────────────────┬──────────────┐
│ Categoría Presupuestal                       │ Inversión (COP)  │ Porcentaje   │
├──────────────────────────────────────────────┼──────────────────┼──────────────┤
│ 1. Talento Humano Especializado              │   $47.200.000    │    76.99%    │
│ 2. Servicios de Infraestructura Cloud & RAG  │    $5.880.000    │     9.59%    │
│ 3. Licencias de Programas y Software         │    $2.110.000    │     3.44%    │
│ 4. Recursos Tecnológicos y Entornos          │    $3.200.000    │     5.22%    │
│ 5. Fondo de Imprevistos y Contingencias (5%) │    $2.919.500    │     4.76%    │
├──────────────────────────────────────────────┴──────────────────┼──────────────┤
│ TOTAL DEL PROYECTO (16 SEMANAS)                                 │ $61.309.500  │
└─────────────────────────────────────────────────────────────────┴──────────────┘
```

> **Equivalente aproximado en divisas:** $\approx \$15.327\text{ USD}$ (calculado con TRM proyectada de $\$4.000\text{ COP/USD}$).

---

## 4. Justificación Técnica de los Nuevos Componentes Presupuestales

### 4.1 Incorporación de Hasta 4 Agentes de Pesos Abiertos (Open-Weight Judges)
A diferencia de arquitecturas que dependen exclusivamente de APIs comerciales cerradas, DAGAZ prevé la gobernanza imparcial del veredicto mediante un panel de jueces algorítmicos independientes ejecutando modelos de pesos abiertos (tales como *Llama 3.1 8B Instruct*, *Mistral NeMo 12B* o *DeepSeek LLM*). El aprovisionamiento de inferencia GPU en la nube permite ejecutar estos 4 agentes en paralelo sin riesgos de bloqueo por cuotas de terceros, garantizando trazabilidad y soberanía sobre los veredictos.

### 4.2 Arquitectura RAG en Neon.tech con `pgvector`
La recuperación mejorada por generación (*Retrieval-Augmented Generation*) se sustenta sobre la base de datos PostgreSQL en la nube Neon.tech utilizando la extensión nativa `pgvector`. El presupuesto asignado cubre el cómputo y almacenamiento vectorial necesario para indexar el grafo de más de 80 fuentes periodísticas calificadas (REQ-20), las 500 afirmaciones del benchmark electoral y los artículos noticiosos recuperados diariamente por el comando de ingesta RSS.

### 4.3 Control de Costes por Circuit Breaker (REQ-18)
La cuota asignada a consumo de APIs comerciales se encuentra blindada por el servicio [`CircuitBreakerService.php`](file:///c:/Users/Vss/ISI-2026-2-G06/backend/app/Services/CircuitBreakerService.php), el cual suspende de forma automática las peticiones ante umbrales anómalos o cuando el gasto diario proyectado excede los límites programados, protegiendo las finanzas operativas del proyecto.
