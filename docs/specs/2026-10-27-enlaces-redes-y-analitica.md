---
Estado: **Aprobada e implementada (2026-10-27)** — pendiente de probar en el servidor.
---

# Enlaces cortos de obituarios para redes, vista previa social y analítica

## 1. Qué se pidió

La funeraria publica los obituarios también en sus redes. El usuario aprobó
(2026-10-27): un enlace corto por obituario para los posts, sin servicio pago;
poder medir los clics por red; una buena vista previa al pegar el enlace en
Facebook/WhatsApp; e instalar Google Analytics 4 (y dejar listo Search Console).

## 2. Diagnóstico previo (verificado en vivo)

- La vista previa social estaba rota: `og:image`/`og:url` salen como
  `https://www.https://legadoholding.com/...` por el `site_url` mal configurado
  en el `config.php` del servidor (pendiente desde el 2026-09-09). **Sin
  corregirlo, nada de esto se verá bien en redes.**
- `og:image` era la imagen genérica del logo, no algo del obituario.
- No había ninguna analítica instalada.
- Search Console **no** mide clics desde redes (solo búsquedas de Google): la
  medición de redes se hace con el contador propio y con UTM + GA4.

## 3. Diseño

### 3.1 Enlace corto: `/o/{id}` y `/o/{id}/{red}`

- `.htaccess` reescribe internamente `o/45/fb` → `o.php?id=45&s=fb`.
- Redes válidas: `fb` (Facebook), `wa` (WhatsApp), `ig` (Instagram — solo
  biografía/historias, Instagram no permite enlaces en publicaciones), `otro`.
  Sin red (o una desconocida) = `directo`.
- `o.php` busca el obituario **activo** por id, cuenta el clic y responde
  **302** (no 301: un 301 queda en caché del navegador y los clics siguientes
  no se contarían) a `obituario.php?slug=…` con
  `utm_source=<red>&utm_medium=social&utm_campaign=obituarios&utm_content=<id>`
  (sin UTM para `directo`). La ruta de destino se arma desde la carpeta real
  del script, no desde `site_url`, para funcionar igual en `/funerzul/` y en el
  dominio final.
- Si el obituario no existe, no está activo o la sección está apagada: 302 a
  `obituarios.php` (o a la portada si la sección está apagada).

### 3.2 Contador de clics

- Tabla `obituary_link_clicks (obituary_id, source, click_date, clicks)` con
  clave primaria compuesta: **un contador agregado por obituario, red y día**.
  No guarda IP, navegador ni ningún dato personal; no usa cookies.
- No se cuentan los robots ni los generadores de vista previa
  (`facebookexternalhit`, `WhatsApp`, `Twitterbot`, `*bot*`, `crawler`,
  `curl`…): si no, pegar el enlace en un chat ya sumaría clics.
- Un fallo al contar nunca impide la redirección.
- `GET api/obituaries.php?action=link_stats&id=` (staff): total y últimos 30
  días por red.

### 3.3 Imagen de vista previa (Open Graph) 1200×630

- `obituario_og.php?id=45&v=<hash>` (público, solo obituarios activos) genera
  con GD (mismas fuentes y estilo que la tarjeta "Cinta") una imagen
  horizontal: fondo azul marino, sello como marca de agua, "En memoria de",
  nombre, fechas y la foto en círculo si hay una foto real.
- Se cachea en `cache/og/` (la carpeta `cache/` ya niega acceso web directo);
  `v` cambia cuando cambian nombre, fechas o foto, así Facebook/WhatsApp toman
  la nueva. Si no se puede escribir la caché, se genera al vuelo igual.
- `obituario.php` publica `og:image` (con `og:image:width/height/alt`) apuntando
  a esa imagen.

### 3.4 Panel

- Botón **"Redes"** en cada fila de obituarios: modal con el enlace corto por
  red (botón copiar), aviso si el obituario no está activo, vista previa de la
  imagen y tabla de clics (total / 30 días) por red.

### 3.5 Google Analytics 4 y Search Console

- Dos ajustes en Configuración → "Analítica (Google)" (solo admin):
  `ga4_measurement_id` (formato `G-XXXXXXX`) y `google_site_verification`
  (código del meta tag de Search Console). Vacíos por defecto = no se inyecta
  nada. Formato validado en `api/settings.php` (422 si no cumple).
- `partials/analytics.php` imprime el `gtag.js` y/o el meta de verificación en
  el `<head>` de todas las páginas públicas (`site_header.php` e `index.php`).
  **Nunca en `admin.html`.** Falla cerrado si la BD no responde.

## 4. Archivos

- Nuevos: `o.php`, `obituario_og.php`, `partials/analytics.php`,
  `database/17_enlaces_redes_y_analitica.sql`.
- Modificados: `.htaccess`, `.cpanel.yml` (desplegar `o.php` y
  `obituario_og.php`), `.gitignore` (`cache/og/`), `obituario.php`,
  `api/lib/obituary_card.php`, `api/lib/helpers.php`, `api/obituaries.php`,
  `api/settings.php`, `admin.html`, `admin.js`, `index.php`,
  `partials/site_header.php`, `styles.css` y documentación.

## 5. Antes de usarlo en redes

1. Corregir `site_url` en el `config.php` del servidor (sin `https://www.`
   duplicado). En el dominio final: `https://www.funerariadelzulia.com`.
2. Importar `database/17_enlaces_redes_y_analitica.sql` y desplegar.
3. Crear la propiedad de GA4 (gratis, analytics.google.com), copiar el ID
   `G-…` en Configuración. Para Search Console, elegir verificación por
   "Etiqueta HTML" y pegar solo el valor de `content`.
4. Empezar a publicar enlaces **cuando el sitio esté en el dominio final**:
   los enlaces de `legadoholding.com/funerzul/` dejarán de funcionar al mudarse
   salvo que se dejen redirecciones.

## 6. Fuera de alcance

- Banner de consentimiento de cookies para GA4 (Venezuela no lo exige como la
  UE; reconsiderar si se apunta a público europeo).
- Que el botón "Compartir" público use el enlace corto (posible mejora: red
  `comp`).
