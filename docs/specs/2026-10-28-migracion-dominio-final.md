---
Estado: **Aprobado (2026-10-28). Corte autorizado por el cliente el 2026-11-02**
(www y sin www). **Reescrito el 2026-11-02**: el sitio ya no vive en la cuenta de
Legado; el ensayo `nuevo.funerariadelzulia.com` está en la **misma cuenta de
cPanel** que el WordPress, así que el corte es un movimiento dentro de esa cuenta.
Lo marcado "(verificar)" no se puede ver desde fuera del servidor.
---

# Migración al dominio definitivo `www.funerariadelzulia.com`

Pasar el sitio nuevo (PHP + MySQL), hoy en prueba en
`https://nuevo.funerariadelzulia.com/`, a `https://www.funerariadelzulia.com/`
y `https://funerariadelzulia.com/`, donde hoy está el **WordPress** del cliente.

## 1. Situación actual (revisada en vivo el 2026-11-02)

| Tema | Estado |
|---|---|
| Cuenta de cPanel | Una sola: `funerariadelzuli`. El dominio es el **principal** de la cuenta, con raíz fija `public_html/`. |
| WordPress | En `public_html/`. Plugins vistos desde fuera: **Wordfence** (con `wordfence-waf.php` en la raíz: firewall cargado por `auto_prepend_file`, ver 4.2) y **Site Kit by Google** (`GT-PJ5S7WTQ`). La tienda WooCommerce no tenía pedidos. |
| Sitio nuevo (ensayo) | En `public_html/nuevo.funerariadelzulia.com/` (cPanel no deja raíces de subdominio fuera de `public_html`). Desplegado por cPanel → Git Version Control con `.cpanel.yml`. Responde bien: base de datos conectada, inicio de sesión con Previsión Funeraria activo, `site_url` ya es `https://www.funerariadelzulia.com` (canónicas correctas). |
| Base de datos del sitio nuevo | Ya está en la cuenta de FDZ y es la que usará el dominio final. **No se exporta ni se importa nada.** |
| Fotos (`uploads/`) | En `public_html/nuevo.funerariadelzulia.com/uploads/`. No están en git: se copian en el corte. |
| Cuenta de Legado (`legadoholding.com/funerzul/`) | Ya no se usa desde hace más de una semana. Solo queda pendiente decidir si se dejan redirecciones para enlaces viejos (sección 7). |
| Redirección sin www → www | Hoy la hace **WordPress** (`x-redirect-by: WordPress`), no Cloudflare. Desde el 2026-11-02 la hace también el `.htaccess` del sitio nuevo. `http` → `https` lo hace Cloudflare. |
| Copia duplicada | `www.funerariadelzulia.com/nuevo.funerariadelzulia.com/` responde 200 (la carpeta del ensayo vista por el dominio principal). Desde el 2026-11-02 el `.htaccess` le pone `noindex`; se borra en la sección 7. |

## 2. Previsión Funeraria (inicio de sesión del panel)

No hace falta un cliente nuevo. Antes del corte se agrega la dirección final al
**mismo** cliente; el `client_id` y el `client_secret` no cambian:

```sql
-- prevision-control (remoto), antes del corte:
UPDATE oauth_clients
  SET redirect_uris_json = json_insert(redirect_uris_json, '$[#]',
      'https://www.funerariadelzulia.com/api/sso.php?action=callback')
  WHERE client_id = 'fdz-sitio-508a4bc';
```

Si la consulta muestra que ya está, no repetirla
(`SELECT redirect_uris_json FROM oauth_clients WHERE client_id = 'fdz-sitio-508a4bc'`).
**No** llenar `tenants.dominio` de `fdz` en PF: ese campo es para servir PF bajo
otro dominio.

## 3. Antes del día del corte

1. **Respaldo completo de WordPress**: cPanel → Respaldos → descargar el respaldo
   de la carpeta de inicio y de la base de WordPress (o phpMyAdmin → Exportar).
   Guardarlo 90 días fuera del servidor.
2. **Respaldo del sitio nuevo**: phpMyAdmin → base del sitio nuevo → Exportar, y
   descargar `nuevo.funerariadelzulia.com/api/config.php`.
3. **Migraciones** en la base del sitio nuevo (phpMyAdmin → Importar), en orden,
   las que falten: `20_plantilla_catolica.sql` y `21_interes_servicios.sql`. Todas
   son idempotentes. Comprobar con `api/diag.php?token=CRON_SECRET`.
