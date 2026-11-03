<?php
if (!defined('OBIT_APP')) { http_response_code(403); exit('Forbidden'); }

/**
 * Generador de tarjetas de obituario para compartir (PNG, formato historia
 * 1080x1920) — adapta en código los dos formatos que la funeraria ya usa
 * hoy (diseñados a mano en Canva/Photoshop): "cinta" (memorial oscuro con
 * cinta blanca, azul marino) y "esquela" (blanco, tradicional, con la
 * invitación al velatorio). Ver docs/specs/2026-09-08-tarjetas-obituario.md.
 *
 * Puro GD (sin Imagick, sin SVG) porque el hosting cPanel solo garantiza GD
 * -- las fuentes reales (Playfair Display / Inter, licencia SIL OFL) están
 * en assets/fonts/. El sello circular del logo (logo-seal-footer.png) hace
 * de marca de agua porque GD no puede rasterizar el SVG del ángel.
 *
 * La foto del difunto es SIEMPRE opcional (pedido explícito del usuario):
 * ninguna de las dos plantillas la fuerza -- obit_card_generate() solo la
 * dibuja si $o['photo_path'] es una foto real (no el placeholder purgado).
 */

const OC_WIDTH  = 1080;
const OC_HEIGHT = 1920;

function oc_font(string $name): string
{
    return __DIR__ . '/../../assets/fonts/' . $name . '.ttf';
}

/** "nacimiento — fallecimiento" (death_date ya viene formateada por el llamador). */
function oc_dates_text(array $o): string
{
    $birth = obit_birth_label($o);
    return trim(($birth !== '' ? $birth . ' — ' : '') . ($o['death_date'] ?? ''));
}

/**
 * Dibuja las fechas centradas en [x0, x0+width]. Con la fecha exacta de
 * nacimiento el texto puede no caber: primero achica la letra (hasta 80 %) y,
 * si aun así no cabe, la parte en dos líneas por el " — ". Devuelve el alto
 * extra usado (0 si fue una sola línea) para que el llamador corra lo de abajo.
 */
function oc_draw_dates($img, string $text, string $font, float $size, $color, float $y, int $x0, int $width): float
{
    if ($text === '') return 0;
    $fits = function (string $t, float $s) use ($font, $width): bool {
        $box = imagettfbbox($s, 0, $font, $t);
        return ($box[2] - $box[0]) <= $width;
    };
    for ($s = $size; $s >= $size * 0.8; $s -= 1) {
        if ($fits($text, $s)) { oc_text_center_in($img, $text, $font, $s, $color, $y, $x0, $width); return 0; }
    }
    $parts = explode(' — ', $text, 2);
    if (count($parts) < 2) { oc_text_center_in($img, $text, $font, $size * 0.8, $color, $y, $x0, $width); return 0; }
    $s = $size;
    while ($s > $size * 0.6 && !($fits($parts[0] . ' —', $s) && $fits($parts[1], $s))) $s -= 1;
    $lh = $s * 1.6;
    oc_text_center_in($img, $parts[0] . ' —', $font, $s, $color, $y, $x0, $width);
    oc_text_center_in($img, $parts[1], $font, $s, $color, $y + $lh, $x0, $width);
    return $lh;
}

