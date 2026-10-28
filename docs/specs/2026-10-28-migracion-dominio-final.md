---
Estado: **Propuesta (2026-10-28)** — plan, nada ejecutado. Las casillas marcadas
"(verificar)" son datos que no se pueden ver desde fuera del servidor.
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

## 3. Decisiones antes de empezar

| # | Pregunta | Recomendación |
|---|---|---|
| D1 | ¿`funerariadelzulia.com` está en la **misma cuenta** de cPanel que `legadoholding.com` (dominio adicional) o en otra cuenta? | Define la base de datos (fase 1.3). Se ve en cPanel → Dominios. |
| D2 | ¿Es el dominio **principal** de su cuenta (raíz fija `public_html`) o un dominio adicional (raíz configurable)? | Define cómo se hace el corte (fase 3). |
| D3 | ¿WooCommerce tiene pedidos, clientes o pagos reales? | Si los tiene, exportarlos antes; hoy las ventas de planes van por Previsión Funeraria. |
| D4 | Páginas de WordPress sin equivalente (`/nosotros/`, testimonios) | Redirigirlas a la portada (tabla de la fase 1.6) o pedirle al cliente el contenido para una página nueva. |
| D5 | Fecha y hora del corte | De noche o fin de semana; avisar al personal que el panel queda congelado ~30 min. |

## 4. Fase 0 — Inventario y respaldos (sin tocar nada)

1. **Respaldo completo de WordPress**: en cPanel → Respaldos, descargar los
   archivos de su raíz y exportar su base de datos (phpMyAdmin → Exportar). Guardar 90 días.
2. **Respaldo del sitio nuevo**: exportar `legadoholding_obituarios` y comprimir
   `public_html/funerzul/uploads/` y `api/config.php`.
3. Responder D1-D5.
4. **PHP del dominio** (cPanel → Seleccionar versión de PHP / MultiPHP): 8.1 o
   superior con `pdo_mysql`, `gd` (con WebP), `curl`, `mbstring`, igual que la
   carpeta de prueba.
5. **Cloudflare** (verificar quién administra la zona):
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

### 1.1 Carpeta nueva

Crear la raíz del sitio nuevo **fuera** de la de WordPress, por ejemplo
`/home/<cuenta>/fdz-sitio/` (D2 decide si luego se apunta el dominio ahí o se
mueve el contenido a `public_html`).

### 1.2 Código

Cambio en el repo: `.cpanel.yml` con `DEPLOYPATH` a la carpeta nueva. Desplegar
desde cPanel → Git. Hasta el corte, la carpeta de prueba deja de recibir
despliegues; si hace falta mantenerla, se puede tener una tarea por cada destino.

### 1.3 Base de datos

- **Misma cuenta de cPanel (D1 = sí): no se mueve nada.** El sitio nuevo usa la
  misma base `legadoholding_obituarios` con el mismo usuario. Obituarios,
  condolencias, usuarios y ajustes quedan tal cual. El prefijo `legadoholding_` es
  solo un nombre.
- **Otra cuenta (D1 = no):** las bases son por cuenta.
  1. Crear base y usuario nuevos en la cuenta de FDZ.
  2. En el corte, con el panel congelado, exportar e importar.
  3. Actualizar `db` en `config.php`.
  4. No volver a escribir en la base vieja.

### 1.4 Fotos y archivos fuera de git

Copiar `public_html/funerzul/uploads/` a la carpeta nueva (Administrador de
archivos → Comprimir → Mover → Extraer), conservando los permisos de escritura
de `uploads/` y `cache/`. `cache/` no se copia: se regenera sola (miniaturas OG,
catálogo de PF).

### 1.5 `api/config.php` de la carpeta nueva

Copiar el de prueba y cambiar solo esto:

| Clave | Valor nuevo |
|---|---|
| `app.site_url` | `https://www.funerariadelzulia.com` |
| `app.env` | `production` |
| `security.allowed_origins` | `https://www.funerariadelzulia.com`, `https://funerariadelzulia.com` |
| `pf_sso.redirect_uri` | `https://www.funerariadelzulia.com/api/sso.php?action=callback` |
| `db` | Igual (D1 = sí) o la base nueva (D1 = no) |

### 1.6 Redirecciones de las URLs de WordPress (cambio en el repo, `.htaccess`)

Para no perder posicionamiento ni enlaces compartidos:

| URL de WordPress | Destino (301) |
|---|---|
| `/preguntas-frecuentes/` | `/#preguntas` |
| `/familias-protegidas/` | `/planes/` |
| `/crematorios-del-zulia/` | `/crematorios-del-zulia.php` |
| `/tienda/`, `/carrito/`, `/finalizar-compra/`, `/mi-cuenta/…`, `/producto/…` | `/planes/` |
| `/nosotros/`, `/testimonial/…`, `/category/…`, `/sample-page/`, `/feed/` | `/` (o la página nueva, según D4) |
| `/sitemap_index.xml`, `/wp-sitemap.xml`, `/*-sitemap.xml` | `/sitemap.php` |
| `/wp-admin/…`, `/wp-login.php`, `/xmlrpc.php` | `410 Gone`. Corta los bots que buscan WordPress. |

### 1.7 Previsión Funeraria

Ejecutar el `UPDATE` de la sección 2. Si se usa un subdominio de pruebas (fase
2), agregar también su `redirect_uri` y quitarla al terminar.

### 1.8 Cron de purga de fotos

En cPanel → Cron, cambiar la ruta a
`/usr/local/bin/php /home/<cuenta>/<raíz nueva>/api/cron/purge_photos.php`.
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
2. Sincronización final: base de datos (solo si D1 = no) y `uploads/` (copiar lo
   nuevo desde la última copia).
3. Poner los valores definitivos en `config.php` (sección 1.5) y desplegar la
   última versión.
4. **Cambiar lo que sirve el dominio** según D2:
   - **Dominio adicional:** cPanel → Dominios → `funerariadelzulia.com` →
     cambiar la raíz a la carpeta nueva. Es instantáneo y se revierte igual.
   - **Dominio principal (`public_html` fija):** mover el contenido de WordPress
     a `public_html/_wp-anterior/` (con un `.htaccess` `Require all denied`) y el
     sitio nuevo a `public_html/`. Ajustar `DEPLOYPATH` a `public_html`.
5. Cloudflare → **Purgar todo el caché**.
6. Cron de purga a la ruta nueva (1.8).
7. **Pruebas de humo** en `https://www.funerariadelzulia.com`:
   - portada, un obituario, `/planes/#comparar`;
   - entrar al panel con Previsión Funeraria y con la cuenta de emergencia;
   - publicar y despublicar un borrador de prueba;
   - las URLs viejas de la tabla 1.6;
   - `https://funerariadelzulia.com` → `www`;
   - `robots.txt` y `sitemap.php`.

### Vuelta atrás

- **Dominio adicional:** devolver la raíz a la carpeta de WordPress y purgar Cloudflare.
- **Dominio principal:** devolver los archivos de `_wp-anterior/`.

WordPress y su base no se tocan en ningún paso, así que la vuelta atrás es completa.
Lo que se haya cargado en el panel nuevo sigue en su base.

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
