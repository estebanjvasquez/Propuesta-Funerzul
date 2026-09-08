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

/** Color sólido desde hex ('#1F4E79' o '1F4E79'). */
function oc_color($img, string $hex)
{
    $hex = ltrim($hex, '#');
    [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    return imagecolorallocate($img, $r, $g, $b);
}

/** Color con transparencia (0 = opaco, 100 = invisible). */
function oc_color_alpha($img, string $hex, int $transparentPct)
{
    $hex = ltrim($hex, '#');
    [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    $alpha = (int)round(127 * max(0, min(100, $transparentPct)) / 100);
    return imagecolorallocatealpha($img, $r, $g, $b, $alpha);
}

/** Degradado vertical simple entre dos colores hex. */
function oc_vertical_gradient($img, string $hexTop, string $hexBottom): void
{
    $w = imagesx($img); $h = imagesy($img);
    $hexTop = ltrim($hexTop, '#'); $hexBottom = ltrim($hexBottom, '#');
    [$r1, $g1, $b1] = [hexdec(substr($hexTop, 0, 2)), hexdec(substr($hexTop, 2, 2)), hexdec(substr($hexTop, 4, 2))];
    [$r2, $g2, $b2] = [hexdec(substr($hexBottom, 0, 2)), hexdec(substr($hexBottom, 2, 2)), hexdec(substr($hexBottom, 4, 2))];
    for ($y = 0; $y < $h; $y++) {
        $t = $y / max(1, $h - 1);
        $r = (int)($r1 + ($r2 - $r1) * $t);
        $g = (int)($g1 + ($g2 - $g1) * $t);
        $b = (int)($b1 + ($b2 - $b1) * $t);
        $c = imagecolorallocate($img, $r, $g, $b);
        imageline($img, 0, $y, $w, $y, $c);
    }
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

/** Rectángulo rotado (grados) relleno -- usado para la cinta conmemorativa. */
function oc_rotated_rect($img, float $cx, float $cy, float $w, float $h, float $deg, $color): void
{
    $rad = deg2rad($deg);
    $cos = cos($rad); $sin = sin($rad);
    $corners = [[-$w / 2, -$h / 2], [$w / 2, -$h / 2], [$w / 2, $h / 2], [-$w / 2, $h / 2]];
    $pts = [];
    foreach ($corners as [$dx, $dy]) {
        $pts[] = $cx + $dx * $cos - $dy * $sin;
        $pts[] = $cy + $dx * $sin + $dy * $cos;
    }
    imagefilledpolygon($img, $pts, $color);
}

/** Cinta doblada en la esquina superior derecha (evoca el lazo de luto). */
function oc_ribbon($img): void
{
    $white   = oc_color($img, '#F4F6F9');
    $shadow  = oc_color_alpha($img, '#0B1B2B', 55);
    oc_rotated_rect($img, OC_WIDTH - 210, 230, 640, 150, 35, $shadow);   // sombra sutil
    oc_rotated_rect($img, OC_WIDTH - 200, 220, 640, 150, 35, $white);
    oc_rotated_rect($img, OC_WIDTH - 200, 220, 640, 26, 35, oc_color($img, '#C7CFDA')); // línea de pliegue
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
    $ext = strtolower(pathinfo($photoAbsPath, PATHINFO_EXTENSION));
    $src = match ($ext) {
        'jpg', 'jpeg' => @imagecreatefromjpeg($photoAbsPath),
        'png'         => @imagecreatefrompng($photoAbsPath),
        'webp'        => @imagecreatefromwebp($photoAbsPath),
        default       => null,
    };
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
    return !empty($o['photo_path']) && empty($o['photo_purged']);
}

/** Plantilla "Cinta Conmemorativa" -- fondo azul marino, cinta blanca, cruz, marca de agua. */
function obit_card_cinta(array $o)
{
    $img = imagecreatetruecolor(OC_WIDTH, OC_HEIGHT);
    oc_vertical_gradient($img, '#1F4E79', '#122F4C');
    imagealphablending($img, true);
    imagesavealpha($img, true);

    oc_ribbon($img);
    oc_watermark_seal($img, (int)(OC_WIDTH / 2), 1460, 620, 92);

    $fPlayfair       = oc_font('PlayfairDisplay-Variable');
    $fPlayfairItalic = oc_font('PlayfairDisplay-Italic-Variable');
    $fInter          = oc_font('Inter-Variable');

    $gold  = oc_color($img, '#F2C572');
    $white = oc_color($img, '#FFFFFF');
    $mist  = oc_color($img, '#C3DAEE');

    oc_cross($img, 160, 300, 64, $gold);

    $y = 470;
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

    oc_text_center($img, 'Paz a su alma', $fPlayfairItalic, 40, $gold, $y);
    $y += 90;

    $dates = trim(($o['birth_year'] ? $o['birth_year'] . ' — ' : '') . ($o['death_date'] ?? ''));
    if ($dates !== '') oc_text_center($img, $dates, $fInter, 34, $mist, $y);

    return $img;
}

/** Plantilla "Esquela Familiar" -- fondo blanco, cruz, invitación al velatorio. */
function obit_card_esquela(array $o)
{
    $img = imagecreatetruecolor(OC_WIDTH, OC_HEIGHT);
    $white = oc_color($img, '#FFFFFF');
    $navy  = oc_color($img, '#1F4E79');
    $ink   = oc_color($img, '#191C1D');
    $muted = oc_color($img, '#5A5F66');
    imagefilledrectangle($img, 0, 0, OC_WIDTH, OC_HEIGHT, $white);
    imagesetthickness($img, 6);
    imagerectangle($img, 40, 40, OC_WIDTH - 41, OC_HEIGHT - 41, $navy);
    imagesetthickness($img, 1);

    $fPlayfair = oc_font('PlayfairDisplay-Variable');
    $fInter    = oc_font('Inter-Variable');

    oc_cross($img, (int)(OC_WIDTH / 2), 220, 88, $navy);

    $y = 380;
    $leadLines = oc_wrap_lines(
        'Participamos con profundo pesar el sensible fallecimiento de',
        $fInter, 32, OC_WIDTH - 260
    );
    $y = oc_text_block($img, $leadLines, $fInter, 32, $muted, $y, 46);
    $y += 60;

    $nameLines = oc_wrap_lines(mb_strtoupper($o['full_name'] ?? ''), $fPlayfair, 58, OC_WIDTH - 200);
    $y = oc_text_block($img, $nameLines, $fPlayfair, 58, $ink, $y, 82);
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
    oc_text_center($img, 'FUNERARIA DEL ZULIA', $fPlayfair, 34, $navy, $y);
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
        'esquela' => obit_card_esquela($o),
        default   => obit_card_cinta($o),
    };
}