/** Color sólido desde hex ('#1F4E79' o '1F4E79'). */
function oc_color($img, string $hex)
{
    $hex = ltrim($hex, '#');
    [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    return imagecolorallocate($img, $r, $g, $b);
}

/** Parte un texto en líneas que quepan en $maxWidth para esa fuente/tamaño. */
function oc_wrap_lines(string $text, string $font, float $size, int $maxWidth): array
{
    $words = preg_split('/\s+/u', trim($text)) ?: [];
    $lines = []; $current = '';
    foreach ($words as $w) {
        $try = $current === '' ? $w : $current . ' ' . $w;
        $box = imagettfbbox($size, 0, $font, $try);
        $width = $box[2] - $box[0];
        if ($width > $maxWidth && $current !== '') {
            $lines[] = $current;
            $current = $w;
        } else {
            $current = $try;
        }
    }
    if ($current !== '') $lines[] = $current;
    return $lines;
}

/** Dibuja una línea de texto centrada horizontalmente en $y (línea base). */
function oc_text_center($img, string $text, string $font, float $size, $color, float $y): void
{
    $canvasW = imagesx($img);
    $box = imagettfbbox($size, 0, $font, $text);
    $textW = $box[2] - $box[0];
    $x = ($canvasW - $textW) / 2 - $box[0];
    imagettftext($img, $size, 0, (int)$x, (int)$y, $color, $font, $text);
}

/** Dibuja varias líneas centradas, retorna la posición Y siguiente disponible. */
function oc_text_block($img, array $lines, string $font, float $size, $color, float $y, float $lineHeight): float
{
    foreach ($lines as $line) {
        oc_text_center($img, $line, $font, $size, $color, $y);
        $y += $lineHeight;
    }
    return $y;
}

/** Cruz simple (dos barras) centrada en ($cx,$cy). */
function oc_cross($img, int $cx, int $cy, int $size, $color): void
{
    $bar = max(4, (int)($size * 0.16));
    imagefilledrectangle($img, $cx - (int)($bar / 2), $cy - (int)($size / 2), $cx + (int)($bar / 2), $cy + (int)($size / 2), $color);
    imagefilledrectangle($img, $cx - (int)($size * 0.32), $cy - (int)($size * 0.18), $cx + (int)($size * 0.32), $cy - (int)($size * 0.18) + $bar, $color);
}

/** Carga un jpg/png/webp por extensión (helper compartido). */
function oc_load_image(string $path)
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return match ($ext) {
        'jpg', 'jpeg' => @imagecreatefromjpeg($path),
        'png'         => @imagecreatefrompng($path),
        'webp'        => @imagecreatefromwebp($path),
        default       => null,
    };
}

/**
 * Compone una imagen (p.ej. img/obit-cinta-header.png) a todo lo ancho del
 * canvas, alineada arriba, preservando su proporción original (nunca la
 * distorsiona). Devuelve el alto real que ocupó, para que el llamador sepa
 * dónde sigue el contenido de abajo.
 */
function oc_header_image($img, string $path): int
{
    $src = oc_load_image($path);
    if (!$src) return 0;
    $sw = imagesx($src); $sh = imagesy($src);
    $targetW = imagesx($img);
    $targetH = (int)round($targetW * $sh / $sw);
    imagecopyresampled($img, $src, 0, 0, 0, 0, $targetW, $targetH, $sw, $sh);
    imagedestroy($src);
    return $targetH;
}

/**
 * Compone el sello (logo-seal-footer.png) como marca de agua translúcida,
 * centrado en ($cx,$cy) con ancho $targetW. Reescala primero a baja
 * resolución (más rápido) y luego reduce el alfa de cada píxel.
 */
function oc_watermark_seal($img, int $cx, int $cy, int $targetW, int $transparentPct): void
{
    $sealPath = __DIR__ . '/../../logo-seal-footer.png';
    if (!is_file($sealPath)) return;
    $seal = @imagecreatefrompng($sealPath);
    if (!$seal) return;

    $sw = imagesx($seal); $sh = imagesy($seal);
    $targetH = (int)round($targetW * $sh / $sw);
    $resized = imagecreatetruecolor($targetW, $targetH);
    imagesavealpha($resized, true);
    imagealphablending($resized, false);
    imagefilledrectangle($resized, 0, 0, $targetW, $targetH, imagecolorallocatealpha($resized, 0, 0, 0, 127));
    imagealphablending($resized, true);
    imagecopyresampled($resized, $seal, 0, 0, 0, 0, $targetW, $targetH, $sw, $sh);
    imagedestroy($seal);

    // Reduce el alfa de cada píxel para que quede como marca de agua.
    imagealphablending($resized, false);
    $factor = max(0, min(100, $transparentPct)) / 100;
    for ($y = 0; $y < $targetH; $y++) {
        for ($x = 0; $x < $targetW; $x++) {
            $rgba = imagecolorsforindex($resized, imagecolorat($resized, $x, $y));
            if ($rgba['alpha'] >= 127) continue;
            $newAlpha = (int)round(127 - (127 - $rgba['alpha']) * (1 - $factor));
            $c = imagecolorallocatealpha($resized, $rgba['red'], $rgba['green'], $rgba['blue'], $newAlpha);
            imagesetpixel($resized, $x, $y, $c);
        }
    }
    imagealphablending($img, true);
    imagesavealpha($img, true);
    imagecopy($img, $resized, $cx - (int)($targetW / 2), $cy - (int)($targetH / 2), 0, 0, $targetW, $targetH);
    imagedestroy($resized);
}

