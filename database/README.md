# Base de Datos — Sistema de Obituarios (Supabase)

Esquema PostgreSQL para el sistema de obituarios en línea de Funeraria del Zulia.

## Contenido

- **`01_schema.sql`** — Esquema completo: tablas, tipos, índices, triggers de
  auditoría, Row Level Security (RLS), funciones y datos semilla.

## Modelo de datos (resumen)

| Tabla | Propósito |
|---|---|
| `profiles` | Usuarios del panel (extiende `auth.users`). Roles: **admin**, **editor**. |
| `obituaries` | Obituarios. Foto en disco; permanece indexable tras la purga. |
| `obituary_templates` | Plantillas editables; una marcada como predeterminada. |
| `condolences` | Mensajes del público (moderados: pending → approved/hidden). |
| `flower_offerings` | Ofrendas florales (simuladas, paridad con el sistema actual). |
| `app_settings` | Configuración global, **incluida la purga** (`photo_retention_days`, `photo_purge_enabled`). |
| `audit_log` | Trazabilidad automática de todos los cambios. |

### Roles y permisos (RLS)

- **Público (anónimo):** lee obituarios `active`, lee condolencias `approved`, puede enviar condolencias (entran como `pending`).
- **Editor:** crea/edita/desactiva obituarios; modera y edita condolencias.
- **Admin:** todo lo anterior + gestiona usuarios, plantillas, configuración, purga y borrado definitivo.

---

## Instalación

### 1. Ejecutar el esquema

1. Entra a tu proyecto en [supabase.com](https://supabase.com) → **SQL Editor** → **New query**.
2. Pega el contenido de [`01_schema.sql`](01_schema.sql) y pulsa **Run**.
3. Verifica en **Table Editor** que aparezcan las 7 tablas.

### 2. Obtener las claves para el frontend

En **Project Settings → API**:

- **Project URL** → la usaremos como `SUPABASE_URL`.
- **anon public key** → la usaremos como `SUPABASE_ANON_KEY` (es segura para el navegador; RLS protege los datos).
- **service_role key** → **NO** la pongas en el frontend ni la compartas. Se usará
  **solo en el servidor** (cPanel) para el script de subida y el cron de purga.

### 3. Crear el primer ADMIN

El primer usuario se crea como `editor` por defecto. Para convertirlo en admin:

1. **Authentication → Users → Add user** (email + contraseña). Esto dispara el
   trigger que crea su fila en `profiles`.
2. Promuévelo a admin en **SQL Editor**:

   ```sql
   update public.profiles
   set role = 'admin'
   where email = 'TU_CORREO_ADMIN@dominio.com';
   ```

A partir de ahí, ese admin podrá crear y gestionar al resto de usuarios desde el panel.

> Alternativa: al crear el usuario puedes pasar `raw_user_meta_data` con
> `{"role":"admin","full_name":"..."}` y el perfil nacerá ya como admin.

### 4. Ajustar la purga (opcional)

Los valores por defecto ya quedan cargados. Para cambiarlos:

```sql
-- Cambiar el periodo de retención (días)
update public.app_settings set value = '45'::jsonb where key = 'photo_retention_days';

-- Desactivar temporalmente la purga
update public.app_settings set value = 'false'::jsonb where key = 'photo_purge_enabled';
```

(Esto también será configurable desde el panel de administración en una fase posterior.)

---

## Notas de seguridad

- Toda la protección de acceso vive en **RLS** + funciones `is_admin()` /
  `is_editor_or_admin()`. La `anon key` no otorga permisos por sí sola.
- El `audit_log` solo lo lee un admin; lo escriben triggers `SECURITY DEFINER` y
  el backend (login, purga), nunca el cliente directamente.
- El cron de purga y la subida de imágenes usan la **service_role key** desde el
  servidor cPanel (fuera del alcance del navegador).
