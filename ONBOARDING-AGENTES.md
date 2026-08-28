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

Los tres documentos que se leen juntos, cada uno con un trabajo distinto,
para minimizar cuánto hay que leer al retomar el proyecto:

| Documento | Para qué | Cuándo se actualiza |
|---|---|---|
| `ONBOARDING-AGENTES.md` (este archivo) | Orientación estable: qué es esto, límites, reglas, mapa de documentos | Solo cuando cambia algo estructural |
| [`docs/SPEC.md`](docs/SPEC.md) | Especificación funcional/técnica vigente: módulos, su estado, roadmap priorizado | Cuando cambia el estado de un módulo o el roadmap |
| [`task.md`](task.md) | Bitácora cronológica: qué se hizo cada día, bloqueos, siguiente acción | En cada cierre de sesión (ver sección 9) |

Para una primera lectura de "¿dónde estamos?", basta este archivo + la
entrada más reciente de `task.md`. `docs/SPEC.md` se lee cuando hace falta
entender el producto a fondo, no para un resume rápido.

Si algo aquí contradice el código o un README específico (`api/README.md`,
`database/README.md`), **manda el código**. Este archivo describe un momento
en el tiempo; actualízalo cuando la realidad cambie de forma relevante.

---

## 1. Qué es este proyecto, en una frase

Sitio web + sistema administrativo de **Funeraria del Zulia** (Maracaibo,
Venezuela): PHP 8.1+/PDO/MySQL en cPanel compartido, sin framework ni build.
Tiene el sistema de obituarios en línea (base original, admin propio) y las
páginas públicas de planes/servicios de previsión, que desde el 2026-08-28
leen precio y capturan leads en vivo desde **Prevision-Funeraria**
(`prevision-funeraria.sisteg.workers.dev`, tenant `fdz`) — el módulo
administrativo de previsión que vivía en PHP/MySQL en este repo (contratos,
cobranza, comisiones, siniestros) **fue retirado ese mismo día**; el staff
lo gestiona ahora desde el panel de Prevision-Funeraria, no desde aquí. Ver
`docs/specs/2026-08-28-fase-e-corte-admin-prevision.md`.

## 2. Estado real del repositorio ahora mismo

**Lee esto antes que nada — es lo que más cambia entre sesiones.**

| Dato | Valor |
|---|---|
| Rama por defecto (`main`) | Solo tiene el sistema de obituarios base (sin previsión, sin capa de pagos, sin la documentación de `docs/agent-standards.md` y hermanos). Congelada desde 2026-07-02. |
| Rama de trabajo activa | `feature/prevision-funeraria` — creada el 2026-08-28 desde `feature/modulo-prevision`, **sin mergear a `main`**. Tiene el sitio + admin de contenido + integración con Prevision-Funeraria (catálogo, leads) + documentación de specboot. **Ya no tiene** el módulo PHP de previsión (retirado). |
| Rama de respaldo (**nunca mergear a `main`**) | `archive/modulo-prevision-php` — snapshot completo con el módulo PHP de previsión íntegro (`admin-prevision.js`, `api/prevision_*.php`, `database/04`-`10_*.sql`), por si hace falta consultarlo. Ver `docs/specs/2026-08-28-fase-e-corte-admin-prevision.md`. |
| Otras ramas existentes | `feature/modulo-prevision` (la rama de trabajo original, sin más commits desde el corte) y `feature/paginas-servicios-planes` — verifica su estado con `git log` antes de asumir qué contienen. |
| ¿Hay un PR/merge a `main` planeado? | Pendiente de decidir con el usuario. No asumas que hay que mergear a `main` sin confirmarlo. |

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

### Qué hay sin commitear en `feature/prevision-funeraria` ahora mismo

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
| **Este repo** (`Propuesta-Funerzul`) | Sitio + admin de contenido de Funeraria del Zulia. El módulo de previsión **original** en PHP/MySQL se retiró el 2026-08-28 (respaldo en `archive/modulo-prevision-php`); las páginas públicas de planes/servicios siguen aquí pero leen datos en vivo de Prevision-Funeraria. | PHP 8.1+/PDO/MySQL, cPanel, sin framework | — |
| `estebanjvasquez/legado-holding` | Sitio + checkout + chatbot de previsión funeraria para venezolanos en EE. UU. Tiene su propio panel admin en producción. | Cloudflare Worker (JS) + Supabase (PostgREST directo) + HTML/CSS/JS sin build + Invoice Ninja | Repo hermano de otra marca del mismo grupo. Según lo verificado el 2026-08-27 (Notion, workspace del usuario): ya migró su facturación de Invoice Ninja a la API pública de `Prevision-Funeraria` (tenant `lh`) — confirma eso con `git log`/código antes de asumir que sigue igual. |
| `estebanjvasquez/Prevision-Funeraria` | Reemplazo **multiempresa** del módulo de previsión. Según lo verificado el 2026-08-27: **ya está en producción** (`prevision-funeraria.sisteg.workers.dev`, Cloudflare Workers + D1), sirviendo a Funeraria del Zulia (tenant `fdz`) y Legado Holding Inc. (tenant `lh`) desde una sola base de código. Su plan vigente es `docs/PLAN.md` **de ese repo** (no de este). | Cloudflare Workers + D1 | Esfuerzo **paralelo y separado** — no recrear su plan aquí (regla explícita de `CLAUDE.md` y `AGENTS.md` de este repo). Dado que ya está en producción sirviendo ambas marcas, cualquier trabajo nuevo de "llevar previsión a otro lado" debería primero confirmar con el usuario si ese repo ya cubre la necesidad, en vez de asumir que hace falta portar el módulo PHP de este repo. |

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
| Tocar la capa de pagos Mercantil (`api/lib/payments/`, conservada como referencia de diseño, sin endpoints activos en este repo) | `docs/payments/mercantil/STATUS.md`, `PROJECT_DISCOVERY.md`, `MIGRATION_PLAN.md` — **superficie crítica**, no cambiar sin leer esto primero |
| Entender el estado vigente del proyecto completo (módulos, roadmap) | `docs/SPEC.md` |
| Entender por qué se retiró el módulo de previsión y qué se conserva | `docs/specs/2026-08-28-fase-e-corte-admin-prevision.md` (código completo del módulo retirado: rama `archive/modulo-prevision-php`, **nunca mergear a `main`**) |
| Hacer un cambio mediano o riesgoso | Crear/actualizar una especificación en `docs/specs/` usando `docs/spec-template.md` como base, **antes** de implementar |
| Entender qué se propuso mejorar en previsión (11-ago-2026, histórico — módulo ya retirado) | `docs/propuesta-mejoras-prevision.md` + `docs/specs/2026-08-11-mejoras-prevision-plan-tecnico.md` |
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

