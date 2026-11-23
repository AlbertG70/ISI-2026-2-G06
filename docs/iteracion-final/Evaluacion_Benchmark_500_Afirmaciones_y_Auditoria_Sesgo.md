# DAGAZ — Informe de Evaluación Formal del Benchmark de 500 Afirmaciones y Auditoría de Sesgo Político (REQ-11, REQ-17, REQ-24)
## Certificación de Precisión Algorítmica, Imparcialidad Editorial y Calibración Post-MVP

> **Proyecto:** DAGAZ — Plataforma de Verificación de Información y Desinformación en Colombia  
> **Asignatura:** Ingeniería de Servicios de Internet — 2026-2 — Grupo 06  
> **Requisitos Asociados:** REQ-11 (Benchmark 500 Afirmaciones), REQ-17 (Auditoría de Sesgo Político), REQ-24 (Evaluación Formal de Precisión y Recall), REQ-30/REQ-31 (Calibración Post-MVP)  
> **Comité de Evaluación:** Alberto Mario Galvis Echeverria & Camilo Isaac Acevedo Rueda  
> **Fecha de Certificación Original:** Octubre de 2026 (Addendum de calibración: Noviembre de 2026)

---

## 1. Resumen Ejecutivo y Metodología del Corpus

Para dar cumplimiento a los criterios de aceptación de los requisitos **REQ-11**, **REQ-17** y **REQ-24**, el equipo técnico de DAGAZ construyó y evaluó un conjunto de datos estandarizado de **500 afirmaciones del entorno sociopolítico colombiano**.

```text
┌─────────────────────────────────────────────────────────────────────────┐
│               COMPOSICIÓN DEL DATASET DE 500 AFIRMACIONES               │
├─────────────────────────────────────────────────────────────────────────┤
│  • Ámbito Electoral y Campañas Políticas:        200 afirmaciones (40%) │
│  • Economía, Inflación, Impuestos y Subsidios:   150 afirmaciones (30%) │
│  • Orden Público, Seguridad Ciudadana y Salud:   100 afirmaciones (20%) │
│  • Cadenas Virales y Redes Sociales (Miscelánea): 50 afirmaciones (10%) │
└─────────────────────────────────────────────────────────────────────────┘
```

### 1.1 Proceso de Etiquetado Manual y Acuerdo Inter-evaluador
El corpus fue sometido a doble etiquetado ciego por un panel colegiado de 3 periodistas de investigación independientes bajo el protocolo de la International Fact-Checking Network (IFCN).

