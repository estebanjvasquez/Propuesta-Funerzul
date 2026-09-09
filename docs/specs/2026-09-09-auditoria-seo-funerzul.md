# Auditoría SEO de /funerzul/: correcciones de repo + mejoras

## Resumen

Auditoría técnica URL por URL del despliegue de prueba en
`https://legadoholding.com/funerzul/` (rama `feature/prevision-funeraria`),
pedida antes de anunciar el lanzamiento a buscadores. Se hizo lectura de
código + verificación en vivo con `curl` contra 34 URLs reales, tanto del
despliegue de prueba como de `www.funerariadelzulia.com` (dominio final
confirmado por el cliente, hoy todavía sirviendo el WordPress que este
proyecto reemplaza).

Este documento cubre la segunda ronda: aplicar en el repo los bugs y mejoras
encontrados que **no** requieren tocar `api/config.php` del servidor (fuera
de git). El bug más grave de todos -- `site_url` mal configurado en el
servidor, con `https://www.` pegado por delante de la URL de prueba, que
rompe canonical/OG/JSON-LD/sitemap en las ~30 páginas dinámicas -- **sigue
pendiente y debe corregirlo el cliente directamente en el servidor**; no es
un archivo del repo.

## Qué se corrigió (bugs)

- **`index.php` ya no hardcodea el dominio de producción.** Tenía 6 literales
  (`canonical`, `og:url`, `og:image`, y 3 campos del JSON-LD `FuneralService`)
  con `https://www.funerariadelzulia.com` escrito a mano -- correcto como
  destino final, pero roto mientras se prueba en otro host, e inconsistente
  con el resto del sitio (que ya usa `site_url()`). Se agregó `fz_site_url()`:
  usa `site_url()` si está disponible (config cargó) y cae al dominio de
  producción si no (index.php degrada sin BD por diseño, ver el bloque de
  `$secObituarios`/`$faqs` más arriba en el mismo archivo).
- **`/index.php` ya no compite con `/` como URL duplicada.** `.htaccess`:
  `RewriteRule ^index\.php$ / [R=301,L]`.
- **`/api/` ya no lista sus archivos.** `.htaccess`: `Options -Indexes`.
- **Búsquedas y filtros ya no se autodeclaran canónicas.**
  `obituarios.php` (`?q=`), `directorio-medico.php` (`?q=`/`?specialty=`) y
  `recursos.php` (`?q=`/`?category=`): cuando hay filtro activo, `meta
  robots` pasa a `noindex, follow` y el canonical apunta a la página limpia
  (sin filtro) en vez de repetir los parámetros de búsqueda.
- **Se quitó el enlace público a `admin.html` de la portada.** Vivía solo en
  el header propio de `index.php` (no en `partials/site_header.php`, que
  usan las otras 17 páginas) -- inconsistencia de plantilla y exposición
  innecesaria del panel desde la página más enlazada del sitio.
- **Falta un tercer y cuarto teléfono en el JSON-LD de la portada.** El
  footer ya mostraba los 4 números (emergencias ×2, previsión ×2); el
  `contactPoint` del JSON-LD `FuneralService` solo tenía 2. Se completaron
  los 4.

`robots.txt` **no se tocó**: ya apunta a `https://www.funerariadelzulia.com/sitemap.php`,
que es el dominio final confirmado -- correcto tal cual, sin cambios. El
único motivo por el que no coincide hoy es que se está probando en un host
distinto, algo inherente a cualquier entorno de staging.

## Qué se agregó (mejoras de SEO)

- **Imagen del hero auto-hospedada.** `.hero-bg` en `styles.css` cargaba
  desde una URL temporal de vista previa de Google
  (`lh3.googleusercontent.com/aida-public/…unnamed.png`, cabeceras con
  `filename="unnamed.png"` y caché de solo 24h) -- típico de una imagen
  generada por IA que nunca se bajó al repo. Es el LCP de toda la portada.
  Se descargó, se convirtió a WebP (283&nbsp;KB PNG → 13&nbsp;KB WebP con GD) y
  ahora vive en `img/hero-bg.webp`. La imagen fuente es de 512×512 -- se
  mantuvo la misma composición, sin rediseñar; si el equipo quiere un hero
  de mayor resolución más adelante, es un reemplazo directo de ese archivo.
- **Fuentes de Google ya no bloquean el render.** `styles.css` tenía
  `@import url(fonts.googleapis.com/...)` en la línea 6 -- obliga a una
  cadena en serie (CSS → hoja de fuentes → archivos de fuente). Se movió a
  `<link rel="preconnect">` + `<link rel="stylesheet">` en
  `partials/site_header.php` e `index.php` (mismo bloque en ambos, ya que
  `index.php` no pasa por el partial compartido).
