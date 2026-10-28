---
Estado: **Aprobada (2026-10-27)** — decisiones en la sección 8. Paso 2 (enlace
al portal de clientes) implementado. La Fase 2 (SSO) se adelantó el 2026-10-28:
`2026-10-28-sso-prevision-funeraria.md` (incluye los 4 perfiles y el límite de
intentos). Resto de la Fase 1 pendiente.
---

# Inicio de sesión del personal administrativo + portal de clientes con OTP

## 1. Qué se pidió (2026-10-27)

1. Revisar cómo se inicia sesión hoy en el panel administrativo de este sitio
   (`admin.html`) y si el mecanismo es seguro.
2. Evaluar dos caminos para el **personal administrativo y sus perfiles**:
   iniciar sesión **contra Prevision-Funeraria** (cuenta única) o mantener un
   inicio de sesión **propio** de este sitio.
3. Para los **clientes**: un mecanismo **OTP** (código de un solo uso) para un
   portal de clientes.

Todo lo de abajo se verificó leyendo el código de ambos repos y con
peticiones en vivo de solo lectura el 2026-10-27.

## 2. Cómo funciona hoy el login del panel (este repo)

URL: `https://legadoholding.com/funerzul/admin.html` (prueba). Flujo: correo +
contraseña → `POST api/auth.php?action=login` → sesión PHP nativa (cookie
`obit_sess`) + token CSRF. Código: `api/lib/auth.php`, `api/lib/bootstrap.php`.

### Lo que está bien

- Contraseñas con bcrypt (`password_hash`/`password_verify`).
- `session_regenerate_id(true)` al entrar (evita fijación de sesión).
- Cookie `HttpOnly`, `Secure`, `SameSite=Lax` (verificado en la respuesta real).
- CSRF con `hash_equals` en toda mutación; CORS con lista blanca de orígenes.
- Usuario recargado de la BD en cada request (`is_active = 1`): desactivar a
  alguien le corta el acceso en la siguiente petición.
- Auditoría de login, login fallido y logout en `audit_log`.

### Lo que falta o es riesgoso (ordenado por gravedad)

| # | Problema | Dónde | Impacto |
|---|---|---|---|
| 1 | **Contraseña del admin inicial publicada en un repo público** (la contraseña sembrada en `database/01_schema.sql`, documentada también en `database/README.md`). El repo `estebanjvasquez/Propuesta-Funerzul` es público (verificado). | BD sembrada | Si nunca se cambió, cualquiera entra como admin. |
| 2 | **Sin límite de intentos** ni bloqueo temporal en el login. | `login_attempt()` | Fuerza bruta / relleno de credenciales sin freno. |
| 3 | **Sin segundo factor (MFA)**. | — | Una contraseña filtrada basta para entrar. |
| 4 | **Sin vencimiento por inactividad ni duración máxima** de sesión (cookie de sesión de navegador; dura lo que el GC de PHP del hosting). | `bootstrap.php` | Sesiones abiertas indefinidamente en equipos compartidos. |
| 5 | **Cambiar la contraseña no cierra las otras sesiones** del usuario. | `users.php` | Quien robó una sesión la conserva tras el cambio. |
| 6 | **No hay "olvidé mi contraseña"**: solo otro admin puede reasignarla. | — | Si el único admin la pierde, hay que entrar por phpMyAdmin. |
| 7 | `app.env = development` en el servidor: la API expone mensajes internos (verificado 2026-10-08). | `config.php` del servidor | Filtra detalles de la BD/código. |
| 8 | Cookie con `path=/` en `legadoholding.com`: se envía también al resto de ese dominio (otro sitio de otra marca). | `bootstrap.php` | Exposición innecesaria mientras se prueba en subcarpeta. |
| 9 | Sin cabeceras HSTS, `X-Frame-Options`/`frame-ancestors` ni `Referrer-Policy`. | `.htaccess` | Clickjacking del panel, downgrade a HTTP. |
| 10 | Solo dos perfiles (`admin`, `editor`), fijos en un `ENUM`. | `01_schema.sql` | No se puede dar acceso solo a condolencias o solo a contenido. |

Conclusión: la base es correcta, pero **hoy no es un login seguro para un
panel expuesto a Internet**, sobre todo por los puntos 1-3.

