# Onboarding para agentes de IA

Léeme primero, completo, antes de escanear el repositorio. Este archivo existe
para que **cualquier agente de IA (Claude Code, Codex u otro) o cualquier
persona nueva** pueda retomar este proyecto con contexto completo, sin tener
que leer commit por commit ni adivinar qué documento es el vigente.

No reemplaza `README.md`, `AGENTS.md` ni los documentos de `docs/` — los
indexa y añade lo que ninguno de ellos cubre por sí solo: **el estado real
del repositorio en este momento** (ramas, qué está commiteado y qué no,
decisiones abiertas) y **cómo se relaciona con los otros dos repositorios**
del mismo negocio.

Si algo aquí contradice el código o un README específico (`api/README.md`,
`database/README.md`), **manda el código**. Este archivo describe un momento
en el tiempo; actualízalo cuando la realidad cambie de forma relevante.

---

## 1. Qué es este proyecto, en una frase

Sitio web + sistema administrativo de **Funeraria del Zulia** (Maracaibo,
Venezuela): PHP 8.1+/PDO/MySQL en cPanel compartido, sin framework ni build,
con un sistema de obituarios en línea (base original) y un **módulo de
previsión funeraria** (pólizas, cobranza, comisiones, siniestros) que es hoy
la parte más grande y activa del código.

## 2. Estado real del repositorio ahora mismo

**Lee esto antes que nada — es lo que más cambia entre sesiones.**

| Dato | Valor |
|---|---|
| Rama por defecto (`main`) | Solo tiene el sistema de obituarios base (sin módulo de previsión, sin capa de pagos, sin la documentación de `docs/agent-standards.md` y hermanos). Congelada desde 2026-07-02. |
| Rama de trabajo activa | `feature/modulo-prevision` — **~25 commits adelante de `main` y sin mergear**. Todo el módulo de previsión, la capa de pagos Mercantil y la documentación de specboot (`AGENTS.md`, `docs/agent-standards.md`, etc.) vive **solo** ahí. |
| Otra rama existente | `feature/paginas-servicios-planes` — verifica su estado con `git log` antes de asumir qué contiene. |
| ¿Hay un PR/merge a `main` planeado? | Pendiente de decidir con el usuario (ver `docs/resume.md`, sección "Decisiones abiertas"). No asumas que hay que mergear a `main` sin confirmarlo. |

### Comprueba siempre en qué rama estás y qué hay sin commitear

```
git status
git log --oneline -5
```

Este repo tiene un historial real de **documentación de trabajo perdida por
quedar sin commitear**:

- El commit `179b80f` registra que una carpeta `docs/architecture/` **nunca
  se commiteó** y se perdió/migró sin dejar rastro en este repo.
- Un paquete de documentación completo (`docs/legado-holding-prevision/`,
  ~1000 líneas, para portar el módulo de previsión al repo `legado-holding`)
  se creó en una sesión y **desapareció antes de commitearse** — probablemente
  el entorno de la sesión se reinició antes de guardar el trabajo en git. Si
  necesitas ese paquete, **hay que rehacerlo**; no existe en ningún commit.

**Regla que se desprende de esto, para cualquier agente:** si produces un
documento o código que vale la pena conservar, coméntaselo al usuario y
**commitea pronto** (aunque sea en la rama de trabajo, sin mergear a `main`
todavía). No confíes en que el entorno de la sesión persista entre
conversaciones si el archivo no llegó a `git commit`.

### Qué hay sin commitear en `feature/modulo-prevision` ahora mismo

A la fecha de escribir esto, el árbol de trabajo de esa rama tiene sin
commitear (entre otros): `AGENTS.md`, `docs/agent-standards.md`,
`docs/backend-standards.md`, `docs/frontend-standards.md`,
`docs/data-model.md`, `docs/spec-template.md`, `docs/specs/README.md`,
`docs/propuesta-mejoras-prevision.md`,
`docs/specs/2026-08-11-mejoras-prevision-plan-tecnico.md`,
`docs/MercantilAPI.md`, `docs/payments/mercantil/*` (PDFs/docx),
`database/SIEMPRE.zip`, `codex.md` y `.claude/`. Es decir: **buena parte del
sistema de especificación del proyecto (specboot) todavía no está en git.**
Ejecuta `git status` para ver el estado real antes de asumir que algo de esto
sigue existiendo.

