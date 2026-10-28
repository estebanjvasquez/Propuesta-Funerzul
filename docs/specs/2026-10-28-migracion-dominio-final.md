---
Estado: **Aprobado (2026-10-28)**, en preparación. Decisiones en la sección 3.
Objetivo: el sitio nuevo en línea **antes del 2 de noviembre de 2026** (fecha
exacta del corte por confirmar). Calendario en la sección 3.1. Lo marcado
"(verificar)" no se puede ver desde fuera del servidor.
---

# Migración al dominio definitivo `www.funerariadelzulia.com`

Mover el sitio nuevo (PHP + MySQL, hoy en prueba en
`https://www.legadoholding.com/funerzul/`) a `https://www.funerariadelzulia.com/`,
donde hoy está el **WordPress** del cliente. Ambos están en el mismo servidor
compartido con cPanel.

## 1. Lo que ya se sabe (revisado en vivo el 2026-10-28)

| Tema | Estado actual |
|---|---|
| DNS | `funerariadelzulia.com` pasa por **Cloudflare** (proxy activo; IPs 188.114.96.5/97.5). El apex redirige 301 a `www`. |
| WordPress | Páginas: `/`, `/nosotros/`, `/preguntas-frecuentes/`, `/familias-protegidas/`, `/crematorios-del-zulia/`, `/tienda/`, `/carrito/`, `/finalizar-compra/`, `/mi-cuenta/` (WooCommerce), `/sample-page/`; 10 testimonios en `/testimonial/…` y `/category/testimonial/`. Sitemap de Yoast/RankMath en `/sitemap_index.xml`. |
| Google | WordPress tiene **Site Kit by Google** con la etiqueta `GT-PJ5S7WTQ`: ya existe una propiedad de Google (Analytics y probablemente Search Console). **Se reutiliza**, no se crea otra. |
| Sitio nuevo | Código en git; despliegue por cPanel Git (`.cpanel.yml` → `/home/legadoholding/public_html/funerzul`). Base MySQL `legadoholding_obituarios`. Fotos en `uploads/` (**no están en git**). |
| Inicio de sesión único | Cliente `fdz-sitio-508a4bc` registrado en Previsión Funeraria con la `redirect_uri` del sitio de prueba. |

## 2. ¿Hay que generar un cliente nuevo de Previsión Funeraria?

**No.** Se agrega la dirección nueva al **mismo** cliente y se cambia
`redirect_uri` en `api/config.php`. El `client_id` y el `client_secret` no
cambian:

```sql
-- prevision-control (remoto), antes del corte:
UPDATE oauth_clients
  SET redirect_uris_json = json_insert(redirect_uris_json, '$[#]',
      'https://www.funerariadelzulia.com/api/sso.php?action=callback')
  WHERE client_id = 'fdz-sitio-508a4bc';
```

Durante la transición el cliente acepta las dos direcciones. La de prueba se
quita en la fase 4. El enlace "Previsión (FDZ)" y el Portal de Clientes apuntan
a `app.previsionfuneraria.com` y no cambian. **No** llenar `tenants.dominio` de
`fdz` en PF: ese campo es para servir PF bajo otro dominio, no aplica aquí.

## 3. Decisiones (respondidas por el usuario el 2026-10-28)

| # | Pregunta | Respuesta | Consecuencia |
|---|---|---|---|
| D1 | ¿Misma cuenta de cPanel que `legadoholding.com`? | **No, cuentas separadas.** | La base de datos **sí se mueve**: exportar en la cuenta de Legado e importar en una base nueva de la cuenta de FDZ (1.3). Las fotos se pasan por descarga/subida, porque el Administrador de archivos no cruza cuentas (1.4). El repositorio se clona también en cPanel → Git de la cuenta de FDZ (1.2). |
| D2 | ¿Dominio principal o adicional? | **Principal** (raíz fija `public_html`). | El corte mueve WordPress a `public_html/_wp-anterior/` y despliega el sitio nuevo en `public_html/` (fase 3). |
| D3 | ¿WooCommerce tiene pedidos reales? | **No, la tienda no funcionaba.** | Nada que exportar; sus URLs redirigen a `/planes/`. |
| D4 | Páginas sin equivalente | **Crear "Nosotros" y "Testimonios"**, encendibles y administrables desde el panel. | Hecho: `nosotros.php`, bloque de testimonios en portada y Nosotros, pestaña Testimonios del panel (`docs/specs/2026-10-28-nosotros-y-testimonios.md`, migración 19). |
| D5 | Fecha del corte | Aún sin fecha; objetivo **antes del 2 de noviembre**. | Calendario 3.1. |