4. **Desplegar el último commit en el ensayo** (Git Version Control → Update from
   Remote → Deploy HEAD Commit) y revisar la página de cremación de mascotas, la
   pestaña Sistema → Interés del público y la tarjeta ✝ de un obituario.
5. **Seguridad**: borrar el usuario temporal `manual@funerariadelzulia.com`
   (Sistema → Usuarios). Dejar 1-2 cuentas locales de emergencia con contraseña
   fuerte.
6. **Previsión Funeraria**: la consulta de la sección 2.
7. **Google**: en Search Console, si la propiedad está verificada con la etiqueta
   de Site Kit, se pierde al quitar WordPress: agregar antes una **propiedad de
   dominio verificada por DNS** (registro TXT en Cloudflare). Anotar el ID `G-…`
   de Analytics para cargarlo en el panel.
8. **Cloudflare**: SSL/TLS en **Full (strict)** y "Always Use HTTPS" activo. Que no
   se cacheen `admin.html`, `/api/*` ni `/o/*` (regla "Bypass" si hace falta).
9. **Avisar al personal** la fecha y la hora del corte (1 hora, de noche).

## 4. El corte, paso a paso (~45 min)

Todo se hace en el cPanel de la cuenta `funerariadelzuli`.

### 4.1 Congelar

Pedir al personal que no cree ni edite nada en el panel durante la ventana. La
base de datos es la misma antes y después, así que no hay que sincronizarla.

### 4.2 Quitar el firewall de Wordfence (obligatorio, antes de mover nada)

Wordfence carga su firewall con la directiva PHP `auto_prepend_file`, que apunta a
`public_html/wordfence-waf.php`. Esa directiva aplica a **todo** PHP que se
ejecute bajo `public_html/`. Si se mueve WordPress y la directiva queda, **cada
página del sitio nuevo da error fatal** (el ensayo no lo sufre porque su raíz es
otra carpeta).