## 3. Los tres repositorios del negocio (no confundirlos)

| Repo | Qué es | Stack | Relación con este repo |
|---|---|---|---|
| **Este repo** (`Propuesta-Funerzul`) | Sitio + admin de Funeraria del Zulia, con el módulo de previsión **original** (`admin-prevision.js` + `api/prevision_*.php`). | PHP 8.1+/PDO/MySQL, cPanel, sin framework | — |
| `estebanjvasquez/legado-holding` | Sitio + checkout + chatbot de previsión funeraria para venezolanos en EE. UU. Tiene su propio panel admin en producción. | Cloudflare Worker (JS) + Supabase (PostgREST directo) + HTML/CSS/JS sin build + Invoice Ninja | Repo hermano de otra marca del mismo grupo. Se le está llevando (o ya se le llevó, según lo que digas al chequear) una versión del módulo de previsión de este repo, adaptada a su stack. |
| `estebanjvasquez/Prevision-Funeraria` | Reemplazo **multiempresa** definitivo del módulo de previsión, pensado para servir tanto a Funerzul como a Legado Holding desde una base de código común. Tiene su propio plan (`docs/PLAN.md` en ese repo). | Por definir en ese repo | Esfuerzo **paralelo y separado** — no recrear su plan aquí (regla explícita de `CLAUDE.md` y `AGENTS.md` de este repo). |

Regla corta: **este repo no toca el plan de `Prevision-Funeraria`**, y
**cualquier trabajo de llevar previsión a `legado-holding` se documenta y
ejecuta pensando en el stack de `legado-holding` (Supabase + Worker), no en
copiar PHP/MySQL tal cual.**

## 4. Mapa de documentación — qué leer según la tarea

No leas todo de entrada. Usa esta tabla para ir directo a lo que necesitas.

| Si vas a... | Lee esto primero |
|---|---|
| Cambiar cualquier código | `AGENTS.md` (si existe en tu checkout — ver advertencia de la sección 2) |
| Tocar PHP, API, sesiones, pagos o SQL | `docs/backend-standards.md` |
| Tocar HTML, CSS o JS | `docs/frontend-standards.md` |
| Tocar base de datos, migraciones o entidades | `docs/data-model.md` + `database/README.md` |
| Entender contratos reales de la API | `api/README.md` |
| Tocar la capa de pagos Mercantil (`api/lib/payments/`, `api/prevision_mercantil_*.php`) | `docs/payments/mercantil/STATUS.md`, `PROJECT_DISCOVERY.md`, `MIGRATION_PLAN.md` — **superficie crítica**, no cambiar sin leer esto primero |
| Entender el módulo de previsión completo (todas las entidades, reglas de negocio, endpoints) | `docs/resume.md` (contexto por rondas) + `api/README.md` + `database/README.md` |
| Hacer un cambio mediano o riesgoso | Crear/actualizar una especificación en `docs/specs/` usando `docs/spec-template.md` como base, **antes** de implementar |
| Entender qué se propuso mejorar en previsión (11-ago-2026) | `docs/propuesta-mejoras-prevision.md` (versión cliente) + `docs/specs/2026-08-11-mejoras-prevision-plan-tecnico.md` (versión técnica) |
| Trabajar en la numeración de contratos configurable | `docs/prevision/plan-2026-08-07.md` |
| Llevar el módulo de previsión a `legado-holding` | Buscar `docs/legado-holding-prevision/` — **si no existe, fue el paquete que se perdió (ver sección 2); hay que rehacerlo** antes de empezar ese trabajo en el otro repo. |
| Responder preguntas de arquitectura o navegar el código sin grep manual | Si existe `graphify-out/graph.json`: `graphify query "<pregunta>"` (o `graphify path`/`graphify explain`) antes que lectura cruda |