### 3.1 Calendario propuesto (objetivo: en línea el 1 de noviembre)

| Día | Qué |
|---|---|
| **Mié 29-oct** | Fase 0 completa: respaldos de WordPress y del sitio de prueba, versión de PHP de la cuenta de FDZ, acceso a Cloudflare, ID `G-…` de Analytics, Search Console a verificación DNS. En el sitio de prueba: desplegar e importar las migraciones 18 y 19, revisar Nosotros y Testimonios. |
| **Jue 30-oct** | Fase 1 en la cuenta de FDZ: base de datos nueva + importación de ensayo, clonar el repo en cPanel → Git, `config.php`, fotos, subdominio `nuevo.funerariadelzulia.com` (fase 2) y pruebas completas. |
| **Vie 31-oct** | Correcciones de lo que salga del ensayo. Validación del cliente sobre el subdominio. Avisar al personal la hora del corte. |
| **Sáb 1-nov (noche)** | Fase 3, el corte. Ventana de 1 h. |
| **Dom 2-nov** | Fase 4, primer día: revisar redirecciones, errores 404, Search Console y que el panel funcione con el personal. |

Si algo del ensayo del jueves no queda resuelto el viernes, el corte se mueve:
WordPress sigue en línea sin ningún cambio mientras tanto.

## 4. Fase 0 — Inventario y respaldos (sin tocar nada)

1. **Respaldo completo de WordPress**: en cPanel → Respaldos, descargar los
   archivos de su raíz y exportar su base de datos (phpMyAdmin → Exportar). Guardar 90 días.
2. **Respaldo del sitio nuevo**: exportar `legadoholding_obituarios` y comprimir
   `public_html/funerzul/uploads/` y `api/config.php`.
3. Responder D1-D5.
4. **PHP del dominio** (cPanel → Seleccionar versión de PHP / MultiPHP): 8.1 o
   superior con `pdo_mysql`, `gd` (con WebP), `curl`, `mbstring`, igual que la
   carpeta de prueba.
5. **Cloudflare** (la zona la administra Esteban):
   - SSL/TLS en **Full (strict)**, con certificado válido en el origen (AutoSSL de cPanel).
   - Anotar las reglas de caché existentes, Rocket Loader y la minificación de JS.
6. **IP real de los visitantes detrás de Cloudflare** (verificar). Si
   `REMOTE_ADDR` llega con la IP de Cloudflare, el límite de intentos del login de
   emergencia agruparía a todos bajo la misma IP. Comprobarlo con `api/diag.php`
   en el subdominio de pruebas (fase 2). Si pasa, hay dos salidas: que el hosting
   active `mod_remoteip` para Cloudflare, o un cambio pequeño de código que confíe
   en `CF-Connecting-IP` solo cuando la petición viene de rangos de Cloudflare.
7. **Correo**: el cambio de sitio no toca los registros MX. Confirmar que existe el
   buzón de `notify_email` (`contacto@funerariadelzulia.com`) y que el SPF del
   dominio autoriza al servidor, porque `mail()` sale con remitente del dominio
   (`api/lib/mail.php`).
8. **Google**: entrar a Analytics con la cuenta que conectó Site Kit y anotar el
   **ID de medición `G-…`** del flujo web (la etiqueta `GT-…` lo agrupa). En
   Search Console, ver cómo está verificada la propiedad. Si la verificación la
   puso Site Kit con meta tag, se pierde al quitar WordPress: pasar antes a una
   **propiedad de dominio verificada por DNS** en Cloudflare.

## 5. Fase 1 — Preparar el destino (en paralelo, el WordPress sigue en línea)

### 1.1 Carpeta de ensayo

En la cuenta de FDZ, crear `/home/funerariadelzuli/fdz-nuevo/` (fuera de
`public_html`, para no mezclarla con WordPress). Sirve para el subdominio de
ensayo (fase 2). En el corte, el sitio se despliega en `public_html/`.

### 1.2 Código (cPanel → Git de la cuenta de FDZ)

1. En la cuenta de FDZ: cPanel → **Git Version Control** → Crear → clonar
   `https://github.com/estebanjvasquez/Propuesta-Funerzul.git`, rama
   `feature/prevision-funeraria`. El repo es público, así que no hace falta
   llave de acceso.
