<?php
/** Sitemap XML dinámico (páginas principales + cada obituario activo). */
require __DIR__ . '/api/lib/public_init.php';
header('Content-Type: application/xml; charset=utf-8');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

function sm_url(string $loc, ?string $lastmod = null, string $freq = 'weekly', string $prio = '0.6'): void
{
    echo "  <url><loc>" . htmlspecialchars($loc, ENT_QUOTES) . "</loc>";
    if ($lastmod) echo "<lastmod>" . $lastmod . "</lastmod>";
    echo "<changefreq>$freq</changefreq><priority>$prio</priority></url>\n";
}

sm_url(site_url('index.html'), null, 'daily', '1.0');
sm_url(site_url('obituarios.php'), null, 'daily', '0.9');
sm_url(site_url('directorio-medico.php'), null, 'weekly', '0.8');
sm_url(site_url('recursos.php'), null, 'weekly', '0.8');

$rows = db()->query(
    "SELECT slug, id, updated_at FROM obituaries
     WHERE status='active' AND deleted_at IS NULL
     ORDER BY death_date DESC LIMIT 5000"
)->fetchAll();

foreach ($rows as $r) {
    $slug = $r['slug'] ?: (string)$r['id'];
    $loc  = site_url('obituario.php?slug=' . urlencode($slug));
    $mod  = $r['updated_at'] ? date('Y-m-d', strtotime($r['updated_at'])) : null;
    sm_url($loc, $mod, 'monthly', '0.7');
}

// Médicos del directorio
foreach (db()->query(
    "SELECT slug, id, updated_at FROM doctors
     WHERE status='active' AND deleted_at IS NULL
     ORDER BY updated_at DESC LIMIT 5000"
)->fetchAll() as $r) {
    $slug = $r['slug'] ?: (string)$r['id'];
    $loc  = site_url('medico.php?slug=' . urlencode($slug));
    $mod  = $r['updated_at'] ? date('Y-m-d', strtotime($r['updated_at'])) : null;
    sm_url($loc, $mod, 'monthly', '0.6');
}

// Recursos de lectura
foreach (db()->query(
    "SELECT slug, id, updated_at FROM articles
     WHERE status='active' AND deleted_at IS NULL
     ORDER BY COALESCE(published_at, created_at) DESC LIMIT 5000"
)->fetchAll() as $r) {
    $slug = $r['slug'] ?: (string)$r['id'];
    $loc  = site_url('recurso.php?slug=' . urlencode($slug));
    $mod  = $r['updated_at'] ? date('Y-m-d', strtotime($r['updated_at'])) : null;
    sm_url($loc, $mod, 'monthly', '0.6');
}

echo '</urlset>';
