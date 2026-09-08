# Tarjetas de obituario: plantillas web + imagen descargable para compartir

## Resumen

El usuario compartió dos formatos de tarjeta que Funeraria del Zulia ya usa
hoy (diseñados a mano en Canva/Photoshop, fuera del sistema) para anunciar
un fallecimiento por WhatsApp/redes: una tarjeta oscura tipo "cinta de
duelo" y una esquela tradicional blanca con la invitación al velatorio.
Pedido: adaptar ambos formatos como plantillas del sistema — tanto para la
**página web** del obituario como para una **imagen descargable** lista
para compartir — con la foto del difunto como **opción**, nunca obligatoria.

## Alcance

Incluye:

- Dos plantillas nuevas en el sistema existente de "Plantillas de
  obituario" (`obituary_templates`, HTML+CSS con marcadores) para
  `obituario.php`: **Cinta Conmemorativa** y **Esquela Familiar**.
- Marcador nuevo `{{photo_optional}}` (`api/lib/render.php`): a diferencia
  de `{{photo}}` (siempre imprime algo, foto real o el placeholder del
  logo), este solo imprime la etiqueta `<img>` si el obituario tiene una
  foto real subida — así la foto queda genuinamente opcional en las
  plantillas que la usan.
- Generador de imagen PNG para compartir (`api/lib/obituary_card.php` +
  endpoint `api/obituary_card.php`), con los mismos dos diseños, en formato
  1080×1920 (historia). Puro GD — sin Imagick, sin SVG (el hosting cPanel
  solo garantiza GD) — con las fuentes reales del sitio (Playfair Display /
  Inter, SIL OFL) en `assets/fonts/`.
- Botones de descarga por fila en la tabla de Obituarios del panel admin.

No incluye:

- Un editor visual para diseñar tarjetas nuevas — los dos diseños quedan
  fijos en código (como las 3 plantillas web originales); crear una tercera
  requiere tocar `api/lib/obituary_card.php`, no es configurable desde el
  panel.
- Recorte exacto pixel-por-pixel de las dos imágenes de referencia que
  compartió el usuario (diseños hechos a mano en Canva/Photoshop) — se
  **adaptaron** en código (mismos elementos: cinta, cruz, esquema de
  colores, estructura del texto), no se trazaron literalmente. Ver
  "Decisiones de diseño" abajo.

## Decisiones de diseño

- **Sin Imagick ni rasterizado de SVG.** El hosting solo garantiza GD (ver
  `README.md`, "Extensiones PHP"). La marca de agua del ángel usa
  `logo-seal-footer.png` (PNG existente, con transparencia) en la imagen
  descargable, y el propio `img/angel-original-vector.svg` vía CSS
  `mask-image` en la página web (misma técnica que el hero — ver
  `docs/specs/2026-09-08-secciones-activables-y-marca-agua-hero.md`).
- **Fuentes reales, no las del sistema del servidor.** GD necesita un
  archivo `.ttf` real para `imagettftext()`. Se descargaron las variables
  de Playfair Display (+ itálica) e Inter directo del repo oficial de
  Google Fonts (licencia SIL Open Font License, `assets/fonts/OFL-*.txt`) —
  son las mismas familias que ya usa el sitio por CSS (`--font-display`,
  `--font-sans`), así que la tarjeta descargada se ve consistente con la
  web. Son fuentes variables (no hay pesos estáticos en el repo oficial);
  GD las renderiza en su instancia por defecto (peso regular) — aceptable,
  el tamaño grande del nombre compensa la falta de negrita real.
- **La cinta/ribbon es una forma geométrica simple** (rectángulo rotado en
  CSS y en GD), no una réplica exacta del lazo doblado de la imagen de
  referencia — mantiene el espíritu del diseño sin depender de curvas
  complejas que no se pueden clonar de una foto sin una herramienta de
  edición de imágenes.
- **La cruz se dibuja geométricamente** (dos rectángulos en GD, `✝` como
  texto en CSS) en vez de confiar en que la fuente tenga el glifo Unicode
  de cruz — no todas las fuentes lo incluyen.

## Reglas de negocio

- La foto del difunto es **siempre opcional** en ambas plantillas/tarjetas
  — se muestra únicamente si el obituario tiene una foto real (no el
  placeholder, no una purgada). Nunca se fuerza.
- La tarjeta descargable no depende de que el obituario esté "activo" — el
  staff puede generarla para un borrador antes de publicarlo.
- El texto de las plantillas usa los campos ya existentes del obituario
  (`biography`, `event_schedule`, `location_name`) para el contenido libre
  (familiares, horario de velatorio/sepelio) — no se agregó ningún campo
  nuevo a la tabla `obituaries`.

## Riesgos