/** Foto del difunto recortada en círculo, centrada en ($cx,$cy). */
function oc_circle_photo($img, string $photoAbsPath, int $cx, int $cy, int $diameter, $borderColor): void
{
    $src = oc_load_image($photoAbsPath);
    if (!$src) return;

    $sw = imagesx($src); $sh = imagesy($src);
    $side = min($sw, $sh);
    $sx = (int)(($sw - $side) / 2); $sy = (int)(($sh - $side) / 2);

    $circle = imagecreatetruecolor($diameter, $diameter);
    imagesavealpha($circle, true);
    imagealphablending($circle, false);
    imagefilledrectangle($circle, 0, 0, $diameter, $diameter, imagecolorallocatealpha($circle, 0, 0, 0, 127));
    imagealphablending($circle, true);
    imagecopyresampled($circle, $src, 0, 0, $sx, $sy, $diameter, $diameter, $side, $side);
    imagedestroy($src);

    // Máscara circular: fuera del círculo, alfa total.
    $transparent = imagecolorallocatealpha($circle, 0, 0, 0, 127);
    for ($y = 0; $y < $diameter; $y++) {
        for ($x = 0; $x < $diameter; $x++) {
            $dx = $x - $diameter / 2; $dy = $y - $diameter / 2;
            if (($dx * $dx + $dy * $dy) > ($diameter / 2) ** 2) {
                imagesetpixel($circle, $x, $y, $transparent);
            }
        }
    }

    imagealphablending($img, true);
    imagesavealpha($img, true);
    $r = (int)($diameter / 2) + 4;
    imagefilledellipse($img, $cx, $cy, ($r + 4) * 2, ($r + 4) * 2, $borderColor);
    imagecopy($img, $circle, $cx - (int)($diameter / 2), $cy - (int)($diameter / 2), 0, 0, $diameter, $diameter);
    imagedestroy($circle);
}

/** true solo si hay una foto real subida (no el placeholder ni una purgada). */
function oc_has_real_photo(array $o): bool
{
    return !empty($o['photo_path']) && empty($o['photo_purged'])
        && $o['photo_path'] !== get_setting('photo_placeholder_path', 'uploads/obituarios/_placeholder.webp');
}

/**
 * Plantilla "Cinta Conmemorativa" -- usa img/obit-cinta-header.png (recorte
 * de la tarjeta real de la funeraria: logo, cinta y cruz) como fondo del
 * tercio superior, en vez de dibujar la cinta a mano; el resto es el mismo
 * navy exacto (#041D31, tomado con cuentagotas de esa misma imagen), así el
 * empalme queda sin costura visible.
 */
