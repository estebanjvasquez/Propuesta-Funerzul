# task.md — Bitácora operativa (Propuesta Funerzul)

> Entradas más recientes **primero**. Cada cierre de sesión agrega una entrada
> arriba (no se edita el historial). Para contexto estable del proyecto (qué
> es, ramas, límites con los repos hermanos) lee primero
> [`ONBOARDING-AGENTES.md`](ONBOARDING-AGENTES.md) — es corto a propósito. Para
> el detalle funcional/técnico vigente, [`docs/SPEC.md`](docs/SPEC.md).
>
> Este archivo puede crecer mucho — no hace falta leerlo completo para
> retomar el trabajo. Basta la entrada de más arriba.

---

## Cierre: 2026-08-27 (verificación del sistema de cierre)

**Rama:** `feature/modulo-prevision` (1 commit sin push desde el cierre
anterior de hoy; 26 commits vs. `main`, sin cambios respecto a la entrada
previa).

**Hecho hoy:** Nada nuevo — el usuario pidió "cerrar proyecto" para probar el
flujo recién construido (`.claude/skills/cerrar-proyecto/`). Se ejecutó el
procedimiento completo (esta entrada, verificación de Notion) para
confirmar que funciona de punta a punta. No hubo cambios de código ni de
documentación más allá de esta entrada.

**Validaciones:** `git status`/`git log` confirman que no hay commits ni
archivos nuevos desde el cierre anterior (commit `c39cee3`). No se corrió
build/test — no aplica a un cierre sin cambios de código.

**Bloqueos:** sin cambios — los mismos de la entrada anterior (merge a
`main` sin decidir, spec de Mercantil pendiente del banco, proveedor de
mensajería sin contratar, rol futuro del módulo PHP frente a
`Prevision-Funeraria`).

**Siguiente acción:** las mismas de la entrada anterior — ver Backlog en
Notion (sin cambios de estado en esta verificación).

**Notion:** [Registro de Proyecto — 27 de agosto, 2026 (verificación)](https://app.notion.com/p/3c96989da22e8187bc65ed97075190cb)

---

## Cierre: 2026-08-27

**Rama:** `feature/modulo-prevision` (25 commits sin mergear a `main`; sin
decisión tomada sobre si mergear o seguir trabajando ahí — ver "Bloqueos").

**Hecho hoy:**
- Se armó un paquete de documentación (`docs/legado-holding-prevision/`, 7
  archivos, ~1000 líneas) para llevar el módulo de previsión al repo hermano
  `legado-holding`. **Se perdió sin commitear** — el entorno de la sesión se
  reinició antes de guardarlo en git. Queda como pendiente rehacer (ver
  Trabajo pendiente).
- Se investigó el estado real del repositorio (no existía `task.md` en ese
  momento) y se creó `ONBOARDING-AGENTES.md` en la raíz: punto de entrada
  corto para cualquier agente nuevo — estado de ramas, relación con
  `legado-holding` y `Prevision-Funeraria`, mapa de qué documento leer según
  la tarea, reglas de trabajo y superficies sensibles. Referenciado desde
  `README.md`.
- Ese cambio (`ONBOARDING-AGENTES.md` + referencia en `README.md`) se llevó
  también a `main` vía un `git worktree` aislado, sin tocar el árbol de
  trabajo de esta rama — commit [`605235d`](https://github.com/estebanjvasquez/Propuesta-Funerzul/commit/605235d)
  en `origin/main`.
- Se estableció el sistema de "spec + retomar con bajo consumo de tokens +
  cierre de jornada", reutilizando el patrón ya probado en los repos
  hermanos (`Prevision-Funeraria`, `EventPass VE` en Notion):
  - Este archivo (`task.md`) como bitácora cronológica local.
  - `docs/SPEC.md` como especificación funcional/técnica vigente del
    proyecto completo (nuevo — no confundir con `docs/PLAN.md` de
    `Prevision-Funeraria`, que es otro repo y otro documento; por eso este
    archivo se llama `SPEC.md` y no `PLAN.md`).
  - `ONBOARDING-AGENTES.md` actualizado con referencias a ambos.
  - `CLAUDE.md` actualizado con un puntero a `ONBOARDING-AGENTES.md` (carga
    automática en cada sesión) y la regla de cierre de jornada.
  - Skill `.claude/skills/cerrar-proyecto/` con el procedimiento exacto de
    cierre (actualizar docs locales + sincronizar Notion).
  - Notion: página hub **"Propuesta Funerzul — Proyecto y documentación"**
    + base de datos **Backlog** + esta primera entrada de "Registro de
    Proyecto", siguiendo el mismo formato que ya usan los repos hermanos.

**Validaciones:**
- `git status`/`git log` revisados antes y después de cada cambio para no
  perder ni mezclar trabajo pendiente de otras tareas.
- Confirmado que el árbol de trabajo de `feature/modulo-prevision` no se
  tocó al publicar en `main` (mismo listado de archivos sin commitear antes
  y después, más los dos archivos nuevos de onboarding).
- No aplica build/test automatizado — este repo no tiene suite (ver
  `docs/backend-standards.md`, sección Pruebas).

**Bloqueos:**
- Sin decidir: ¿merge de `feature/modulo-prevision` a `main`, o se sigue
  trabajando sin mergear? (heredado, ver `docs/resume.md`).
- Spec completa de Mercantil pendiente del banco (esquema `POST /api` +
  MasterKey del webhook) — heredado, ver commit `d8ffed0`.
- Proveedor de mensajería WhatsApp/SMS sin contratar — heredado.

**Siguiente acción:**
1. Si se retoma la integración con `legado-holding`: rehacer
   `docs/legado-holding-prevision/` **y commitearlo apenas esté listo**
   (no esperar a tener todo el paquete completo).
2. Definir si `feature/modulo-prevision` se mergea a `main` o sigue como
   rama de trabajo de largo plazo.
3. Empezar a usar `docs/SPEC.md` como documento vigente en vez de releer
   `docs/resume.md` completo (que queda como histórico de las rondas hasta
   2026-07-07).

**Notion:** [Registro de Proyecto — 27 de agosto, 2026](https://app.notion.com/p/3c96989da22e818a9606cde42e22ffdf)
· [Hub del proyecto](https://app.notion.com/p/3c96989da22e81bcbfd9e7b80e017054)
· [Backlog](https://app.notion.com/p/b16fb5ea38b4461c99156a5f6e777d97) (8
tarjetas cargadas, todas `Not started`).
