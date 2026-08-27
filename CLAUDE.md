## Onboarding y cierre de sesión

Antes de cambiar código, lee `ONBOARDING-AGENTES.md` (raíz del repo) — resume
el estado real de las ramas, qué está commiteado y qué no, y la relación con
`legado-holding` y `Prevision-Funeraria`. Para el estado funcional/técnico
vigente del proyecto, `docs/SPEC.md`. Para la bitácora día a día, `task.md`
(la entrada de más arriba basta para retomar).

Cuando el usuario pida cerrar la jornada/el proyecto/la sesión, sigue
`.claude/skills/cerrar-proyecto/SKILL.md`: agrega una entrada a `task.md`,
actualiza `docs/SPEC.md` si cambió algo material, commitea, y sincroniza con
Notion (hub "Propuesta Funerzul — Proyecto y documentación": registro del
día + base de datos Backlog).

## Módulo de Previsión (nuevo sistema, otro repo)

El reemplazo multiempresa del módulo de previsión (`admin-prevision.js` +
`api/prevision_*.php` de este repo) se está construyendo en **otro repositorio
separado**: `estebanjvasquez/Prevision-Funeraria` (en producción desde
2026-08 en `prevision-funeraria.sisteg.workers.dev`, según lo confirmado en
Notion el 2026-08-27 — verificar con el usuario si cambió algo antes de
asumir el estado exacto). Sirve tanto a Funeraria del Zulia
(este repo) como a Legado Holding Inc. (`estebanjvasquez/legado-holding`). El plan de
arquitectura completo vive ahí (`docs/PLAN.md`), no en este repo — no recrear ese
análisis aquí. La capa de pagos Mercantil de este repo (`api/lib/payments/`,
`docs/payments/mercantil/`) sigue siendo la referencia de diseño que ese proyecto nuevo
reutiliza para su propio adaptador de pagos.

## Specboot adaptado

Este repo usa una adaptacion ligera de `lidr-specboot`, no la plantilla completa.
Antes de cambios de codigo, leer `AGENTS.md` y los documentos aplicables en `docs/`:
`agent-standards.md`, `backend-standards.md`, `frontend-standards.md`,
`data-model.md` y `spec-template.md`.

Para cambios medianos o riesgosos, crear o actualizar una especificacion breve en
`docs/specs/` antes de implementar.

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

Rules:
- For codebase questions, first run `graphify query "<question>"` when graphify-out/graph.json exists. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