function obit_card_cinta(array $o)
{
    $img = imagecreatetruecolor(OC_WIDTH, OC_HEIGHT);
    $navy = oc_color($img, '#041D31');
    imagefilledrectangle($img, 0, 0, OC_WIDTH, OC_HEIGHT, $navy);
    imagealphablending($img, true);
    imagesavealpha($img, true);

    $headerH = oc_header_image($img, __DIR__ . '/../../img/obit-cinta-header.png');
    oc_watermark_seal($img, (int)(OC_WIDTH / 2), (int)($headerH + (OC_HEIGHT - $headerH) * 0.68), 560, 92);

    $fPlayfair       = oc_font('PlayfairDisplay-Variable');
    $fPlayfairItalic = oc_font('PlayfairDisplay-Italic-Variable');
    $fInter          = oc_font('Inter-Variable');

    $gold  = oc_color($img, '#F2C572');
    $white = oc_color($img, '#FFFFFF');
    $mist  = oc_color($img, '#C3DAEE');

    $y = $headerH + 90;
    oc_text_center($img, 'En memoria de', $fPlayfairItalic, 42, $mist, $y);
    $y += 100;

    $nameLines = oc_wrap_lines(mb_strtoupper($o['full_name'] ?? ''), $fPlayfair, 66, OC_WIDTH - 200);
    if (count($nameLines) > 2) {
        // Nombres muy largos: reduce el tamaño en vez de partir en 3+ líneas.
        $nameLines = oc_wrap_lines(mb_strtoupper($o['full_name'] ?? ''), $fPlayfair, 52, OC_WIDTH - 160);
        $y = oc_text_block($img, $nameLines, $fPlayfair, 52, $white, $y, 76);
    } else {
        $y = oc_text_block($img, $nameLines, $fPlayfair, 66, $white, $y, 96);
    }
    $y += 30;

    if (oc_has_real_photo($o)) {
        $photoAbs = __DIR__ . '/../../' . ltrim($o['photo_path'], '/');
        if (is_file($photoAbs)) {
            oc_circle_photo($img, $photoAbs, (int)(OC_WIDTH / 2), (int)$y + 180, 340, $white);
            $y += 400;
        }
    }

    oc_text_center($img, 'Paz a su alma', $fPlayfairItalic, 58, $white, $y);
    $y += 110;

    oc_draw_dates($img, oc_dates_text($o), $fInter, 34, $mist, $y, 80, OC_WIDTH - 160);

    return $img;
}

/** Dorado de la cruz: mismas paradas que el degradado SVG de la plantilla web. */
function oc_gold_at(float $t): array
{
    $stops = [[0.0, [156, 114, 20]], [0.45, [247, 223, 160]], [0.55, [232, 196, 108]], [1.0, [156, 114, 20]]];
    for ($i = 1; $i < count($stops); $i++) {
        [$t1, $c1] = $stops[$i];
        if ($t <= $t1) {
            [$t0, $c0] = $stops[$i - 1];
            $k = ($t - $t0) / max(0.0001, $t1 - $t0);
            return array_map(fn($a, $b) => (int)round($a + ($b - $a) * $k), $c0, $c1);
        }
    }
    return $stops[count($stops) - 1][1];
}

/** Barra de la cruz con degradado dorado de izquierda a derecha. */
function oc_gold_bar($img, int $x1, int $y1, int $x2, int $y2): void
{
    $w = max(1, $x2 - $x1);
    for ($x = $x1; $x <= $x2; $x++) {
        [$r, $g, $b] = oc_gold_at(($x - $x1) / $w);
        imageline($img, $x, $y1, $x, $y2, imagecolorallocate($img, $r, $g, $b));
    }
}

/** Compone una imagen con alfa (p.ej. el logo) en ($x,$y) con ancho $w. */
function oc_place_image($img, string $path, int $x, int $y, int $w): void
{
    $src = oc_load_image($path);
    if (!$src) return;
    $h = (int)round($w * imagesy($src) / imagesx($src));
    imagecopyresampled($img, $src, $x, $y, 0, 0, $w, $h, imagesx($src), imagesy($src));
    imagedestroy($src);
}

/**
 * Plantilla "Cruz de Luz (Católica)" -- variante de la Cinta (mismo navy,
 * textos y sello de agua) con una cruz latina dorada sobre halo y rayos de
 * luz en vez de la cinta. Todo dibujado con GD (sin imagen de fondo). Ver
 * docs/specs/2026-11-02-plantillas-obituario-por-credo.md.
 */
