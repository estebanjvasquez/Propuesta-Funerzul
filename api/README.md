# Backend PHP — Sistema de Obituarios (Fase 2)

API REST en PHP (PDO + MySQL) para el sistema de obituarios. Corre en el mismo
hosting cPanel del sitio. Requiere **PHP 8.1+** (probado para PHP 8.4) con **PDO_MySQL**
y **GD con soporte WebP** (para la subida de fotos).

## Estructura

```
api/
├── config.example.php      → copiar a config.php (credenciales MySQL)
├── auth.php                → login / logout / me
├── obituaries.php          → CRUD obituarios, portada, fijar destacados
├── condolences.php         → enviar (público) + moderar (staff)
├── templates.php           → plantillas (CRUD admin, marcar predeterminada)
├── settings.php            → configuración (purga, portada, moderación)
├── users.php               → gestión de usuarios (solo admin)
├── upload.php              → subida de foto al disco (→ WebP)
├── cron/
│   └── purge_photos.php    → rutina de purga (cron de cPanel)
└── lib/                    → bootstrap, db, helpers, auth (no se acceden directo)
```

## Instalación

### 1. Configuración
```bash
cp api/config.example.php api/config.php
```
Edita `api/config.php` y pon la **contraseña** del usuario MySQL
(`legadoholding_chat`). Cambia también `cron_secret` por un valor aleatorio largo.

> `config.php` está en `.gitignore`: no se sube al repo y el deploy de cPanel
> **no lo sobrescribe**.

### 2. Verificar requisitos del servidor
En cPanel → **Select PHP Version** (PHP 8.4) asegúrate de tener activadas las
extensiones: `pdo_mysql`, `gd`, `fileinfo`, `mbstring`.

### 3. Permisos de la carpeta de fotos
La carpeta `uploads/obituarios/` debe existir y ser escribible por PHP
(normalmente 755). El deploy ya la incluye con el placeholder.

### 4. Programar la purga (cron)
cPanel → **Cron Jobs** → añade uno **diario** (p.ej. a las 3:00 AM):
```
/usr/local/bin/php /home/legadoholding/public_html/funerzul/api/cron/purge_photos.php
```
(Ajusta la ruta a la real de tu cuenta. `which php` o el panel te da la ruta de PHP.)

La purga respeta `app_settings.photo_purge_enabled` y `photo_retention_days`.

## Endpoints (resumen)

Todos responden JSON `{ ok: true|false, ... }`. Las acciones que modifican datos
requieren sesión y la cabecera `X-CSRF-Token` (se obtiene al hacer login).

| Endpoint | Método | Acceso | Descripción |
|---|---|---|---|
| `auth.php?action=login` | POST | público | inicia sesión, devuelve `csrf` |
| `auth.php?action=me` | GET | público | usuario actual + `csrf` |
| `auth.php?action=logout` | POST | sesión | cierra sesión |
| `obituaries.php?action=homepage` | GET | público | destacados + recientes |
| `obituaries.php?action=list` | GET | público* | listado (filtros q/type/time, paginado) |
| `obituaries.php?action=get` | GET | público | por `id` o `slug` |
| `obituaries.php?action=create\|update\|pin\|delete\|restore` | POST | editor/admin | gestión |
| `condolences.php?action=list` | GET | público | aprobadas (staff: todas) |
| `condolences.php?action=create` | POST | público | enviar (entra pending) |
| `condolences.php?action=moderate\|update\|delete` | POST | editor/admin | moderación |
| `templates.php?action=list\|get` | GET | público | plantillas activas |
| `templates.php?action=create\|update\|set_default\|delete` | POST | admin | gestión |
| `settings.php?action=get` | GET | staff | leer configuración |
| `settings.php?action=update` | POST | admin | cambiar purga/portada |
| `users.php?action=...` | GET/POST | admin | gestión de usuarios |
| `upload.php` | POST | editor/admin | subir foto (multipart, campo `photo`) |

\* `list` con `?scope=admin` requiere sesión y devuelve también inactivos.

### Módulo de Previsión (requiere `database/04_prevision.sql`)

Todos los endpoints de previsión requieren sesión de **staff** (admin/editor);
las eliminaciones definitivas requieren **admin**.

