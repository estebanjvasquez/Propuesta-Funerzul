# Plan técnico — Mejoras del módulo de Previsión

> **Archivado (2026-08-28):** el módulo PHP de Previsión fue retirado (ver
> [`2026-08-28-fase-e-corte-admin-prevision.md`](2026-08-28-fase-e-corte-admin-prevision.md)).
> Documento histórico, no implementar.

**Fecha:** 2026-08-11
**Relacionado (cara al cliente):** `docs/propuesta-mejoras-prevision.md`
**Estado:** Borrador de planificación. No implementar sin spec por mejora y aprobación.

Este documento traduce las seis mejoras de la propuesta a detalle técnico, anclado en
el sistema actual (PHP 8.1 + PDO + MySQL, sin framework; panel `admin.html` +
`admin-prevision.js`; capa de pagos `api/lib/payments/` con Mercantil). Cada mejora
requerirá su **spec breve** en `docs/specs/` antes de implementar (ver `AGENTS.md`).

## Origen de las ideas (repos analizados)

- **funeral-manager-org/funeral-admin** (Python/Flask) — pólizas, `Claims`,
  `CoverPlanDetails`, multiempresa/sucursal, `payment_code_reference`.
- **Blue-IT-Marketing/funeral-cover-admin** (Flask) — `leads`, `funeralforms`,
  `PaymentsDetails`, `branches`.
- **AlexMGP7/web-funeraria** (PHP/MySQL) y **Josexv1/admin-poliza-seguros** (PHP/PDO)
  — mismo stack; referencia de esquema.
- **lfmll/…gestion-de-polizas** (Laravel) — `Pólizas/Clientes/Pagos/Coberturas/Agentes`
  + casos de uso documentados.
- **meravimane/assure** (Spring Boot) — prima por edad, miembros con edad, tipos de
  reclamo, auth OTP, dashboard del asegurado.
- **Omaral1215/Health-Insurance / caresure** (MERN) — cara cliente vs admin,
  renovación, flujo de reclamo submission→approval→payment.

> Adaptar **conceptos y modelo**, no código (stacks distintos). No copiar auth de
> demos.

## Estado actual relevante (tablas `prev_*` existentes)

`prev_clientes`, `prev_contratos`, `prev_beneficiarios`, `prev_planes`, `prev_cuotas`,
`prev_pagos`, `prev_pagos_electronicos`, `prev_pago_eventos`, `prev_siniestros`,
`prev_siniestro_detalles`, `prev_adjuntos`, `prev_vendedores`, `prev_comisiones`,
`prev_solicitudes_publicas`, `prev_sucursales`, `prev_servicios`,
`prev_contrato_servicios`, `prev_gestiones`, `prev_msg_envios`, `prev_msg_plantillas`,
`prev_tasas`, `prev_ajustes`, `prev_parentescos`, `prev_rutas`, `prev_cobradores`,
`prev_import_lotes`.

Varias mejoras **extienden** estructuras que ya existen (sucursales, beneficiarios,
siniestros, solicitudes), lo que reduce el riesgo.

---

## Mejora 1 — Portal de autogestión del titular

**Inspiración:** assure (OTP, dashboard del asegurado), caresure (cara cliente).

**Objetivo:** zona pública autenticada donde el titular consulta su contrato y paga en
línea, sin acceso al panel de staff.

**Datos:**
- Nueva tabla `prev_titular_accesos` (o similar): `cliente_id`, `codigo_otp_hash`,
  `expira_en`, `intentos`, `ultimo_login`. OTP de un solo uso, con expiración.
- Reutiliza (solo lectura): `prev_contratos`, `prev_cuotas`, `prev_pagos`,
  `prev_beneficiarios`, `prev_siniestros`.

**Backend:**
- Nuevo endpoint público `api/prevision_portal.php` con acciones: `solicitar_otp`
  (envía OTP por WhatsApp vía la infraestructura de `prev_msg_envios`),
  `verificar_otp` (crea sesión de titular separada de la de staff), `resumen`,
  `cuotas`, `pagar` (reusa `PaymentService::crearIntento()` → Mercantil).
- Sesión de titular **aislada** de la sesión admin (cookie/scope distinto; nunca
  exponer endpoints de staff).

**Frontend:** páginas públicas nuevas (no en `admin.html`), estilo sobrio de
`styles.css`. Reutiliza el botón Mercantil ya integrado.

**Reglas de negocio:** el pago se aprueba solo por `PaymentService::conciliar()` o el
webhook; la vista del titular nunca aprueba por sí sola. Rate-limit en `solicitar_otp`.

**Riesgos:** seguridad (autenticación de cara pública, PII, enumeración de cédulas),
límites de envío de OTP. Requiere spec de seguridad dedicada.

**Dependencias:** cobranza Mercantil operativa; canal WhatsApp (`prev_msg_envios`).

---

## Mejora 2 — Gestión de siniestros más completa

**Inspiración:** funeral-admin `Claims` (claim_amount, claim_total_paid, date_paid,
claim_status), caresure (submission→approval→payment), assure (tipos de reclamo).

**Objetivo:** enriquecer el ciclo del siniestro ya existente.

**Datos (extender, no recrear):**
- `prev_siniestros`: añadir `tipo_reclamo`, `monto_aprobado`, `monto_pagado`,
  `fecha_pago`, `proveedor_id` (opcional).
- `prev_siniestro_detalles` / `prev_adjuntos`: ya soportan desglose y documentos;
  formalizar checklist de documentos requeridos por tipo.
