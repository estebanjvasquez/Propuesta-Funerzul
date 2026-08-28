# Especificación vigente — Propuesta Funerzul

Documento único de referencia funcional/técnica de todo el proyecto. No
duplica lo que ya está bien documentado en otro lugar — apunta a la fuente
real y solo resume lo que hace falta para orientarse rápido.

No confundir con `docs/PLAN.md` del repositorio `Prevision-Funeraria` (otro
repo, otro producto) — por eso este archivo se llama `SPEC.md`.

Si algo aquí contradice el código, el código manda. Actualiza este documento
cuando cambie algo estructural (nuevo módulo, cambio de estado de una fase,
nueva decisión de arquitectura) — no hace falta actualizarlo por cada commit,
para eso está `task.md`.

## 1. Qué es

Sitio web + sistema administrativo de **Funeraria del Zulia**. PHP 8.1+ (PDO)
+ MySQL/MariaDB en cPanel compartido, HTML/CSS/JS sin framework ni build.
Detalle de arquitectura y estructura de carpetas: `README.md`.

## 2. Módulos y su estado actual

| Módulo | Estado | Documento de referencia |
|---|---|---|
| Sitio público (obituarios, directorio médico, recursos, FAQs) | Producción, estable | `README.md` (manual de usuario, secciones 3-13) |
| Panel admin base (usuarios, plantillas, configuración) | Producción, estable | `README.md`, `api/README.md` |
| **Módulo de Previsión** (`admin-prevision.js` + `api/prevision_*.php`) | En desarrollo activo sobre `feature/modulo-prevision`, sin mergear a `main`. Funcionalidad amplia ya construida (ver tabla de rondas abajo). | `docs/resume.md` (histórico por rondas hasta 2026-07-07), `api/README.md` (contratos reales, sección "Módulo de Previsión"), `database/README.md` |
| Capa de pagos electrónicos (`api/lib/payments/`, Mercantil) | Modo simulado únicamente. Cobro real al banco bloqueado hasta que Mercantil entregue el esquema completo de `POST /api` y la MasterKey del webhook. | `docs/payments/mercantil/STATUS.md`, `PROJECT_DISCOVERY.md`, `MIGRATION_PLAN.md` |
| Numeración de contratos configurable | Diseñado, no implementado (plan del día 2026-08-07) | `docs/prevision/plan-2026-08-07.md` |
| Seis mejoras propuestas al módulo de previsión (11-ago-2026) | Propuesta enviada al cliente, sin priorización confirmada | `docs/propuesta-mejoras-prevision.md` (versión cliente), `docs/specs/2026-08-11-mejoras-prevision-plan-tecnico.md` (versión técnica) |
| Rediseño visual de la consola de previsión | Hecho | `docs/specs/2026-08-11-prevision-ui-redesign.md` |

### Estado del módulo de Previsión por ronda (resumen — detalle en `docs/resume.md`)