* **Coeficiente de Acuerdo (Fleiss' Kappa):** $\kappa = 0{,}794$
* **Criterio de Aceptación:** $\kappa \ge 0{,}75$
* **Resultado:** **Aprobado con alta concordancia inter-evaluador.**

---

## 2. Resultados de la Evaluación Formal del Motor (REQ-24)

El motor automatizado de DAGAZ (descomposición de afirmaciones + recuperación semántica en 80 fuentes de credibilidad) procesó las 500 afirmaciones en el corte de evaluación de Octubre de 2026, comparando el veredicto inferido contra la verdad fundamental (*Ground Truth*).

### 2.1 Matriz de Confusión Multiclase (Corte Octubre 2026)

| Ground Truth \ Predicción | VERDADERO | FALSO | ENGAÑOSO | NO_VERIFICABLE | Total Real |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **VERDADERO** | **104** | 4 | 9 | 3 | 120 |
| **FALSO** | 6 | **184** | 18 | 7 | 215 |
| **ENGAÑOSO** | 11 | 15 | **79** | 5 | 110 |
| **NO_VERIFICABLE** | 2 | 3 | 0 | **50** | 55 |
| **Total Predicho** | 123 | 206 | 106 | 65 | **500** |

---

### 2.2 Tabla de Métricas de Rendimiento por Clase

| Clase de Veredicto | Precisión (Precision) | Exhaustividad (Recall) | F1-Score | Soporte (Muestras) |
| :--- | :---: | :---: | :---: | :---: |
| **VERDADERO** | 0.845 (84.5%) | 0.867 (86.7%) | 0.856 | 120 |
| **FALSO** | 0.893 (89.3%) | 0.856 (85.6%) | 0.874 | 215 |
| **ENGAÑOSO** | 0.745 (74.5%) | 0.718 (71.8%) | 0.731 | 110 |
| **NO_VERIFICABLE** | 0.769 (76.9%) | 0.909 (90.9%) | 0.833 | 55 |
| **PROMEDIO PONDERADO** | **0.846 (84.6%)** | **0.812 (81.2%)** | **0.828 (82.8%)** | **500** |

### 2.3 Comparación contra Umbrales Contractuales (Criterios de Aceptación)

$$
\begin{aligned}
\text{Precisión Global Obtenida:} &\quad \mathbf{84{,}6\%} \quad (\text{Exigencia REQ-24: } \ge 80{,}0\%) \quad \longrightarrow \quad \textbf{SUPERADO} (+4.6\%) \\
\text{Exhaustividad (Recall) Obtenida:} &\quad \mathbf{81{,}2\%} \quad (\text{Exigencia REQ-24: } \ge 75{,}0\%) \quad \longrightarrow \quad \textbf{SUPERADO} (+6.2\%) \\
\text{Exactitud Global (Accuracy):} &\quad \mathbf{83{,}4\%} \quad (417 \text{ de } 500 \text{ afirmaciones clasificadas con exactitud})
\end{aligned}
$$

> 📌 **Conclusión REQ-24:** El motor de verificación supera formalmente los dos umbrales cuantitativos estipulados en el pliego de requerimientos y el plan de gestión del proyecto.

---

## 3. Informe de Auditoría de Sesgo Político (REQ-17)

El requisito **REQ-17** exige certificar ante el comité de gobernanza y las redacciones aliadas que el sistema no favorece ni penaliza sistemáticamente a ningún sector del espectro político colombiano.

### 3.1 Distribución del Corpus Electoral (200 Afirmaciones Políticas)
* **Sector Gubernamental / Coalición de Gobierno:** 68 afirmaciones (34.0%)
* **Sector Oposición / Partidos Tradicionales:** 66 afirmaciones (33.0%)
* **Sector Independiente / Centro / Organismos Electorales:** 66 afirmaciones (33.0%)

### 3.2 Tasa de Falsos Positivos y Falsos Negativos por Espectro Político

| Espectro Político | Muestras | Tasa de Error Falso Positivo (FPR) | Tasa de Error Falso Negativo (FNR) | Tasa de Acierto (Accuracy) |
| :--- | :---: | :---: | :---: | :---: |
| **Gobierno / Oficialismo** | 68 | 8.8% | 11.7% | 85.3% |
| **Oposición** | 66 | 9.1% | 12.1% | 84.8% |
| **Independientes / Centro** | 66 | 7.6% | 10.6% | 86.4% |

### 3.3 Prueba de Independencia y Significancia Estadística ($\chi^2$)
Se aplicó la prueba de Chi-Cuadrado de Pearson ($\chi^2$) sobre las distribuciones de error por alineación política:
* **Estadístico $\chi^2$ Calculado:** $0.682$
* **Grados de Libertad ($df$):** $2$
* **Valor $p$ ($p\text{-value}$):** $0.711$
* **Nivel de Significancia ($\alpha$):** $0.05$

$$p = 0.711 > 0.05 \implies \text{No se rechaza la hipótesis nula } (H_0)$$

> ⚖️ **Dictamen de Auditoría de Sesgo:**  
> **No existe evidencia estadísticamente significativa de sesgo político partidista o ideológico.** Las tasas de error se distribuyen homogéneamente entre los distintos actores de la esfera pública colombiana, garantizando la imparcialidad algorítmica exigida por la IFCN y los estándares deontológicos del periodismo.

---

## 4. Medidas de Mitigación Implementadas

1. **Jerarquía Equilibrada del Grafo de Credibilidad (REQ-20):** 80 fuentes balanceadas entre organismos de control del Estado, medios nacionales de diversas líneas editoriales y verificadores no partidistas.
2. **Intervención Humana Colegiada (REQ-13):** Mecanismo de apelación y rectificación donde periodistas humanos pueden subsanar ambigüedades en temas de alta polarización política.
3. **Registro Forense Transparente (REQ-25):** Trazabilidad pública de cada evidencia utilizada para que cualquier ciudadano o regulador pueda auditar la procedencia de los veredictos.

---

## 5. Calibración y Endurecimiento Algorítmico Post-MVP (`v1.3.0` – `v1.4.1`)

Con posterioridad a la liberación del corte base de Octubre de 2026 (`v1.0.0`), se incorporaron tres mejoras de precisión y estabilidad validadas en la batería automatizada de **169 pruebas** (`142` PHPUnit + `23` Vitest + `4` Python):

1. **Protección *Anti-Downgrade* en el Consejo Multi-Modelo (`MultiModeloHarnessService`):** Se implementó la salvaguarda explícita que impide degradar un veredicto `FALSO` del motor documental a `NO_VERIFICABLE` cuando la confianza promedio del consejo es menor al `85.0%` (`$esDegradacionInvalida`), reduciendo falsos negativos por incertidumbre temporal de modelos externos en afirmaciones electorales o institucionales refutadas.
2. **Normalización Diacrítica para Evidencia en Español (`BusquedaSemanticaService::normalizarSinTildes`):** Normalización uniforme de tildes y acentos tanto en consultas como en fragmentos/títulos indexados antes de calcular coincidencia y contraste institucional, elevando la recuperación sobre nombres propios y entidades de América Latina (verificado en [`BusquedaSemanticaTest.php`](../../backend/tests/Unit/BusquedaSemanticaTest.php) y [`VerificacionesBackendFeatureTest.php`](../../backend/tests/Feature/VerificacionesBackendFeatureTest.php)).
3. **Soporte nativo para dictámenes parciales (`PARCIAL`) y resiliencia OpenRouter (`OpenAiDriver`):** Limpieza de bloques de razonamiento `<think>` y soporte de campos `reasoning`, evitando respuestas vacías ante modelos de razonamiento extendido (`REQ-31`).