- **Pagos Mercantil** (`api/lib/payments/`): se conserva **solo como
  referencia de diseño** (regla de `CLAUDE.md`) para el adaptador de pagos de
  Prevision-Funeraria — los endpoints que la usaban (`prevision_mercantil_*.php`)
  se retiraron con el módulo de previsión (2026-08-28), no hay nada activo en
  este repo que la llame. Si se revive cobro electrónico propio de este repo
  (no ligado a previsión), leer `docs/payments/mercantil/STATUS.md` primero:
  máquina de estados estricta, idempotencia, nunca guardar PAN/CVV/PIN/claves.
  Sigue pendiente la spec completa de Mercantil (esquema de `POST /api` +
  MasterKey del webhook).
- **Datos personales**: cédulas, teléfonos, direcciones, datos de pago de
  clientes/beneficiarios/fallecidos/condolientes. Ver `docs/data-model.md`,
  sección "Campos sensibles".
- **`database/SIEMPRE.sql`**: respaldo del sistema legado (~1GB), en
  `.gitignore` a propósito — nunca debe llegar a git.

## 7. Decisiones abiertas conocidas

- ¿Se hace merge de `feature/prevision-funeraria` a `main` (o PR)? — sin
  decidir, confirmar con el usuario.
- **SSO del panel admin de este sitio con Prevision-Funeraria** (pedido por
  el usuario el 2026-08-28): el login de `admin.html` debería integrarse con
  las cuentas de staff del tenant `fdz` en Prevision-Funeraria, en vez de
  mantener dos sistemas de login separados. **Bloqueado**: ese módulo de
  autenticación compartida no existe todavía del lado de Prevision-Funeraria
  (no hay OAuth ni verificación de sesión para terceros). No se puede
  planear el detalle desde este repo — es trabajo nuevo del otro repo. Ver
  `docs/specs/2026-08-28-fase-e-corte-admin-prevision.md`, última sección.
- Spec completa de Mercantil: pendiente de que el banco entregue el esquema
  de `POST /api` y la MasterKey del webhook (ver sección 6) — relevante para
  Prevision-Funeraria, no para este repo (que ya no tiene endpoints de cobro
  activos).
- El paquete `docs/legado-holding-prevision/` (integración con el repo
  hermano `legado-holding`) se perdió sin commitear en su momento y sigue
  sin rehacerse — `Prevision-Funeraria` ya está en producción sirviendo a
  `legado-holding` (tenant `lh`), así que probablemente ya no haga falta;
  confirmar con el usuario antes de invertir tiempo en rehacerlo.

## 8. Cómo arrancar una sesión nueva sobre este proyecto

1. Lee la entrada más reciente de `task.md` (basta la de más arriba) — qué se
   hizo la última vez, qué quedó bloqueado, cuál es la siguiente acción.
2. `git status` y `git log --oneline -10` — confirma rama y qué hay sin
   commitear antes de dar nada por sentado (`task.md` puede haber quedado
   desactualizado si algo cambió fuera de una sesión con este flujo).
3. Lee `AGENTS.md` si existe en tu checkout (ver sección 2 sobre su
   disponibilidad real).
4. Según la tarea concreta, usa la tabla de la sección 4 para ir directo al
   documento que corresponde — no leas todo `docs/` de entrada.
5. Si el cambio es mediano o riesgoso, escribe la spec en `docs/specs/`
   **antes** de tocar código.
6. Si produces documentación o código que vale la pena conservar, **coméntalo
   y commitealo pronto** — no dejes trabajo valioso solo en el árbol de
   trabajo de la sesión (ver la lección de la sección 2).

## 9. Cómo cerrar una sesión ("cerrar el proyecto")

Cuando el usuario pida cerrar la jornada/el proyecto/la sesión, sigue el
procedimiento de `.claude/skills/cerrar-proyecto/SKILL.md`. Resumen:

1. Agregar una entrada nueva **arriba** de `task.md` con lo hecho, lo
   validado, los bloqueos y la siguiente acción.
2. Actualizar `docs/SPEC.md` si el estado de algún módulo o el roadmap
   cambió de forma material (no por cada detalle menor).
3. Commitear esos cambios de documentación (y el código del día, si aplica)
   en la rama actual.
4. Sincronizar con Notion: crear la página "Registro de Proyecto — DD de
   mes, YYYY" del día bajo el hub **"Propuesta Funerzul — Proyecto y
   documentación"**, y actualizar el estado de las tarjetas correspondientes
   en la base de datos **Backlog** de ese mismo hub.

No hace falta pedir este cierre al final de cada sesión — solo cuando el
usuario lo pida explícitamente.
