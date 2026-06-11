<?php
/**
 * Página pública individual de un obituario (server-rendered para SEO/GEO).
 * URL:  obituario.php?slug=...
 */
require __DIR__ . '/api/lib/public_init.php';

$slug = $_GET['slug'] ?? '';
$o = $slug !== '' ? get_public_obituary($slug) : null;

if (!$o) {
    http_response_code(404);
    $PAGE = ['title' => 'Obituario no encontrado | Funeraria del Zulia', 'description' => 'El homenaje solicitado no está disponible.'];
    require __DIR__ . '/partials/site_header.php';
    echo '<main class="container section"><div class="section-header"><h2>Obituario no encontrado</h2><p>El homenaje que busca no está disponible o fue retirado.</p></div><div style="text-align:center"><a class="btn btn-primary" href="obituarios.php">Ver todos los obituarios</a></div></main>';
    require __DIR__ . '/partials/site_footer.php';
    exit;
}

// Contador de vistas
try { db()->prepare("UPDATE obituaries SET view_count = view_count + 1 WHERE id = ?")->execute([$o['id']]); } catch (\Throwable $e) {}

$photo     = obit_photo_url($o);
$canonical = site_url('obituario.php?slug=' . urlencode($o['slug'] ?: (string)$o['id']));
$title     = $o['full_name'] . ' (Q.E.P.D.) — Obituario | Funeraria del Zulia';
$bioPlain  = trim(preg_replace('/\s+/', ' ', strip_tags($o['biography'] ?? '')));
$desc      = $o['meta_description'] ?: ('Obituario y homenaje a ' . $o['full_name'] . ' en Maracaibo. ' . mb_substr($bioPlain, 0, 140));

// JSON-LD (Person) para buscadores e IA
$jsonld = [
    '@context'  => 'https://schema.org',
    '@type'     => 'Person',
    'name'      => $o['full_name'],
    'deathDate' => $o['death_date'],
    'url'       => $canonical,
    'image'     => site_url($photo),
];
if (!empty($o['birth_year'])) $jsonld['birthDate'] = (string)$o['birth_year'];
$jsonld['subjectOf'] = [
    '@type'     => 'WebPage',
    'name'      => $title,
    'publisher' => ['@type' => 'FuneralService', 'name' => 'Funeraria del Zulia', 'url' => site_url()],
];

$head = '<meta name="robots" content="index, follow">'
    . '<meta property="og:type" content="profile">'
    . '<meta property="og:title" content="' . esc($title) . '">'
    . '<meta property="og:description" content="' . esc($desc) . '">'
    . '<meta property="og:image" content="' . esc(site_url($photo)) . '">'
    . '<meta property="og:url" content="' . esc($canonical) . '">'
    . '<script type="application/ld+json">' . json_encode($jsonld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';

$PAGE = ['title' => $title, 'description' => $desc, 'canonical' => $canonical, 'head' => $head];
require __DIR__ . '/partials/site_header.php';

$bodyHtml = render_obituary_html($o);
$conds    = approved_condolences((int)$o['id']);
?>
<main class="container obit-detail-main section">
    <nav class="breadcrumb"><a href="index.html">Inicio</a> › <a href="obituarios.php">Obituarios</a> › <span><?= esc($o['full_name']) ?></span></nav>

    <article class="obit-detail">
        <?= $bodyHtml ?>
        <div class="obit-detail-meta">
            <span class="status-badge badge-blue"><?= esc($o['service_type']) ?></span>
            <?php if ($o['location_name']): ?><span class="obit-detail-loc"><?= esc($o['location_name']) ?><?= $o['location_address'] ? ' — ' . esc($o['location_address']) : '' ?></span><?php endif; ?>
        </div>
        <?php if ($o['event_schedule']): ?><p class="obit-detail-event"><strong>Oficios y sepelio:</strong> <?= esc($o['event_schedule']) ?></p><?php endif; ?>
        <div class="obit-detail-actions">
            <a class="btn btn-outline" href="#condForm">Dejar condolencias</a>
            <button class="btn btn-text" type="button" onclick="shareObituary('<?= esc($o['slug'] ?: $o['id']) ?>')">Compartir</button>
        </div>
    </article>

    <section class="obit-condolences">
        <h2>Mensajes de Condolencia</h2>
        <div id="condList">
            <?php if (!$conds): ?>
                <p class="condolence-empty">Aún no hay mensajes publicados. Sea el primero en dejar sus condolencias.</p>
            <?php else: foreach ($conds as $c): ?>
                <div class="condolence-item">
                    <div class="author"><span><?= esc($c['author_name']) ?></span><span class="date"><?= esc(fmt_date_es($c['created_at'])) ?></span></div>
                    <div><?= nl2br(esc($c['message'])) ?></div>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <form id="condForm" class="obit-cond-form" onsubmit="return submitDetailCondolence(event, <?= (int)$o['id'] ?>)">
            <h3>Enviar condolencias</h3>
            <div class="form-group"><label class="form-label">Su nombre completo</label><input id="cdAuthor" class="form-control" required></div>
            <div class="form-group"><label class="form-label">Mensaje de acompañamiento</label><textarea id="cdMsg" class="form-control" required></textarea></div>
            <button type="submit" class="btn btn-primary">Publicar mensaje</button>
        </form>
    </section>
</main>

<script>
async function submitDetailCondolence(e, obId) {
    e.preventDefault();
    try {
        const r = await apiPost('condolences.php?action=create', {
            obituary_id: obId,
            author_name: document.getElementById('cdAuthor').value.trim(),
            message: document.getElementById('cdMsg').value.trim()
        });
        showToast(r.message || 'Mensaje enviado.');
        e.target.reset();
    } catch (ex) { showToast(ex.message); }
    return false;
}
</script>
<?php require __DIR__ . '/partials/site_footer.php'; ?>