## 3. Qué ofrece Prevision-Funeraria hoy (repo hermano, `main` @ `c7282c2`)

Mucho más de lo que se suponía en agosto (ver `docs/auth/architecture.md` de
ese repo):

- **Personal (staff):** usuarios en una base de control común, roles por
  empresa (`fdz`, `lh`) **configurables** con permisos finos (`/roles`),
  contraseñas PBKDF2, **MFA TOTP** (obligatorio para administradores, exigible
  por usuario), límite de intentos, sesiones revocables (logout-all, revocación
  al cambiar contraseña), alta por invitación/activación por correo y
  auditoría. Login en `https://app.previsionfuneraria.com/login`.
- **Portal del titular (clientes) — ya existe, con OTP:**
  `https://app.previsionfuneraria.com/portal/login?empresa=fdz` (responde 200).
  El cliente ingresa su **cédula**, recibe un **código de 6 dígitos por correo**
  (vence en 10 min, máx. 5 intentos, 1 código por minuto, respuesta genérica que
  no revela si la cédula existe, guardado como HMAC), y obtiene una sesión
  propia de 30 min, separada de la del personal. Dentro ve sus contratos,
  cuotas, formas de pago y puede pagar. También hay un portal de vendedores con
  el mismo mecanismo.
- **Lo que NO tiene:** ningún mecanismo para que **otro sitio** autentique a sus
  usuarios contra él (no hay OAuth/OIDC, ni intercambio de códigos, ni
  verificación de sesión para terceros — búsqueda en `src/` y `docs/auth/` sin
  resultados). Su `PLAN.md` deja la interfaz preparada para SSO "más adelante",
  sin fecha. Es el mismo bloqueo registrado el 2026-08-28.

Dato técnico que condiciona el diseño: el sitio vive en otro dominio
(`legadoholding.com` → `funerariadelzulia.com`) que Prevision-Funeraria
(`app.previsionfuneraria.com`). **Las cookies no se pueden compartir entre
dominios distintos**, así que un login único exige un flujo por redirección
con intercambio de código entre servidores — no basta con "leer la cookie".

## 4. Personal administrativo: opciones

### Opción A — Login único contra Prevision-Funeraria (SSO)

El botón "Entrar" del panel redirige a `app.previsionfuneraria.com/login`
(con su MFA), vuelve al sitio con un código de un solo uso, y el servidor PHP
lo canjea **servidor a servidor** por los datos del usuario y sus permisos en
el tenant `fdz`. El sitio crea su sesión local con esos permisos.

- **A favor:** una sola cuenta y contraseña por persona; MFA, bloqueo y altas/
  bajas ya resueltos en PF; dar de baja a alguien en PF le corta también este
  panel; los perfiles se administran en un solo lugar (`/roles` de PF).
- **En contra:** requiere **construir el proveedor de identidad en
  Prevision-Funeraria** (otro repo, no es trabajo de este); si PF está caído,
  nadie puede editar obituarios; más piezas móviles entre Cloudflare y cPanel.

Contrato mínimo que este sitio necesitaría de PF (el diseño interno se decide
en ese repo — aquí solo la interfaz):

1. Registro de este sitio como cliente (`client_id`, secreto, lista blanca de
   `redirect_uri`).
2. `GET /oauth/authorize?client_id&redirect_uri&state&code_challenge` → exige
   sesión de PF (con MFA) y membresía en `fdz` → redirige con `code` (un uso,
   ~60 s).
3. `POST /oauth/token` (servidor a servidor, con secreto + PKCE) →
   `{ sub, email, nombre, tenant: "fdz", permisos: [...] }`.
4. Permisos nuevos en el catálogo de roles de `fdz` para este sitio, p. ej.
   `sitio.obituarios.write`, `sitio.condolencias.moderate`,
   `sitio.contenido.write`, `sitio.admin`.

### Opción B — Login propio de este sitio, reforzado

Mantener las cuentas en MySQL y corregir los 10 puntos de la sección 2: límite
de intentos, MFA TOTP, vencimientos, revocación, recuperación por correo,
cabeceras y perfiles.

- **A favor:** independiente de PF; se implementa ya, en PHP puro sin
  dependencias nuevas; funciona aunque PF esté caído.