1. Lo más limpio: entrar al WordPress → **Wordfence → Firewall → All Firewall
   Options → "Remove Extended Protection"** (o "Optimize the Wordfence
   Firewall" → desactivar). Wordfence borra la directiva él mismo.
2. Si no se puede entrar al WordPress: Administrador de archivos → `public_html/`
   → mostrar archivos ocultos (paso 4.3.1) → abrir **`.user.ini`** y, si existe,
   **`php.ini`**, y borrar la línea `auto_prepend_file = '…/wordfence-waf.php'`.
   Revisar también el `.htaccess` de WordPress: si tiene un bloque
   `# Wordfence WAF` con `php_value auto_prepend_file`, borrarlo.
3. **Esperar 5 minutos**: PHP guarda en caché `.user.ini` hasta 300 segundos.

### 4.3 Mover WordPress a `public_html/_wp-anterior/`

1. Administrador de archivos → entrar a `public_html/` → **Configuración**
   (arriba a la derecha) → marcar **"Mostrar archivos ocultos (dotfiles)"** →
   Guardar. Sin esto no se ven `.htaccess` ni `.user.ini` y quedarían atrás.
2. Ver qué carpetas **no son de WordPress** y no se deben mover: cPanel →
   **Dominios** muestra la raíz de cada subdominio o dominio adicional. Las que
   estén dentro de `public_html/` se quedan. Como mínimo:
   - `.well-known/` (lo usa el certificado SSL de AutoSSL);
   - `cgi-bin/`;
   - `nuevo.funerariadelzulia.com/` (el ensayo; de aquí salen el `config.php` y
     las fotos);
   - la carpeta del repositorio, si cPanel → Git Version Control lo clonó dentro
     de `public_html/` (la ruta aparece en "Ruta del repositorio"). (verificar)
3. Botón **+ Carpeta** → nombre `_wp-anterior` → Crear.
4. **Seleccionar todo** (casilla de arriba) y **desmarcar** lo del punto 2 y la
   propia `_wp-anterior/`. Lo que queda marcado es WordPress: `wp-admin/`,
   `wp-content/`, `wp-includes/`, `index.php`, `wp-*.php`, `xmlrpc.php`,
   `wordfence-waf.php`, `license.txt`, `readme.html`, `.htaccess`, `.user.ini`,
   `php.ini` y cualquier otro archivo suelto.
5. Botón **Mover** → escribir `/public_html/_wp-anterior` → **Mover archivos**.
6. Entrar a `_wp-anterior/`. Renombrar `.htaccess` a `.htaccess-wordpress`. Crear
   un archivo nuevo `.htaccess` con una sola línea:

   ```apache
   Require all denied
   ```

   Así nadie puede abrir el `wp-config.php` ni nada del WordPress viejo desde
   internet.
7. Comprobar: `https://www.funerariadelzulia.com/` ahora da un error o una
   página vacía. Es lo esperado durante unos minutos.

### 4.4 Desplegar el sitio nuevo en `public_html/`

1. cPanel → **Git Version Control** → el repositorio → pestaña "Pull or Deploy" →
   **Update from Remote** → **Deploy HEAD Commit**. No hay que tocar el repo:
   como ya existe `public_html/_wp-anterior/`, `.cpanel.yml` despliega en
   `public_html/` en vez de en la carpeta del ensayo.
2. Comprobar en el Administrador de archivos que aparecieron en `public_html/`
   el `index.php` nuevo, `.htaccess`, `api/`, `partials/`, `servicios/` y
   `planes/`.

### 4.5 Configuración y fotos

1. **`config.php`**: copiar `public_html/nuevo.funerariadelzulia.com/api/config.php`
   a `public_html/api/` (clic derecho → Copiar → `/public_html/api`). Editar la
   copia y revisar solo esto:

   | Clave | Valor |
   |---|---|
   | `app.site_url` | `https://www.funerariadelzulia.com` (ya está así en el ensayo) |
   | `app.env` | `production` |
   | `security.allowed_origins` | `https://www.funerariadelzulia.com`, `https://funerariadelzulia.com` |
   | `pf_sso.redirect_uri` | `https://www.funerariadelzulia.com/api/sso.php?action=callback` |
   | `db` | **Sin cambios**: la misma base del ensayo |

2. **Fotos**: copiar `public_html/nuevo.funerariadelzulia.com/uploads/` sobre
   `public_html/uploads/` (Copiar → `/public_html`). Debe quedar
   `public_html/uploads/obituarios/…`. `uploads/` y `cache/` con permiso 755.
   `cache/` no se copia: se regenera sola.

### 4.6 Cron, caché y pruebas

1. cPanel → **Cron**: cambiar la ruta de la purga de fotos a
   `/usr/local/bin/php /home/funerariadelzuli/public_html/api/cron/purge_photos.php`.
   Nunca dejar dos crons activos.
2. Cloudflare → **Caching → Purgar todo**.
3. **Pruebas de humo** en `https://www.funerariadelzulia.com` (en una ventana de
   incógnito):
   - portada, un obituario, `/planes/#comparar`, `servicios/cremacion-mascotas.php`;
   - `https://funerariadelzulia.com/planes/` → debe llevar a `https://www.funerariadelzulia.com/planes/`;
   - URLs viejas: `/nosotros/`, `/preguntas-frecuentes/`, `/crematorios-del-zulia/`,
     `/tienda/` (301) y `/wp-login.php` (410);
   - `robots.txt` y `sitemap.php`;
   - entrar al panel con **Entrar con Previsión Funeraria** y con la cuenta de
     emergencia (`admin.html?emergencia=1`);
   - crear un obituario en borrador con foto, descargar su tarjeta y borrarlo;
   - `api/diag.php?token=CRON_SECRET`: tablas completas y `site_url` correcta.
4. Avisar al personal que ya pueden usar el panel en la dirección nueva.

### Vuelta atrás (~10 min)

1. En `public_html/` crear `_sitio-nuevo/` y mover ahí lo que desplegó el paso 4.4
   (`index.php`, `.htaccess`, `api/`, `partials/`, `servicios/`, `planes/`,
   `img/`, `assets/`, `uploads/`, `cache/`, `manual/` y los `.php`, `.js`,
   `.css` y `.png` sueltos).
2. En `_wp-anterior/`: borrar el `.htaccess` de `Require all denied`, renombrar
   `.htaccess-wordpress` a `.htaccess` y mover todo su contenido de vuelta a
   `public_html/`. Borrar la carpeta vacía `_wp-anterior/` (si no, el próximo
   despliegue iría otra vez a `public_html/`).
3. Volver a activar el firewall de Wordfence desde su panel.
4. Cloudflare → Purgar todo.

WordPress y su base no se modifican en ningún paso, así que la vuelta atrás es
completa. Lo cargado en el panel nuevo sigue en su base y en el ensayo.

## 5. Redirecciones de las URLs de WordPress (hecho en `.htaccess`, 2026-10-28)

| URL de WordPress | Destino (301) |
|---|---|
| `/nosotros/` | `/nosotros.php` |
| `/preguntas-frecuentes/` | `/#preguntas` |
| `/familias-protegidas/` | `/planes/` |
| `/crematorios-del-zulia/` | `/crematorios-del-zulia.php` |
| `/tienda/`, `/carrito/`, `/finalizar-compra/`, `/mi-cuenta/…`, `/producto/…` | `/planes/` |
| `/testimonial/…`, `/category/testimonial/` | `/#testimonios` |
| `/category/…`, `/tag/…`, `/author/…`, `/sample-page/`, `/feed/`, `/?p=…`, `/?page_id=…` | `/` |
| `/sitemap_index.xml`, `/wp-sitemap.xml`, `/*-sitemap.xml` | `/sitemap.php` |
| `/wp-admin/…`, `/wp-content/…`, `/wp-includes/…`, `/wp-json/…`, `/wp-login.php`, `/xmlrpc.php` | `410 Gone`. Corta los bots que buscan WordPress. |
| `funerariadelzulia.com/…` (sin www) | `https://www.funerariadelzulia.com/…` |

## 6. Lo que el sitio resuelve solo

- Todas las URLs absolutas (canónicas, og:url, sitemap, correos) salen de
  `app.site_url`. Las internas son relativas, y el `.htaccess` funciona igual en
  raíz que en subcarpeta.
- `.cpanel.yml` elige solo la carpeta de destino (ensayo o `public_html/`).
- La sesión del panel es por dominio: el personal vuelve a entrar una vez.
- `robots.txt` ya apunta a `https://www.funerariadelzulia.com/sitemap.php`.

## 7. Después del corte

| Cuándo | Tarea |
|---|---|
| El mismo día | **Borrar el ensayo**: cPanel → Dominios → eliminar `nuevo.funerariadelzulia.com`; borrar la carpeta `public_html/nuevo.funerariadelzulia.com/` (comparte la base de datos con el sitio final: si se deja, alguien podría editar ahí y subir fotos que el sitio final no ve); su registro DNS en Cloudflare y su `redirect_uri` en PF (`json_remove` sobre `redirect_uris_json`). |
| El mismo día | Panel → Configuración: cargar el `G-…` de Analytics y el botón "Previsión (FDZ)" (`https://app.previsionfuneraria.com/?empresa=fdz`; hoy está vacío en el ensayo). |
| Primera semana | Search Console: enviar `https://www.funerariadelzulia.com/sitemap.php`, inspeccionar la portada y un obituario, y vigilar "Páginas" (404 y redirecciones). |
| Primera semana | Compartir un obituario en Facebook y WhatsApp y revisar la vista previa (depurador de enlaces de Facebook). |
| Primera semana | Regenerar las capturas del manual (`docs/manual/capturar-manual.mjs` contra el dominio final). |
| Opcional | Si se publicaron enlaces de `legadoholding.com/funerzul/…` (por ejemplo `/o/…` en redes), dejar en la cuenta de Legado una carpeta `public_html/funerzul/` con solo un `.htaccess` que redirija con 301 a `https://www.funerariadelzulia.com/` conservando la ruta. |
| A las 2-4 semanas | cPanel → Errores: URLs viejas que siguen dando 404, para agregarlas a la sección 5. |
| A los 60-90 días | Borrar `public_html/_wp-anterior/` y la base de WordPress, conservando el respaldo de la sección 3. |
| Al cerrar | Actualizar `README.md` (rutas de cron y despliegue), `docs/SPEC.md`, `docs/BACKLOG.md` y `config.example.php`. Simplificar `.cpanel.yml` (quitar la rama de Legado y la del ensayo). |

## 8. Historia

- 2026-10-28: plan original. El sitio estaba en prueba en la cuenta de Legado
  (`legadoholding.com/funerzul/`) y había que mover base, fotos y repo entre
  cuentas. Ese traslado se hizo durante el ensayo en `nuevo.funerariadelzulia.com`
  (IP real de los visitantes detrás de Cloudflare verificada ahí el 2026-10-28).
- 2026-11-02: corte autorizado; plan reescrito para la cuenta única de FDZ.
  Se agregaron el riesgo de Wordfence, el paso a paso para mover WordPress, la
  redirección sin www → www en `.htaccess` y el `noindex` de la carpeta del ensayo
  vista desde el dominio principal.
