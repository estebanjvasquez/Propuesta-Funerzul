<?php
/**
 * Página pública individual de un médico del directorio (server-rendered para SEO/GEO).
 * URL:  medico.php?slug=...
 */
require __DIR__ . '/api/lib/public_init.php';

$slug = $_GET['slug'] ?? '';
$d = $slug !== '' ? get_public_doctor($slug) : null;

if (!$d) {
    http_response_code(404);
    $PAGE = ['title' => 'Médico no encontrado | Funeraria del Zulia', 'description' => 'La ficha solicitada no está disponible.'];
    require __DIR__ . '/partials/site_header.php';
    echo '<main class="container section"><div class="section-header"><h2>Médico no encontrado</h2><p>La ficha que busca no está disponible o fue retirada.</p></div><div style="text-align:center"><a class="btn btn-primary" href="directorio-medico.php">Ver el directorio médico</a></div></main>';
    require __DIR__ . '/partials/site_footer.php';
    exit;
}

// Contador de vistas
try { db()->prepare("UPDATE doctors SET view_count = view_count + 1 WHERE id = ?")->execute([$d['id']]); } catch (\Throwable $e) {}

$photo     = doctor_photo_url($d);
$canonical = site_url('medico.php?slug=' . urlencode($d['slug'] ?: (string)$d['id']));
$title     = $d['full_name'] . ' — ' . $d['specialty'] . ' en Maracaibo | Funeraria del Zulia';
$bioPlain  = trim(preg_replace('/\s+/', ' ', strip_tags($d['bio'] ?? '')));
$desc      = $d['meta_description'] ?: (trim($d['full_name'] . ', ' . $d['specialty']) . ' en Maracaibo, Estado Zulia. ' . mb_substr($bioPlain, 0, 140));

// JSON-LD (Physician) para buscadores e IA
$jsonld = [
    '@context'        => 'https://schema.org',
    '@type'           => 'Physician',
    'name'            => $d['full_name'],
    'medicalSpecialty'=> $d['specialty'],
    'url'             => $canonical,
    'image'           => site_url($photo),
    'areaServed'      => 'Maracaibo, Estado Zulia, Venezuela',
];
if (!empty($d['phone'])) $jsonld['telephone'] = $d['phone'];
if (!empty($d['email'])) $jsonld['email'] = $d['email'];
if (!empty($d['location_name']) || !empty($d['location_address'])) {
    $jsonld['address'] = [
        '@type' => 'PostalAddress',
        'name'  => $d['location_name'] ?: '',
        'streetAddress' => $d['location_address'] ?: '',
        'addressLocality' => 'Maracaibo',
        'addressRegion' => 'Zulia',
        'addressCountry' => 'VE',
    ];
}

$head = '<meta name="robots" content="index, follow">'
    . '<meta property="og:type" content="profile">'
    . '<meta property="og:title" content="' . esc($title) . '">'
    . '<meta property="og:description" content="' . esc($desc) . '">'
    . '<meta property="og:image" content="' . esc(site_url($photo)) . '">'
    . '<meta property="og:url" content="' . esc($canonical) . '">'
    . '<script type="application/ld+json">' . json_encode($jsonld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';

$PAGE = ['title' => $title, 'description' => $desc, 'canonical' => $canonical, 'head' => $head];
require __DIR__ . '/partials/site_header.php';
?>
<main class="container obit-detail-main section">
    <nav class="breadcrumb"><a href="index.html">Inicio</a> › <a href="directorio-medico.php">Directorio Médico</a> › <span><?= esc($d['full_name']) ?></span></nav>

    <article class="obit-detail doctor-detail">
        <div class="doctor-detail-head">
            <img src="<?= esc($photo) ?>" alt="Foto de <?= esc($d['full_name']) ?>" class="doctor-detail-photo" loading="lazy">
            <div>
                <span class="status-badge badge-blue"><?= esc($d['specialty']) ?></span>
                <h1><?= esc($d['full_name']) ?></h1>
                <?php if ($d['location_name'] || $d['location_address']): ?>
                    <p class="doctor-detail-loc"><?= esc(trim($d['location_name'] . ($d['location_address'] ? ' — ' . $d['location_address'] : ''), ' —')) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (trim((string)$d['bio']) !== ''): ?>
            <div class="doctor-detail-bio"><?= nl2br(esc($d['bio'])) ?></div>
        <?php endif; ?>

        <ul class="doctor-detail-contact">
            <?php if ($d['phone']): ?><li><strong>Teléfono:</strong> <a href="tel:<?= esc(preg_replace('/[^0-9+]/', '', $d['phone'])) ?>"><?= esc($d['phone']) ?></a></li><?php endif; ?>
            <?php if ($d['email']): ?><li><strong>Correo:</strong> <a href="mailto:<?= esc($d['email']) ?>"><?= esc($d['email']) ?></a></li><?php endif; ?>
            <?php if ($d['location_address']): ?><li><strong>Dirección:</strong> <?= esc($d['location_address']) ?></li><?php endif; ?>
        </ul>

        <div class="obit-detail-actions">
            <a class="btn btn-outline" href="directorio-medico.php">← Volver al directorio</a>
        </div>
    </article>
</main>
<?php require __DIR__ . '/partials/site_footer.php'; ?>
