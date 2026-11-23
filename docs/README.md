# Documentación del Proyecto — DAGAZ

Esta carpeta contiene la documentación técnica, funcional, arquitectónica y metodológica correspondiente a todas las iteraciones y entregas evolutivas del proyecto **DAGAZ** (Sistema de Detección de Desinformación y Verificación Multicanal).

---

## 📑 Estructura de Iteraciones y Entregables

```text
docs/
├── iteracion-01/          # Fase 0 y Consolidación del Backlog: Requisitos, DSS, Mockups y Guías Base
│   ├── Diagramas/         # Diagramas de Casos de Uso, Secuencia (Caja Negra y Gris), Dominio y BD
│   ├── Guidelines/        # Product Backlog extendido (v1.4.1), Plan de Gestión e Historial de Control
│   ├── Guideline-Persistencia/ # Especificación fundacional de casos de uso y arquitectura
│   ├── Mockups/           # Prototipos de servilleta de las interfaces de usuario
│   └── README.md          # Resumen de entregables de la Iteración 01
│
├── iteracion-02/          # Fases 1, 2 y Extensión: Backend Freeze, Persistencia, Servicios e Integración
│   ├── Guidelines/
│   │   ├── Backlog del Proyecto.md               # Backlog consolidado (35 requisitos + acciones fundacionales)
│   │   ├── Plan_Gestion_Desarrollo_Implementacion.md # Plan ágil Backend-First en 8 Sprints + Evolutivo
│   │   ├── Diagrama_de_Gantt.md                  # Cronograma de 16 semanas (S1 a S8), WBS, ruta crítica y post-MVP
│   │   ├── Diagrama_de_Gantt_Visual.html         # Exportación visual HTML interactiva del cronograma
│   │   ├── Sprint_Backlog_Realizados.md          # Trazabilidad ejecutada Sprint 1–8 + Post-MVP
│   │   └── Historial_de_Control.md               # Bitácora maestra de cambios
│   ├── Guideline-Persistencia/                   # Contexto maestro, casos de uso, diseño BD y presentación
│   ├── Mockups/                                  # 10 Mockups de baja fidelidad (PNG y SVG) y especificación
│   ├── Documentacion_Funcional_Tecnica_Backend_6_Tareas.md # Especificación técnica backend + Multi-Modelo
│   └── README.md          # Resumen de entregables y evidencias de la Iteración 02
│
├── iteracion-03/          # Fase 3: Evaluación Formal, Benchmark 500, UI Accesible y Pruebas Piloto
│   └── README.md          # Entregables consolidados de QA, UI y pilotaje
│
├── iteracion-final/       # Fase 4: CI/CD, Manuales, Traspaso, Liberación MVP y Evolución
│   ├── Documentacion_Consolidada_Iteracion_Final.md # Memoria técnica consolidada del proyecto
│   ├── Evaluacion_Benchmark_500_Afirmaciones_y_Auditoria_Sesgo.md # Certificación empírica y sesgo
│   ├── Manual_Usuario_Adultos.md                 # Guía para adultos (+40 años) WCAG 2.1 AA
│   ├── Manual_Administrador_Revisor.md           # Protocolo de rectificación editorial (REQ-13)
│   ├── Manual_Despliegue_Infraestructura.md      # Infraestructura, Nginx, Docker, PaaS y PostgreSQL
│   ├── Onboarding_Redacciones_Socias_REQ26.md    # Integración M2M para salas de redacción aliadas
│   ├── Retrospectiva_Final_y_Acta_Traspaso_REQ28.md # Acta formal de cierre y lecciones aprendidas
│   └── README.md          # Resumen de la iteración final
│
├── app-icons/             # Recursos gráficos oficiales para Android (Play Store) e iOS (AppIcon.appiconset)
│
├── mockups-alta-fidelidad/# Galería interactiva de 10 prototipos HTML5 accesibles (WCAG 2.1 AA)
│   └── index.html         # Selector de vistas responsivas para adultos mayores (+40)
│
└── Presupuesto de Desarrollo.md # Estimación presupuestal técnica a año fiscal 2026 ($57.6M COP)
```

---

## 🧭 Documentos Clave de Consulta Rápida

- 📱 **[Clientes Móviles: Capacitor 6](../mobile/README.md) y [React Native / Expo SDK 58](../mobile-expo/README.md)**: Cliente nativo iOS/Android para adultos (+40 años), selector `A / A+`, streaming de pensamiento fáctico, compilación `DAGAZ.ipa` para Sideloadly y contenedor Android APK.
- 📋 **[Product Backlog Actualizado (v1.4.1)](iteracion-01/Guidelines/Backlog%20del%20Proyecto.md)**: 35 requisitos (28 base + 7 evolución post-MVP), 196 puntos de historia + 53 fundacionales y matriz de trazabilidad completa.
- 🎨 **[Portal de Mockups de Alta Fidelidad](mockups-alta-fidelidad/index.html)**: 10 vistas interactivas HTML5/Tailwind para Adultos (+40) bajo norma WCAG 2.1 AA.
- 💰 **[Presupuesto de Desarrollo 2026](Presupuesto%20de%20Desarrollo.md)**: Estimación financiera detallada a 16 semanas (\$57.600.000 COP) para talento humano, clúster GPU de inferencia, Neon.tech y licencias.
- 📑 **[Memoria Técnica Consolidada](iteracion-final/Documentacion_Consolidada_Iteracion_Final.md)**: Arquitectura Laravel 13, Consejo Multi-Modelo, streaming SSE, búsqueda web en vivo, **190 pruebas automatizadas (163 PHPUnit, 23 Vitest, 4 Python)** y evaluación empírica.
- 📜 **[Historial de Control y Bitácora de Decisiones](iteracion-02/Guidelines/Historial_de_Control.md)**: Registro cronológico oficial de todas las decisiones técnicas y evolutivas.
- 🏷️ **[Changelog Estándar del Proyecto](../CHANGELOG.md)**: Historial de versiones del repositorio en formato Keep a Changelog.
- 📅 **[Cronograma Maestro y Diagrama de Gantt](iteracion-02/Guidelines/Diagrama_de_Gantt.md)**: Planificación temporal detallada de los 8 Sprints, hitos de control (Gates 0 a 4), fase evolutiva y dependencias en Mermaid.
- 🛠️ **[Plan de Gestión y Desarrollo](iteracion-02/Guidelines/Plan_Gestion_Desarrollo_Implementacion.md)**: Reglas Backend-First, calidad QA, CI/CD (`ci.yml` + `build-ios-ipa.yml`) y Ley 1581 de 2012.
- ⚙️ **[Documentación Técnica del Backend](iteracion-02/Documentacion_Funcional_Tecnica_Backend_6_Tareas.md)**: Pipeline de 14 etapas, Consejo Multi-Modelo (`MultiModeloHarnessService`), microservicios FastAPI, contratos OpenAPI y catálogo REST v1.
- 🗄️ **[Diseño de Base de Datos y Diccionario](../database/Documentacion-Raiden.md)**: Esquema de 10 tablas, PostgreSQL 18 con `pgvector` en Neon.tech y script DDL [`Raiden.sql`](../database/Raiden.sql).

---

## Integrantes del Proyecto

- **Alberto Mario Galvis Echeverria** (Código: `0222410070`) — Analista de Requerimientos / Desarrollador FullStack
- **Camilo Isaac Acevedo Rueda** (Código: `0222410049`) — Analista de Calidad / Desarrollador FullStack
