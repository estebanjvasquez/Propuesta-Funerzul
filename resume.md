# resume.md — Contexto del Módulo de Previsión (para continuar en próximos sprints)

> Documento de trabajo interno. Resume la arquitectura, convenciones y el plan
> por rondas del módulo de Previsión, para retomar el desarrollo sin perder contexto.
> Última actualización: 2026-07-07 (Ronda 2 completada, commit `d0c0923`).

## 1. Qué es este proyecto

Sitio web + panel administrativo de **Funeraria del Zulia** (PHP 8 + PDO/MySQL en
cPanel compartido, JS vanilla, sin frameworks). El **módulo de Previsión** replica
y moderniza el sistema administrativo legado **SIEMPRE** (respaldo en
`database/SIEMPRE.sql`, 1.15 GB, **está en `.gitignore` y NUNCA debe commitearse**).

## 2. Estado por rondas

| Ronda | Fases | Estado | Commit |
|---|---|---|---|
| Base (v1) | Clientes, contratos, beneficiarios, cuotas/pagos, planes, vendedores+comisiones, importación CSV | ✅ | `4b74812` |
| Ronda 1 (v2) | A: sucursales+servicios · B: siniestros con validación de cobertura · C: cobranza (morosos, gestiones, auto-lapsado+cron, rutas/hoja de cobro) | ✅ | `3d30730` |
| Ronda 2 (v3) | D: ajuste masivo de tarifas (reversible) · E: mensajería WhatsApp/SMS multi-proveedor · I: reportes (aging, producción, cobranza, cartera) + CSV | ✅ | `d0c0923` |
| Ajustes 2.1 (v4) | Comisiones por estados (calcular→aprobar→pagar, `07_prevision_v4.sql`) · Reorganización de menús (topbar Sitio web/Previsión/Sistema; subnav de previsión por grupos) | ✅ | pendiente commit |
| **Ronda 3** | F: domiciliación bancaria por lotes (archivo de débito + retorno) · G: empleadores/planes colectivos (descuento por nómina) · H: documentos imprimibles (contrato, carnet, estado de cuenta) · J: envejecimiento automático de dependientes (edad tope del parentesco) | ⬜ pendiente | — |
| **Ronda 4** | K: portal de autogestión del cliente | ⬜ pendiente | — |

**Decisiones abiertas:**
- Proveedor de mensajería: aún sin contratar. El sistema quedó **configurable desde
  el panel** (Previsión → Mensajes → Configuración): manual / WhatsApp Cloud API /
  Twilio / API HTTP genérica. Cuando el cliente contrate uno, solo se cargan credenciales.
- Rama `feature/modulo-prevision` sin push: falta decidir push+PR o merge a `main`.

## 3. Convenciones del código (respetarlas en próximas fases)

### API PHP (`api/prevision_*.php`)
- Cada endpoint: `require lib/bootstrap.php` + `lib/prevision.php`; switch por `$_GET['action']`.
- Helpers: `require_method()`, `$u = require_role('admin','editor')`, `require_csrf()`,
  `json_out(['ok'=>...], code)`, `audit($accion, $tabla, $id, $detalles)`, `clean_str()`,
  `body_json()`, `db()` (PDO), `get_setting/set_setting/setting_int/setting_bool` (app_settings).
- Eliminaciones definitivas = solo `admin`. Transacciones con `beginTransaction/commit/rollBack`.
- Catálogos ENUM de la BD espejados como constantes `PREV_*` en `api/lib/prevision.php`.

### JS (`admin-prevision.js`)
- Objeto global `Prevision` (estado + wire + loadSub); **debe terminar con
  `window.Prevision = Prevision;`** (hook de pestañas en admin.js).
- Funciones globales `pv*` invocadas por `onclick` inline. Helpers de admin.js:
  `$`, `$all`, `escapeHtml` (atributos HTML), `escapeAttr` (SOLO literales JS en onclick:
  incluye comillas), `openModal/closeModal`, `toast`, `confirmAction`, `fmtDate`,
  `API.req(url, {method,json}|{form})`, `State.user.role` (`pvEsAdmin()`).
- Cache-bust: subir `?v=` de `admin-prevision.js` en admin.html en cada release.

### BD (`database/0N_prevision*.sql`)
- InnoDB, utf8mb4_unicode_ci, prefijo `prev_*`, FKs a `users(id)`, `INSERT IGNORE` para seeds.
- Orden de importación: 01 → 02 → 03 → 04 → 05 → 06.

### Deploy
- `.cpanel.yml` copia `api/` completo + lista explícita de archivos raíz
  (admin-prevision.js ya está). Los `.sql` no se despliegan (se importan por phpMyAdmin).
- Crons existentes: `api/cron/purge_photos.php`, `api/cron/prevision_lapsar.php`
  (CLI o `?token=` = `cron_secret` de config.php).

## 4. Mapa de archivos del módulo

