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
├── pf_solicitud.php        → lead público de plan/servicio → Prevision-Funeraria
├── obituary_card.php       → tarjeta de obituario para compartir (PNG, staff)
├── cron/
│   └── purge_photos.php    → rutina de purga (cron de cPanel)
└── lib/                    → bootstrap, db, helpers, auth, prevision_funeraria.php,
                               obituary_card.php, payments/ (no se acceden directo)
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
| `settings.php?action=update` | POST | admin | cambiar purga/portada/**secciones del sitio** (`section_obituarios_enabled`, `section_directorio_medico_enabled`, `section_recursos_enabled`, `section_faqs_enabled` — ver `site_section_enabled()` en `lib/helpers.php`) |
| `users.php?action=...` | GET/POST | admin | gestión de usuarios |
| `upload.php` | POST | editor/admin | subir foto (multipart, campo `photo`) |
| `obituary_card.php?id=&style=cinta\|esquela` | GET | editor/admin | Descarga PNG (1080×1920) de la tarjeta de obituario para compartir — ver `docs/specs/2026-09-08-tarjetas-obituario.md`. Puro GD (sin Imagick), fuentes en `assets/fonts/`. |

\* `list` con `?scope=admin` requiere sesión y devuelve también inactivos.

### Módulo de Previsión — retirado (2026-08-28)

Los 16 endpoints `prevision_*.php` (clientes, planes, vendedores, comisiones,
contratos, adjuntos, cuotas, pagos, tasas, importación CSV, siniestros,
cobranza, catálogos, ajustes de tarifa, mensajería, cron de auto-lapsado) se
retiraron junto con el resto del módulo — ver
[`docs/specs/2026-08-28-fase-e-corte-admin-prevision.md`](../docs/specs/2026-08-28-fase-e-corte-admin-prevision.md).
Siguen disponibles solo en la rama `archive/modulo-prevision-php` (nunca se
mergea a `main`).

| Endpoint | Método | Acceso | Descripción |
|---|---|---|---|
| `pf_solicitud.php` | POST | público | Lead de plan/servicio (reemplaza a `prevision_solicitudes.php?action=crear`) — reenvía directo a Prevision-Funeraria (tenant `fdz`), sin guardado local, y avisa por correo al staff. Ver abajo. |

## Integración con Prevision-Funeraria (2026-08-28, activa — verificada en vivo 2026-09-13)

Ver `docs/specs/2026-08-28-migracion-a-prevision-funeraria.md` y
`docs/specs/2026-08-28-fase-e-corte-admin-prevision.md` para el plan
completo. Cliente HTTP en `api/lib/prevision_funeraria.php`, configurado en
`config.php` bajo la clave `prevision_funeraria` (ver `config.example.php`).
**`enabled => true` ya está activo en el servidor de producción** (confirmado
en vivo el 2026-09-13: las 4 páginas de plan muestran su cuota real leída de
Prevision-Funeraria) — no es el estado "apagado por defecto" que describía
este documento hasta ahora.

- **Catálogo de planes** — `planes/plan-*.php` muestran la cuota mensual real
  leída de `GET /api/public/t/fdz/planes` (partial
  `partials/pf_precio_plan.php`), con cache de `cache_ttl` segundos en
  `cache/prevision_funeraria/*.json`. Verificado en vivo 2026-09-13: Esencial
  US$13,70, Tradición US$12,00, Vanguardia US$17,00, Vanguardia Plus
  US$24,00. **Inconsistencia de datos pendiente, ya visible al público**:
  Tradición (US$12,00) cuesta menos que Esencial (US$13,70), aunque este
  sitio presenta Tradición como el plan superior — corregirlo requiere acceso
  al panel de Prevision-Funeraria (`fdz`), no es algo editable desde este
  repo. El catálogo también trae 5 entradas de import (`emp-esencial-30`,
  `esencial-new`, etc.) que este sitio ignora a propósito por no coincidir
  con los 4 slugs conocidos.
- **Mostrar el precio es un interruptor aparte de `enabled`** (nuevo,
  2026-09-13): `prevision_funeraria.show_prices` en `config.php`, en `false`
  por defecto. El sitio real en producción (WordPress) nunca publica montos
  — la API pública de Prevision-Funeraria tampoco trae un campo por
  plan/servicio para decidirlo, es todo o nada. Este interruptor deja
  ocultar el precio en todo el sitio sin tocar código mientras eso no exista
  del lado de PF, o mientras haya inconsistencias de catálogo sin corregir
  (como la de arriba). `partials/pf_precio_plan.php` no imprime nada si está
  en `false`, aunque `enabled` sea `true` y el plan exista.
- **Catálogo de servicios — sigue vacío para `fdz`** (`GET
  /api/public/t/fdz/servicios` → `{items: []}`, confirmado igual el
  2026-08-28 y el 2026-09-13). Comparado en vivo contra el tenant `lh`
  (Legado Holding, `GET /api/public/t/lh/servicios`): **sí** tiene su
  catálogo de servicios cargado (3 ítems, con precio y `es_emergencia`) — la
  mecánica funciona, es una carga de datos pendiente específica del tenant
  `fdz` que solo se puede hacer desde el panel de Prevision-Funeraria, no
  desde este repo (sin credenciales/acceso a ese sistema desde aquí).
  Mientras el catálogo de servicios de `fdz` siga vacío, `servicios/*.php` no
  muestra precio y el triage de emergencias (abajo) queda inerte.
- **Leads** — `pf_solicitud.php` reenvía cada lead directo a
  `POST /api/public/t/fdz/solicitudes` (verificado en vivo 2026-09-13:
  responde `400` con errores de validación ante un payload vacío — endpoint
  alcanzable extremo a extremo, sin crear ninguna solicitud de prueba real).
  El frontend manda `plan_slug`/`servicio_slug`; el backend resuelve el
  `plan_id`/`servicio_id` real de Prevision-Funeraria antes de reenviar. Si
  Prevision-Funeraria no responde, se lo decimos al visitante (no hay
  respaldo local desde el corte del módulo de Previsión). **Aviso por
  correo** (nuevo, 2026-09-13): cada solicitud creada con éxito dispara
  `notify_email()` (`api/lib/mail.php`, PHP `mail()` nativo, sin
  dependencias nuevas) hacia `app.notify_email` de `config.php` — de pruebas,
  `contacto@funerariadelzulia.com`. Best-effort: si el correo falla, se
  registra en el log de PHP y el lead sigue intacto en Prevision-Funeraria
  (la fuente de verdad nunca depende de que el correo salga).
- **Triage de emergencias** — `partials/cta_pago_electronico.php` reemplaza
  el formulario de lead por un botón directo a WhatsApp cuando el servicio
  está marcado `es_emergencia` en el catálogo de Prevision-Funeraria. Sigue
  inerte hoy: el catálogo de servicios de `fdz` está vacío (ver arriba).

**Confirmar en el servidor:** que `cache/prevision_funeraria/` sea escribible
por PHP (mismo criterio que `uploads/`, normalmente 755) — si no lo es, la
integración sigue funcionando pero sin cache (pide el catálogo a
Prevision-Funeraria en cada carga de página). Y agregar
`'notify_email' => 'contacto@funerariadelzulia.com'` (o el correo real que
se quiera usar) dentro de `'app' => [...]` en `config.php` para que salgan
los avisos de leads nuevos.

**Panel admin del staff de Funerzul:** ya no está en este repo — es
`https://prevision-funeraria.sisteg.workers.dev/login.html` (tenant `fdz`),
con cuentas de staff propias (ver el plan de la Fase E citado arriba). Login
único integrado con el resto del panel de este sitio: **pendiente**, no
desarrollado todavía del lado de Prevision-Funeraria.

## Seguridad

- Contraseñas con **bcrypt** (`password_hash`/`password_verify`).
- Sesiones con cookie `HttpOnly`, `SameSite=Lax`, `Secure` en HTTPS.
- **CSRF** en todas las mutaciones (`X-CSRF-Token`).
- Permisos por rol verificados en cada endpoint (admin / editor / público).
- **Auditoría** automática en `audit_log` de cada acción.
- `config.php` y `lib/` bloqueados por `.htaccess`; `uploads/` no ejecuta scripts.
