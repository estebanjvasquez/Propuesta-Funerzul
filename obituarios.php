<?php
/**
 * Página pública con TODOS los obituarios (estilo periódico, server-rendered).
 * URL:  obituarios.php?page=1&q=...
 */
require __DIR__ . '/api/lib/public_init.php';

$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 12;
$offset = ($page - 1) * $per;
$q      = trim($_GET['q'] ?? '');

$where = "status='active' AND deleted_at IS NULL";
$params = [];
if ($q !== '') { $where .= " AND full_name LIKE ?"; $params[] = "%$q%"; }

$countSt = db()->prepare("SELECT COUNT(*) FROM obituaries WHERE $where");
$countSt->execute($params);
$total = (int)$countSt->fetchColumn();
$pages = max(1, (int)ceil($total / $per));

$st = db()->prepare("SELECT * FROM obituaries WHERE $where ORDER BY is_pinned DESC, death_date DESC, id DESC LIMIT $per OFFSET $offset");
$st->execute($params);
$rows = $st->fetchAll();

$canonical = site_url('obituarios.php' . ($page > 1 ? '?page=' . $page : ''));
$PAGE = [
    'title' => 'Obituarios y Homenajes en Maracaibo | Funeraria del Zulia',
    'description' => 'Obituarios recientes y homenajes a quienes nos han dejado en Maracaibo y el Estado Zulia. Deje sus condolencias en Funeraria del Zulia.',
    'canonical' => $canonical,
    'head' => '<meta name="robots" content="index, follow">',
];
require __DIR__ . '/partials/site_header.php';
?>
<main class="container section obit-paper">
    <div class="obit-paper-masthead">
        <h1>Obituarios</h1>
        <p>Honrando la memoria de quienes nos han dejado en Maracaibo y todo el Estado Zulia.</p>
    </div>

    <form class="obit-paper-search" method="get" action="obituarios.php">
        <input type="search" name="q" class="form-control" placeholder="Buscar por nombre del fallecido..." value="<?= esc($q) ?>">
        <button class="btn btn-primary" type="submit">Buscar</button>
        <?php if ($q !== ''): ?><a class="btn btn-outline" href="obituarios.php">Limpiar</a><?php endif; ?>
    </form>

    <?php if (!$rows): ?>
        <p class="empty-row"><?= $q !== '' ? 'No se encontraron obituarios para “' . esc($q) . '”.' : 'Por el momento no hay obituarios publicados.' ?></p>
    <?php else: ?>
        <div class="obituaries-grid">
            <?php foreach ($rows as $o) { echo render_public_card($o); } ?>
        </div>

        <?php if ($pages > 1): ?>
        <nav class="obit-pagination">
            <?php if ($page > 1): ?><a class="btn btn-outline" href="obituarios.php?page=<?= $page - 1 ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>">← Anteriores</a><?php endif; ?>
            <span class="obit-pagination-info">Página <?= $page ?> de <?= $pages ?></span>
            <?php if ($page < $pages): ?><a class="btn btn-outline" href="obituarios.php?page=<?= $page + 1 ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>">Siguientes →</a><?php endif; ?>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/partials/site_footer.php'; ?>