- **En contra:** el personal que use ambos sistemas tiene dos cuentas; altas y
  bajas se hacen dos veces; se duplica lógica de seguridad que PF ya tiene.

### Opción C — Híbrida por fases (**recomendada**)

1. **Fase 1 (ya, en este repo):** Opción B en su versión esencial. Es
   necesaria igual: cierra riesgos reales **hoy** y deja una **cuenta de
   emergencia local** para cuando exista el SSO.
2. **Fase 2 (cuando PF construya el contrato de la Opción A):** el login normal
   pasa a ser "Entrar con Previsión Funeraria"; el login local queda solo para
   1-2 cuentas de emergencia con MFA, en una ruta no enlazada y auditada.

Motivo: el SSO depende de trabajo en otro repo sin fecha, y mientras tanto el
panel está expuesto con los problemas 1-3. La Fase 1 no se desperdicia al
llegar la Fase 2.

### Alcance concreto de la Fase 1

| Medida | Detalle |
|---|---|
| Rotar la credencial sembrada | Forzar cambio de contraseña en el primer ingreso si nunca se cambió (`password_changed_at` nulo). Cambiarla en el servidor **de inmediato**, sin esperar la Fase 1. |
| Límite de intentos | Tabla `auth_throttle` (por IP y por correo): 5 fallos en 15 min → bloqueo de 15 min. Verificación de contraseña simulada cuando el correo no existe (igual tiempo de respuesta). |
| MFA TOTP | RFC 6238 en PHP puro (`hash_hmac` + base32), compatible con Google Authenticator/Authy. Obligatorio para `admin`, opcional para el resto. Secreto cifrado con una clave de `config.php`. 8 códigos de recuperación de un solo uso. |
| Vencimientos de sesión | Inactividad 30 min; máximo absoluto 10 h. |
| Revocación | Columna `users.session_version`: se incrementa al cambiar contraseña, desactivar o "cerrar todas las sesiones"; cada request la compara. |
| Recuperar contraseña | Enlace por correo (token aleatorio guardado como hash, 30 min, un uso) usando `notify_email()` existente. |
| Política de contraseñas | Mínimo 12 caracteres; `password_needs_rehash()` al entrar. |
| Cookie | `path` según la carpeta de instalación (`/funerzul/` en prueba); prefijo `__Host-` en el dominio final. |
| Cabeceras | En `.htaccess`: HSTS, `X-Frame-Options: DENY` + `frame-ancestors 'none'` para `admin.html` y `api/`, `Referrer-Policy`. |
| Producción | `app.env = production` en el servidor. |

Implica una migración nueva numerada (`database/15_...sql`; la 14 ya la usa el
portal de clientes) — nunca editar
`01_schema.sql` ya aplicado.

## 5. Perfiles del personal

Hoy: `admin` (todo) y `editor` (obituarios, condolencias, directorio,
recursos, FAQs). Propuesta — mapa de permisos en PHP (mismo enfoque que PF),
de modo que en la Fase 2 cada perfil corresponda 1:1 a un permiso de PF:

| Perfil | Puede | Permiso equivalente en PF (Fase 2) |
|---|---|---|
| Administrador | Todo: usuarios, configuración, plantillas, secciones, borrado definitivo | `sitio.admin` |
| Editor de obituarios | Crear/editar/destacar/dar de baja obituarios; tarjetas | `sitio.obituarios.write` |
| Moderador | Aprobar/ocultar/editar condolencias | `sitio.condolencias.moderate` |
| Editor de contenido | Directorio médico, recursos de lectura, preguntas frecuentes | `sitio.contenido.write` |

Una persona puede tener varios perfiles. El `editor` actual se migra a
"Editor de obituarios + Moderador + Editor de contenido" (mismo acceso que hoy).

## 6. Clientes: portal con OTP

**Recomendación: no construir un portal ni un OTP en este repo. Usar el portal
del titular que ya existe en Prevision-Funeraria.**

Motivos:

- Los datos del cliente (contratos, cuotas, pagos) viven en Prevision-Funeraria
  desde el 2026-08-19; la base MySQL de este sitio no los tiene. Un portal aquí
  tendría que pedirle todo a PF de todos modos.