function obit_card_catolica(array $o)
{
    $img = imagecreatetruecolor(OC_WIDTH, OC_HEIGHT);
    imagefilledrectangle($img, 0, 0, OC_WIDTH, OC_HEIGHT, oc_color($img, '#041D31'));
    imagealphablending($img, true);
    imagesavealpha($img, true);

    $cx = (int)(OC_WIDTH / 2); $cy = 430;

    // Rayos: 24 haces finos, apilados en 4 largos para que se desvanezcan hacia afuera.
    foreach ([640, 500, 360, 220] as $len) {
        $ray = imagecolorallocatealpha($img, 247, 223, 160, 121);
        for ($deg = 0; $deg < 360; $deg += 15) {
            $a1 = deg2rad($deg); $a2 = deg2rad($deg + 3);
            imagefilledpolygon($img, [
                $cx, $cy,
                (int)($cx + $len * cos($a1)), (int)($cy + $len * sin($a1)),
                (int)($cx + $len * cos($a2)), (int)($cy + $len * sin($a2)),
            ], $ray);
        }
    }
    // Halo: círculos concéntricos casi transparentes (más denso al centro).
    $halo = imagecolorallocatealpha($img, 232, 196, 108, 126);
    for ($r = 440; $r > 0; $r -= 8) imagefilledellipse($img, $cx, $cy, $r * 2, $r * 2, $halo);

    // Cruz latina: brillo alrededor y luego las dos barras doradas.
    $v = [$cx - 33, $cy - 250, $cx + 33, $cy + 250];      // vertical
    $h = [$cx - 140, $cy - 128, $cx + 140, $cy - 62];      // horizontal
    $glow = imagecolorallocatealpha($img, 247, 223, 160, 123);
    for ($g = 26; $g > 0; $g -= 2) {
        imagefilledrectangle($img, $v[0] - $g, $v[1] - $g, $v[2] + $g, $v[3] + $g, $glow);
        imagefilledrectangle($img, $h[0] - $g, $h[1] - $g, $h[2] + $g, $h[3] + $g, $glow);
    }
    oc_gold_bar($img, ...$v);
    oc_gold_bar($img, ...$h);

    oc_place_image($img, __DIR__ . '/../../logo-horizontal-white.png', 64, 64, 300);

    $headerH = 900;
    oc_watermark_seal($img, $cx, (int)($headerH + (OC_HEIGHT - $headerH) * 0.62), 560, 92);

    $fPlayfair       = oc_font('PlayfairDisplay-Variable');
    $fPlayfairItalic = oc_font('PlayfairDisplay-Italic-Variable');
    $fInter          = oc_font('Inter-Variable');
    $gold  = oc_color($img, '#F2C572');
    $white = oc_color($img, '#FFFFFF');
    $mist  = oc_color($img, '#C3DAEE');
    $soft  = oc_color($img, '#DDE6EE');

    $y = $headerH + 70;
    oc_text_center($img, 'En memoria de', $fPlayfairItalic, 42, $mist, $y);
    $y += 100;

    $nameLines = oc_wrap_lines(mb_strtoupper($o['full_name'] ?? ''), $fPlayfair, 66, OC_WIDTH - 200);
    if (count($nameLines) > 2) {
        $nameLines = oc_wrap_lines(mb_strtoupper($o['full_name'] ?? ''), $fPlayfair, 52, OC_WIDTH - 160);
        $y = oc_text_block($img, $nameLines, $fPlayfair, 52, $white, $y, 76);
    } else {
        $y = oc_text_block($img, $nameLines, $fPlayfair, 66, $white, $y, 96);
    }
    $y += 30;

    $hasPhoto = false;
    if (oc_has_real_photo($o)) {
        $photoAbs = __DIR__ . '/../../' . ltrim($o['photo_path'], '/');
        if (is_file($photoAbs)) {
            oc_circle_photo($img, $photoAbs, $cx, (int)$y + 160, 300, $gold);
            $y += 350;
            $hasPhoto = true;
        }
    }

    oc_text_center($img, 'Descanse en paz', $fPlayfairItalic, 58, $white, $y);
    $y += 100;

    $dates = oc_dates_text($o);
    if ($dates !== '') { $y += oc_draw_dates($img, $dates, $fInter, 34, $mist, $y, 80, OC_WIDTH - 160); $y += 80; }

    // Cita bíblica: se omite si con la foto ya no cabe en la tarjeta.
    $verse = oc_wrap_lines('«Yo soy la resurrección y la vida; el que cree en mí, aunque muera, vivirá.»', $fPlayfairItalic, 32, OC_WIDTH - 280);
    if ($y + 60 + count($verse) * 48 + 60 < OC_HEIGHT - 60) {
        imagefilledrectangle($img, $cx - 220, (int)$y, $cx + 220, (int)$y + 1, $gold);
        $y += 70;
        $y = oc_text_block($img, $verse, $fPlayfairItalic, 32, $soft, $y, 48);
        oc_text_center($img, 'JUAN 11, 25', $fInter, 22, $gold, $y + 10);
    } elseif (!$hasPhoto) {
        oc_text_center($img, 'FUNERARIA DEL ZULIA', $fInter, 22, $gold, OC_HEIGHT - 70);
    }

    return $img;
}

