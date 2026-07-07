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
│  index.php               Página principal (FAQ renderizado desde la BD para SEO)
│  obituarios.php          Listado público de obituarios (estilo periódico)
│  obituario.php           Página individual de un obituario (SEO + JSON-LD)
│  servicios/              Páginas de servicios (hub + cremación, sepelio, traslados, capillas)
│  planes/                 Páginas de planes de previsión (hub + 4 planes)
│  directorio-medico.php   Directorio médico (lista) · medico.php (ficha)
│  recursos.php            Recursos de lectura (lista) · recurso.php (artículo)
│  sitemap.php             Sitemap XML dinámico
│  robots.txt              Reglas de rastreo
│  admin.html              Panel de administración (login + gestión)
│  app.js                  Lógica del sitio público (consume la API)
│  admin.js                Lógica del panel de administración
│  styles.css              Estilos globales
│  .cpanel.yml             Despliegue automático en cPanel
│
│  admin-prevision.js      Lógica del módulo de Previsión del panel
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
│   prevision_clientes.php    Previsión: clientes titulares
│   prevision_planes.php      Previsión: planes
│   prevision_vendedores.php  Previsión: vendedores y comisiones
│   prevision_contratos.php   Previsión: contratos, beneficiarios, cuotas y pagos
│   prevision_import.php      Previsión: importación CSV desde otros sistemas
│   prevision_siniestros.php  Previsión: siniestros/reclamos con validación de cobertura
│   prevision_cobranza.php    Previsión: morosos, gestiones, auto-lapsado, hoja de cobro
│   prevision_catalogos.php   Previsión: sucursales, servicios, cobradores y rutas
│   diag.php               Diagnóstico de instalación (protegido)
│   cron/purge_photos.php  Rutina de purga de fotos
│   lib/                   Núcleo (BD, auth, helpers, render, previsión)
│
├─ partials/               Cabecera, pie y banda de contacto compartidos (PHP)
├─ servicios/img/          Imágenes SVG de cada servicio
├─ planes/img/             Imágenes SVG de cada plan
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

### 11. Directorio Médico (pestaña Directorio Médico)

Directorio público de médicos y especialistas como servicio de apoyo a las familias.
Lo administra todo el personal (editor y admin).

- **+ Nuevo médico**: nombre y **especialidad** son obligatorios; opcionalmente
  teléfono, correo, centro/clínica, dirección/zona, descripción y **foto**.
- **Estado**: Activo (visible), Borrador (guardado, no visible) o Inactivo (oculto).
- **Destacar**: aparece primero en el directorio (útil para referencias preferentes).
- **Baja**: lo retira del sitio; se puede restaurar (no se borra del todo).
- Las fotos de los médicos **no se purgan** (contenido permanente).

Páginas públicas: lista buscable por especialidad en `directorio-medico.php` y ficha
individual en `medico.php?slug=…` (con datos estructurados `schema.org/Physician`).

### 12. Recursos de Lectura (pestaña Recursos)

Biblioteca de artículos y guías (duelo, trámites, previsión…). Lo administra todo el
personal.

- **+ Nuevo recurso**: **título** y **contenido** son obligatorios; opcionalmente
  categoría, extracto, descripción SEO e **imagen de portada**.
- El **contenido admite HTML** (`<p>`, `<h2>`, `<ul><li>`, `<strong>`, `<a href>`…).
- **Estado**: Borrador (no visible), Activo (publicado) o Inactivo. Al publicarse por
  primera vez se fija la fecha de publicación.
- **Destacar** y **Baja/restaurar** funcionan igual que en los demás módulos.

Páginas públicas: lista con filtro por categoría en `recursos.php` y artículo
individual en `recurso.php?slug=…` (con datos estructurados `schema.org/Article`).

> Para activar estos módulos en una base existente, importe el archivo
> `database/02_directorio_recursos.sql` en phpMyAdmin (crea las tablas `doctors` y
> `articles`). Las fotos se guardan en `uploads/doctors/` y `uploads/recursos/`.

### 13. Preguntas Frecuentes (pestaña Preguntas)

La sección **“Preguntas Frecuentes”** de la portada lee las preguntas creadas aquí.
Lo administra todo el personal.

- **+ Nueva pregunta**: pregunta y respuesta (obligatorias) + **Orden** (menor aparece
  primero) y **Estado**.
- **Pausar / Activar**: una pregunta en pausa deja de mostrarse en la portada sin
  borrarla.
- **Editar** y **Eliminar** (definitivo).
- La portada (`index.php`) **renderiza en el servidor** tanto la sección visible como el
  JSON-LD (`FAQPage`) a partir de estas preguntas, leyendo de la BD en cada visita: son
  la **única fuente** y el contenido es **indexable sin JavaScript** (mejor SEO/GEO para
  Google, Bing e IA). Si la BD no respondiera, se muestra un respaldo mínimo.

> Para activarlo en una base existente, importe `database/03_faqs.sql` en phpMyAdmin
> (crea la tabla `faqs` con las preguntas que ya estaban en la portada, incluida la de
> atención 24 horas que solo figuraba en los datos estructurados).

### 14. Módulo de Previsión (pestaña Previsión)

Administración completa de los **planes de previsión funeraria**, modelada sobre la
base de datos del sistema administrativo **SIEMPRE** (`database/SIEMPRE.sql`).
Lo usa todo el personal (editor y admin); las eliminaciones definitivas y las
reversiones de pagos son solo del admin.

**Para activarlo**: importe `database/04_prevision.sql` y luego
`database/05_prevision_v2.sql` en phpMyAdmin (crean las tablas `prev_*` con los
catálogos de SIEMPRE: 18 parentescos y los 9 planes vigentes — Tradición,
Esencial, Vanguardia, etc. — más sucursales, servicios, siniestros y cobranza).

