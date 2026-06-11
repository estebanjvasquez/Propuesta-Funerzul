# Base de Datos — Sistema de Obituarios (MySQL / cPanel)

Esquema MySQL/MariaDB para el sistema de obituarios en línea de Funeraria del Zulia.
Se ejecuta en el **mismo hosting cPanel** del sitio (base de datos, imágenes en
disco y backend PHP, todo junto).

## Contenido

- **`01_schema.sql`** — Esquema completo: 7 tablas, índices, claves foráneas y
  datos semilla (admin inicial, configuración de purga y plantillas).

> El control de acceso (roles) y la **auditoría** se implementan en la capa **PHP**
> (Fase 2/3), ya que MySQL no tiene RLS ni autenticación integrada como Supabase.

## Modelo de datos (resumen)

| Tabla | Propósito |
|---|---|
| `users` | Usuarios del panel con login PHP (bcrypt). Roles: **admin**, **editor**. |
| `obituaries` | Obituarios. Foto en disco; permanece indexable tras la purga. |
| `obituary_templates` | Plantillas editables; una marcada como predeterminada. |
| `condolences` | Mensajes del público (moderados: pending → approved/hidden). |
| `flower_offerings` | Ofrendas florales (simuladas, paridad con el sistema actual). |
| `app_settings` | Configuración global, **incluida la purga** (`photo_retention_days`, `photo_purge_enabled`). |
| `audit_log` | Trazabilidad: quién hizo qué y cuándo (la escribe PHP). |

### Roles y permisos (aplicados en PHP)

- **Público:** ve obituarios `active`, ve condolencias `approved`, puede enviar condolencias (entran como `pending`).
- **Editor:** crea/edita/desactiva obituarios; modera y edita condolencias.
- **Admin:** todo + gestiona usuarios, plantillas, configuración, purga y borrado definitivo.

---

## Instalación

### 1. Crear la base de datos en cPanel

1. cPanel → **MySQL® Databases**.
2. **Create New Database** → anota el nombre completo (cPanel le antepone tu
   prefijo, p.ej. `legadoholding_funerzul`).
3. **Add New User** → crea un usuario y una contraseña fuerte (anótalos).
4. **Add User To Database** → asígnalo con **ALL PRIVILEGES**.

### 2. Importar el esquema

1. cPanel → **phpMyAdmin** → selecciona la base recién creada (panel izquierdo).
2. Pestaña **Importar** → **Seleccionar archivo** → `database/01_schema.sql` → **Continuar**.
3. Verifica que aparezcan las **7 tablas**.

### 3. Iniciar sesión y asegurar el admin

El esquema siembra un administrador inicial:

| Campo | Valor |
|---|---|
| Email | `admin@funerariadelzulia.com` |
| Contraseña | `FZulia.Admin2026` |

- **Cambia el email** por el real y **la contraseña** en cuanto el panel esté
  disponible (Fase 3). Si quieres cambiar el email ya mismo:

  ```sql
  UPDATE users SET email = 'tu_correo@dominio.com' WHERE role = 'admin';
  ```

> El hash almacenado es **bcrypt**, compatible con `password_verify()` de PHP.
> Para crear más usuarios usarás el panel (o el script de Fase 2).

### 4. Ajustar la purga (opcional)

Valores por defecto ya cargados. Para cambiarlos por SQL:

```sql
-- Periodo de retención de fotos (días)
UPDATE app_settings SET setting_value = '45' WHERE setting_key = 'photo_retention_days';

-- Desactivar temporalmente la purga (0 = off, 1 = on)
UPDATE app_settings SET setting_value = '0'  WHERE setting_key = 'photo_purge_enabled';
```

(También será configurable desde el panel en una fase posterior.)

---

## Lo que necesito para la Fase 2 (backend PHP)

Para conectar el backend necesitaré (puedes ponerlos tú directamente en el
archivo de configuración del servidor, **no hace falta que compartas la contraseña por aquí**):

- **Nombre de la base** (p.ej. `legadoholding_funerzul`)
- **Usuario de la base** (p.ej. `legadoholding_obit`)
- **Host**: normalmente `localhost` en cPanel
- La **contraseña** del usuario MySQL → directo en el `config.php` del servidor

---

## Notas

- Motor **InnoDB**, charset **utf8mb4** (soporta acentos y emojis).
- Las **fotos NO se guardan en la base**: van al disco (`uploads/obituarios/`) y
  la tabla solo guarda la ruta y el estado de purga.
- La **única plantilla por defecto** se garantiza desde PHP (al marcar una como
  predeterminada se desmarcan las demás en una transacción).
- El `audit_log` registra cada acción (creación/edición/baja de obituarios,
  moderación de condolencias, login, y la purga del cron).
