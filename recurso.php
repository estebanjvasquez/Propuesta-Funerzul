<?php
/**
 * Página pública individual de un recurso/artículo (server-rendered para SEO/GEO).
 * URL:  recurso.php?slug=...
 */
require __DIR__ . '/api/lib/public_init.php';

$slug = $_GET['slug'] ?? '';
$a = $slug !== '' ? get_public_article($slug) : null;

if (!$a) {
    http_response_code(404);
    $PAGE = ['title' => 'Recurso no encontrado | Funeraria del Zulia', 'description' => 'El artículo solicitado no está disponible.'];
    require __DIR__ . '/partials/site_header.php';
    echo '<main class="container section"><div class="section-header"><h2>Recurso no encontrado</h2><p>El artículo que busca no está disponible o fue retirado.</p></div><div style="text-align:center"><a class="btn btn-primary" href="recursos.php">Ver todos los recursos</a></div></main>';
    require __DIR__ . '/partials/site_footer.php';
    exit;
}

// Contador de vistas
try { db()->prepare("UPDATE articles SET view_count = view_count + 1 WHERE id = ?")->execute([$a['id']]); } catch (\Throwable $e) {}

$canonical = site_url('recurso.php?slug=' . urlencode($a['slug'] ?: (string)$a['id']));
$title     = $a['title'] . ' | Funeraria del Zulia';
$plain     = trim(preg_replace('/\s+/', ' ', strip_tags($a['content'] ?? '')));
$desc      = $a['meta_description'] ?: ($a['excerpt'] ?: mb_substr($plain, 0, 155));
$cover     = !empty($a['cover_path']) ? $a['cover_path'] : null;
$pubDate   = !empty($a['published_at']) ? substr((string)$a['published_at'], 0, 10) : substr((string)$a['created_at'], 0, 10);
$modDate   = !empty($a['updated_at']) ? substr((string)$a['updated_at'], 0, 10) : $pubDate;

// JSON-LD (Article) para buscadores e IA
$jsonld = [
    '@context'      => 'https://schema.org',
    '@type'         => 'Article',
    'headline'      => $a['title'],
    'description'   => $desc,
    'url'           => $canonical,
    'datePublished' => $pubDate,
    'dateModified'  => $modDate,
    'author'        => ['@type' => 'Organization', 'name' => 'Funeraria del Zulia', 'url' => site_url()],
    'publisher'     => ['@type' => 'Organization', 'name' => 'Funeraria del Zulia', 'url' => site_url()],
];
if ($cover) $jsonld['image'] = site_url($cover);
if (!empty($a['category'])) $jsonld['articleSection'] = $a['category'];

$head = '<meta name="robots" content="index, follow">'
    . '<meta property="og:type" content="article">'
    . '<meta property="og:title" content="' . esc($title) . '">'
    . '<meta property="og:description" content="' . esc($desc) . '">'
    . ($cover ? '<meta property="og:image" content="' . esc(site_url($cover)) . '">' : '')
    . '<meta property="og:url" content="' . esc($canonical) . '">'
    . '<script type="application/ld+json">' . json_encode($jsonld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';

$PAGE = ['title' => $title, 'description' => $desc, 'canonical' => $canonical, 'head' => $head];
require __DIR__ . '/partials/site_header.php';
?>
<main class="container obit-detail-main section">
    <nav class="breadcrumb"><a href="index.php">Inicio</a> › <a href="recursos.php">Recursos</a> › <span><?= esc($a['title']) ?></span></nav>

    <article class="obit-detail resource-detail">
        <?php if (!empty($a['category'])): ?><span class="resource-category"><?= esc($a['category']) ?></span><?php endif; ?>
        <h1 class="resource-detail-title"><?= esc($a['title']) ?></h1>
        <p class="resource-detail-date"><?= esc(fmt_date_es($pubDate)) ?></p>
        <?php if ($cover): ?><img src="<?= esc($cover) ?>" alt="<?= esc($a['title']) ?>" class="resource-detail-cover" loading="lazy"><?php endif; ?>
        <div class="resource-detail-body"><?= $a['content'] ?></div>

        <div class="obit-detail-actions">
            <a class="btn btn-outline" href="recursos.php">← Volver a recursos</a>
        </div>
    </article>
</main>
<?php require __DIR__ . '/partials/site_footer.php'; ?>