/** Plantilla "Esquela Familiar" -- fondo blanco, cruz, invitación al velatorio. */
function obit_card_esquela(array $o)
{
    $img = imagecreatetruecolor(OC_WIDTH, OC_HEIGHT);
    $white = oc_color($img, '#FFFFFF');
    $navy  = oc_color($img, '#0B2A54'); // tomado con cuentagotas de la tarjeta de referencia
    $ink   = oc_color($img, '#191C1D');
    $muted = oc_color($img, '#5A5F66');
    imagefilledrectangle($img, 0, 0, OC_WIDTH, OC_HEIGHT, $white);
    imagesetthickness($img, 6);
    imagerectangle($img, 40, 40, OC_WIDTH - 41, OC_HEIGHT - 41, $navy);
    imagesetthickness($img, 1);

    $fInter = oc_font('Inter-Variable');

    oc_cross($img, (int)(OC_WIDTH / 2), 220, 88, $navy);

    $y = 380;
    $leadLines = oc_wrap_lines(
        'Participamos con profundo pesar el sensible fallecimiento de',
        $fInter, 32, OC_WIDTH - 260
    );
    $y = oc_text_block($img, $leadLines, $fInter, 32, $muted, $y, 46);
    $y += 60;

    // Nombre en sans-serif negrita, igual que la tarjeta de referencia (no serif).
    $nameLines = oc_wrap_lines(mb_strtoupper($o['full_name'] ?? ''), $fInter, 52, OC_WIDTH - 200);
    $y = oc_text_block($img, $nameLines, $fInter, 52, $ink, $y, 74);
    // Fechas bajo el nombre (2026-11-03: la esquela no mostraba ni el año).
    $dates = oc_dates_text($o);
    if ($dates !== '') {
        $y -= 10; // oc_text_block() deja $y una línea de nombre más abajo
        $y += oc_draw_dates($img, $dates, $fInter, 30, $muted, $y, 100, OC_WIDTH - 200);
        $y += 30;
    }
    $y += 40;

    if (oc_has_real_photo($o)) {
        $photoAbs = __DIR__ . '/../../' . ltrim($o['photo_path'], '/');
        if (is_file($photoAbs)) {
            oc_circle_photo($img, $photoAbs, (int)(OC_WIDTH / 2), (int)$y + 170, 320, $navy);
            $y += 380;
        }
    }

    if (!empty($o['biography'])) {
        $bioLines = oc_wrap_lines($o['biography'], $fInter, 28, OC_WIDTH - 260);
        $bioLines = array_slice($bioLines, 0, 6); // no desbordar la tarjeta
        $y = oc_text_block($img, $bioLines, $fInter, 28, $ink, $y, 42);
        $y += 50;
    }

    oc_text_center($img, 'Invitamos al servicio velatorio en', $fInter, 28, $muted, $y);
    $y += 46;
    oc_text_center($img, 'FUNERARIA DEL ZULIA', $fInter, 32, $navy, $y);
    $y += 60;

    if (!empty($o['location_name'])) {
        oc_text_center($img, $o['location_name'], $fInter, 26, $muted, $y);
        $y += 40;
    }

    if (!empty($o['event_schedule'])) {
        $schedLines = oc_wrap_lines($o['event_schedule'], $fInter, 26, OC_WIDTH - 280);
        $schedLines = array_slice($schedLines, 0, 5);
        oc_text_block($img, $schedLines, $fInter, 26, $ink, $y, 38);
    }

    return $img;
}

/** Dispatcher: genera y devuelve la imagen GD para el estilo pedido. */
function obit_card_generate(array $o, string $style)
{
    return match ($style) {
        'esquela'  => obit_card_esquela($o),
        'catolica' => obit_card_catolica($o),
        default    => obit_card_cinta($o),
    };
}