2. `.cpanel.yml` tiene la ruta de la cuenta de Legado
   (`/home/legadoholding/public_html/funerzul`). Para el ensayo, cambiar
   `DEPLOYPATH` a `/home/funerariadelzuli/fdz-nuevo` (cambio en el repo). En el
   corte, a `/home/funerariadelzuli/public_html`. Desde ese momento la cuenta de
   Legado deja de recibir despliegues; su carpeta `funerzul/` pasa a ser solo
   redirección (fase 4).
3. Desplegar con "Deploy HEAD Commit".

### 1.3 Base de datos (cuentas separadas: se mueve)

1. Cuenta de FDZ → **Bases de datos MySQL**: crear la base (p. ej.
   `funerariadelzuli_sitio`; cPanel antepone el usuario de la cuenta) y un usuario con contraseña fuerte, con **todos los
   privilegios** sobre esa base.
2. **Ensayo (jueves):** en la cuenta de Legado, phpMyAdmin →
   `legadoholding_obituarios` → Exportar (rápido, SQL). En la cuenta de FDZ,
   phpMyAdmin → base nueva → Importar. Comprobar que estén todas las tablas
   (`users`, `obituaries`, `condolences`, `faqs`, `testimonials`,
   `app_settings`, `auth_throttle`…).
3. **Corte:** con el panel de prueba congelado, repetir la exportación y la
   importación. Antes de importar, vaciar la base nueva con phpMyAdmin →
   seleccionar todas las tablas → Eliminar, para no mezclar datos.
4. Desde el corte, la base de la cuenta de Legado no se vuelve a usar. Se
   conserva un mes como respaldo y luego se borra.

### 1.4 Fotos y archivos fuera de git

En la cuenta de Legado, Administrador de archivos → `public_html/funerzul/uploads/`
→ **Comprimir** (zip) → descargar. En la cuenta de FDZ, subir el zip a la raíz
del sitio y **Extraer**. Debe quedar `uploads/obituarios/…`, y `uploads/` y
`cache/` con permiso de escritura (755). `cache/` no se copia: se regenera sola
(miniaturas OG, catálogo de PF). En el corte se repite con lo subido después del
ensayo (o se vuelve a copiar todo; son pocos MB).

### 1.5 `api/config.php` de la carpeta nueva

Copiar el de prueba y cambiar solo esto:

| Clave | Valor nuevo |
|---|---|
| `app.site_url` | `https://www.funerariadelzulia.com` |
| `app.env` | `production` |
| `security.allowed_origins` | `https://www.funerariadelzulia.com`, `https://funerariadelzulia.com` |
| `pf_sso.redirect_uri` | `https://www.funerariadelzulia.com/api/sso.php?action=callback` |
| `db` | Base, usuario y contraseña nuevos de la cuenta de FDZ (1.3) |
| `paths` | Sin cambios: son relativos a la carpeta del sitio |

### 1.6 Redirecciones de las URLs de WordPress (**hecho** en `.htaccess`, 2026-10-28)

Para no perder posicionamiento ni enlaces compartidos:

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

### 1.7 Previsión Funeraria

Ejecutar el `UPDATE` de la sección 2. Si se usa un subdominio de pruebas (fase
2), agregar también su `redirect_uri` y quitarla al terminar.

### 1.8 Cron de purga de fotos

En cPanel → Cron, cambiar la ruta a
`/usr/local/bin/php /home/funerariadelzuli/public_html/api/cron/purge_photos.php`
(cuenta de FDZ).
Hacerlo en el corte; nunca dejar los dos activos.

## 6. Fase 2 — Ensayo en un subdominio de pruebas (recomendado)

1. Crear `nuevo.funerariadelzulia.com` en cPanel apuntando a la carpeta nueva,
   más su registro DNS en Cloudflare (con proxy) y AutoSSL.
2. Con un `config.php` temporal (`site_url` del subdominio), probar:
   - portada, planes, servicios, obituarios, directorio, recursos y FAQ;
   - formulario de solicitud de plan → llega a PF;
   - condolencia → moderación;
   - "Entrar con Previsión Funeraria" (con la `redirect_uri` del subdominio registrada);
   - cuenta de emergencia con `?emergencia=1`;
   - subir una foto;
   - tarjeta PNG;
   - `/o/{id}/fb`;
   - `obituario_og.php`;
   - `api/diag.php` (IP real, punto 0.6).
3. Revisar el sitio con Cloudflare en medio: que no se rompa el JavaScript del
   panel y que no se cachee `admin.html`, `/api/*` ni `/o/*`. Si hace falta, crear
   una regla de caché "Bypass" para esas rutas.
