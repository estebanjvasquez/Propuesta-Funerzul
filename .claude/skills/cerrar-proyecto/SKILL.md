---
name: cerrar-proyecto
description: "Cierra la jornada de trabajo en Propuesta Funerzul: actualiza task.md, docs/BACKLOG.md y docs/SPEC.md, y commitea. Trigger: cuando el usuario pide 'cerrar el proyecto', 'cerrar la sesión', 'cerrar la jornada' o equivalente."
---

# Cerrar proyecto — Propuesta Funerzul

Procedimiento de cierre de jornada. Se dispara cuando el usuario pide cerrar
el proyecto/la sesión/la jornada — no se ejecuta automáticamente al final de
cada turno.

**Sin Notion.** Hasta el 2026-08-27 este cierre también sincronizaba un hub
y un Backlog en Notion; el usuario perdió el acceso en octubre de 2026 y el
Backlog se migró a `docs/BACKLOG.md`. No intentes usar Notion.

## Pasos

### 1. Reunir qué pasó en la sesión

- `git status` y `git log --oneline` (compara contra el último commit
  referenciado en la entrada más reciente de `task.md`, si la hay) para
  saber qué cambió realmente: archivos nuevos, modificados, commits hechos.
  Si hay commits posteriores a la última entrada que no salen en la
  conversación, menciónalos (así se detectan sesiones que no cerraron).
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
```

### 3. Actualizar `docs/BACKLOG.md`

- Tareas completadas hoy → estado `Hecho` y muévelas a la sección
  "Cerradas" con una línea de motivo.
- Tareas que ya no aplican → `Obsoleto` con el motivo, en "Cerradas". No
  borres filas; si dudas, pregúntale al usuario.
- Tareas nuevas que surgieron → fila nueva en "Abiertas", en el grupo que
  corresponda, con estado `Pendiente` (o `Bloqueado` si depende de un
  tercero) y una nota corta con el contexto necesario para retomarla.

### 4. Actualizar `docs/SPEC.md` (solo si hace falta)

Si el estado de algún módulo cambió (pasó de "pendiente" a "en desarrollo",
se completó una fase, cambió el roadmap, se agregó/deprecó un documento de
referencia), actualiza la sección correspondiente. Si nada de eso cambió,
**no toques el archivo** — `SPEC.md` no se actualiza por cada cierre, solo
cuando cambia algo material (a diferencia de `task.md`, que sí crece cada
vez).

### 5. Commitear y pushear

Commitea `task.md` y `docs/BACKLOG.md` (siempre) y `docs/SPEC.md` (si se
tocó) en la rama actual. No hace falta pedir permiso para este commit —
cerrar la jornada ya es la instrucción de guardarlo.

Haz push de la rama de trabajo (no de `main`): el proyecto ya perdió trabajo
dos veces por dejarlo solo en el equipo local (ver `ONBOARDING-AGENTES.md`,
sección 2), la última al dañarse el equipo en octubre de 2026. Nunca hagas
push a `main` sin que el usuario lo pida explícitamente para ese cambio.

### 6. Confirmar al usuario

Un resumen corto: qué se documentó, qué se commiteó y pusheó, y qué tareas
del Backlog cambiaron de estado.