| Endpoint | Método | Descripción |
|---|---|---|
| `prevision_clientes.php?action=list\|get\|create\|update\|delete\|restore` | GET/POST | Clientes titulares (búsqueda paginada por cédula/nombre/teléfono; baja lógica). |
| `prevision_planes.php?action=list\|get\|create\|update\|toggle\|delete` | GET/POST | Planes de previsión. |
| `prevision_vendedores.php?action=list\|get\|create\|update\|retirar\|reactivar\|delete` | GET/POST | Vendedores: % de comisión, **cargo/jerarquía** (vendedor, coordinador, gerente + supervisor) y datos de pago (banco/cuenta/**Zelle**). |
| `prevision_vendedores.php?action=comisiones\|comisiones_resumen\|comisiones_pendientes\|comisiones_estado\|comision_calcular\|comision_actualizar\|comision_aprobar\|comision_pagar\|comision_anular\|comision_delete\|descuentos` | GET/POST | Comisiones por contrato/etapa (semana1, fin_mes1, mes2, mes13) con **flujo por estados**: `calcular` (genera las etapas vencidas → *calculada*), `actualizar`/`aprobar` (verificación → *aprobada*) y `pagar` (→ *pagada*); `anular` descarta una no pagada. Las etapas se cuentan desde `fecha_corte` (o ingreso). Al anular un contrato con comisiones pagadas se generan **descuentos** que se compensan automáticamente en el próximo pago del vendedor. Requiere `07`/`09`. |
| `prevision_contratos.php?action=list\|get\|create\|update\|set_estatus` | GET/POST | Contratos (estatus: activo/suspendido/anulado/renuncia). `get` incluye `bienvenida_enviada` para el aviso post-creación. |
| `prevision_contratos.php?action=parentescos\|beneficiario_add\|beneficiario_update\|beneficiario_estatus\|beneficiario_delete` | GET/POST | Beneficiarios del contrato (con estado civil; el panel precarga datos si la cédula ya es cliente). |
| `prevision_contratos.php?action=eventos` | GET | Bitácora del contrato (entradas de `audit_log` propias y de sus entidades). |
| `prevision_adjuntos.php?action=list\|subir\|eliminar` | GET/POST | **Adjuntos por contrato** (PDF/JPG/PNG/WebP, máx 5 MB) en `uploads/prevision/{id}/`. Requiere `09_prevision_v6.sql`. |
| `prevision_contratos.php?action=cuotas\|cuotas_generar\|cuota_update\|cuota_anular` | GET/POST | Cuotas por cobrar (generación según frecuencia). |
| `prevision_contratos.php?action=pagos\|pago_registrar\|pago_delete` | GET/POST | Pagos; se aplican en cascada a las cuotas más antiguas, con conversión Bs/USD por tasa del día. |
| `prevision_contratos.php?action=stats\|tasa\|tasa_set\|tasa_historial\|tasa_preview\|tasa_aplicar` | GET/POST | Indicadores del tablero y tasa de cambio (histórico en `prev_tasas`). Contratos en Bs guardan referencia en USD (`monto_ref_usd`) + `tasa_cambio`; `tasa_aplicar` (admin) recalcula **manualmente** las cuotas en Bs pendientes a la nueva tasa. |
| `prevision_import.php?action=plantilla\|lotes\|importar` | GET/POST | Importación CSV desde otros sistemas (clientes, vendedores, contratos, beneficiarios, pagos) con modo simulación. |
| `prevision_siniestros.php?action=list\|preparar\|create\|get\|detalle_add\|detalle_pagado\|detalle_delete\|set_estado\|delete` | GET/POST | Siniestros/reclamos con validación automática de cobertura (estatus, plazo de espera, solvencia) y liquidación por partidas. Requiere `05_prevision_v2.sql`. |
| `prevision_cobranza.php?action=morosos\|gestiones\|gestion_add\|gestion_delete\|lapsado_config\|lapsado_config_set\|lapsado_preview\|lapsado_ejecutar\|hoja_cobro` | GET/POST | Morosidad, bitácora de gestiones/promesas de pago, auto-lapsado configurable y hoja de cobro por ruta. |
| `prevision_catalogos.php?action=all\|sucursales\|servicios\|cobradores\|rutas\|*_save\|*_toggle\|*_delete\|contrato_servicio_*` | GET/POST | Catálogos del módulo (sucursales, servicios adicionales, cobradores, rutas) y servicios contratados por contrato. |
| `cron/prevision_lapsar.php` | CLI/URL+token | Cron diario: suspende contratos activos con ≥ N cuotas vencidas (`prev_lapse_enabled` / `prev_lapse_cuotas`). |
| `prevision_ajustes.php?action=list\|get\|preview\|aplicar\|revertir` | GET/POST | **Ajuste masivo de tarifas** (% o monto, por plan/moneda, con redondeo): vista previa sin guardar, aplicación con detalle valor anterior → nuevo y **reverso completo** (admin). Requiere `06_prevision_v3.sql`. |
| `prevision_mensajes.php?action=config\|config_set\|test\|plantillas\|plantilla_save\|plantilla_toggle\|plantilla_delete\|enviar\|preparar\|enviar_morosos\|envios` | GET/POST | **Notificaciones WhatsApp/SMS** con proveedor configurable por canal: `manual` (registra y abre wa.me), `whatsapp_cloud` (Meta), `twilio`, `http` (gateway genérico). Plantillas con variables `{{cliente}}`, `{{cedula}}`, `{{contrato}}`, `{{saldo_vencido}}`... `preparar` filtra contratos y devuelve los enlaces wa.me listos (consola «Enviar por WhatsApp», sin registrar); `enviar_morosos` es el lote registrado. |
| `prevision_reportes.php?action=aging\|produccion\|cobranza\|cartera` | GET | **Reportes**: antigüedad de CxC (1-30/31-60/61-90/+90), producción por vendedor, cobranza por período/forma de pago y cartera por plan. Todos con `&formato=csv`. |
| `prevision_solicitudes.php?action=crear` | POST | **Público** — captura un lead desde `planes/`/`servicios/` (CTA "pago electrónico"); no cobra ni crea contrato. Desde 2026-08-28 también reenvía de mejor esfuerzo a Prevision-Funeraria (ver abajo). |
| `prevision_solicitudes.php?action=list\|actualizar_estado\|vincular_contrato` | GET/POST | Staff — gestión de leads capturados (estado, vínculo a contrato). |

## Integración con Prevision-Funeraria (Fases A/B/C, 2026-08-28)

Ver `docs/specs/2026-08-28-migracion-a-prevision-funeraria.md` para el plan
completo. Cliente HTTP en `api/lib/prevision_funeraria.php`, configurado en
`config.php` bajo la clave `prevision_funeraria` (ver `config.example.php`).
**Apagado por defecto** (`enabled => false`): mientras no se active, ninguna
página ni endpoint llama a Prevision-Funeraria y todo se comporta igual que
antes de estos cambios.

- **Fase A** — `planes/plan-*.php` muestran la cuota mensual real leída de
  `GET /api/public/t/fdz/planes` (partial `partials/pf_precio_plan.php`),
  con cache de `cache_ttl` segundos en `cache/prevision_funeraria/*.json`
  (no en `app_settings` — es cache HTTP interno, no configuración
  administrable). `servicios/` todavía no muestra precio: el catálogo de
  servicios de Prevision-Funeraria para el tenant `fdz` está vacío al
  2026-08-28.
- **Fase B** — `prevision_solicitudes.php?action=crear` reenvía cada lead a
  `POST /api/public/t/fdz/solicitudes` después de guardarlo localmente
  (mejor esfuerzo: un fallo de red no afecta la respuesta al visitante ni el
  guardado en `prev_solicitudes_publicas`, solo queda en el log del
  servidor). El frontend manda `plan_slug`/`servicio_slug`; el backend
  resuelve el `plan_id`/`servicio_id` real de Prevision-Funeraria antes de
  reenviar.
- **Fase C** — `partials/cta_pago_electronico.php` reemplaza el formulario
  de lead por un botón directo a WhatsApp cuando el servicio está marcado
  `es_emergencia` en el catálogo de Prevision-Funeraria. Inerte hoy por el
  mismo motivo que Fase A (catálogo de servicios vacío para `fdz`).

**Para activar en el servidor:** copiar el bloque `prevision_funeraria` de
`config.example.php` a `config.php` con `enabled => true`, y confirmar que
`cache/prevision_funeraria/` es escribible por PHP (mismo criterio que
`uploads/`, normalmente 755) — si no lo es, la integración sigue funcionando
pero sin cache (pide el catálogo a Prevision-Funeraria en cada carga de
página).

## Seguridad

- Contraseñas con **bcrypt** (`password_hash`/`password_verify`).
- Sesiones con cookie `HttpOnly`, `SameSite=Lax`, `Secure` en HTTPS.
- **CSRF** en todas las mutaciones (`X-CSRF-Token`).
- Permisos por rol verificados en cada endpoint (admin / editor / público).
- **Auditoría** automática en `audit_log` de cada acción.
- `config.php` y `lib/` bloqueados por `.htaccess`; `uploads/` no ejecuta scripts.
