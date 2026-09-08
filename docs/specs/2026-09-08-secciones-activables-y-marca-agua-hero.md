# Secciones activables/desactivables + marca de agua del ángel en el hero

## Resumen

Dos pedidos del usuario, sin relación entre sí, implementados en la misma
sesión:

1. **Marca de agua**: en el hero de la portada (`index.php`, banda azul
   "Desde 1942..."), agregar el ángel del logo (`img/angel-original-vector.svg`)
   como fondo decorativo, sin distorsión, detrás del texto.
2. **Secciones activables**: el admin debe poder activar/desactivar desde el
   panel las secciones Obituarios, Directorio Médico, Recursos de Lectura y
   Preguntas Frecuentes. Al desactivar una, **debe desaparecer del sitio**
   (no solo del menú).

## Alcance

Incluye:

- CSS/HTML: `styles.css`, `index.php` (marca de agua del hero).
- Nueva función `site_section_enabled(string $section): bool` en
  `api/lib/helpers.php`, respaldada por 4 claves booleanas en `app_settings`
  (`section_obituarios_enabled`, `section_directorio_medico_enabled`,
  `section_recursos_enabled`, `section_faqs_enabled`), todas con default
  `true` (fail-open: nunca se oculta contenido por una falla de BD/config,
  solo por decisión explícita del admin).
- Migración `database/11_secciones_toggle.sql` (`INSERT IGNORE`, sin tocar
  el esquema — la tabla `app_settings` ya existe).
- `api/settings.php`: las 4 claves entran a `EDITABLE_SETTINGS`.
- Admin: `admin.html` (4 interruptores nuevos en Configuración, bajo
  "Secciones del sitio") + `admin.js` (`loadSettings`/`saveSettings`).
- Sitio público, todos los puntos donde una sección aparece o se enlaza:
  - Navegación y pie de página: `index.php` (copia propia del header/footer,
    ver `README.md` sobre esta duplicación preexistente) y
    `partials/site_header.php` / `partials/site_footer.php` (usados por el
    resto de páginas).
  - Portada: sección `#obituarios`, sección `#preguntas`, y las dos tarjetas
    de "Acompañamiento en el Duelo" que enlazan a Recursos/Directorio Médico.
  - Páginas propias de cada sección: `obituarios.php`, `obituario.php`,
    `directorio-medico.php`, `medico.php`, `recursos.php`, `recurso.php` —
    si la sección está desactivada, responden 404 con un mensaje "Sección no
    disponible" en vez de servir el contenido (mismo patrón ya usado para
    "obituario no encontrado", etc.).
  - `sitemap.php`: no lista URLs de una sección desactivada.

No incluye:

- Tocar `api/obituaries.php`, `api/doctors.php`, `api/articles.php`,
  `api/faqs.php` (CRUD) — el admin sigue pudiendo gestionar el contenido de
  una sección aunque esté desactivada de cara al público; el toggle es
  puramente de visibilidad pública.
- Previsión/planes/servicios — no están en el alcance pedido.
- Un selector de color/tamaño configurable para la marca de agua del hero —
  quedó fijo en CSS (celeste claro a baja opacidad, tamaño relativo al alto
  del hero).

## Reglas De Negocio

- Todas las secciones empiezan **activas** (`1`) — nunca se instala el sitio
  con algo oculto por defecto.
- Fail-open: si `api/config.php` falta o la consulta a `app_settings` falla,
  `site_section_enabled()` devuelve `true` (nunca se cae accidentalmente el
  contenido por un problema de infraestructura).
- El toggle es solo de **visibilidad pública** — no afecta el panel admin
  (el editor/admin sigue viendo y gestionando Obituarios, Directorio Médico,
  Recursos y FAQs desde sus pestañas normalmente, estén o no visibles al
  público).
- Página directa a una sección desactivada → HTTP 404 con mensaje "Sección
  no disponible" (mismo look que los 404 existentes de item-no-encontrado).

## Riesgos

- Duplicación de nav/footer entre `index.php` y `partials/site_header.php`/
  `site_footer.php` (preexistente, no introducida por este cambio): hay que
  mantener los 3 lugares sincronizados a mano si se agrega una sección más
  adelante. Documentado también en `README.md`.
- El checkbox del admin depende de que la migración `11_secciones_toggle.sql`
  se haya importado; mientras no lo esté, `admin.js` asume `true` si la
  clave no viene en la respuesta de `settings.php?action=get` (mismo default
  fail-open que el backend), así que la UI y el sitio público quedan
  consistentes igual sin la migración — pero el interruptor no persiste
  hasta que exista la fila (se crea sola al primer guardado).

## Plan

1. `api/lib/helpers.php`: `site_section_enabled()`.
2. `database/11_secciones_toggle.sql` + `api/settings.php` (`EDITABLE_SETTINGS`).
3. `admin.html` + `admin.js`: interruptores.
4. `partials/site_header.php`, `partials/site_footer.php`: nav/footer condicional.
5. `index.php`: variables `$secObituarios`/`$secDirectorioMedico`/
   `$secRecursos`/`$secFaqs`, nav, footer, secciones de portada, tarjetas de
   duelo, y marca de agua del hero (`.hero-angel-watermark`, CSS `mask-image`
   sobre `img/angel-original-vector.svg`).
6. Guardas 404 en `obituarios.php`, `obituario.php`, `directorio-medico.php`,
   `medico.php`, `recursos.php`, `recurso.php`.
7. `sitemap.php`: excluir URLs de secciones desactivadas.
8. Documentación: este archivo, `README.md` (manual, sección 10),
   `api/README.md`, `database/README.md`.

## Verificación

- `php -l` sobre los 13 archivos PHP tocados — sin errores de sintaxis.
- No se pudo probar contra MySQL real en esta sesión (sin credenciales
  locales configuradas) — pendiente de que el usuario verifique en local o
  en el servidor de pruebas: activar cada interruptor desde Configuración y
  confirmar que la sección correspondiente desaparece de nav/footer/portada
  y que su página directa muestra "Sección no disponible" (404), y que
  reactivarla la devuelve exactamente como estaba.
- Visual: revisar el hero en desktop y móvil (breakpoint 768px) para
  confirmar que la marca de agua del ángel no compite con el texto ni se ve
  distorsionada.

## Documentación A Actualizar

- [x] `README.md`
- [x] `api/README.md`
- [x] `database/README.md`
- Otro: este documento.
