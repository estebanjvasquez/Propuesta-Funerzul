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
| Secciones del sitio activables/desactivables (Obituarios, Directorio Médico, Recursos, FAQs) + marca de agua del ángel en el hero | Hecho (2026-09-08) | `docs/specs/2026-09-08-secciones-activables-y-marca-agua-hero.md` |
| Tarjetas de obituario para compartir (2 plantillas web + imagen PNG descargable, foto opcional) | Hecho (2026-09-08), pendiente de probar en el servidor real | `docs/specs/2026-09-08-tarjetas-obituario.md` |
| **Módulo de Previsión** (`admin-prevision.js` + `api/prevision_*.php`) | **Retirado el 2026-08-28** (corte inmediato). Código completo solo en la rama `archive/modulo-prevision-php` (nunca se mergea a `main`). | `docs/specs/2026-08-28-fase-e-corte-admin-prevision.md`; histórico previo en `docs/resume.md` |
| Capa de pagos electrónicos (`api/lib/payments/`, Mercantil) | Modo simulado únicamente. Cobro real al banco bloqueado hasta que Mercantil entregue el esquema completo de `POST /api` y la MasterKey del webhook. | `docs/payments/mercantil/STATUS.md`, `PROJECT_DISCOVERY.md`, `MIGRATION_PLAN.md` |
| Numeración de contratos configurable | Diseñado, no implementado (plan del día 2026-08-07) | `docs/prevision/plan-2026-08-07.md` |
| Seis mejoras propuestas al módulo de previsión (11-ago-2026) | Propuesta enviada al cliente, sin priorización confirmada | `docs/propuesta-mejoras-prevision.md` (versión cliente), `docs/specs/2026-08-11-mejoras-prevision-plan-tecnico.md` (versión técnica) |
| Rediseño visual de la consola de previsión | Hecho | `docs/specs/2026-08-11-prevision-ui-redesign.md` |

### Historial del módulo de Previsión por ronda (retirado — referencia)

El módulo administrativo de previsión en PHP/MySQL descrito en esta tabla
**se retiró por completo el 2026-08-28** (ver más abajo). Queda como
referencia histórica de lo que llegó a construirse; el código real solo
sigue vivo en la rama `archive/modulo-prevision-php` (nunca se mergea a
`main`).

| Ronda | Contenido | Estado |
|---|---|---|
| Base (v1) | Clientes, contratos, beneficiarios, cuotas/pagos, planes, vendedores+comisiones, importación CSV | ✅ (retirado) |
| Ronda 1 (v2) | Sucursales+servicios, siniestros con validación de cobertura, cobranza (morosos, gestiones, auto-lapsado+cron, rutas) | ✅ (retirado) |
| Ronda 2 (v3) | Ajuste masivo de tarifas (reversible), mensajería WhatsApp/SMS multi-proveedor, reportes (aging, producción, cobranza, cartera) | ✅ (retirado) |
| Ajustes 2.1 (v4) | Comisiones por estados (calcular→aprobar→pagar), reorganización de menús | ✅ (retirado) |
| v5-v7 | Cuotas en Bs ancladas a la tasa, paridad flujo SIEMPRE/KM, pagos electrónicos (Mercantil simulado) | ✅ (retirado) |
| Ronda 3 / Ronda 4 | Domiciliación bancaria, empleadores/planes colectivos, portal de autogestión del cliente | ⬜ nunca se construyeron — ver más abajo si siguen haciendo falta en Prevision-Funeraria |

`docs/resume.md` queda como bitácora histórica de cómo se llegó hasta la
Ronda 2.

**2026-08-28 — el módulo de previsión de este repo se reemplazó por
Prevision-Funeraria y se retiró del código activo.** Análisis de huecos y
plan de migración: [`docs/specs/2026-08-28-migracion-a-prevision-funeraria.md`](specs/2026-08-28-migracion-a-prevision-funeraria.md)
(Fases A-D: catálogo, leads, triage de emergencias, cobro electrónico
bloqueado por Mercantil). Corte real (Fase E), ejecutado el mismo día por
decisión del usuario — corte inmediato, no gradual:
[`docs/specs/2026-08-28-fase-e-corte-admin-prevision.md`](specs/2026-08-28-fase-e-corte-admin-prevision.md).

Estado actual: las páginas públicas `planes/`/`servicios/` siguen en PHP en
este repo, pero leen precio y capturan leads en vivo desde
Prevision-Funeraria (`enabled => true` en el `config.php` de prueba). El
panel admin de previsión (`admin-prevision.js` + `api/prevision_*.php`) ya
no existe en este repo — el staff lo gestiona desde el panel de
Prevision-Funeraria (`prevision-funeraria.sisteg.workers.dev`, tenant
`fdz`). Las seis mejoras propuestas el 11-ago quedan como referencia
histórica (`docs/propuesta-mejoras-prevision.md`), sin implementarse aquí.

**Pendiente real, del lado de Prevision-Funeraria (no de este repo):** login
único del panel admin de este sitio contra las cuentas de staff del tenant
`fdz` — ese módulo de autenticación compartida no existe todavía en
Prevision-Funeraria. Ver última sección de la spec de la Fase E.

## 3. Los tres repositorios del negocio

Ver `ONBOARDING-AGENTES.md`, sección 3, para la tabla completa y las reglas
de límites entre repos. Resumen de una línea cada uno:

- **Este repo**: sitio + admin de contenido (PHP/MySQL). El módulo de
  previsión original se retiró el 2026-08-28.
- `estebanjvasquez/legado-holding`: otra marca del grupo (Cloudflare Worker +
  Supabase), con su propio checkout y chatbot.
- `estebanjvasquez/Prevision-Funeraria`: reemplazo multiempresa del módulo de
  previsión, en producción (`prevision-funeraria.sisteg.workers.dev`),
  sirviendo a Funeraria del Zulia y Legado Holding Inc. desde una sola base
  de código. **No se recrea su plan en este repo.**

## 4. Roadmap / próximos pasos priorizados

1. Decidir si `feature/prevision-funeraria` se mergea a `main` (o PR).
2. SSO del panel admin de este sitio con las cuentas de staff de
   Prevision-Funeraria (tenant `fdz`) — **bloqueado**: ese módulo de
   autenticación compartida no existe todavía del lado de
   Prevision-Funeraria (ver sección 2).
3. Rehacer `docs/legado-holding-prevision/` (se perdió sin commitear el
   2026-08-27) **solo si** sigue haciendo falta algo que Prevision-Funeraria
   no cubra ya para Legado Holding — verificar antes de asumirlo (ya cubre
   el tenant `lh` en producción).
4. Retomar con Mercantil la solicitud de spec completa (bloqueado del lado
   del banco) — relevante para `Prevision-Funeraria`, no para este repo
   (que ya no tiene endpoints de cobro activos).

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