Al entrar se ven los indicadores del módulo: **contratos activos**, **clientes**,
**cuotas vencidas** (con su monto), **cobrado en el mes** y la **tasa del día**
(Bs/USD, con botón para actualizarla). Debajo, seis sub-pestañas:

- **Contratos** — buscar por número/cédula/nombre, filtrar por estatus o por
  contratos con cuotas vencidas. **“+ Nuevo contrato”**: se busca al titular por su
  cédula, se elige plan (autocompleta cuota y moneda), vendedor, frecuencia
  (semanal/quincenal/mensual/trimestral/semestral/anual), forma de cobro, plazo de
  espera y, opcionalmente, se generan las primeras cuotas. El titular queda
  registrado automáticamente como primer beneficiario.
  Desde **“Ver”** se maneja todo el contrato:
  - *Beneficiarios*: agregar (con validación de edad según el parentesco), editar,
    excluir, registrar defunción o reactivar.
  - *Cuotas*: generar por lote según la frecuencia, cobrar o anular.
  - *Pagos*: se aplican a las cuotas pendientes más antiguas (o a una específica);
    si el pago viene en otra moneda se convierte con la tasa del día y el
    excedente queda como abono a favor.
  - *Estatus*: activo, suspendido, anulado o renuncia (con fecha y motivo; al
    anular se liquidan las cuotas pendientes).
- **Clientes** — ficha completa del titular (cédula única, contacto, dirección,
  empleador). “Ver” muestra sus contratos; la baja es lógica (restaurable).
- **Planes** — catálogo de planes con cuota, moneda, cuota inicial y cobertura;
  activar/desactivar sin afectar contratos existentes.
- **Vendedores** — datos personales, porcentajes de comisión
  (semanal/mensual/anual) y cuenta bancaria para el pago; retiro y reactivación.
- **Comisiones** — el sistema calcula qué etapas están **por pagar** por contrato
  según el esquema de SIEMPRE (**Semana 1**, **Fin de mes 1**, **Mes 2** y
  **Mes 13**) con un monto sugerido; se registra el pago en USD y/o Bs con su
  tasa. Vistas de pagadas e historial y resumen por vendedor. Una etapa no puede
  pagarse dos veces para el mismo contrato.
- **Siniestros** — el corazón del servicio: al fallecer un titular o
  beneficiario se registra el siniestro en dos pasos (contrato + quién
  falleció) y el sistema **valida la cobertura automáticamente** (contrato
  activo, plazo de espera cumplido, beneficiario vigente y solvencia),
  dejando constancia de los chequeos en el expediente. Luego se liquida por
  partidas (servicio funerario, pagos, reintegros, proveedores) y se cierra:
  si el fallecido es el titular, el contrato pasa a **finalizado** y se
  anulan las cuotas pendientes. Estados: abierto → liquidado → cerrado, o
  rechazado con motivo.
- **Cobranza** — vista de **morosos** (cuotas vencidas, días de mora, última
  gestión) con acciones de cobro y gestión; **bitácora de gestiones**
  (llamada/visita/WhatsApp, con promesas de pago); **auto-lapsado**
  configurable (suspende contratos con ≥ N cuotas vencidas, con vista previa,
  ejecución manual y cron diario `api/cron/prevision_lapsar.php`); y **hoja de
  cobro imprimible** por ruta para el cobrador.
- **Catálogos** — sucursales, **servicios adicionales** (bóveda, cremación,
  traslados; recurrentes o de cargo único, contratables por contrato),
  cobradores y **rutas de cobranza** (zona, día de cobro, cobrador asignado).
- **Importar** — migración desde otros sistemas por **CSV** (clientes,
  vendedores, contratos, beneficiarios y pagos históricos). Detecta el separador,
  acepta alias de encabezados y fechas DD/MM/AAAA, actualiza por cédula/número
  (sin duplicar) y tiene **modo simulación** para validar antes de guardar.
  Plantillas CSV descargables y bitácora de importaciones con errores por fila.
  Orden recomendado: clientes → vendedores → contratos → beneficiarios → pagos.

---

## Cómo se ve en el sitio público

- **Página principal** (`index.php`): sección de obituarios con los **destacados +
  recientes** (3 por defecto). Botón **“Ver todos los obituarios”**.
- **Listado completo** (`obituarios.php`): todos los obituarios en estilo periódico,
  con **buscador** y paginación.
- **Página individual** (`obituario.php?slug=…`): homenaje completo con la plantilla
  elegida, **datos estructurados (JSON-LD)** para SEO/GEO, lista de condolencias
  aprobadas y formulario para enviar nuevas.
- **Compartir**: cada obituario se puede compartir por WhatsApp/enlace.
- **Directorio Médico** (`directorio-medico.php` → `medico.php?slug=…`): lista buscable
  por especialidad y ficha individual con JSON-LD `Physician`.
- **Recursos de Lectura** (`recursos.php` → `recurso.php?slug=…`): artículos con filtro
  por categoría y detalle con JSON-LD `Article`.
- Ambos figuran en el **menú principal**, el **pie de página** y el **sitemap.xml**.

---

## Instalación / despliegue

Resumen (guías detalladas en `database/README.md` y `api/README.md`):

1. **Base de datos**: crear la BD MySQL en cPanel e importar
   [`database/01_schema.sql`](database/01_schema.sql) y, para el Directorio Médico y
   los Recursos, [`database/02_directorio_recursos.sql`](database/02_directorio_recursos.sql), con phpMyAdmin.
   Para el **módulo de Previsión**, importar además
   [`database/04_prevision.sql`](database/04_prevision.sql) y
   [`database/05_prevision_v2.sql`](database/05_prevision_v2.sql).
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
