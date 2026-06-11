<?php
if (!defined('OBIT_APP')) { http_response_code(403); exit('Forbidden'); }

/** htmlspecialchars corto. */
function esc($s): string { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }

/** Fecha larga en español (a partir de YYYY-MM-DD). */
function fmt_date_es(?string $d): string
{
    if (!$d) return '';
    $ts = strtotime($d);
    if (!$ts) return $d;
    $meses = [1=>'enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    return (int)date('j', $ts) . ' de ' . $meses[(int)date('n', $ts)] . ' de ' . date('Y', $ts);
}

/** Obtiene un obituario activo por slug (o id numérico). */
function get_public_obituary(string $slugOrId): ?array
{
    if (ctype_digit($slugOrId)) {
        $st = db()->prepare("SELECT * FROM obituaries WHERE id = ? AND status='active' AND deleted_at IS NULL");
        $st->execute([(int)$slugOrId]);
    } else {
        $st = db()->prepare("SELECT * FROM obituaries WHERE slug = ? AND status='active' AND deleted_at IS NULL");
        $st->execute([$slugOrId]);
    }
    return $st->fetch() ?: null;
}

/** URL pública de la foto (placeholder si fue purgada o no hay). */
function obit_photo_url(array $o): string
{
    if (!empty($o['photo_purged']) || empty($o['photo_path'])) {
        return get_setting('photo_placeholder_path', 'uploads/obituarios/_placeholder.webp');
    }
    return $o['photo_path'];
}

/** Carga la plantilla del obituario (la suya, o la predeterminada). */
function get_template_for(array $o): ?array
{
    if (!empty($o['template_id'])) {
        $st = db()->prepare("SELECT * FROM obituary_templates WHERE id = ? AND is_active = 1");
        $st->execute([$o['template_id']]);
        if ($row = $st->fetch()) return $row;
    }
    $row = db()->query("SELECT * FROM obituary_templates WHERE is_default = 1 AND is_active = 1 LIMIT 1")->fetch();
    return $row ?: null;
}

/** Renderiza el cuerpo del obituario usando su plantilla (reemplazo de marcadores). */
function render_obituary_html(array $o): string
{
    $photoUrl = obit_photo_url($o);
    $photoImg = '<img src="' . esc($photoUrl) . '" alt="Retrato de ' . esc($o['full_name']) . '" class="obit-detail-photo" loading="lazy">';
    $tpl = get_template_for($o);

    if (!$tpl) {
        // Layout por defecto si no hay plantilla
        return '<h1>' . esc($o['full_name']) . '</h1>'
            . '<p class="obit-detail-dates">' . esc($o['birth_year']) . ' — ' . esc(fmt_date_es($o['death_date'])) . '</p>'
            . '<p class="obit-qepd">Q.E.P.D.</p>' . $photoImg
            . '<p>' . nl2br(esc($o['biography'])) . '</p>';
    }

    $map = [
        '{{full_name}}'        => esc($o['full_name']),
        '{{birth_year}}'       => esc($o['birth_year']),
        '{{death_date}}'       => esc(fmt_date_es($o['death_date'])),
        '{{service_type}}'     => esc($o['service_type']),
        '{{location_name}}'    => esc($o['location_name']),
        '{{location_address}}' => esc($o['location_address']),
        '{{event_schedule}}'   => esc($o['event_schedule']),
        '{{biography}}'        => nl2br(esc($o['biography'])),
        '{{photo}}'            => $photoImg,
    ];
    $html = strtr($tpl['body_html'], $map);
    $css  = !empty($tpl['styles']) ? '<style>' . $tpl['styles'] . '</style>' : '';
    return $css . $html;
}

/** Condolencias aprobadas de un obituario. */
function approved_condolences(int $obituaryId): array
{
    $st = db()->prepare("SELECT author_name, message, created_at FROM condolences WHERE obituary_id = ? AND status='approved' ORDER BY created_at DESC");
    $st->execute([$obituaryId]);
    return $st->fetchAll();
}

/** Tarjeta pública de obituario (server-side) que enlaza al detalle. */
function render_public_card(array $o): string
{
    $url = 'obituario.php?slug=' . urlencode($o['slug'] ?: (string)$o['id']);
    $photo = obit_photo_url($o);
    $loc = $o['location_name'] ? '<li class="obituary-detail-item"><svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 110-5 2.5 2.5 0 010 5z"/></svg><div><span class="obituary-detail-label">' . esc($o['location_name']) . '</span><span class="obituary-detail-val">' . esc($o['location_address']) . '</span></div></li>' : '';
    return '<article class="obituary-card">'
        . '<a href="' . esc($url) . '" class="obituary-img-container" aria-label="Ver homenaje a ' . esc($o['full_name']) . '">'
        . '<img src="' . esc($photo) . '" alt="Retrato de ' . esc($o['full_name']) . '" class="obituary-img" loading="lazy">'
        . '<span class="obituary-badge">' . esc($o['service_type']) . '</span></a>'
        . '<div class="obituary-content">'
        . '<div class="obituary-dates">Q.E.P.D. &bull; Falleció el ' . esc(fmt_date_es($o['death_date'])) . '</div>'
        . '<h3 class="obituary-name"><a href="' . esc($url) . '">' . esc($o['full_name']) . '</a></h3>'
        . '<ul class="obituary-details-list">' . $loc . '</ul>'
        . '<div class="obituary-actions"><a class="btn btn-outline" href="' . esc($url) . '">Ver homenaje</a></div>'
        . '</div></article>';
}

/** URL absoluta del sitio. */
function site_url(string $path = ''): string
{
    $base = rtrim($GLOBALS['CONFIG']['app']['site_url'] ?? '', '/');
    return $base . '/' . ltrim($path, '/');
}
