<?php
/**
 * Página pública del Directorio Médico (server-rendered para SEO/GEO).
 * URL:  directorio-medico.php?page=1&q=...&specialty=...
 */
require __DIR__ . '/api/lib/public_init.php';

if (!site_section_enabled('directorio_medico')) {
    http_response_code(404);
    $PAGE = ['title' => 'Sección no disponible | Funeraria del Zulia', 'description' => 'Esta sección no está disponible en este momento.'];
    require __DIR__ . '/partials/site_header.php';
    echo '<main class="container section"><div class="section-header"><h2>Sección no disponible</h2><p>Esta sección no está disponible en este momento.</p></div><div style="text-align:center"><a class="btn btn-primary" href="index.php">Volver al inicio</a></div></main>';
    require __DIR__ . '/partials/site_footer.php';
    exit;
}

$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 24;
$offset = ($page - 1) * $per;
$q      = trim($_GET['q'] ?? '');
$sp     = trim($_GET['specialty'] ?? '');

$where = "status='active' AND deleted_at IS NULL";
$params = [];
if ($q !== '') { $where .= " AND (full_name LIKE ? OR specialty LIKE ? OR location_name LIKE ?)"; $like = "%$q%"; array_push($params, $like, $like, $like); }
if ($sp !== '') { $where .= " AND specialty = ?"; $params[] = $sp; }

$countSt = db()->prepare("SELECT COUNT(*) FROM doctors WHERE $where");
$countSt->execute($params);
$total = (int)$countSt->fetchColumn();
$pages = max(1, (int)ceil($total / $per));

$st = db()->prepare("SELECT * FROM doctors WHERE $where ORDER BY is_featured DESC, COALESCE(feature_order,999999) ASC, full_name ASC LIMIT $per OFFSET $offset");
$st->execute($params);
$rows = $st->fetchAll();

// Lista de especialidades activas para el filtro
$specs = db()->query("SELECT DISTINCT specialty FROM doctors WHERE status='active' AND deleted_at IS NULL AND specialty <> '' ORDER BY specialty ASC")->fetchAll(PDO::FETCH_COLUMN);

// Buscar (?q=) o filtrar (?specialty=) es contenido fino/variable: no se
// indexa a sí mismo, la canónica consolida en la versión limpia (paginación
// sin filtro sí puede seguir siendo indexable a su propia página).
$isFiltered = $q !== '' || $sp !== '';
$canonical  = site_url('directorio-medico.php' . (!$isFiltered && $page > 1 ? '?page=' . $page : ''));
$breadcrumbLd = breadcrumb_jsonld([
    ['name' => 'Inicio', 'url' => site_url('index.php')],
    ['name' => 'Directorio Médico', 'url' => site_url('directorio-medico.php')],
]);

$PAGE = [
    'title' => 'Directorio Médico en Maracaibo | Funeraria del Zulia',
    'description' => 'Directorio de médicos y especialistas en Maracaibo y el Estado Zulia. Un servicio de apoyo de la Funeraria del Zulia para las familias.',
    'canonical' => $canonical,
    'head' => '<meta name="robots" content="' . ($isFiltered ? 'noindex, follow' : 'index, follow') . '">' . $breadcrumbLd,
];
require __DIR__ . '/partials/site_header.php';

/** Conserva los parámetros activos al paginar. */
function dir_qs(int $page, string $q, string $sp): string {
    $p = ['page=' . $page];
    if ($q !== '') $p[] = 'q=' . urlencode($q);
    if ($sp !== '') $p[] = 'specialty=' . urlencode($sp);
    return '?' . implode('&', $p);
}
?>
<main class="container section obit-paper">
    <div class="obit-paper-masthead">
        <h1>Directorio Médico</h1>
        <p>Médicos y especialistas de referencia en Maracaibo y todo el Estado Zulia.</p>
    </div>

    <form class="obit-paper-search" method="get" action="directorio-medico.php">
        <input type="search" name="q" class="form-control" placeholder="Buscar por nombre o especialidad..." value="<?= esc($q) ?>">
        <?php if ($specs): ?>
        <select name="specialty" class="form-control">
            <option value="">Todas las especialidades</option>
            <?php foreach ($specs as $s): ?>
                <option value="<?= esc($s) ?>" <?= $sp === $s ? 'selected' : '' ?>><?= esc($s) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <button class="btn btn-primary" type="submit">Buscar</button>
        <?php if ($q !== '' || $sp !== ''): ?><a class="btn btn-outline" href="directorio-medico.php">Limpiar</a><?php endif; ?>
    </form>

    <?php if (!$rows): ?>
        <p class="empty-row"><?= ($q !== '' || $sp !== '') ? 'No se encontraron médicos con esos criterios.' : 'Por el momento no hay médicos publicados en el directorio.' ?></p>
    <?php else: ?>
        <div class="doctors-grid">
            <?php foreach ($rows as $d) { echo render_public_doctor_card($d); } ?>
        </div>

        <?php if ($pages > 1): ?>
        <nav class="obit-pagination">
            <?php if ($page > 1): ?><a class="btn btn-outline" href="directorio-medico.php<?= dir_qs($page - 1, $q, $sp) ?>">← Anteriores</a><?php endif; ?>
            <span class="obit-pagination-info">Página <?= $page ?> de <?= $pages ?></span>
            <?php if ($page < $pages): ?><a class="btn btn-outline" href="directorio-medico.php<?= dir_qs($page + 1, $q, $sp) ?>">Siguientes →</a><?php endif; ?>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/partials/site_footer.php'; ?>
