---
Estado: **Aprobada (2026-10-28)** — en implementación. Es la Fase 2 de
`2026-10-27-autenticacion-staff-y-portal-clientes.md` (opción C), adelantada a
pedido del usuario.
---

# "Entrar con Previsión Funeraria" (inicio de sesión único del panel)

## 1. Qué se pidió (2026-10-28)

1. Que el personal de Funeraria del Zulia entre al panel de este sitio con **los
   mismos usuarios que tiene en su empresa (`fdz`) de Previsión Funeraria (PF)**:
   cambian de entorno, no de usuario.
2. Un **enlace en el panel de este sitio al sistema de `fdz` en PF**.

## 2. Decisiones del usuario (2026-10-28)

1. **Los perfiles se asignan en los roles de PF** (`/roles` de la empresa `fdz`),
   no en este panel. Quitar a alguien en PF le corta también este panel.
2. **El login local queda solo para emergencias**: 1-2 cuentas locales en una
   ruta no enlazada (`admin.html?emergencia=1`), con límite de intentos y
   auditadas, para cuando PF esté caído. El resto entra solo por PF.

## 3. Flujo

Dominios distintos (`legadoholding.com`/`funerariadelzulia.com` vs.
`app.previsionfuneraria.com`) → no se comparte cookie. Se usa el flujo estándar
OAuth 2.0 *authorization code* con PKCE (S256), con el canje **servidor a
servidor**:

1. Panel → botón "Entrar con Previsión Funeraria" → `api/sso.php?action=start`.
   PHP genera `state` y `code_verifier` (en la sesión PHP) y redirige a
   `PF/oauth/authorize?client_id&redirect_uri&state&code_challenge&code_challenge_method=S256`.
2. PF exige su sesión normal (contraseña + MFA). Sin sesión → `/login?next=…` y
   al terminar vuelve a `/oauth/authorize`.
3. PF verifica: cliente activo, `redirect_uri` en la lista blanca (igualdad
   exacta), usuario activo, membresía **activa** en el tenant del cliente
   (`fdz`), tenant `active`, y al menos un permiso `sitio.*`. Si todo va bien,
   redirige a `redirect_uri?code&state` (código de un solo uso, 60 s, guardado
   como HMAC). Si no, `redirect_uri?error=access_denied&state`.
4. `api/sso.php?action=callback` comprueba `state`, y canjea por cURL
   `POST PF/oauth/token` (`client_id` + `client_secret` + `code` +
   `code_verifier` + `redirect_uri`) → `{ access_token, expires_in, usuario:
   { id, email, nombre }, permisos: [...] }`.
5. PHP crea/actualiza la fila local en `users` (vinculada por `pf_user_id`),
   regenera la sesión y guarda los permisos en ella.
6. **Revalidación**: cada 5 min, `GET PF/oauth/userinfo` (Bearer). PF responde 401
   si el token venció, si la sesión de PF que lo originó se cerró o revocó, si se
   desactivó la cuenta o la membresía, o si ya no tiene permisos `sitio.*` → este
   panel cierra la sesión. Si PF no responde, se tolera hasta 30 min desde la
   última verificación exitosa; después se cierra la sesión (las cuentas de
   emergencia cubren la caída).

El token vive como máximo lo que la sesión de PF que lo originó (12 h). Cerrar
sesión en PF cierra este panel en ≤ 5 min. Cerrar sesión en este panel revoca el
token (`POST PF/oauth/revoke`), no la sesión de PF.

## 4. Permisos (catálogo de roles de PF)

| Permiso PF | Perfil en este panel | Acceso |
|---|---|---|
| `sitio.admin` | Administrador | Todo: usuarios, configuración, plantillas, borrado definitivo |
| `sitio.obituarios.write` | Editor de obituarios | Obituarios y tarjetas (incluye subir fotos) |
| `sitio.condolencias.moderate` | Moderador | Condolencias |
| `sitio.contenido.write` | Editor de contenido | Directorio médico, recursos, FAQs (incluye subir imágenes) |

`sitio.admin` implica los otros tres. La migración de PF se los da al rol
protegido `tenant_global_admin` de `fdz`; a los demás roles los asigna el admin
de la empresa desde `/roles`.

Cuentas locales de emergencia: `admin` → `sitio.admin`; `editor` → los otros tres
(mismo acceso que hoy).

## 5. Cambios en este repo

| Pieza | Detalle |
|---|---|
| `database/18_sso_prevision.sql` | `users.auth_source` (`local`/`pf`), `users.pf_user_id` único; tabla `auth_throttle`; ajuste `pf_panel_url`. |
| `api/config.php` (servidor, no git) | Bloque `pf_sso`: `base_url`, `client_id`, `client_secret`, `redirect_uri`. Sin bloque → SSO apagado y el login local funciona como hasta hoy (despliegue seguro antes de que PF esté listo). |
| `api/lib/sso.php` + `api/sso.php` | `start`, `callback`; revalidación en `auth_user()`. |
| `api/lib/auth.php` | `user_perms()`, `require_perm()`; el login local solo para `auth_source = 'local'`, con límite de intentos (5 fallos en 15 min por IP y por correo → bloqueo 15 min). |
| `api/*.php` | `require_role(...)` → `require_perm(...)` según la tabla de la sección 4. |
| `admin.html` / `admin.js` | Pantalla de entrada con "Entrar con Previsión Funeraria"; formulario local solo con `?emergencia=1`; pestañas según permisos; en Usuarios, las cuentas PF son de solo lectura (se administran en PF). |
| Enlace a PF | Botón "Previsión (FDZ)" en la cabecera del panel → ajuste `pf_panel_url` (por defecto `https://app.previsionfuneraria.com/?empresa=fdz`), editable en Configuración; vacío = oculto. |

## 6. Cambios en Prevision-Funeraria (otro repo)

Diseño interno documentado allá (`docs/auth/sso-sitios.md`). Resumen del contrato:
migración `control/0017_oauth_sitios.sql` (clientes, códigos, tokens, permisos
`sitio.*`), rutas `/oauth/authorize|token|userinfo|revoke`, `?next=` en el login,
`?empresa=fdz` en el panel, y `npm run oauth-client` para registrar este sitio
(imprime `client_id` y `client_secret` una sola vez).

## 7. Puesta en marcha

1. PF: aplicar la migración `0017` en remoto, desplegar el Worker, registrar el
   cliente con la `redirect_uri` del sitio de prueba (y luego la del dominio
   final).
2. Este sitio: importar `18_sso_prevision.sql`, pegar el bloque `pf_sso` en
   `api/config.php` del servidor, desplegar.
3. Dejar 1-2 cuentas locales de emergencia (`auth_source = 'local'`) con
   contraseñas fuertes; desactivar las demás cuentas locales.

## 8. Fuera de alcance

- MFA propio para las cuentas de emergencia (sigue en la Fase 1 de la spec del
  2026-10-27).
- SSO para el sitio de Legado Holding (el diseño de PF lo permite registrando otro
  cliente con `tenant_slug = 'lh'`).