- Opcional: catálogo `prev_proveedores` para coordinación de servicios (o reutilizar
  `prev_servicios`/catálogos).

**Backend:** extender `api/prevision_siniestros.php` (nuevos campos, transiciones de
estado aprobado→pagado, validación monto_pagado ≤ monto_aprobado).

**Frontend:** subpanel "Siniestros" de la consola (`admin-prevision.js`,
`pvLoadSiniestros`): sección de aprobación/pago y adjuntos por tipo.

**Riesgos:** consistencia contable (aprobado vs pagado), auditoría (`audit_log`).

---

## Mejora 3 — Renovación / reactivación de contratos

**Inspiración:** caresure (renew), funeral-admin (`policy_active`).

**Objetivo:** complementar el auto-lapsado existente con el flujo inverso.

**Datos:** `prev_contratos`: estados/campos para reactivación (`fecha_lapso`,
`fecha_reactivacion`, `motivo`); parametrizar **período de gracia** en `prev_ajustes`
o settings.

**Backend:** acción `reactivar` en `api/prevision_contratos.php`; regla de gracia en
el cron de lapsado ya existente (`api/cron`).

**Frontend:** acción en el detalle de contrato (`pvVerContrato`).

**Riesgos:** recálculo de cuotas atrasadas al reactivar; definir si se condonan o se
exigen. Bajo esfuerzo si se apoya en la máquina de estados actual.

---

## Mejora 4 — Captación y seguimiento de prospectos (leads)

**Inspiración:** Blue-IT `leads`/`funeralforms`.

**Objetivo:** convertir `prev_solicitudes_publicas` en pipeline con prioridad.

**Datos:** `prev_solicitudes_publicas`: añadir `puntuacion`/`prioridad`,
`asignado_a` (vendedor), `proximo_contacto`. Reglas simples de scoring (plan de
interés, recencia, método de pago).

**Backend:** extender `api/prevision_solicitudes.php` (orden por prioridad, asignación).

**Frontend:** subpanel "Solicitudes" existente (`pvLoadSolicitudes`): orden y filtros
por prioridad/vendedor.

**Riesgos:** bajos; es evolución de una función existente.

---

## Mejora 5 — Tarifa por edad y cobertura por beneficiario

**Inspiración:** assure (prima por edad, miembros con edad), funeral-admin
(`total_family_members`, coverage_amount).

**Objetivo:** precios por banda de edad y montos de cobertura por beneficiario.

**Datos:**
- Nueva `prev_plan_tarifas`: `plan_id`, `edad_min`, `edad_max`, `cuota`, `cobertura`.
- `prev_beneficiarios`: añadir `monto_cobertura`, `edad`/`fecha_nacimiento` si falta.
- `prev_planes` mantiene compatibilidad (cuota plana como caso por defecto).

**Backend:** cálculo de cuota en alta de contrato (`api/prevision_contratos.php`,
`api/prevision_planes.php`).

**Frontend:** editor de planes (`pvFormPlan`) con tabla de bandas de edad.

**Reglas de negocio:** **no** re-tarifar contratos vigentes; aplica a nuevas
contrataciones. Interacción con "Ajustes de tarifas" (`prev_ajustes`) a definir.

**Riesgos:** compatibilidad con planes existentes; pruebas de cálculo.

---

## Mejora 6 — Operación por sucursales / multiempresa

**Inspiración:** funeral-admin (`branch_uid`, `company_uid`).

**Objetivo:** consolidar operación por sede/empresa.

**Estado:** `prev_sucursales` ya existe (operación por sucursal es viable aquí). La
capa **multiempresa completa** (varias empresas, aislamiento por `empresa_id`) es el
alcance del **repositorio separado `estebanjvasquez/Prevision-Funeraria`** — ver
`docs/PLAN.md` de ese repo. **No recrear ese plan aquí** (regla de `CLAUDE.md` y
`AGENTS.md`).

**Alcance local posible (sin invadir el otro proyecto):** asegurar que contratos,
cuotas, vendedores y reportes puedan **filtrar y consolidar por `sucursal_id`** de
forma consistente en la UI y los reportes actuales.

**Riesgos:** migración de datos existentes a sucursal; alcance debe coordinarse con
`Prevision-Funeraria` para no duplicar esfuerzo.

---

## Plan por fases (técnico)

- **Fase 1 (bajo riesgo, extiende lo existente):** Mejora 3 (renovación) + Mejora 4
  (leads). Sin tablas nuevas mayores.
- **Fase 2 (mayor valor de servicio):** Mejora 1 (portal titular, requiere spec de
  seguridad) + Mejora 2 (siniestros).
- **Fase 3 (estructural):** Mejora 5 (tarifas por edad) + Mejora 6 (sucursales/coord.
  con multiempresa).

## Verificación transversal (por mejora)

- Migración incremental nueva (numerada tras `10_prevision_pagos_electronicos.sql`),
  con requisitos previos declarados.
- `php -l` en PHP nuevo/editado; `node --check` en `admin-prevision.js`.
- Prueba de flujo principal + estado vacío/error en la consola.
- Actualizar `api/README.md`, `database/README.md` y `docs/data-model.md` según
  corresponda.

## Documentación a actualizar al ejecutar

- Spec por mejora en `docs/specs/`.
- `docs/data-model.md` (estados válidos de contratos/siniestros; nuevas tablas).
- `api/README.md` (nuevos endpoints/acciones).
