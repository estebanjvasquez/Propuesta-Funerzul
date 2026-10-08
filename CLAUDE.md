## Onboarding y cierre de sesión

Antes de cambiar código, lee `ONBOARDING-AGENTES.md` (raíz del repo) — resume
el estado real de las ramas, qué está commiteado y qué no, y la relación con
`legado-holding` y `Prevision-Funeraria`. Para el estado funcional/técnico
vigente del proyecto, `docs/SPEC.md`. Para la bitácora día a día, `task.md`
(la entrada de más arriba basta para retomar). Tareas pendientes y su
estado: `docs/BACKLOG.md`.

Cuando el usuario pida cerrar la jornada/el proyecto/la sesión, sigue
`.claude/skills/cerrar-proyecto/SKILL.md`: agrega una entrada a `task.md`,
actualiza `docs/BACKLOG.md` y, si cambió algo material, `docs/SPEC.md`, y
commitea. **Ya no se usa Notion** (el usuario perdió el acceso en octubre de
2026) — no intentes sincronizar con él.

## Módulo de Previsión (nuevo sistema, otro repo)

El reemplazo multiempresa del módulo de previsión (`admin-prevision.js` +
`api/prevision_*.php` de este repo) se está construyendo en **otro repositorio
separado**: `estebanjvasquez/Prevision-Funeraria` (en producción en
`prevision-funeraria.sisteg.workers.dev`; API pública verificada en vivo el
2026-10-08). Sirve tanto a Funeraria del Zulia
(este repo) como a Legado Holding Inc. (`estebanjvasquez/legado-holding`). El plan de
arquitectura completo vive ahí (`docs/PLAN.md`), no en este repo — no recrear ese
análisis aquí. La capa de pagos Mercantil de este repo (`api/lib/payments/`,
`docs/payments/mercantil/`) sigue siendo la referencia de diseño que ese proyecto nuevo
reutiliza para su propio adaptador de pagos.

## Specboot adaptado

Este repo usa una adaptacion ligera de `lidr-specboot`, no la plantilla completa.
Antes de cambios de codigo, leer los documentos aplicables en `docs/`:
`backend-standards.md` y `data-model.md`. `AGENTS.md`, `agent-standards.md`,
`frontend-standards.md` y `spec-template.md` se perdieron sin commitear con
el equipo anterior (2026-10) y están pendientes de recrearse (ver
`docs/BACKLOG.md`); mientras tanto, las reglas resumidas están en
`ONBOARDING-AGENTES.md`, sección 5.

Para cambios medianos o riesgosos, crear o actualizar una especificacion breve en
`docs/specs/` antes de implementar.

## graphify

This project may have a knowledge graph at graphify-out/ (local, gitignored — it was lost with the previous machine in 2026-10 and must be regenerated before use) with god nodes, community structure, and cross-file relationships.

Rules:
- For codebase questions, first run `graphify query "<question>"` when graphify-out/graph.json exists. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