| Ronda | Contenido | Estado |
|---|---|---|
| Base (v1) | Clientes, contratos, beneficiarios, cuotas/pagos, planes, vendedores+comisiones, importación CSV | ✅ |
| Ronda 1 (v2) | Sucursales+servicios, siniestros con validación de cobertura, cobranza (morosos, gestiones, auto-lapsado+cron, rutas) | ✅ |
| Ronda 2 (v3) | Ajuste masivo de tarifas (reversible), mensajería WhatsApp/SMS multi-proveedor, reportes (aging, producción, cobranza, cartera) | ✅ |
| Ajustes 2.1 (v4) | Comisiones por estados (calcular→aprobar→pagar), reorganización de menús | ✅ |
| v5-v7 | Cuotas en Bs ancladas a la tasa, paridad flujo SIEMPRE/KM, pagos electrónicos (Mercantil simulado) | ✅ (posteriores a `resume.md`, ver `git log` y `api/README.md`) |
| Ronda 3 | Domiciliación bancaria por lotes, empleadores/planes colectivos, documentos imprimibles, envejecimiento automático de dependientes | ⬜ pendiente |
| Ronda 4 | Portal de autogestión del cliente | ⬜ pendiente (ver mejora #1 de `docs/propuesta-mejoras-prevision.md`) |

`docs/resume.md` queda como bitácora histórica de cómo se llegó hasta la
Ronda 2 (convenciones de código detalladas incluidas). Este documento
(`SPEC.md`) es el que se mantiene vigente hacia adelante.

**Actualización 2026-08-28 — el módulo de previsión de este repo tiene un
plan de salida.** `Prevision-Funeraria` (repo hermano, ya en producción)
cubre o supera casi todo lo de este módulo. Hay un plan concreto para migrar
Funerzul hacia ese sistema, con análisis de huecos reales (no solo
funcionalidad, también bloqueos operativos) y qué parte de la página web
pública migra vs. qué se queda en PHP: ver
[`docs/specs/2026-08-28-migracion-a-prevision-funeraria.md`](specs/2026-08-28-migracion-a-prevision-funeraria.md).
Ronda 3 y Ronda 4 de la tabla de arriba (y las seis mejoras propuestas) están
en pausa hasta que se confirme si compite o queda reemplazado por ese plan —
ver riesgo #5 de ese documento.

**Mismo día — Fases A, B y C del plan ya están implementadas** (catálogo de
planes con precio real, reenvío de leads, triage de emergencias), apagadas
por defecto (`prevision_funeraria.enabled => false`). Ver sección "7bis.
Progreso real" del spec para el detalle, incluidos dos huecos de datos reales
encontrados en el catálogo de Prevision-Funeraria que alguien con acceso a
ese panel debe revisar antes de activarlo en producción.

## 3. Los tres repositorios del negocio

Ver `ONBOARDING-AGENTES.md`, sección 3, para la tabla completa y las reglas
de límites entre repos. Resumen de una línea cada uno:

- **Este repo**: sitio + admin + módulo de previsión original (PHP/MySQL).
- `estebanjvasquez/legado-holding`: otra marca del grupo (Cloudflare Worker +
  Supabase), con su propio checkout y chatbot.
- `estebanjvasquez/Prevision-Funeraria`: reemplazo multiempresa del módulo de
  previsión, en producción (`prevision-funeraria.sisteg.workers.dev`),
  sirviendo a Funeraria del Zulia y Legado Holding Inc. desde una sola base
  de código. **No se recrea su plan en este repo.**

## 4. Roadmap / próximos pasos priorizados

1. Decidir el plan de migración a `Prevision-Funeraria`
   (`docs/specs/2026-08-28-migracion-a-prevision-funeraria.md`) — usuario
   confirma orden de fases y el riesgo #5 (si reemplaza las seis mejoras
   propuestas en vez de ejecutarlas en paralelo). Esto probablemente
   resuelve los puntos 3 y 4 de abajo, no los duplica.
2. Rehacer `docs/legado-holding-prevision/` (se perdió sin commitear el
   2026-08-27) **solo si**, tras el punto 1, sigue haciendo falta algo que
   `Prevision-Funeraria` no cubra ya para Legado Holding — verificar antes
   de asumirlo (ya cubre el tenant `lh` en producción).
3. Decidir si `feature/modulo-prevision` se mergea a `main` — probablemente
   ligado a la decisión del punto 1 (si el módulo PHP se apaga, puede no
   valer la pena mergear).
4. Ronda 3 y Ronda 4 del módulo PHP, y las seis mejoras propuestas — en
   pausa hasta resolver el punto 1 (ver nota de la sección 2).
5. Retomar con Mercantil la solicitud de spec completa (bloqueado del lado
   del banco) — relevante para **ambos** sistemas (PHP y `Prevision-Funeraria`
   comparten el mismo bloqueo, aunque por razones ligeramente distintas, ver
   sección 2 del plan de migración).

El backlog operativo día a día (con estado Not started/In progress/Done) vive
en Notion — ver sección 6.

## 5. Reglas y superficies sensibles

No se repiten aquí — ver `ONBOARDING-AGENTES.md`, secciones 5 y 6
(`docs/agent-standards.md`, `docs/backend-standards.md`,
`docs/frontend-standards.md` para el detalle completo).

## 6. Notion

- Hub del proyecto: [**"Propuesta Funerzul — Proyecto y documentación"**](https://app.notion.com/p/3c96989da22e81bcbfd9e7b80e017054)
- Backlog: [**"Propuesta Funerzul — Backlog"**](https://app.notion.com/p/b16fb5ea38b4461c99156a5f6e777d97) (base de datos, enlazada desde el hub)
- Bitácora diaria: páginas **"Registro de Proyecto — DD de mes, YYYY"** bajo
  el hub, una por cierre de sesión relevante. El detalle completo de cada
  cierre vive en `task.md`; Notion tiene el resumen curado. Primera entrada:
  [27 de agosto, 2026](https://app.notion.com/p/3c96989da22e818a9606cde42e22ffdf).

Procedimiento de cierre (qué se actualiza y cómo se sincroniza con Notion):
`.claude/skills/cerrar-proyecto/SKILL.md`.
