# Plan — Migrar el módulo de Previsión de Funerzul a Prevision-Funeraria

**Fecha:** 2026-08-28
**Estado:** Fases A, B y C **implementadas** (mismo día, a pedido del
usuario) y **apagadas por defecto** (`enabled => false` en config) — no
afectan producción hasta que alguien las active explícitamente. El resto del
plan (fases D, E, F y las decisiones de la sección 6) sigue siendo un plan
para revisión, no implementar sin aprobación explícita.
**Relacionado:** `docs/SPEC.md` (roadmap #1), `ONBOARDING-AGENTES.md`
(sección 3 y 7), Notion → tarjeta "Confirmar con el usuario: ¿sigue el módulo
PHP de previsión, o se migra a Prevision-Funeraria?" en el Backlog.

---

## 0. Cómo se hizo este análisis

No se asumió nada del estado de `Prevision-Funeraria` desde este repo. Se
clonó el repo real (`estebanjvasquez/Prevision-Funeraria`, commit
`2026-08-27 15:46 UTC`, el más reciente en `main` al momento de escribir esto)
y se leyó directamente: `README.md`, `CLAUDE.md`, `docs/ONBOARDING-AGENTES.md`,
`docs/modulos-propuesta-funerzul.md` (análisis que ese mismo repo ya hizo del
código de **este** repo), `docs/PLAN.md` (secciones 5, 6 y partes de 9),
`docs/api-publica-wizard.md`, las 36 migraciones D1 de su tenant `fdz`, y
`src/routes/`, `src/lib/public-cors.ts` y `task.md`.

Esto importa porque `docs/PLAN.md` de ese repo tiene partes desactualizadas
que su propio equipo ya señaló (p. ej. la sección 5 sobre migración de datos
asume migrar las tablas `prev_*` de este repo, pero la migración real ya
tomada es importar directo del CRM legado **SIEMPRE**, porque `prev_*` nunca
se pobló en producción — ver sección 2 abajo). Este plan usa lo más actual
verificado en código y en `task.md`/`docs/ONBOARDING-AGENTES.md` de ese repo,
no lo primero que dice `docs/PLAN.md`.

## 1. Alcance actual de Prevision-Funeraria vs. el módulo PHP de Funerzul

Comparación funcionalidad-por-funcionalidad. "PF" = Prevision-Funeraria.

| Funcionalidad (módulo PHP) | ¿Existe en PF? | Nota |
|---|---|---|
| Clientes (titulares) | ✅ | |
| Planes (cuota, moneda, cobertura) | ✅, **mejorado** | PF ya soporta tarifa por edad (`plan_tarifas`, mejora #5 de la propuesta al cliente) — el PHP todavía no. |
| Contratos + beneficiarios | ✅ | Cascada de pagos, cuota más antigua primero — igual que el PHP. |
| Cuotas + pagos | ✅ | Incluye renovación automática de cuotas por cron — el PHP la genera manual/por lote. |
| Vendedores + comisiones (4 etapas) | ✅ | Cálculo escalonado, flujo `calculada→aprobada→pagada→anulada`, descuentos por anulación — paridad confirmada con datos reales del dump `prev_*`. |
| Siniestros con validación de cobertura | ✅, **simplificado** | PF usa 4 estados (`reportado→aprobado→pagado`, `rechazado`) en vez del ciclo `abierto/liquidado/cerrado` con reapertura del PHP — decisión ya confirmada con el usuario de PF, no es un hueco, es una simplificación deliberada. El campo `validacion` (JSON con cada chequeo) se preservó igual. |
| Cobranza (morosos, gestiones, auto-lapsado) | ✅ | |
| Sucursales, cobradores, rutas | ✅ | Solo filtro/catálogo dentro del tenant, como en el PHP — no es la capa multiempresa (esa ya está resuelta a otro nivel). |
| Ajustes masivos de tarifa (reversible) | ✅ | Migración + ruta (`src/routes/ajustes.ts`) ya existen. |
| Mensajería (plantillas) | ✅ esquema, ❌ envío real | Plantillas y catálogo listos; el envío real de WhatsApp/SMS nunca se resolvió — el OTP del portal usa **email** en su lugar (ver sección 2). |
| Reportes (aging, producción, cobranza, cartera) | ✅ | |
| Importación masiva desde sistema anterior | ✅ parcial | Importa de **SIEMPRE** (el CRM legado real), no de `prev_*` — y según `task.md` del 27-ago, falta terminar de importar "el resto de las tablas". |
| Adjuntos por contrato | ❌ | Bloqueado: la cuenta de Cloudflare de PF no tiene R2 habilitado todavía (paso manual pendiente). |
| Portal de autogestión del titular (mejora #1) | ✅, **ya en producción** | Login por cédula + OTP por email, ver contrato/cuotas/beneficiarios. El PHP no tiene nada de esto. |
| Portal de autoservicio del vendedor | ✅ (no existía en el PHP) | Login OTP, ficha `/vendedor?id=`, atribución de campaña/canal — más allá de lo que el PHP hace. |
| Captación de leads (solicitudes públicas) | ✅, **con API pública lista** | Ver sección 3 — es la pieza central de este plan. |
| Pagos electrónicos Mercantil (Venezuela) | ✅ código, ❌ en vivo | Compra síncrona vía `punto`+`pago_tarjeta` ya implementada (`POST /compras`); bloqueada porque el sandbox de Mercantil rechaza las llamadas del Worker de Cloudflare (filtro de IP/ASN del banco, no resuelto). Mismo bloqueo que ya tenía el PHP (spec completa de Mercantil pendiente del banco), pero **distinto motivo**: acá no es falta de spec, es una llamada que el banco rechaza en red. |
| Pagos Stripe (EE. UU., Legado Holding) | ✅ código, ⚠️ cuenta en modo test | No aplica a Funerzul directamente, pero confirma que `PagosProvider` funciona de punta a punta con al menos una pasarela real. |
| Servicios de emergencia (triage) | ✅ esquema, ❌ flujo completo | Novedad que el PHP **no tiene**: un servicio marcado `es_emergencia` se excluye del flujo normal de leads y debe ir directo a WhatsApp con un humano — "triage de emergencias" sigue en el backlog de PF sin cerrar. |
| Página web pública (obituarios, directorio médico, recursos, FAQs) | ❌, **fuera de alcance de PF por diseño** | Ver sección 3 — nunca estuvo en el plan de PF, no es un hueco. |

**Conclusión de la comparación:** PF ya cubre o supera casi todo el módulo de
previsión del PHP. Los huecos reales que faltan para poder migrar Funerzul
completo están en la sección 2, no en funcionalidad de negocio faltante.

## 2. Huecos y fricciones reales (lo que sí bloquea, verificado en código/`task.md`)

Esto es lo que hay que resolver — no funcionalidad por construir, sino
bloqueos operativos y decisiones pendientes:

1. **Mercantil: el Worker de Cloudflare no puede llamar al sandbox del banco**
   (filtro de IP/ASN del lado de Mercantil, confirmado — funciona desde una
   red local, no desde Cloudflare). Bloquea el cobro electrónico real para
   Funerzul. Sin este resuelto, cualquier compra en Bs con tarjeta queda en
   modo simulado o debe conciliarse manualmente, igual que hoy en el PHP.
2. **Adjuntos de contrato (R2 sin habilitar)**: la cuenta de Cloudflare de PF
   no tiene R2 activado. Bloquea migrar `prev_adjuntos` (documentos por
   contrato) hasta que se resuelva en el dashboard de Cloudflare (paso
   manual, no de código).
3. **Importación de datos reales de Funerzul incompleta**: PF importa desde
   **SIEMPRE** (el sistema legado real), no desde las tablas `prev_*` de
   este repo — porque **el módulo PHP de previsión nunca se puso en
   producción** (sigue en la rama `feature/modulo-prevision`, sin mergear a
   `main`, tal como quedó documentado en `ONBOARDING-AGENTES.md` de este
   repo). Esto en realidad **simplifica** la migración: no hay que
   reconciliar dos fuentes de verdad (SIEMPRE + `prev_*`), solo terminar de
   importar de SIEMPRE lo que falte. Según `task.md` de PF (27-ago), queda
   pendiente importar "el resto de las tablas" — no se especifica cuáles.
4. **Envío real de mensajería (WhatsApp/SMS)**: sin resolver en PF, igual que
   en el PHP (ahí tampoco hay proveedor contratado). El OTP del portal del
   titular ya sortea esto usando email en vez de WhatsApp, pero el dominio
   remitente (`previsionfuneraria.com`) todavía necesita onboardearse a mano
   en Cloudflare Email antes de que el envío real funcione en producción
   (hoy falla silenciosamente).
5. **Triage de servicios de emergencia**: PF tiene el esquema
   (`es_emergencia`) pero el flujo completo de atención (redirigir a
   WhatsApp con un humano) sigue en el backlog sin cerrar. Relevante para
   Funerzul porque su sitio ya distingue servicios de urgencia
   (`crematorios-del-zulia.php`, CTA de pago electrónico) — hay que
   confirmar que el diseño de PF cubre los mismos casos antes del corte.
6. **`funerariadelzulia.com` no llama todavía a PF**: el CORS y las rutas
   públicas ya están listas del lado de PF (`src/lib/public-cors.ts` ya
   incluye `funerariadelzulia.com`/`www.funerariadelzulia.com` en la lista
   de orígenes permitidos), pero nadie conectó el sitio real todavía — es
   trabajo pendiente **de este repo**, no de PF. Es la pieza central de la
   sección 3.
7. **3 PRs de PF pendientes de merge a `main`** (código ya en producción vía
   deploy directo, según `task.md` de PF del 27-ago) — no bloquea este plan,
   pero conviene que el usuario lo sepa antes de asumir que el `main` de PF
   refleja el estado real desplegado.

## 3. La pregunta central: ¿migra también la construcción dinámica de la página web?

**Respuesta corta: no toda — solo planes, servicios y captación de
leads/compras. El resto de la página web (obituarios, directorio médico,
recursos, FAQs, SEO) se queda en este repo, sin cambios.**

Esto no es una recomendación en el vacío — es el **mismo patrón que ya está
decidido y parcialmente ejecutado** para Legado Holding, el otro tenant real
de PF:

- **Decisión ya tomada con el usuario (2026-08-18, `docs/PLAN.md` de PF,
  sección 9):** "Dónde vive el formulario público de captación de leads —
  en cada dominio, no centralizado. Cada sitio
  (`funerariadelzulia.com`, `legadoholding.com`) tiene su propio
  embudo/captura de leads, y ambos escriben los leads en este sistema para
  que el staff los vea y trabaje ahí."
- **Ya implementado y en CORS de producción** para ambos dominios
  (`src/lib/public-cors.ts`): `GET /planes`, `GET /servicios` (catálogo, sin
  token, pensado para `fetch()` directo desde el navegador) y
  `POST /solicitudes-publico` / `POST /compras` (con token de API por
  tenant, server-to-server).
- **Legado Holding ya migró su wizard a esto** (confirmado en Notion, sesión
  del repo `legado-holding` del 25-ago: "migración completa de billing de
  Invoice Ninja a la API pública de este sistema") y **su plan es
  desinstalar Invoice Ninja por completo** una vez PF cubra todo lo que IN
  resuelve hoy — es decir, el mismo tipo de corte que este plan propone para
  Funerzul, ya en curso del otro lado.

Lo que **no** migra, y por qué:

- **Obituarios, directorio médico, recursos, FAQs**: no tienen nada que ver
  con previsión funeraria como producto. PF nunca los tuvo en su alcance
  (confirmado en `docs/modulos-propuesta-funerzul.md` de PF: los agrupa como
  "módulo 1 y 2", explícitamente fuera de lo que PF reemplaza). Se quedan en
  PHP/MySQL, sin cambios.
- **SEO/JSON-LD de las páginas públicas**: server-rendering en PHP es lo que
  hoy da indexabilidad sin depender de JavaScript. Mover eso a un `fetch()`
  contra un Worker externo sería un paso atrás en SEO, no una mejora —
  ninguna razón para tocarlo.
- **El panel admin de contenido del sitio** (obituarios, doctores, artículos,
  FAQs, usuarios — "módulo 2" en la nomenclatura de PF): sigue siendo PHP,
  no tiene relación con previsión.

Lo que **sí** migra (contenido dinámico, pero de producto/comercial, no de
contenido editorial):

- Las páginas `planes/` y `servicios/` dejan de leer su catálogo de
  `prev_planes`/`prev_servicios` en MySQL y pasan a consumir
  `GET /api/public/t/fdz/planes` y `GET /api/public/t/fdz/servicios` de PF
  (mismo patrón CORS ya habilitado). El **contenido SEO de cada página**
  (textos largos, ficha descriptiva) puede seguir siendo estático/PHP si es
  contenido editorial que no cambia con el catálogo comercial — a decidir
  caso por caso al implementar, no es una regla de todo-o-nada.
- El CTA de "pago electrónico" / solicitud de contratación
  (`partials/cta_pago_electronico.php`, `prevision_solicitudes.php`) deja de
  escribir en `prev_solicitudes_publicas`/`prev_pagos_electronicos` de MySQL
  y pasa a llamar `POST /api/public/t/fdz/solicitudes` o
  `POST /api/public/t/fdz/compras` de PF, con el token de API del tenant
  `fdz` guardado del lado servidor (nunca expuesto al navegador — mismo
  criterio que ya sigue este repo para no exponer credenciales).
- Servicios marcados como urgencia (equivalente a `es_emergencia`) deben
  respetar el mismo patrón que ya define PF: CTA directo a WhatsApp, sin
  pasar por el formulario normal — alineado con lo que
  `crematorios-del-zulia.php` ya intenta resolver hoy en el PHP.

## 4. Plan de migración por fases (Funeraria del Zulia)

Orden pensado para no arriesgar nada que ya funciona en el sitio público
mientras se resuelve lo que sí depende de terceros (Mercantil, R2, mensajería).

**Fase A — Conectar catálogo de planes/servicios (bajo riesgo, sin dinero de por medio)**

- Generar el token de API del tenant `fdz` en PF (`POST` de staff, ver
  `docs/api-publica-wizard.md` sección "Generar el token").
- `planes/` y `servicios/` de este repo pasan a hacer `fetch()` a
  `GET /api/public/t/fdz/planes` / `.../servicios` para el catálogo
  (precio, moneda, tarifas por edad). El HTML/SEO alrededor no cambia.
- No toca pagos ni contratos — es solo lectura de catálogo. Reversible en
  minutos si algo falla (volver a leer de MySQL).

**Fase B — Conectar captación de leads (bajo riesgo, sin pagos automáticos)**

- El CTA "pago electrónico" / formulario de contratación pasa a llamar
  `POST /api/public/t/fdz/solicitudes` (o `/compras` con `forma_pago`
  distinto de `tarjeta`/`punto`, que siempre queda `pendiente` para
  conciliación manual de staff — sin riesgo de aprobar nada solo).
  `prevision_solicitudes.php` de este repo puede convivir en paralelo
  durante la transición si se decide no cortarlo de una vez.
- Deja de escribirse en `prev_solicitudes_publicas` de MySQL; el staff
  trabaja los leads desde el panel de PF en vez del panel PHP.

**Fase C — Triage de emergencias alineado**

- Confirmar con el equipo de PF que el flujo de `es_emergencia` cubre lo que
  `crematorios-del-zulia.php` necesita hoy, antes de apagar cualquier ruta
  de emergencia existente en el PHP.

**Fase D — Cobro electrónico real (bloqueada por Mercantil, no por este repo)**

- No se puede activar el cobro con tarjeta en vivo (`forma_pago: "punto"` +
  `pago_tarjeta`) hasta que se resuelva el filtro de IP/ASN de Mercantil del
  lado de PF. Mientras tanto, toda compra con tarjeta queda igual de
  bloqueada que hoy en el PHP (ambos sistemas dependen de la misma spec
  pendiente del banco).

**Fase E — Migración de datos y corte del módulo admin de previsión**

- Confirmar el estado real de la importación desde SIEMPRE en PF (qué tablas
  faltan, según el pendiente de `task.md` del 27-ago) antes de anunciar
  cualquier fecha de corte.
- Dado que el módulo PHP de previsión **nunca estuvo en producción real**
  (sigue en `feature/modulo-prevision`, sin mergear), no hay "big-bang" de
  datos de clientes reales que migrar desde MySQL — la migración de datos
  real ya está resuelta del lado de PF (importación desde SIEMPRE). Esto
  hace el corte más simple de lo que `docs/PLAN.md` de PF (sección 5)
  todavía describe: no hace falta congelar el PHP en solo-lectura ni
  validar totales MySQL-vs-D1, porque MySQL nunca tuvo los datos operativos
  reales de previsión.
- Una vez el staff empiece a usar el panel de PF para clientes/contratos
  reales de Funerzul, el módulo `admin-prevision.js` + `api/prevision_*.php`
  de este repo se puede **desactivar del panel admin** (ocultar el tab, no
  borrar el código todavía — mantenerlo unos meses como referencia/rollback).

**Fase F — Decisión sobre adjuntos y ajustes de tarifa** (dependen de R2 en PF)

- Sin fecha hasta que R2 esté habilitado en la cuenta de Cloudflare de PF.

## 5. Qué pasa con este repo después del corte

- El sitio público (obituarios, directorio médico, recursos, FAQs, planes/
  servicios) **sigue viviendo aquí**, en PHP/MySQL, sin cambios de
  arquitectura — solo cambia de dónde saca los datos de planes/servicios/
  leads (secciones 3 y 4).
- El panel admin de contenido (`admin.html` módulos 1 y 2 según la
  nomenclatura de PF) sigue igual.
- `admin-prevision.js` + `api/prevision_*.php` + `api/lib/payments/` quedan
  como código legado, ocultos del panel una vez el corte esté validado, sin
  borrarse de inmediato — permite volver atrás si algo del lado de PF falla
  en producción.
- La capa de pagos Mercantil de este repo (`api/lib/payments/`,
  `docs/payments/mercantil/`) sigue siendo la referencia de diseño para el
  adaptador de PF (regla ya vigente en `CLAUDE.md`) — no se retira aunque el
  módulo de previsión se apague, porque documenta el conocimiento real
  acumulado sobre la integración con el banco.
- `docs/resume.md`, `docs/SPEC.md` y `ONBOARDING-AGENTES.md` de este repo se
  actualizan para reflejar que el módulo de previsión es legado, no la
  fuente activa de verdad, una vez el corte se ejecute (no antes).

## 6. Riesgos y decisiones que requieren al usuario (no se asumen aquí)

1. **Fecha y forma del corte** (Fase E) — quién y cuándo confirma que el
   staff ya puede trabajar 100% desde el panel de PF para Funerzul.
2. **Qué contenido de `planes/`/`servicios/` sigue siendo texto editorial
   fijo vs. qué se vuelve dinámico desde PF** (sección 3) — puede decidirse
   página por página, no requiere ser todo-o-nada desde el día uno.
3. **Alcance real de "importar el resto de las tablas" desde SIEMPRE** —
   solo lo puede confirmar quien tenga acceso al estado actual de PF
   (`task.md` del 27-ago no lo detalla).
4. **Si se desactiva el módulo PHP de previsión del panel admin, o se borra
   directamente** — este plan recomienda ocultarlo primero y evaluar borrar
   más adelante, pero es una decisión de producto, no técnica.
5. **Prioridad relativa** de este plan frente a las seis mejoras propuestas
   el 11-ago (`docs/propuesta-mejoras-prevision.md`) — si PF ya cubre 4 de
   esas 6 mejoras (portal titular, planes por edad, siniestros ampliados,
   leads con prioridad — ver sección 1), es razonable que este plan de
   migración **reemplace** esa propuesta en vez de ejecutarse en paralelo.
   Requiere confirmarlo con la clienta (Damiana Villalobos), que ya dio su
   feedback de priorización sobre esas seis mejoras directamente al equipo
   de PF (`docs/PLAN.md` de PF, sección 6) — evitar que este repo y PF le
   pidan la misma decisión dos veces por separado.

## 7bis. Progreso real (actualizado 2026-08-28, mismo día del plan)

Implementación verificada contra la API real de producción (no contra
documentación) — ver `api/README.md`, sección "Integración con
Prevision-Funeraria", para el detalle técnico completo.

- **Fase A (catálogo)** — ✅ código listo, planes con precio real
  (`GET /api/public/t/fdz/planes` probado en vivo: 9 planes en el tenant
  `fdz`, incluye 4 duplicados/variantes de import que no se muestran en este
  sitio porque el mapeo es por slug explícito, no por listar todo el
  catálogo — ver huecos de datos abajo). Servicios sin cambios: el catálogo
  de servicios de PF para `fdz` está **vacío** (`GET /servicios` → `{items:
  []}`), así que no hay nada que mostrar todavía.
- **Fase B (leads)** — ✅ código listo, `POST /api/public/t/fdz/solicitudes`
  probado en vivo (sin token, sin problema de CORS server-to-server —
  verificado con el honeypot, no se creó ningún lead real de prueba).
  Reenvío de mejor esfuerzo, guardado local sigue intacto.
- **Fase C (triage de emergencias)** — ✅ código listo, pero **inerte**
  hasta que el catálogo de servicios de `fdz` tenga al menos un ítem — no
  hay nada que probar en vivo todavía (mismo motivo que Fase A/servicios).

**Huecos de datos encontrados al implementar** (no eran visibles solo
leyendo código, hacía falta llamar la API real):

1. El catálogo de planes de `fdz` en PF tiene **9 entradas**, no 4 — junto a
   `esencial`/`tradicion`/`vanguardia`/`vanguardia-plus` (los 4 que este
   sitio muestra) hay `emp-esencial-30`, `emple-esencial-50`,
   `esencial-new`, `tradicion-new`, `vanguardia-total`, con nombres que
   sugieren restos de la importación desde SIEMPRE (planes de empleador,
   duplicados con precio distinto). Este sitio los ignora a propósito (solo
   busca los 4 slugs conocidos), pero alguien con acceso al panel de PF
   debería revisar si esas 5 entradas son datos reales que faltan
   depurar/renombrar o basura de importación — no es algo que se pueda
   decidir desde este repo.
2. Confirmado en vivo: `precio_mensual_centavos` de `tradicion` (1200) es
   **menor** que el de `esencial` (1370) en el catálogo de PF, aunque en
   este sitio Tradición se presenta como el plan "de más categoría" que
   Esencial. Puede ser un dato de importación sin depurar (mismo origen que
   el punto 1) — vale la pena confirmarlo antes de mostrar el precio en
   producción, para no exhibir una inconsistencia real al público.

**Qué falta para que esto quede visible en producción:**

1. Copiar el bloque `prevision_funeraria` de `api/config.example.php` a
   `api/config.php` en el servidor, con `enabled => true`.
2. Confirmar permisos de escritura de `cache/prevision_funeraria/` (o
   aceptar que funcione sin cache, más lento).
3. Resolver los dos huecos de datos de arriba con quien tenga acceso al
   panel de Prevision-Funeraria — **idealmente antes** de activar `enabled`,
   para no mostrarle al público un precio de Tradición más barato que
   Esencial.
4. Decidir si se activa ya, o se espera a tener también servicios cargados
   en PF (para que Fase C deje de estar inerte).

## 7. Checklist antes de ejecutar cualquier fase

- [ ] Usuario confirma el orden de fases de la sección 4 (o lo ajusta).
- [ ] Token de API del tenant `fdz` generado y guardado como secreto en este
      repo (nunca en el navegador).
- [ ] Confirmado con el equipo de PF: estado real de la importación desde
      SIEMPRE (punto 3 de la sección 2).
- [ ] Confirmado con el equipo de PF: diseño de triage de emergencias cubre
      el caso de uso real de Funerzul (punto 5 de la sección 2).
- [ ] Decisión tomada sobre el punto 5 de riesgos (reemplazar vs. ejecutar
      en paralelo la propuesta de seis mejoras).

## Documentación a actualizar al ejecutar

- `docs/SPEC.md` (estado del módulo de previsión, roadmap).
- `ONBOARDING-AGENTES.md` (sección 3 y 7 — ya no serían preguntas abiertas).
- `README.md` (sección 14, una vez el panel deje de usar el módulo PHP).
- Backlog de Notion — cerrar/actualizar la tarjeta relacionada.