| Archivo | Contenido |
|---|---|
| `api/lib/prevision.php` | Constantes PREV_*, validadores (prev_date/money/cedula...), tasa del día, `prev_validar_cobertura()`, `prev_lapsar()`, motor de mensajería (`prev_msg_enviar()` con drivers manual/whatsapp_cloud/twilio/http, `prev_msg_render()`, `prev_msg_telefono()`), `prev_csv_out()`, formatters `prev_*_out()` |
| `api/prevision_clientes.php` | CRUD titulares (baja lógica, hard delete admin) |
| `api/prevision_planes.php` | CRUD planes |
| `api/prevision_vendedores.php` | Vendedores + comisiones 4 etapas SIEMPRE (semana1, fin_mes1, mes2, mes13) |
| `api/prevision_contratos.php` | Contratos, beneficiarios, cuotas, pagos en cascada con conversión Bs/USD, stats, tasa |
| `api/prevision_import.php` | Importación CSV con simulación (clientes/vendedores/contratos/beneficiarios/pagos) |
| `api/prevision_siniestros.php` | Siniestros 2 pasos, snapshot de validación JSON, cierre de titular → contrato finalizado |
| `api/prevision_cobranza.php` | Morosos, gestiones/promesas, auto-lapsado config+preview+ejecutar, hoja de cobro |
| `api/prevision_catalogos.php` | Sucursales, servicios, cobradores, rutas + servicios por contrato |
| `api/prevision_ajustes.php` | Ajuste masivo: preview/aplicar/revertir/list/get |
| `api/prevision_mensajes.php` | config/config_set/test, plantillas CRUD, enviar/enviar_morosos/envios |
| `api/prevision_reportes.php` | aging/produccion/cobranza/cartera (+`&formato=csv`) |
| `admin.html` | Pestaña Previsión: 6 stats + 12 sub-pestañas (`pvSub-*`) |
| `admin-prevision.js` | Toda la UI del módulo (~2.700 líneas) |

## 5. Reglas de negocio clave (heredadas de SIEMPRE)

- **Pagos**: se aplican en cascada a las cuotas pendientes más antiguas; si la moneda
  difiere se convierte con la tasa del día; el excedente queda como abono a favor
  (`cuota_id NULL`). `pago_delete` (admin) restaura saldos exactos.
- **Comisiones**: 4 etapas por contrato; monto sugerido por `comision_venta` % del
  contrato o % mensual del vendedor; una etapa no se genera/paga dos veces (UNIQUE
  contrato_id+etapa). **Flujo por estados** (`prev_comisiones.estado`):
  calculada → aprobada → pagada (o anulada = se borra la fila no pagada para
  poder recalcular). `comisiones_pendientes` = etapas vencidas aún sin fila;
  `comision_calcular` las genera; `comision_aprobar`/`comision_pagar` avanzan el
  estado; los totales de `comisiones_resumen`/`comisiones` filtran estado='pagada'.
- **Cobertura de siniestro**: contrato activo + beneficiario activo + plazo de espera
  (del beneficiario u override, si no `vigente_desde`) + solvencia → cubierto /
  con_observaciones / sin_cobertura; los checks se congelan en JSON en el expediente.
- **Cierre de siniestro del titular** → contrato `finalizado` + cuotas pendientes anuladas.
- **Auto-lapsado**: contratos activos con ≥ N cuotas vencidas → `suspendido`
  (settings `prev_lapse_enabled`, `prev_lapse_cuotas`).
- **Ajustes de tarifas**: guardan valor anterior → nuevo por contrato/plan/cuota en
  `prev_ajuste_detalles`; el reverso solo restaura cuotas aún pendientes sin abonos.
- **Mensajería**: proveedor por canal en app_settings (`prev_msg_proveedor_whatsapp/sms`);
  secretos nunca viajan al navegador (se enmascaran como `__set__`); teléfonos se
  normalizan a internacional (código país `prev_msg_pais`, por defecto 58).
  - **Consola «Enviar por WhatsApp»** (Mensajes → Enviar): filtra contratos
    (buscar / estatus / plan / solo morosos), elige plantilla o texto libre y
    genera enlaces **wa.me** por contrato (acción `preparar`, no registra) para
    abrir WhatsApp Web con el mensaje ya escrito — modo actual sin proveedor API.
    Atajos «Bienvenida» y «Cobranza». `enviar_morosos` sigue siendo el lote registrado.
- **Alta de contrato → crear cliente al vuelo**: si la búsqueda por cédula no
  encuentra al titular, aparece «+ Crear este cliente»; se abre el formulario de
  cliente (con la cédula precargada) y al guardar vuelve al contrato con el titular
  ya seleccionado, conservando lo que se había capturado (`pvContratoDraft`).
- **Cuotas en Bs ancladas a la tasa** (08_prevision_v5.sql): `prev_contratos`
  guarda `monto_ref_usd` (referencia USD por cuota) y `tasa_cambio`. Al crear un
  contrato en Bs, la cuota se calcula = ref_USD × tasa vigente y se guarda en Bs
  (si el plan es USD, ref = cuota del plan; si se escribe el monto en Bs, ref =
  monto ÷ tasa). El histórico de tasas está en `prev_tasas` (fecha + tasa). El
  modal **Tasa** registra la tasa, muestra el histórico y ofrece el botón
  **«Actualizar cuotas en Bs»** (`tasa_aplicar`, solo admin) que recalcula las
  cuotas `programada` pendientes sin abonos a la nueva tasa — **manual**, nunca
  automático. Cuotas ya cobradas/parciales no se tocan. La referencia USD para el
  recálculo es `COALESCE(monto_ref_usd, cuota del plan si el plan está en USD)`,
  así también funciona con contratos en Bs previos que no tenían `monto_ref_usd`
  (y de paso se lo fija). El botón aparece tras guardar una tasa y también al abrir
  el modal con la tasa vigente.
- Plazo de espera por defecto: 4 meses. Parentescos con rango de edad (18 seeds).

## 6. Activación en el hosting (pendiente de ejecutar)

1. `git push` + Deploy en cPanel Git Version Control.
2. phpMyAdmin: importar en orden `04` → `05` → `06_prevision_v3.sql` →
   `07_prevision_v4.sql` → `08_prevision_v5.sql` (los que falten).
3. Cron diario (si se quiere auto-lapsado): `api/cron/prevision_lapsar.php`.
4. Cuando haya proveedor de mensajería: Previsión → Mensajes → Configuración.