/* ============================================================================
 * Imagen de vista previa para redes (Open Graph), 1200x630 horizontal --
 * la que muestran Facebook/WhatsApp/X al pegar el enlace del obituario.
 * Mismo estilo que la "Cinta": azul marino, sello como marca de agua, foto
 * en círculo solo si hay una foto real. Ver
 * docs/specs/2026-10-27-enlaces-redes-y-analitica.md.
 * ========================================================================== */

const OG_WIDTH  = 1200;
const OG_HEIGHT = 630;

/** Como oc_text_center(), pero centrado dentro de la franja [$x0, $x0 + $width]. */
function oc_text_center_in($img, string $text, string $font, float $size, $color, float $y, int $x0, int $width): void
{
    $box = imagettfbbox($size, 0, $font, $text);
    $x = $x0 + ($width - ($box[2] - $box[0])) / 2 - $box[0];
    imagettftext($img, $size, 0, (int)$x, (int)$y, $color, $font, $text);
}

/** Versión de la imagen OG: cambia cuando cambia algo que se dibuja en ella. */
function obit_og_version(array $o): string
{
    return substr(md5(implode('|', [
        $o['full_name'] ?? '', $o['birth_year'] ?? '', $o['birth_date'] ?? '', $o['death_date'] ?? '',
        oc_has_real_photo($o) ? ($o['photo_path'] ?? '') : '', 'og-v1',
    ])), 0, 10);
}

/** $o['death_date'] debe venir ya formateado (fmt_date_es) por el llamador. */
function obit_og_image(array $o)
{
    $img = imagecreatetruecolor(OG_WIDTH, OG_HEIGHT);
    imagefilledrectangle($img, 0, 0, OG_WIDTH, OG_HEIGHT, oc_color($img, '#041D31'));
    imagealphablending($img, true);
    imagesavealpha($img, true);

    $white = oc_color($img, '#FFFFFF');
    $mist  = oc_color($img, '#C3DAEE');
    $gold  = oc_color($img, '#F2C572');
    $fPlayfair       = oc_font('PlayfairDisplay-Variable');
    $fPlayfairItalic = oc_font('PlayfairDisplay-Italic-Variable');
    $fInter          = oc_font('Inter-Variable');

    // Franja de texto: a la derecha de la foto si la hay, si no, todo el ancho.
    $x0 = 80; $w = OG_WIDTH - 160;
    if (oc_has_real_photo($o)) {
        $photoAbs = __DIR__ . '/../../' . ltrim($o['photo_path'], '/');
        if (is_file($photoAbs)) {
            oc_circle_photo($img, $photoAbs, 260, (int)(OG_HEIGHT / 2), 340, $white);
            $x0 = 480; $w = OG_WIDTH - 480 - 60;
        }
    }
    oc_watermark_seal($img, $x0 + (int)($w / 2), (int)(OG_HEIGHT / 2), 420, 93);

    $y = 170;
    oc_text_center_in($img, 'En memoria de', $fPlayfairItalic, 34, $mist, $y, $x0, $w);
    $y += 82;

    $name = mb_strtoupper($o['full_name'] ?? '');
    $size = 56; $lh = 72;
    $lines = oc_wrap_lines($name, $fPlayfair, $size, $w);
    if (count($lines) > 2) { $size = 42; $lh = 56; $lines = array_slice(oc_wrap_lines($name, $fPlayfair, $size, $w), 0, 3); }
    foreach ($lines as $line) { oc_text_center_in($img, $line, $fPlayfair, $size, $white, $y, $x0, $w); $y += $lh; }

    $y += 6;
    oc_text_center_in($img, 'Paz a su alma', $fPlayfairItalic, 34, $white, $y, $x0, $w);
    $y += 58;
    oc_draw_dates($img, oc_dates_text($o), $fInter, 24, $mist, $y, $x0, $w);

    oc_text_center_in($img, 'FUNERARIA DEL ZULIA', $fInter, 20, $gold, OG_HEIGHT - 42, $x0, $w);
    return $img;
}
