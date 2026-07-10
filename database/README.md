# Base de Datos — Sistema de Obituarios (MySQL / cPanel)

Esquema MySQL/MariaDB para el sistema de obituarios en línea de Funeraria del Zulia.
Se ejecuta en el **mismo hosting cPanel** del sitio (base de datos, imágenes en
disco y backend PHP, todo junto).

## Contenido

- **`01_schema.sql`** — Esquema completo: 7 tablas, índices, claves foráneas y
  datos semilla (admin inicial, configuración de purga y plantillas).
- **`02_directorio_recursos.sql`** — Tablas `doctors` y `articles` (Directorio
  Médico y Recursos de Lectura).
- **`03_faqs.sql`** — Tabla `faqs` (Preguntas Frecuentes de la portada).
- **`04_prevision.sql`** — **Módulo de Previsión**: 11 tablas `prev_*` modeladas
  sobre la base de datos del sistema administrativo SIEMPRE (`SIEMPRE.sql`):
  clientes, contratos, beneficiarios, planes, vendedores y comisiones, cuotas
  por cobrar, pagos, tasas de cambio y lotes de importación. Incluye los
  catálogos reales de SIEMPRE (18 parentescos y los 9 planes vigentes).
- **`05_prevision_v2.sql`** — **Previsión, Ronda 2**: sucursales, servicios
  adicionales (bóveda, cremación, traslados), cobradores y rutas de cobranza,
  **siniestros/reclamos** con validación de cobertura (equivale a
  `cm_siniestros` de SIEMPRE), gestiones de mora y configuración del
  **auto-lapsado**. Requiere `04_prevision.sql` importado previamente.
- **`06_prevision_v3.sql`** — **Previsión, Ronda 3**: **ajustes masivos de
  tarifas** (`prev_ajustes` + detalle reversible), **mensajería WhatsApp/SMS**
  (`prev_msg_plantillas` con 7 plantillas iniciales y `prev_msg_envios`) y la
  configuración del proveedor de mensajería en `app_settings` (manual,
  WhatsApp Cloud API, Twilio o API HTTP genérica — se elige desde el panel).
  Requiere `05_prevision_v2.sql` importado previamente.
- **`07_prevision_v4.sql`** — **Previsión, comisiones por estados**: añade a
  `prev_comisiones` el flujo *calculada → aprobada → pagada* (columnas `estado`,
  `base_monto`, `porcentaje`, `monto_calculado`, `fecha_calculo`, `aprobado_por`,
  `fecha_aprobacion`) para generar, verificar/aprobar y luego pagar las
  comisiones. Requiere `04_prevision.sql`. (Las comisiones ya existentes quedan
  como *pagada*.)
- **`08_prevision_v5.sql`** — **Previsión, cuotas en Bs ancladas a la tasa**:
  añade a `prev_contratos` las columnas `monto_ref_usd` (referencia en USD por
  cuota) y `tasa_cambio` (tasa con la que se calculó el monto en Bs), para
  recalcular las cuotas en bolívares cuando cambie la tasa **sin perder el valor
  real del plan**. El histórico de tasas ya vive en `prev_tasas`. La actualización
  de cuotas es **manual** (botón en el panel). Requiere `04_prevision.sql`.
- **`SIEMPRE.sql`** — Respaldo completo del sistema SIEMPRE (referencia; no se
  importa en el hosting: pesa más de 1 GB y usa el esquema antiguo).

### Módulo de Previsión (`04_prevision.sql`)

| Tabla | Propósito | Equivale en SIEMPRE |
|---|---|---|
| `prev_clientes` | Titulares de contratos (cédula única, datos personales y de contacto). | `clientes` |
| `prev_planes` | Planes de previsión con cuota, moneda y cobertura. | `planes` / `planesunidos` |
| `prev_vendedores` | Vendedores con % de comisión y datos bancarios. | `vendedores` + `vendedores_bancos` |
| `prev_contratos` | Contratos: plan, vendedor, frecuencia, forma de cobro, estatus (activo/suspendido/anulado/renuncia). | `contratos` + `cmestadoscontrato` |
| `prev_beneficiarios` | Beneficiarios por contrato con parentesco, exclusión y defunción. | `cmbeneficiarios` |
| `prev_parentescos` | Catálogo de parentescos con rangos de edad. | `parentesco` |
| `prev_cuotas` | Cuotas por cobrar con vencimiento, saldo y estado. | `cuotascxc` |
| `prev_pagos` | Pagos/abonos aplicados a cuotas (con tasa del día). | `abonoscxc` + `caja1` |
| `prev_comisiones` | Comisiones pagadas por contrato/etapa (semana1, fin_mes1, mes2, mes13). | `comisiones_pagadas` |
| `prev_tasas` | Tasa de cambio Bs/USD por día. | `tasas_diarias` |
| `prev_import_lotes` | Bitácora de importaciones desde otros sistemas. | — |

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
4. Repite la importación con `02_directorio_recursos.sql`, `03_faqs.sql`,
   `04_prevision.sql`, `05_prevision_v2.sql`, `06_prevision_v3.sql`,
   `07_prevision_v4.sql` y `08_prevision_v5.sql` (módulo de Previsión, en ese
   orden). El `04` requiere que `users` (del `01`) ya exista; el `05` requiere el
   `04`, el `06` el `05`, y el `07` y el `08` requieren el `04`.

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