- **Nombres muy largos** pueden desbordar el ancho de la tarjeta en la
  imagen PNG; `obit_card_cinta()` reduce el tamaño de fuente si el nombre
  necesita más de 2 líneas, pero un nombre extremadamente largo igual puede
  verse apretado — aceptable para el caso normal, revisar manualmente casos
  extremos.
- **Fuentes variables sin negrita real** (ver arriba) — si más adelante
  hace falta un titular realmente en negrita, hay que sustituir los `.ttf`
  por versiones estáticas Bold (mismo directorio `assets/fonts/`, mismo
  nombre de variable en `oc_font()`).
- **`.cpanel.yml`** ahora copia `assets/` al desplegar — sin este cambio,
  las fuentes no llegarían al servidor y la tarjeta descargable fallaría en
  producción (la página web no se vería afectada, solo la descarga).

## Revisión 2026-09-08 (mismo día): "Cinta Conmemorativa" pasa a usar la imagen real

El usuario probó el resultado en el servidor y no se parecía lo suficiente
a su referencia — la cinta/cruz dibujadas a mano en CSS/GD (rectángulos
rotados) se veían visiblemente peor que el diseño real hecho en Canva.
Subió los dos archivos de referencia (`img/WhatsApp Image 2026-08-20 at
21.00.5{7,8}.jpeg`, borrados del repo tras usarlos — no son contenido
público, son material de referencia con datos de una persona específica).

Cambio para **"Cinta Conmemorativa"** únicamente (la "Esquela Familiar" ya
era fiel al no depender de una forma difícil de replicar como el lazo):

- Se recortó el tercio superior de esa imagen (logo, cinta y cruz reales,
  sin texto de nombre — `img/obit-cinta-header.png`, 900×745) y se usa
  como imagen de fondo real, tanto en CSS (`.obit-cinta-header`) como en
  GD (`oc_header_image()`), en vez de dibujar la cinta con
  `imagefilledpolygon`/`clip-path`.
- El navy del resto de la tarjeta (`#041D31`) se tomó con cuentagotas
  (`imagecolorat()`) de la propia imagen de referencia — empalme sin
  costura entre la foto recortada y el relleno sólido de abajo.
- "Esquela Familiar" recibió un ajuste menor: el nombre pasa de serif
  (Playfair) a sans-serif en negrita (Inter), y el navy de la cruz/marco a
  `#0B2A54` — más cerca del tono real de la referencia.
- Migración nueva `database/13_plantilla_cinta_imagen_real.sql`: `UPDATE`
  sobre la fila id=4 (no se edita `12_plantillas_esquela.sql`, puede que ya
  esté aplicada) — quita el `<div class="obit-cinta-cross">` del
  `body_html` porque la cruz ya viene en la imagen. Seguro correrla aunque
  la 12 todavía no se haya aplicado (no encuentra la fila y no hace nada).

## Plan

1. `api/lib/render.php`: marcador `{{photo_optional}}`.
2. `database/12_plantillas_esquela.sql`: siembra las 2 plantillas web
   nuevas en `obituary_templates` (ids 4 y 5, `is_default = 0`).
3. `styles.css`: `.obit-cinta` / `.obit-esquela` (y su marca de agua CSS).
4. `assets/fonts/`: Playfair Display + Inter (variables, SIL OFL) +
   licencias.
5. `api/lib/obituary_card.php`: compositor GD (fondo, cinta/cruz, marca de
   agua, texto con salto de línea automático, foto circular opcional).
6. `api/obituary_card.php`: endpoint de descarga (staff, `admin`/`editor`).
7. `admin.js`: botones "Tarjeta ✝" / "Tarjeta 📰" por fila de obituario;
   marcador `{{photo_optional}}` agregado a la ayuda del formulario de
   plantillas.
8. `.cpanel.yml`: agrega `assets` a la lista de despliegue.
9. Documentación: este archivo, `README.md`, `api/README.md`,
   `database/README.md`.

## Verificación

- `php -l` sobre los 3 archivos PHP nuevos/editados — sin errores.
- Generación real probada en local (PHP CLI + GD, `gd_info()` confirma
  FreeType activo): las dos plantillas se renderizaron correctamente con
  datos de muestra basados en las imágenes de referencia del usuario
  (texto, saltos de línea, cruz, cinta, marca de agua, foto circular
  opcional con y sin foto) — capturas revisadas visualmente antes de
  finalizar el diseño.
- Pendiente que el usuario confirme en el servidor real (no se pudo probar
  contra MySQL de producción en esta sesión): importar
  `database/12_plantillas_esquela.sql`, asignar una de las 2 plantillas
  nuevas a un obituario de prueba desde el panel, confirmar que
  `obituario.php` se ve bien, y descargar ambas tarjetas desde la tabla de
  Obituarios.

## Documentación a actualizar

- [x] `README.md`
- [x] `api/README.md`
- [x] `database/README.md`
- Otro: este documento.
