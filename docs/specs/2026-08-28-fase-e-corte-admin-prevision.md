---
Estado: Plan (sin ejecutar) — ver "Alcance" y "Plan" abajo antes de tocar nada.
---

# Fase E — Corte del panel admin de Previsión hacia Prevision-Funeraria

Desglosa la Fase E de
[`docs/specs/2026-08-28-migracion-a-prevision-funeraria.md`](2026-08-28-migracion-a-prevision-funeraria.md#4-plan-de-migración-por-fases-funeraria-del-zulia),
con hallazgos nuevos que **corrigen una suposición** de ese documento. Este
plan no ejecuta nada todavía — es la respuesta a "planear la Fase E" pedida
el 2026-08-28, después de que activar `prevision_funeraria.enabled` (Fases
A/B/C, ya hechas) no cambió el panel admin — porque nunca estaba en su
alcance. Ver también [`docs/SPEC.md`](../SPEC.md) sección 2.

## Resumen

El staff de Funeraria del Zulia hoy gestiona clientes/contratos/cobros desde
`admin.html` (tab "Previsión") de este repo, contra MySQL. El objetivo de la
Fase E es que dejen de usar ese panel y pasen a usar el panel de
Prevision-Funeraria (`https://prevision-funeraria.sisteg.workers.dev`,
tenant `fdz`) para el trabajo real del día a día. Esto **no es un cambio de
código en este repo** más allá de un paso final (ocultar el tab) — es
principalmente coordinación: confirmar datos, crear cuentas de staff en PF y
fijar una fecha, todo del lado de Prevision-Funeraria (otro repo, otra
infraestructura, sin acceso desde esta sesión).

## Hallazgo clave (corrige la Fase E de la spec del 2026-08-28)

La spec anterior asumía "no hay big-bang de datos porque el módulo PHP nunca
tuvo datos reales". Es correcto, pero por una razón más específica que vale
la pena dejar registrada — investigado directamente en el repo
`Prevision-Funeraria` (`docs/PLAN.md`, ítem fechado 2026-08-19):

- **La migración de datos reales de Funeraria del Zulia ya se ejecutó**, y no
  vino de las tablas `prev_*` de MySQL de este repo (esas nunca se
  poblaron en producción — no hay evidencia de datos reales de cliente en
  ellas, solo el tab de admin sin uso confirmado). Vino directo del dump
  legacy **`database/SIEMPRE.sql`** — que sí vive en este repo — parseado por
  `scripts/extraer-siempre.ts` e importado a D1 (`fdz`) el 2026-08-19.
- Resultado de esa importación: **118 clientes/contratos, 471 beneficiarios,
  2881 cuotas, 10 planes, 13 vendedores**, vía `POST /importacion/{preview,aplicar}`
  (idempotente, columna `legacy_id`). Solo se importó la capa "actual" del
  dump (limpia, autoconsistente); la capa `-orig` (10466 filas de historial
  de pagos huérfano, sin cabecera de cliente/contrato) quedó **fuera de
  alcance por decisión ya tomada del usuario** — no hace falta revisitar esa
  decisión acá.
- **Blocker real pendiente, del lado de PF, no de este repo:** al
  2026-08-27, la migración de moneda del historial de FDZ ("Fase B2" en el
  plan de PF) tenía el `preview` corrido y validado contra los 118
  contratos/2881 cuotas reales, pero **`aplicar` no se había corrido contra
  producción** — a la espera de que alguien confirme el preview. Si esto
  sigue así, los contratos importados pueden estar mostrando montos
  derivados de bolívares crudos del legacy en vez de convertidos
  correctamente. **Hay que reconfirmar el estado actual (puede haber
  cambiado desde el 27-ago) antes de poner a un solo miembro del staff a
  trabajar con esos contratos en PF.**

Esto responde y cierra el punto 3 de la sección 6 de la spec de migración
("alcance real de la importación desde SIEMPRE") — ya no es una incógnita,
es un hecho verificado con fecha y cifras. Se actualiza esa sección abajo.

## Alcance

Incluye:

- Confirmar el estado real de la Fase B2 de PF (migración de moneda del
  historial FDZ) antes de fijar fecha de corte.
- Crear cuentas de staff de Funerzul en el `usuarios`/`usuario_tenant` de
  `DB_CONTROL` de PF (tabla y flujo documentados abajo).
- Verificación manual del staff: login, selección de tenant `fdz`, revisión
  de una muestra de los 118 contratos importados contra lo que conocen del
  cliente real (sanity check, no auditoría exhaustiva).
- Fecha de corte y comunicación al staff.
- Este repo: ocultar (no borrar) el tab "Previsión" de `admin.html`
  (`data-tab="prevision"`, línea 59; panel `id="tab-prevision"`, línea 177)
  una vez el corte esté confirmado — **último paso, no el primero**.

No incluye:

- Migrar nada de las tablas `prev_*` de MySQL — no hay datos reales que
  migrar de ahí (ver hallazgo arriba). Quedan como están, sin tocar.
- Tocar la integración pública (Fases A/B/C, sitio web) — ya está hecha y es
  independiente de esto.
- Resolver la Fase B2 de PF en sí (migración de moneda) — eso lo ejecuta
  quien tenga acceso al repo/Cloudflare de PF, no esta sesión.
- Borrar `admin-prevision.js` / `api/prevision_*.php` — se ocultan primero,
  se evalúa borrar meses después (ya acordado en la spec de migración,
  sección 5).

## Reglas de negocio / condiciones

- **No se crea ninguna cuenta de staff en PF, ni se fija fecha de corte,
  hasta confirmar el estado de la Fase B2** (montos en Bs vs. USD de los
  118 contratos importados) — un staff viendo montos incorrectos el primer
  día mata la confianza en el corte.
- Dado que no hay datos reales en MySQL, **no hace falta una ventana de
  "corrida en paralelo" con reconciliación de totales** — no hay dos fuentes
  de verdad que cuadrar. El corte puede ser el mismo día que el staff
  termine de validar el login y la muestra de contratos (paso de
  verificación abajo), no un proceso de semanas.
- Cada miembro de staff de Funerzul necesita su **propia cuenta** (`usuarios`
  + fila en `usuario_tenant` con `tenant_slug='fdz'`) — no se comparte una
  cuenta genérica entre varias personas (auditoría/trazabilidad).
- El login de PF es compartido entre tenants (`fdz`/`lh`) por diseño de PF
  — al crear la cuenta, la fila de `usuario_tenant` debe llevar
  **exactamente** `tenant_slug='fdz'`, nunca `'lh'` por error de copiar/pegar
  (un staff de Funerzul no debe terminar viendo datos de Legado Holding).

## Riesgos

- **Riesgo de datos:** si se crea acceso y el staff empieza a trabajar antes
  de confirmar la Fase B2, pueden registrarse cobros/decisiones sobre montos
  mostrados incorrectamente (Bs crudo vs. convertido). Mitigación: el paso 1
  del plan es explícitamente bloqueante.
- **Riesgo de aislamiento entre tenants:** un error al insertar
  `usuario_tenant` (tenant_slug equivocado) filtraría acceso cruzado entre
  Funerzul y Legado Holding. Mitigación: verificar cada alta con una
  consulta `SELECT` antes de entregar la contraseña al staff.
- **Riesgo operativo/humano:** el staff no tiene experiencia con el panel de
  PF (interfaz distinta, aunque cubre lo mismo). Mitigación: sesión de
  verificación supervisada (paso 4) antes de anunciar el corte como
  definitivo, no como parte del entrenamiento formal en sí.
- **Sin acceso de esta sesión a PF:** todos los pasos que tocan
  `DB_CONTROL`/D1 de Prevision-Funeraria requieren credenciales de Cloudflare
  de ese proyecto, que esta sesión (trabajando en `Propuesta-Funerzul`) no
  tiene. Cada paso marcado "(fuera de este repo)" abajo lo ejecuta quien
  tenga acceso a `Prevision-Funeraria`.

## Plan

1. **(fuera de este repo, bloqueante)** Confirmar con quien administre
   Prevision-Funeraria si la Fase B2 (migración de moneda del historial FDZ,
   `aplicar` de `src/routes/migracion-moneda.ts`) ya se corrió contra
   producción desde el 2026-08-27. Si no, decidir si se corre antes de dar
   acceso a staff, o si se documenta la limitación y se corre en paralelo
   sin bloquear el corte (decisión de quien tenga ese contexto, no se asume
   acá).
2. **(fuera de este repo)** Por cada persona de staff de Funerzul que va a
   usar el panel: generar hash con
   `npm run hash-password -- "<contraseña-fuerte>"` en el repo
   `Prevision-Funeraria`, luego:
   ```sql
   INSERT INTO usuarios (email, password_hash, nombre)
     VALUES ('correo@dominio', '<hash>', 'Nombre Apellido');
   INSERT INTO usuario_tenant (usuario_id, tenant_slug, rol)
     VALUES (<id insertado>, 'fdz', 'staff');
   ```
   vía `wrangler d1 execute prevision-control --remote --command "..."`.
   Verificar con un `SELECT` que `tenant_slug='fdz'` quedó correcto antes de
   entregar la contraseña.
3. **(supervisado, con staff real)** Login de prueba en
   `https://prevision-funeraria.sisteg.workers.dev/login.html`, confirmar
   que ve el tenant Funeraria del Zulia, y revisar 5-10 contratos conocidos
   contra lo que el staff recuerda del cliente real (nombre, plan, cuota
   aproximada) — sanity check, no cuadre contable exhaustivo.
4. Si el paso 3 pasa sin sorpresas: fijar y comunicar fecha de corte al
   staff (puede ser inmediata, no requiere ventana de semanas — ver "Reglas
   de negocio").
5. **En este repo**, el día del corte: ocultar el tab "Previsión" en
   `admin.html` (línea 59 `data-tab="prevision"`, línea 177
   `id="tab-prevision"`) — cambio pequeño y reversible (comentar el botón o
   agregar `hidden` fijo), sin borrar `admin-prevision.js` ni
   `api/prevision_*.php`.
6. Actualizar documentación (ver sección de abajo).

## Verificación

- Rol: staff de Funerzul con cuenta nueva en PF.
- Pantalla: `prevision-funeraria.sisteg.workers.dev/login.html` → dashboard
  del tenant `fdz`.
- Dato de prueba: 5-10 contratos reales importados (de los 118).
- Resultado esperado: login exitoso, tenant correcto (nunca `lh`), montos de
  cuota/contrato razonables (no bolívares crudos mostrados como si fueran
  USD — ver hallazgo de la Fase B2).
- Después de ocultar el tab en `admin.html`: recargar el panel admin de este
  repo, confirmar que el tab "Previsión" ya no aparece pero el resto del
  panel (usuarios, plantillas, configuración, obituarios) sigue funcionando
  igual.

## Documentación a actualizar

- [`docs/specs/2026-08-28-migracion-a-prevision-funeraria.md`](2026-08-28-migracion-a-prevision-funeraria.md) —
  sección 6, punto 3: ya no es una incógnita, referenciar este documento.
- `docs/SPEC.md` — marcar Fase E como "planeada" (no ejecutada) con link a
  este archivo.
- `ONBOARDING-AGENTES.md` — si el corte se ejecuta, actualizar la fila de
  Prevision-Funeraria (sección 3) para reflejar que ya es la fuente de
  verdad operativa para FDZ, no solo un plan.
- `api/README.md` — cuando el tab se oculte, nota breve en la sección
  "Módulo de Previsión" indicando que quedó en modo legado/solo lectura de
  referencia.