4. Ponerle `noindex` al subdominio o borrarlo al terminar.

## 7. Fase 3 — Corte (ventana de ~30-60 min)

1. Avisar al personal; **congelar el panel de prueba** (no crear ni editar).
2. Respaldo de último momento de `public_html/` (WordPress) por si acaso.
3. Sincronización final de la base de datos (1.3, paso 3) y de `uploads/` (1.4).
4. **Cambiar lo que sirve el dominio** (dominio principal, `public_html` fija):
   1. En `public_html/` crear la carpeta `_wp-anterior/` con un `.htaccess` que
      diga `Require all denied`.
   2. Mover **todo el contenido de WordPress** (`wp-admin/`, `wp-content/`,
      `wp-includes/`, `wp-*.php`, `index.php`, `.htaccess`, `xmlrpc.php`,
      `license.txt`, etc.) a `_wp-anterior/`. **No mover** `.well-known/` (lo usa
      el certificado SSL) ni `cgi-bin/`.
   3. Cambiar en el repo `DEPLOYPATH` a `/home/funerariadelzuli/public_html`, hacer
      push y Deploy HEAD Commit en la cuenta de FDZ.
   4. Copiar a `public_html/` el `config.php` definitivo (en `api/`) y
      `uploads/` (1.4).
5. Cloudflare → **Purgar todo el caché**.
6. Cron de purga a la ruta nueva (1.8).
7. **Pruebas de humo** en `https://www.funerariadelzulia.com`:
   - portada, un obituario, `/planes/#comparar`;
   - entrar al panel con Previsión Funeraria y con la cuenta de emergencia;
   - publicar y despublicar un borrador de prueba;
   - las URLs viejas de la tabla 1.6;
   - `https://funerariadelzulia.com` → `www`;
   - `robots.txt` y `sitemap.php`.

### Vuelta atrás (~10 min)

1. Mover a una carpeta aparte los archivos del sitio nuevo que quedaron en
   `public_html/` (`index.php`, `.htaccess`, `api/`, …).
2. Devolver a `public_html/` el contenido de `_wp-anterior/`.
3. Purgar Cloudflare.

WordPress y su base no se modifican en ningún paso, así que la vuelta atrás es
completa. Lo que se haya cargado en el panel nuevo sigue en su base.

## 8. Fase 4 — Después del corte

| Cuándo | Tarea |
|---|---|
| El mismo día | **Sitio de prueba → redirección**: reemplazar `public_html/funerzul/` por un `.htaccess` que redirija todo con 301 a `https://www.funerariadelzulia.com/` conservando la ruta. Mantiene vivos los enlaces `/o/…` ya publicados en redes. Dejarlo al menos 6 meses. |
| El mismo día | Panel → Configuración: cargar el `G-…` de Analytics (fase 0.8). Revisar que el Portal de Clientes y "Previsión (FDZ)" sigan bien. |
| Primera semana | Search Console: enviar `https://www.funerariadelzulia.com/sitemap.php`, inspeccionar la portada y un obituario, y vigilar "Páginas" (404 y redirecciones). |
| Primera semana | Probar compartir un obituario en Facebook y WhatsApp (vista previa con imagen) y depurarlo en el depurador de enlaces de Facebook. |
| Primera semana | Regenerar las capturas del manual (`docs/manual/capturar-manual.mjs` contra el dominio final). |
| A las 2-4 semanas | Revisar en cPanel → Errores / registros de acceso las URLs viejas que siguen dando 404 y agregarlas a la tabla 1.6. |
| A las 4 semanas | Quitar de PF la `redirect_uri` de prueba (`json_remove` sobre `redirect_uris_json`). |
| A los 60-90 días | Borrar los archivos y la base de WordPress, conservando el respaldo de la fase 0. Desinstalar antes Site Kit no hace falta. |
| Al cerrar | Actualizar `README.md` (rutas de cron y despliegue), `docs/SPEC.md`, `docs/BACKLOG.md` y las URLs de `config.example.php`. Revisar si el sitio de Legado Holding enlaza a `legadoholding.com/funerzul/` y cambiarlo. |

## 9. Lo que el sitio ya resuelve solo (no requiere cambios)

- Todas las URLs absolutas (canónicas, og:url, sitemap, correos) salen de
  `app.site_url`. Las internas son relativas, y el `.htaccess` funciona igual en
  raíz que en subcarpeta.
- La sesión del panel es por dominio: el personal vuelve a entrar una vez.
- `robots.txt` ya apunta a `https://www.funerariadelzulia.com/sitemap.php`.