## 5. Reglas de trabajo que no cambian

- Sin framework, sin bundler, sin build, sin dependencias Node/ORM nuevas sin
  especificación previa y aprobación explícita del usuario.
- PHP 8.1+ con PDO y consultas preparadas; respuestas API `{ ok: true|false, ... }`.
- Toda mutación requiere sesión de staff y `X-CSRF-Token` (salvo endpoints
  públicos documentados explícitamente).
- Cambios de esquema van en una migración incremental numerada en `database/`,
  nunca editando migraciones antiguas ya aplicadas.
- Cambios medianos o riesgosos (pagos, contratos/cuotas/comisiones/siniestros
  de previsión, migraciones SQL, auth/sesiones/CSRF, reorganización de
  carpetas) requieren spec previa en `docs/specs/`.
- Nunca commitear: `api/config.php`, `database/SIEMPRE.sql` (~1GB, en
  `.gitignore`), fotos/adjuntos subidos (`uploads/**`, salvo placeholders),
  secretos o credenciales de ningún tipo.

Detalle completo de estas reglas: `docs/agent-standards.md` (ver advertencia
de disponibilidad en la sección 2).

## 6. Superficies especialmente sensibles

Trátalas con más cuidado del habitual — cambios ahí sin entender el contexto
completo pueden costar dinero real o exponer datos personales:

- **Pagos Mercantil** (`api/lib/payments/`, `api/prevision_mercantil_callback.php`,
  `api/prevision_mercantil_webhook.php`, `prev_pagos_electronicos`): máquina de
  estados estricta, idempotencia en callbacks/webhooks, nunca guardar
  PAN/CVV/PIN/claves. Hay una solicitud **pendiente de spec completa a
  Mercantil** (esquema de `POST /api` + MasterKey del webhook) — ver commit
  `d8ffed0`; hasta que eso llegue, el cobro real al banco sigue
  deshabilitado por diseño (solo modo simulado).
- **Datos personales**: cédulas, teléfonos, direcciones, datos de pago de
  clientes/beneficiarios/fallecidos/condolientes. Ver `docs/data-model.md`,
  sección "Campos sensibles".
- **`database/SIEMPRE.sql`**: respaldo del sistema legado (~1GB), en
  `.gitignore` a propósito — nunca debe llegar a git.

## 7. Decisiones abiertas conocidas

- ¿Se hace merge de `feature/modulo-prevision` a `main` (o PR), o se sigue
  trabajando sobre la rama sin mergear? — sin decidir, confirmar con el
  usuario.
- Proveedor de mensajería WhatsApp/SMS del módulo de previsión: sin
  contratar. El sistema ya quedó configurable desde el panel (manual /
  WhatsApp Cloud API / Twilio / API HTTP genérica); cuando se contrate uno,
  solo hace falta cargar credenciales.
- Spec completa de Mercantil: pendiente de que el banco entregue el esquema
  de `POST /api` y la MasterKey del webhook (ver sección 6).
- El paquete `docs/legado-holding-prevision/` (integración con el repo
  hermano) se perdió sin commitear y está pendiente de rehacerse — ver
  sección 2 y la fila correspondiente en la tabla de la sección 4.

## 8. Cómo arrancar una sesión nueva sobre este proyecto

1. `git status` y `git log --oneline -10` — confirma rama y qué hay sin
   commitear antes de dar nada por sentado.
2. Lee `AGENTS.md` si existe en tu checkout (ver sección 2 sobre su
   disponibilidad real).
3. Según la tarea concreta, usa la tabla de la sección 4 para ir directo al
   documento que corresponde — no leas todo `docs/` de entrada.
4. Si el cambio es mediano o riesgoso, escribe la spec en `docs/specs/`
   **antes** de tocar código.
5. Si produces documentación o código que vale la pena conservar, **coméntalo
   y commitealo pronto** — no dejes trabajo valioso solo en el árbol de
   trabajo de la sesión (ver la lección de la sección 2).
