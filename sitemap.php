<?php
/** Sitemap XML dinámico (páginas principales + cada obituario activo). */
require __DIR__ . '/api/lib/public_init.php';
header('Content-Type: application/xml; charset=utf-8');

// Última vez que se tocó el contenido/plantillas de las páginas estáticas
// (portada, servicios, planes, crematorios). Bajar la mano si se vuelven a
// editar -- no hace falta que sea la fecha de hoy en cada despliegue.
const SITEMAP_STATIC_LASTMOD = '2026-09-09';

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

/** $image: URL absoluta de una foto real (no el placeholder) -- opcional, SEO de imágenes. */
function sm_url(string $loc, ?string $lastmod = null, string $freq = 'weekly', string $prio = '0.6', ?string $image = null): void
{
    echo "  <url><loc>" . htmlspecialchars($loc, ENT_QUOTES) . "</loc>";
    if ($lastmod) echo "<lastmod>" . $lastmod . "</lastmod>";
    echo "<changefreq>$freq</changefreq><priority>$prio</priority>";
    if ($image) echo "<image:image><image:loc>" . htmlspecialchars($image, ENT_QUOTES) . "</image:loc></image:image>";
    echo "</url>\n";
}

sm_url(site_url('index.php'), SITEMAP_STATIC_LASTMOD, 'daily', '1.0');
if (site_section_enabled('obituarios')) sm_url(site_url('obituarios.php'), SITEMAP_STATIC_LASTMOD, 'daily', '0.9');
if (site_section_enabled('directorio_medico')) sm_url(site_url('directorio-medico.php'), SITEMAP_STATIC_LASTMOD, 'weekly', '0.8');
if (site_section_enabled('recursos')) sm_url(site_url('recursos.php'), SITEMAP_STATIC_LASTMOD, 'weekly', '0.8');
sm_url(site_url('crematorios-del-zulia.php'), SITEMAP_STATIC_LASTMOD, 'monthly', '0.8');

// Servicios
sm_url(site_url('servicios/'), SITEMAP_STATIC_LASTMOD, 'monthly', '0.9');
foreach (['sepelio-tradicional', 'cremacion', 'traslados', 'capillas-velatorias'] as $s) {
    sm_url(site_url('servicios/' . $s . '.php'), SITEMAP_STATIC_LASTMOD, 'monthly', '0.8');
}

// Planes de previsión
sm_url(site_url('planes/'), SITEMAP_STATIC_LASTMOD, 'monthly', '0.9');
foreach (['plan-esencial', 'plan-tradicion', 'plan-vanguardia', 'plan-vanguardia-plus'] as $p) {
    sm_url(site_url('planes/' . $p . '.php'), SITEMAP_STATIC_LASTMOD, 'monthly', '0.8');
}

if (site_section_enabled('obituarios')) {
    $rows = db()->query(
        "SELECT slug, id, updated_at, photo_path, photo_purged FROM obituaries
         WHERE status='active' AND deleted_at IS NULL
         ORDER BY death_date DESC LIMIT 5000"
    )->fetchAll();

    foreach ($rows as $r) {
        $slug = $r['slug'] ?: (string)$r['id'];
        $loc  = site_url('obituario.php?slug=' . urlencode($slug));
        $mod  = $r['updated_at'] ? date('Y-m-d', strtotime($r['updated_at'])) : null;
        // Solo foto real (no el placeholder) -- misma regla que {{photo_optional}}.
        $img  = (!empty($r['photo_path']) && empty($r['photo_purged'])) ? site_url($r['photo_path']) : null;
        sm_url($loc, $mod, 'monthly', '0.7', $img);
    }
}

// Médicos del directorio
if (site_section_enabled('directorio_medico')) {
    foreach (db()->query(
        "SELECT slug, id, updated_at, photo_path FROM doctors
         WHERE status='active' AND deleted_at IS NULL
         ORDER BY updated_at DESC LIMIT 5000"
    )->fetchAll() as $r) {
        $slug = $r['slug'] ?: (string)$r['id'];
        $loc  = site_url('medico.php?slug=' . urlencode($slug));
        $mod  = $r['updated_at'] ? date('Y-m-d', strtotime($r['updated_at'])) : null;
        $img  = !empty($r['photo_path']) ? site_url($r['photo_path']) : null;
        sm_url($loc, $mod, 'monthly', '0.6', $img);
    }
}

// Recursos de lectura
if (site_section_enabled('recursos')) {
    foreach (db()->query(
        "SELECT slug, id, updated_at, cover_path FROM articles
         WHERE status='active' AND deleted_at IS NULL
         ORDER BY COALESCE(published_at, created_at) DESC LIMIT 5000"
    )->fetchAll() as $r) {
        $slug = $r['slug'] ?: (string)$r['id'];
        $loc  = site_url('recurso.php?slug=' . urlencode($slug));
        $mod  = $r['updated_at'] ? date('Y-m-d', strtotime($r['updated_at'])) : null;
        $img  = !empty($r['cover_path']) ? site_url($r['cover_path']) : null;
        sm_url($loc, $mod, 'monthly', '0.6', $img);
    }
}

echo '</urlset>';