- **Breadcrumb en JSON-LD en las 6 páginas dinámicas.** `obituario.php`,
  `medico.php`, `recurso.php` y sus 3 listados ya mostraban
  `<nav class="breadcrumb">` visible pero no lo declaraban en structured
  data -- se agregó `breadcrumb_jsonld()` (ya existía como helper, usado en
  `servicios/`, `planes/` y `crematorios-del-zulia.php`) a los 6.
- **`og:site_name` + `twitter:card` en todo el sitio.** Se agregaron a
  `page_head_meta()` (`api/lib/render.php`, cubre servicios/planes/
  crematorios automáticamente) y a los `$head` armados a mano en
  `index.php`, `obituario.php`, `medico.php` y `recurso.php`. Sin
  `twitter:title`/`description`/`image` explícitos: X usa los `og:*`
  correspondientes como respaldo cuando esas etiquetas no están.
- **Sitemap con extensión de imágenes + `lastmod` en páginas estáticas.**
  `sitemap.php`: `xmlns:image` en el `<urlset>`, `<image:image>` opcional
  por obituario/médico/artículo **con foto real** (se respeta la misma
  regla que `{{photo_optional}}`: nunca se anuncia el placeholder genérico
  como si fuera contenido). Las páginas estáticas (portada, servicios,
  planes, crematorios) ahora llevan `<lastmod>` con la constante
  `SITEMAP_STATIC_LASTMOD` -- subirla a mano la próxima vez que se edite
  contenido/plantillas de esas páginas.
- **Títulos y meta descriptions recortados.** Medido con caracteres reales
  antes de tocar nada: varios títulos pasaban de 60 (hasta 93) y varias
  descripciones de 155-160 (hasta 197). Se recortaron las 12 páginas
  estáticas (portada, servicios ×5, planes ×5, crematorios) a ~45-66
  caracteres de título y ~116-139 de descripción, conservando la palabra
  clave de ciudad/servicio y el "24 horas" donde aplicaba.

## Decisiones de diseño

- **`fz_site_url()` en vez de mover `index.php` al `site_header.php`
  compartido.** La alternativa más "limpia" -- unificar `index.php` sobre
  `partials/site_header.php` como las otras 17 páginas -- se descartó por
  riesgo: `index.php` tiene una estructura de `<head>`/hero propia y
  bastante particular (FAQPage generado desde BD, degradación sin config).
  Migrarlo es un cambio más grande que lo que pedía esta auditoría; queda
  como mejora futura si se justifica.
- **No se tocó `robots.txt`.** Ver arriba -- ya apunta al dominio final
  correcto, el desajuste actual es 100% un artefacto de probar en un host
  de staging, no un bug de código.
- **Imagen del hero: mismo diseño, solo re-hospedada.** No se generó una
  imagen nueva ni se le pidió al usuario un asset de mayor resolución --
  eso es una decisión de contenido/marca, no de código. Si se quiere
  cambiar la composición del hero más adelante, basta reemplazar
  `img/hero-bg.webp`.

## Pendiente (no aplicable desde el repo)

- **Crítico, sin resolver:** `api/config.php` en el servidor tiene
  `site_url` con `https://www.` pegado por delante de la URL de prueba --
  rompe canonical/OG/JSON-LD/sitemap en todas las páginas dinámicas. Debe
  corregirlo el cliente directo en el servidor (no está en git). Mientras
  se prueba en `legadoholding.com/funerzul/`, el valor correcto es
  `https://legadoholding.com/funerzul`; el día del lanzamiento a
  `funerariadelzulia.com`, un solo cambio de ese valor alcanza -- ya no
  hace falta tocar código, con `index.php` corregido en este mismo trabajo.

## Verificación

- `php -l` sobre los 21 archivos PHP tocados -- sin errores.
- No fue necesario ni se intentó conectar a MySQL local ni de producción
  para este trabajo (instrucción vigente del usuario) -- las correcciones
  no dependen de datos, solo de código/config.
- Pendiente que el usuario redepliegue (`cache-busting` ya subido a
  `styles.css?v=20260909-1`) y confirme visualmente: portada con el nuevo
  fondo de hero, breadcrumbs, y que las búsquedas dentro de Obituarios/
  Directorio Médico/Recursos ya no aparecen como `index` en el código
  fuente al filtrar.

## Documentación a actualizar

- [x] Este documento.
- Sin cambios en `README.md`/`docs/SPEC.md`/`database/README.md`: no hay
  tablas, endpoints ni flujos nuevos, solo correcciones de metadatos y
  hardening de configuración estática.
