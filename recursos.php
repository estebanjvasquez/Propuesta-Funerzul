<?php
/**
 * Página pública de Recursos de Lectura (artículos/guías, server-rendered para SEO/GEO).
 * URL:  recursos.php?page=1&q=...&category=...
 */
require __DIR__ . '/api/lib/public_init.php';

if (!site_section_enabled('recursos')) {
    http_response_code(404);
    $PAGE = ['title' => 'Sección no disponible | Funeraria del Zulia', 'description' => 'Esta sección no está disponible en este momento.'];
    require __DIR__ . '/partials/site_header.php';
    echo '<main class="container section"><div class="section-header"><h2>Sección no disponible</h2><p>Esta sección no está disponible en este momento.</p></div><div style="text-align:center"><a class="btn btn-primary" href="index.php">Volver al inicio</a></div></main>';
    require __DIR__ . '/partials/site_footer.php';
    exit;
}

$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 12;
$offset = ($page - 1) * $per;
$q      = trim($_GET['q'] ?? '');
$cat    = trim($_GET['category'] ?? '');

$where = "status='active' AND deleted_at IS NULL";
$params = [];
if ($q !== '') { $where .= " AND (title LIKE ? OR excerpt LIKE ? OR category LIKE ?)"; $like = "%$q%"; array_push($params, $like, $like, $like); }
if ($cat !== '') { $where .= " AND category = ?"; $params[] = $cat; }

$countSt = db()->prepare("SELECT COUNT(*) FROM articles WHERE $where");
$countSt->execute($params);
$total = (int)$countSt->fetchColumn();
$pages = max(1, (int)ceil($total / $per));

$st = db()->prepare("SELECT id, slug, title, category, excerpt, cover_path, is_featured, feature_order, published_at, created_at
                     FROM articles WHERE $where
                     ORDER BY is_featured DESC, COALESCE(feature_order,999999) ASC, COALESCE(published_at, created_at) DESC, id DESC
                     LIMIT $per OFFSET $offset");
$st->execute($params);
$rows = $st->fetchAll();

$cats = db()->query("SELECT DISTINCT category FROM articles WHERE status='active' AND deleted_at IS NULL AND category IS NOT NULL AND category <> '' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);

// Buscar (?q=) o filtrar (?category=) es contenido fino/variable: no se
// indexa a sí mismo, la canónica consolida en la versión limpia (paginación
// sin filtro sí puede seguir siendo indexable a su propia página).
$isFiltered = $q !== '' || $cat !== '';
$canonical  = site_url('recursos.php' . (!$isFiltered && $page > 1 ? '?page=' . $page : ''));
$breadcrumbLd = breadcrumb_jsonld([
    ['name' => 'Inicio', 'url' => site_url('index.php')],
    ['name' => 'Recursos', 'url' => site_url('recursos.php')],
]);

$PAGE = [
    'title' => 'Recursos de Lectura y Acompañamiento | Funeraria del Zulia',
    'description' => 'Artículos y guías sobre duelo, trámites y previsión para acompañar a las familias en Maracaibo y el Estado Zulia. Funeraria del Zulia.',
    'canonical' => $canonical,
    'head' => '<meta name="robots" content="' . ($isFiltered ? 'noindex, follow' : 'index, follow') . '">' . $breadcrumbLd,
];
require __DIR__ . '/partials/site_header.php';

function res_qs(int $page, string $q, string $cat): string {
    $p = ['page=' . $page];
    if ($q !== '') $p[] = 'q=' . urlencode($q);
    if ($cat !== '') $p[] = 'category=' . urlencode($cat);
    return '?' . implode('&', $p);
}
?>
<main class="container section obit-paper">
    <div class="obit-paper-masthead">
        <h1>Recursos de Lectura</h1>
        <p>Guías y artículos de apoyo sobre el duelo, los trámites y la previsión familiar.</p>
    </div>

    <form class="obit-paper-search" method="get" action="recursos.php">
        <input type="search" name="q" class="form-control" placeholder="Buscar artículos..." value="<?= esc($q) ?>">
        <?php if ($cats): ?>
        <select name="category" class="form-control">
            <option value="">Todas las categorías</option>
            <?php foreach ($cats as $c): ?>
                <option value="<?= esc($c) ?>" <?= $cat === $c ? 'selected' : '' ?>><?= esc($c) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <button class="btn btn-primary" type="submit">Buscar</button>
        <?php if ($q !== '' || $cat !== ''): ?><a class="btn btn-outline" href="recursos.php">Limpiar</a><?php endif; ?>
    </form>

    <?php if (!$rows): ?>
        <p class="empty-row"><?= ($q !== '' || $cat !== '') ? 'No se encontraron recursos con esos criterios.' : 'Por el momento no hay recursos publicados.' ?></p>
    <?php else: ?>
        <div class="resources-grid">
            <?php foreach ($rows as $a) { echo render_public_article_card($a); } ?>
        </div>

        <?php if ($pages > 1): ?>
        <nav class="obit-pagination">
            <?php if ($page > 1): ?><a class="btn btn-outline" href="recursos.php<?= res_qs($page - 1, $q, $cat) ?>">← Anteriores</a><?php endif; ?>
            <span class="obit-pagination-info">Página <?= $page ?> de <?= $pages ?></span>
            <?php if ($page < $pages): ?><a class="btn btn-outline" href="recursos.php<?= res_qs($page + 1, $q, $cat) ?>">Siguientes →</a><?php endif; ?>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/partials/site_footer.php'; ?>