- El mecanismo OTP ya existe allí, con los controles correctos (sección 3).
- Duplicarlo crearía dos puntos de acceso a datos personales que mantener.

Lo que hace este repo — **implementado el 2026-10-27**:

- Enlace **"Portal de Clientes"** en el menú y el pie de página
  (`partials/site_header.php`, `partials/site_footer.php` y la copia propia de
  `index.php`).
- En Configuración del panel (subsección "Portal de Clientes"): un
  **interruptor** para mostrarlo u ocultarlo y la **URL** a usar, ambos
  editables por el admin (`client_portal_enabled`, `client_portal_url` en
  `app_settings`, sembrados por `database/14_portal_clientes.sql`).
- A diferencia de las secciones del sitio (que fallan abiertas), el enlace
  **arranca apagado** y **falla cerrado**: si la BD no responde o la URL no es
  `https://` válida, no se muestra (`client_portal_url()` en
  `api/lib/helpers.php`). `api/settings.php` rechaza con 422 una URL que no sea
  https y valida todos los valores antes de guardar ninguno.
- URL por defecto: `https://app.previsionfuneraria.com/portal/login?empresa=fdz`.
- Se abre en la misma pestaña; no se agrega al sitemap (es un sitio externo).

Huecos del portal de PF a resolver **en ese repo** antes de anunciarlo:

1. **El código solo llega por correo.** Clientes sin correo registrado (muchos
   vienen del sistema legado SIEMPRE) no pueden entrar, y la respuesta genérica
   no les dice por qué. Opciones: envío por **WhatsApp** (requiere una plantilla
   de autenticación aprobada por Meta en WhatsApp Cloud API) o SMS — enlaza con
   el proveedor de mensajería que sigue sin contratarse.
2. **Entregabilidad del correo:** la guía de pruebas de PF (2026-08-31) registra
   correos en spam de Outlook y el riesgo de que el código "nunca llegue" si el
   dominio remitente no está verificado. Confirmar el estado actual.
3. **Marca:** el correo llega como "Previsión Funeraria", no "Funeraria del
   Zulia". Opcional: dominio propio del portal (p. ej.
   `portal.funerariadelzulia.com` como dominio personalizado en Cloudflare).

## 7. Plan propuesto

| Paso | Dónde | Depende de |
|---|---|---|
| Paso | Dónde | Estado |
|---|---|---|
| 0. Cambiar ya la contraseña del admin y poner `env = production` | Servidor (manual) | Pendiente |
| 1. Fase 1 de seguridad del login + perfiles (secciones 4 y 5) | Este repo | Pendiente (aprobada) |
| 2. Enlace "Portal de Clientes" con interruptor y URL configurables | Este repo | **Hecho** (2026-10-27), apagado hasta que PF esté listo |
| 3. Entregabilidad del correo del OTP y marca FDZ | Prevision-Funeraria | Pendiente |
| 4. Proveedor de identidad (contrato de la Opción A) | Prevision-Funeraria | Pendiente |
| 5. Fase 2: "Entrar con Previsión Funeraria" + cuentas de emergencia | Este repo | Depende del paso 4 |

## 8. Decisiones del usuario (2026-10-27)

1. Personal: **Opción C** — híbrida por fases (login propio reforzado ahora,
   SSO con Prevision-Funeraria después, login local solo de emergencia).
2. Perfiles: **los cuatro de la sección 5**, sin cambios.
3. Clientes: **enlazar el portal de Prevision-Funeraria** — los clientes
   finales van a estar allí. El enlace del menú y su URL deben poder
   activarse/desactivarse desde Configuración, como las secciones, hasta que
   todo esté listo en Prevision-Funeraria.
4. Canal del OTP para clientes: **solo correo**. WhatsApp/SMS queda fuera por
   ahora; el hueco 1 de la sección 6 (clientes sin correo registrado no pueden
   entrar) queda como limitación conocida.

## 9. Fuera de alcance

- El diseño interno del proveedor de identidad en Prevision-Funeraria (se
  documenta en ese repo; regla de `CLAUDE.md`).
- Cambios al portal de vendedores de PF.
- Login de los visitantes del sitio público (no lo necesitan: las condolencias
  son anónimas con moderación).
