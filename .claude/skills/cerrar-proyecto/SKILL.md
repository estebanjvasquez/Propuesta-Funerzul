---
name: cerrar-proyecto
description: "Cierra la jornada de trabajo en Propuesta Funerzul: actualiza task.md y docs/SPEC.md, commitea, y sincroniza con Notion (registro del día + backlog). Trigger: cuando el usuario pide 'cerrar el proyecto', 'cerrar la sesión', 'cerrar la jornada' o equivalente."
---

# Cerrar proyecto — Propuesta Funerzul

Procedimiento de cierre de jornada. Se dispara cuando el usuario pide cerrar
el proyecto/la sesión/la jornada — no se ejecuta automáticamente al final de
cada turno.

Referencias fijas de Notion para este repo (no hace falta volver a buscarlas
con `notion-search` cada vez — solo si alguna de estas URLs deja de resolver):

- Hub del proyecto: `https://app.notion.com/p/3c96989da22e81bcbfd9e7b80e017054`
  (id `3c96989d-a22e-81bc-bfd9-e7b80e017054`)
- Backlog (base de datos): `https://app.notion.com/p/b16fb5ea38b4461c99156a5f6e777d97`
  (data source id `8ef2a8ad-ab82-4e12-9a87-d0a26b19f81e`)

## Pasos

### 1. Reunir qué pasó en la sesión

- `git status` y `git log --oneline` (compara contra el último commit
  referenciado en la entrada más reciente de `task.md`, si la hay) para
  saber qué cambió realmente: archivos nuevos, modificados, commits hechos.
- Repasa la conversación de la sesión para decisiones tomadas, hallazgos,
  bloqueos nuevos o resueltos, y la siguiente acción que quedó pendiente.
- No inventes validaciones que no se corrieron. Si no se ejecutó ningún
  test/build, dilo explícitamente ("no aplica" o "no se corrió").

### 2. Actualizar `task.md`

Agrega una entrada nueva **arriba** de la anterior (no la edites, no borres
historial), con este formato:

```markdown
## Cierre: YYYY-MM-DD

**Rama:** <rama actual> (<n> commits vs. main si aplica; cualquier dato de
estado relevante)

**Hecho hoy:**
- ...

**Validaciones:**
- ...

**Bloqueos:**
- ... (arrastra los que siguen abiertos de la entrada anterior; no los
  repitas si no cambiaron en sustancia, pero no los omitas tampoco)

**Siguiente acción:**
1. ...

**Notion:** <link a la página de Registro del día, se agrega después de
publicarla en el paso 4>
```

### 3. Actualizar `docs/SPEC.md` (solo si hace falta)

Si el estado de algún módulo cambió (pasó de "pendiente" a "en desarrollo",
se completó una fase, cambió el roadmap, se agregó/deprecó un documento de
referencia), actualiza la sección correspondiente. Si nada de eso cambió,
**no toques el archivo** — `SPEC.md` no se actualiza por cada cierre, solo
cuando cambia algo material (a diferencia de `task.md`, que sí crece cada
vez).

### 4. Sincronizar con Notion

1. Crea la página del día bajo el hub (`parent: {type: "page_id", page_id:
   "3c96989d-a22e-81bc-bfd9-e7b80e017054"}`), título
   `Registro de Proyecto — <día> de <mes>, <año>` (en español, sin ceros a
   la izquierda en el día — ej. "3 de septiembre, 2026"). Estructura del
   contenido (usa solo las secciones que apliquen; no fuerces secciones
   vacías):

   ```markdown
   ## Hito de hoy
   <resumen de 3-6 líneas de lo más importante hecho hoy>
   ## Validaciones
   <qué se corrió/verificó, o "no aplica">
   ## Bloqueos
   <lista, o "sin cambios nuevos" si nada cambió>
   ## Trabajo pendiente (priorizado)
   <lista corta, o "ver base de datos Backlog de este hub">
   ## Referencias
   - Panorama estable del proyecto: `ONBOARDING-AGENTES.md` (repositorio, raíz)
   - Especificación vigente: `docs/SPEC.md` (repositorio)
   - Detalle completo: `task.md` del repositorio, entrada "Cierre: YYYY-MM-DD"
   ```

   Guarda la URL que devuelve `notion-create-pages` — la necesitas para el
   paso 2 de `task.md` (edítalo después de este paso, o vuelve atrás y
   complétalo).

2. Actualiza el Backlog (`data_source_id`
   `8ef2a8ad-ab82-4e12-9a87-d0a26b19f81e`):
   - Tareas completadas hoy → `notion-update-page` con
     `properties: {"Status": "Done"}`.
   - Tareas nuevas que surgieron → `notion-create-pages` con ese
     `data_source_id` como parent, `Status: "Not started"`.
   - No borres tarjetas viejas aunque parezcan obsoletas — si ya no aplican,
     márcalas `Done` con una nota, o pregúntale al usuario antes de eliminar.
   - Antes de asumir el estado actual del Backlog, puedes `notion-fetch` el
     hub o la base de datos si pasó mucho tiempo desde el último cierre.

### 5. Commitear

Commitea `task.md` (siempre) y `docs/SPEC.md` (si se tocó) en la rama
actual. No hace falta pedir permiso para este commit — cerrar la jornada ya
es la instrucción de guardarlo. Push solo si el usuario lo pide o si ya es
la práctica establecida en esa rama (ver `ONBOARDING-AGENTES.md` sobre la
lección de trabajo perdido por no commitear/pushear a tiempo). Nunca hagas
push a `main` sin que el usuario lo pida explícitamente para ese cambio.

### 6. Confirmar al usuario

Un resumen corto: qué se documentó, qué se commiteó (y si se hizo push),
el link a la página de Registro de Notion, y cuántas tarjetas del Backlog
cambiaron de estado.
