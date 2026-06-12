# Funeraria del Zulia — Sitio Web y Sistema de Obituarios

Sitio web de **Funeraria del Zulia** (Maracaibo, Estado Zulia) con un **sistema de
obituarios en línea** completo: panel de administración con login, base de datos
MySQL, backend PHP, subida de fotos al disco con purga automática, moderación de
condolencias, plantillas editables y páginas públicas optimizadas para SEO/GEO.

---

## Índice

1. [Arquitectura y tecnologías](#arquitectura-y-tecnologías)
2. [Estructura del proyecto](#estructura-del-proyecto)
3. [**Manual de usuario — Administración de obituarios**](#manual-de-usuario--administración-de-obituarios)
4. [Cómo se ve en el sitio público](#cómo-se-ve-en-el-sitio-público)
5. [Instalación / despliegue](#instalación--despliegue)
6. [Mantenimiento](#mantenimiento)
7. [Solución de problemas](#solución-de-problemas)

---

## Arquitectura y tecnologías

| Capa | Tecnología |
|---|---|
| Frontend público | HTML5, CSS3 y JavaScript (sin framework ni build) |
| Páginas de obituarios | PHP server-rendered (SEO/GEO con JSON-LD) |
| Backend / API | PHP 8.4 (PDO) — endpoints REST en `/api` |
| Base de datos | MySQL / MariaDB (cPanel) |
| Autenticación | Sesiones PHP + contraseñas bcrypt + CSRF |
| Fotos | Disco del servidor (WebP) con purga automática por cron |

**Requisitos del servidor (cPanel):** PHP 8.1+ (probado en 8.4) con las extensiones
`pdo_mysql`, `gd` (con WebP), `mbstring` y `fileinfo`.

---

## Estructura del proyecto

```
Propuesta Funerzul/
│  index.html              Página principal
│  obituarios.php          Listado público de obituarios (estilo periódico)
│  obituario.php           Página individual de un obituario (SEO + JSON-LD)
│  sitemap.php             Sitemap XML dinámico
│  robots.txt              Reglas de rastreo
│  admin.html              Panel de administración (login + gestión)
│  app.js                  Lógica del sitio público (consume la API)
│  admin.js                Lógica del panel de administración
│  styles.css              Estilos globales
│  .cpanel.yml             Despliegue automático en cPanel
│
├─ api/                    Backend PHP
│   config.example.php     Plantilla de configuración (copiar a config.php)
│   auth.php               Login / logout / sesión
│   obituaries.php         CRUD de obituarios, portada, destacados
│   condolences.php        Condolencias públicas + moderación
│   templates.php          Plantillas de obituario
│   settings.php           Configuración (purga, portada, moderación)
│   users.php              Gestión de usuarios
│   upload.php             Subida de fotos al disco
│   diag.php               Diagnóstico de instalación (protegido)
│   cron/purge_photos.php  Rutina de purga de fotos
│   lib/                   Núcleo (BD, auth, helpers, render)
│
├─ partials/               Cabecera y pie compartidos (PHP)
├─ database/               Esquema SQL + guía de instalación de la BD
└─ uploads/obituarios/     Fotos de obituarios (en disco) + placeholder
```

> Documentación técnica detallada: [`database/README.md`](database/README.md) (esquema
> de la base de datos) y [`api/README.md`](api/README.md) (endpoints del backend).

---

## Manual de usuario — Administración de obituarios

Guía para el personal de Funeraria del Zulia que gestiona los obituarios del sitio.

### 1. Acceso al panel

1. Abre en el navegador: **`https://www.funerariadelzulia.com/admin.html`**
   (o la URL donde esté publicado el sitio).
2. Inicia sesión con tu **correo** y **contraseña**.
3. Si es la primera vez, usa el administrador inicial y **cámbiale la contraseña
   de inmediato** (ver punto 7).

> **Seguridad:** cada persona debe tener su propio usuario. No compartas credenciales.
> La sesión se cierra con el botón **Salir** (arriba a la derecha).

### 2. Roles de usuario

| Rol | Puede hacer |
|---|---|
| **Editor** | Crear, editar, destacar y dar de baja obituarios; moderar y editar condolencias. |
| **Admin** | Todo lo del editor **+** gestionar plantillas, usuarios, configuración y purga, y eliminar definitivamente. |

Las pestañas **Plantillas**, **Configuración** y **Usuarios** solo las ve el admin.

### 3. Tablero (pestaña Obituarios)

Al entrar verás cuatro indicadores: **Total**, **Activos**, **Destacados** y
**Condolencias por revisar** (este último también aparece como un número rojo en la
pestaña *Condolencias* cuando hay mensajes pendientes).

### 4. Crear un obituario

1. Pestaña **Obituarios** → botón **“+ Nuevo obituario”**.
2. Completa el formulario:
   - **Nombre completo del fallecido** *(obligatorio)*.
   - **Año de nacimiento** y **Fecha de fallecimiento** *(obligatoria)*.
   - **Tipo de servicio**: Velación, Cremación, Homenaje Póstumo, Traslado u Otro.
   - **Estado**:
     - *Activo* → visible en el sitio.
     - *Borrador* → guardado pero **no** visible (para preparar con calma).
     - *Inactivo* → oculto del sitio.
   - **Lugar**, **dirección**, **oficios/sepelio** (fecha y hora del velorio/sepelio).
   - **Biografía / nota recordatoria**.
   - **Plantilla**: el diseño con que se mostrará su página (ver punto 8).
   - **Fotografía**: elige un archivo **JPG, PNG o WebP**. Se sube al servidor y se
     optimiza automáticamente. Si no pones foto, se usa una imagen con el logo.
3. **Guardar obituario**. Aparecerá en la tabla y, si está *Activo*, en el sitio.

> La **URL pública** (slug) se genera sola a partir del nombre y la fecha,
> p.ej. `obituario.php?slug=manuel-ferrero-2026-06-11`.

### 5. Editar, dar de baja o restaurar

En la tabla de obituarios, cada fila tiene acciones:

- **Editar** — abre el formulario para modificar cualquier dato o cambiar la foto.
- **Fijar / Quitar** — destaca el obituario en la portada (ver punto 6).
- **Baja** — lo retira del sitio (**baja lógica**, se puede restaurar; no se borra).
  El admin puede borrarlo definitivamente desde la base si fuera necesario.

> Al **cambiar la foto** de un obituario, el contador de purga se reinicia (la nueva
> foto vuelve a tener el periodo completo de retención).

### 6. Destacar obituarios en la portada

La página principal muestra una **cantidad fija** de obituarios (3 por defecto,
configurable). El orden es: **primero los destacados** (fijados) y luego los más
recientes.

- Para destacar uno: en su fila pulsa **“Fijar”**.
- Para quitarlo de destacados: pulsa **“Quitar”**.

Úsalo para mantener visibles homenajes especiales aunque ya no sean los más recientes.

### 7. Moderar condolencias (pestaña Condolencias)

Las condolencias que el público envía entran como **“Por revisar”** (si la moderación
está activada). Desde esta pestaña:

- Filtra por **Por revisar / Aprobadas / Ocultas / Todas**.
- **Aprobar** → se publica en la página del obituario.
- **Ocultar** → deja de mostrarse (sin borrarla).
- **Editar** → corrige el nombre o el texto del mensaje.
- **Eliminar** → la borra definitivamente.

> Si en *Configuración* desactivas la moderación, las condolencias se publican al
> instante (úsalo con cuidado).

### 8. Plantillas de obituario *(solo admin)*

Cada obituario se muestra con una **plantilla** (diseño). Hay tres incluidas y puedes
crear las que quieras.

- **Nueva plantilla / Editar**: defines un **nombre**, una **descripción**, el
  **contenido HTML** y, opcionalmente, **CSS**. En el HTML usa estos marcadores y el
  sistema los reemplaza por los datos reales:

  `{{full_name}}` `{{birth_year}}` `{{death_date}}` `{{photo}}` `{{biography}}`
  `{{service_type}}` `{{location_name}}` `{{location_address}}` `{{event_schedule}}`

- **Predeterminar**: marca la plantilla que se usará por defecto cuando un obituario
  no tenga una asignada. Solo puede haber **una** predeterminada.
- **Activa/Inactiva**: una plantilla inactiva no se ofrece al crear obituarios.

### 9. Usuarios *(solo admin)*

Pestaña **Usuarios**:

- **+ Nuevo usuario**: correo, contraseña (mín. 8 caracteres) y rol (Editor/Admin).
- **Editar**: cambiar nombre, rol o activar/desactivar el acceso.
- **Clave**: asignar una contraseña nueva.
- **Eliminar**: quitar el usuario.

> Por seguridad no puedes quitarte a ti mismo el rol de admin ni eliminar tu propio
> usuario.

### 10. Configuración y purga de fotos *(solo admin)*

Pestaña **Configuración**:

- **Purga automática de fotos** (interruptor): activa/desactiva la limpieza.
- **Días de retención de fotos**: cuánto se conservan las fotos antes de purgarse
  (30 por defecto).
- **Obituarios en la portada**: cuántos se muestran en la página principal.
- **Moderar condolencias**: si las condolencias requieren aprobación.

**¿Qué es la purga?** Para ahorrar espacio en el disco, una rutina automática
**borra la foto** de los obituarios con más de N días y la reemplaza por una imagen
con el logo. **El obituario y todo su texto permanecen publicados e indexables** por
buscadores e IA — solo se libera el peso de la foto.

---

## Cómo se ve en el sitio público

- **Página principal** (`index.html`): sección de obituarios con los **destacados +
  recientes** (3 por defecto). Botón **“Ver todos los obituarios”**.
- **Listado completo** (`obituarios.php`): todos los obituarios en estilo periódico,
  con **buscador** y paginación.
- **Página individual** (`obituario.php?slug=…`): homenaje completo con la plantilla
  elegida, **datos estructurados (JSON-LD)** para SEO/GEO, lista de condolencias
  aprobadas y formulario para enviar nuevas.
- **Compartir**: cada obituario se puede compartir por WhatsApp/enlace.

---

## Instalación / despliegue

Resumen (guías detalladas en `database/README.md` y `api/README.md`):

1. **Base de datos**: crear la BD MySQL en cPanel e importar
   [`database/01_schema.sql`](database/01_schema.sql) con phpMyAdmin.
2. **Backend**: copiar `api/config.example.php` → `api/config.php` y poner las
   credenciales de MySQL y un `cron_secret` aleatorio.
3. **Extensiones PHP** (cPanel → *Select PHP Version → Extensions*): activar
   `pdo_mysql`, `gd`, `mbstring`, `fileinfo`.
4. **Despliegue**: `git push` y *Deploy* en **cPanel → Git Version Control**
   (`.cpanel.yml` copia los archivos a `public_html/funerzul`).
5. **Cron de purga**: cPanel → *Cron Jobs* (diario):
   ```
   /usr/local/bin/php /home/legadoholding/public_html/funerzul/api/cron/purge_photos.php
   ```
6. **SEO**: enviar `sitemap.php` en Google Search Console.

### Verificación de la instalación

Abre (con tu `cron_secret`):
```
https://<tu-sitio>/api/diag.php?token=TU_CRON_SECRET
```
Debe responder `db_connected: true`, las 7 tablas, `gd_webp: true` y
`uploads_writable: true`.

---

## Mantenimiento

- **Respaldos**: exporta periódicamente la base de datos desde phpMyAdmin
  (Exportar) y guarda copia de la carpeta `uploads/obituarios/`.
- **Cron de purga**: corre solo a diario; ajusta los días desde *Configuración*.
- **Auditoría**: cada acción (crear/editar/baja, moderación, login, purga) queda
  registrada en la tabla `audit_log`.
- **Cambiar contraseñas** del personal periódicamente desde la pestaña *Usuarios*.

---

## Solución de problemas

| Síntoma | Causa probable / solución |
|---|---|
| Los obituarios no cargan (error 500) | Falta `pdo_mysql` o credenciales de BD incorrectas en `api/config.php`. Revisa `api/diag.php`. |
| No se puede subir foto | Falta la extensión **GD con WebP** o **fileinfo**. Actívalas en cPanel. |
| “could not find driver” | Falta `pdo_mysql` (PDO está, pero sin el conector MySQL). |
| Access denied (1045) | Contraseña de MySQL no coincide con la de `config.php`, o el usuario no está asignado a la base. |
| Cambios de diseño no se ven | Caché del navegador/CDN: recarga con **Ctrl+F5** (los archivos usan `?v=` para forzar recarga). |
| Para ver el error real de un 500 | Pon `'env' => 'development'` en `api/config.php` temporalmente; luego vuelve a `'production'`. |

---

*Funeraria del Zulia — Desde 1942. Hacemos de la despedida un homenaje a la vida.*
