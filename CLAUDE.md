## Módulo de Previsión (nuevo sistema, otro repo)

El reemplazo multiempresa del módulo de previsión (`admin-prevision.js` +
`api/prevision_*.php` de este repo) se está construyendo en **otro repositorio
separado**: `estebanjvasquez/Prevision-Funeraria`. Sirve tanto a Funeraria del Zulia
(este repo) como a Legado Holding Inc. (`estebanjvasquez/legado-holding`). El plan de
arquitectura completo vive ahí (`docs/PLAN.md`), no en este repo — no recrear ese
análisis aquí. La capa de pagos Mercantil de este repo (`api/lib/payments/`,
`docs/payments/mercantil/`) sigue siendo la referencia de diseño que ese proyecto nuevo
reutiliza para su propio adaptador de pagos.

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

Rules:
- For codebase questions, first run `graphify query "<question>"` when graphify-out/graph.json exists. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
