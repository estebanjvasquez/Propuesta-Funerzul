# Backlog — Propuesta Funerzul

Reemplaza a la base de datos **"Propuesta Funerzul — Backlog"** de Notion,
que dejó de estar disponible (el usuario perdió el acceso a Notion en
octubre de 2026). Las 8 tarjetas que había allí se migraron a este archivo
el 2026-10-08 con su estado actualizado; las demás son nuevas.

Estados: `Pendiente` · `En curso` · `Bloqueado` (depende de un tercero) ·
`Hecho` · `Obsoleto` (ya no aplica — se conserva con el motivo, no se borra).

Cómo se mantiene: en cada cierre de jornada (`.claude/skills/cerrar-proyecto/`)
se cambian estados aquí y se agregan tareas nuevas. Las tareas `Hecho` /
`Obsoleto` se mueven a la sección final en vez de borrarse.

---

## Abiertas

### Urgente — sitio de prueba (`legadoholding.com/funerzul/`)

| Tarea | Estado | Notas |
|---|---|---|
| Restablecer la conexión a MySQL del sitio de prueba | Pendiente | Desde el 2026-10-08 (al menos) todo endpoint con BD responde `SQLSTATE[HY000] [2002] Resource temporarily unavailable` de forma sostenida (6/6 intentos en 30 s). Caídos: `obituarios.php`, `api/obituaries.php`, `api/templates.php`. Revisar en cPanel → phpMyAdmin; si no entra, ticket al hosting; si entra, probar `host` = `127.0.0.1` o `160.153.190.119` en `api/config.php`. |
| Poner `app.env` en `'production'` en el `api/config.php` del servidor | Pendiente | Hoy está en `development`: la API publica el mensaje crudo de la excepción de PDO (ver `api/lib/bootstrap.php`). |
| Corregir `app.site_url` en el `api/config.php` del servidor | Pendiente | Verificado otra vez el 2026-10-08: canonical/og:url salen como `https://www.https://legadoholding.com/funerzul/...`. Rompe canonical/OG/JSON-LD/sitemap en ~30 páginas. Lo corrige quien tenga acceso al servidor (archivo fuera de git). Ver `docs/specs/2026-09-09-auditoria-seo-funerzul.md`. |
| Recuperar una copia de `api/config.php` del servidor | Pendiente | La copia local se perdió con el equipo anterior. Guardarla fuera del repo (nunca se commitea). |
| Aplicar y probar en el servidor real las migraciones 11, 12 y 13 + tarjetas de obituario | Pendiente | `database/11_secciones_toggle.sql`, `12_plantillas_esquela.sql`, `13_plantilla_cinta_imagen_real.sql`. Nunca se probaron contra la BD real. `api/diag.php` ya reporta si la 12 está aplicada y prueba GD/FreeType. Depende de la tarea de MySQL. |

### Del lado de Prevision-Funeraria (no se resuelve en este repo)

| Tarea | Estado | Notas |
|---|---|---|
| Cargar el catálogo de servicios del tenant `fdz` | Pendiente | `GET /api/public/t/fdz/servicios` sigue devolviendo `items: []` (2026-10-08). El tenant `lh` sí tiene 3 servicios. Ojo: los slugs no coinciden con los 4 de `servicios/*.php`. |
| Corregir precio de Tradición por debajo de Esencial | Pendiente | 2026-10-08: TRADICION US$12,00/mes vs. ESENCIAL US$13,70/mes. El catálogo de `fdz` tiene ahora 9 planes (incluye variantes "NEW" y planes de empleados al 30%/50%) — confirmar cuáles deben mostrarse en el sitio público. |
| SSO del panel admin de este sitio con el staff del tenant `fdz` | Bloqueado | El módulo de autenticación compartida no existe en Prevision-Funeraria. Ver `docs/specs/2026-08-28-fase-e-corte-admin-prevision.md`. |
| Mercantil: spec completa (`POST /api` + MasterKey del webhook) | Bloqueado | Depende del banco. Relevante para Prevision-Funeraria; este repo ya no tiene endpoints de cobro. *(Migrada de Notion.)* |

### Repositorio / documentación

| Tarea | Estado | Notas |
|---|---|---|
| Decidir si `feature/prevision-funeraria` se mergea a `main` (o PR) | Pendiente | *(Migrada de Notion, donde decía `feature/modulo-prevision` — esa rama quedó congelada tras el corte del 2026-08-28.)* `main` sigue en el estado del 2026-07-02 + onboarding. |
| Evaluar usar `mostrar_precio` por plan (API de PF) en vez del interruptor global `show_prices` | Pendiente | El commit `e7e24ee` asumía que PF no exponía visibilidad por ítem; el 2026-10-08 la API ya devuelve `mostrar_precio` en cada plan. Cambio chico en `api/lib/prevision_funeraria.php` + `partials/pf_precio_plan.php`. |
| Recrear los documentos de reglas perdidos | Pendiente | `AGENTS.md`, `docs/agent-standards.md`, `docs/frontend-standards.md`, `docs/spec-template.md`, `docs/specs/README.md` nunca se commitearon y se perdieron con el equipo anterior. `CLAUDE.md` y `ONBOARDING-AGENTES.md` todavía los mencionan. Sí sobreviven `docs/backend-standards.md` y `docs/data-model.md`. |
| Rehacer `docs/legado-holding-prevision/` | Pendiente (probablemente innecesaria) | *(Migrada de Notion.)* Solo si Prevision-Funeraria (tenant `lh`, en producción) no cubre ya lo que necesita Legado Holding. Confirmar con el usuario antes de invertir tiempo. |

---

## Cerradas

| Tarea | Estado | Motivo |
|---|---|---|
| Confirmar si sigue el módulo PHP de previsión o se migra a Prevision-Funeraria | Hecho | Decidido el 2026-08-28: corte inmediato, módulo retirado (respaldo en `archive/modulo-prevision-php`). *(Migrada de Notion.)* |
| Ronda 3 del módulo de previsión (domiciliación, planes colectivos, imprimibles, envejecimiento de dependientes) | Obsoleto | El módulo PHP se retiró el 2026-08-28; si hace falta, es trabajo de Prevision-Funeraria. *(Migrada de Notion.)* |
| Numeración de contratos configurable (`docs/prevision/plan-2026-08-07.md`) | Obsoleto | Mismo motivo: los contratos viven ahora en Prevision-Funeraria. *(Migrada de Notion.)* |
| Priorizar con el cliente las 6 mejoras de previsión (`docs/propuesta-mejoras-prevision.md`) | Obsoleto | Mismo motivo; el documento queda como histórico. *(Migrada de Notion.)* |
| Contratar proveedor de mensajería WhatsApp/SMS | Obsoleto (en este repo) | La mensajería configurable era parte del módulo PHP retirado. Si sigue haciendo falta, se resuelve en Prevision-Funeraria. *(Migrada de Notion.)* |
