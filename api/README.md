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

## Seguridad

- Contraseñas con **bcrypt** (`password_hash`/`password_verify`).
- Sesiones con cookie `HttpOnly`, `SameSite=Lax`, `Secure` en HTTPS.
- **CSRF** en todas las mutaciones (`X-CSRF-Token`).
- Permisos por rol verificados en cada endpoint (admin / editor / público).
- **Auditoría** automática en `audit_log` de cada acción.
- `config.php` y `lib/` bloqueados por `.htaccess`; `uploads/` no ejecuta scripts.
