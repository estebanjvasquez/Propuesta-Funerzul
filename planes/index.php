<?php
/** Hub de Planes de Previsión Familiar (server-rendered, SEO/GEO). */
require __DIR__ . '/../api/lib/public_init.php';

$canonical = site_url('planes/');
$img       = site_url('planes/img/plan-vanguardia.svg');
$title     = 'Planes de Previsión Funeraria en Maracaibo';
$desc      = 'Planes de previsión funeraria en Maracaibo: Tradición, Esencial y Vanguardia. Cuotas accesibles y cobertura familiar.';

// Orden y textos pedidos por el cliente (2026-10-27): Tradición, Esencial,
// Vanguardia; el Vanguardia Plus se retiró y su lugar lo ocupará Grupo Selecto
// (pendiente de contenido). Edad máxima: catálogo de Prevision-Funeraria.
$planes = [
    ['slug' => 'plan-tradicion.php', 'img' => 'img/plan-tradicion.svg', 'name' => 'Plan Tradición', 'featured' => false,
     'subtitle' => '(Servicio funerario)',
     'target' => 'Para familias que poseen bóveda o espacio para inhumación.',
     'features' => ['Servicio funerario', 'Sala velatoria o servicio a domicilio', 'Oficios religiosos católicos', 'Arreglos florales y cafetería', 'Traslados y gestión'],
     'age' => 'Edad máxima de ingreso: 65 años'],
    ['slug' => 'plan-esencial.php', 'img' => 'img/plan-esencial.svg', 'name' => 'Plan Esencial', 'featured' => false,
     'subtitle' => '(Funeral + cremación)',
     'target' => 'Protección básica para familias que buscan una alternativa económica.',
     'features' => ['Acto velatorio y cremación', 'Traslados locales', 'Gestión documental (actas y permisos)', 'Coordinación funeraria'],
     'age' => 'Edad máxima de ingreso: 65 años'],
    ['slug' => 'plan-vanguardia.php', 'img' => 'img/plan-vanguardia.svg', 'name' => 'Plan Vanguardia', 'featured' => true,
     'subtitle' => '(Funeral + cremación / gastos de inhumación)',
     'target' => 'Servicio funerario completo, con cremación o gastos de inhumación incluidos.',
     'features' => ['Servicio funerario completo', 'Cremación o gastos de inhumación', 'Acompañamiento integral'],
     'age' => 'Edad máxima de ingreso: 65 años'],
];

$itemList = ['@context' => 'https://schema.org', '@type' => 'ItemList',
    'itemListElement' => array_map(fn($i, $p) => [
        '@type' => 'ListItem', 'position' => $i + 1, 'name' => $p['name'],
        'url' => site_url('planes/' . $p['slug']),
    ], array_keys($planes), $planes)];

$head = page_head_meta($title, $desc, $canonical, $img, 'website',
    '<script type="application/ld+json">' . json_encode($itemList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>'
    . breadcrumb_jsonld([
        ['name' => 'Inicio', 'url' => site_url('index.php')],
        ['name' => 'Planes', 'url' => $canonical],
    ]));

$PAGE = ['base' => '../', 'title' => $title, 'description' => $desc, 'canonical' => $canonical, 'head' => $head];
require __DIR__ . '/../partials/site_header.php';
?>
<main class="container section">
    <nav class="breadcrumb"><a href="../index.php">Inicio</a> › <span>Planes</span></nav>

    <div class="section-header">
        <h1>Planes de Previsión Funeraria Familiar</h1>
        <p>Proteja a su familia y garantice tranquilidad financiera ante el futuro. Planifique hoy, con cuotas accesibles y cobertura para todo el grupo familiar en el Estado Zulia.</p>
    </div>

    <div class="plans-detail-grid">
        <?php foreach ($planes as $p): ?>
        <article class="plan-detail-card<?= $p['featured'] ? ' plan-featured' : '' ?>">
            <?php if ($p['featured']): ?><span class="plan-badge">Más elegido</span><?php endif; ?>
            <img src="<?= esc($p['img']) ?>" alt="<?= esc($p['name']) ?> — Funeraria del Zulia" loading="lazy" width="1200" height="600">
            <div class="plan-detail-card-body">
                <h2 class="plan-name"><?= esc($p['name']) ?></h2>
                <p class="plan-subtitle"><?= esc($p['subtitle']) ?></p>
                <p class="plan-target"><?= esc($p['target']) ?></p>
                <ul class="plan-features">
                    <?php foreach ($p['features'] as $f): ?><li><?= esc($f) ?></li><?php endforeach; ?>
                </ul>
                <p class="plan-age"><?= esc($p['age']) ?></p>
                <a class="btn btn-primary plan-cta" href="<?= esc($p['slug']) ?>">Ver detalle del plan</a>
            </div>
        </article>
        <?php endforeach; ?>
    </div>

    <p class="prevision-note">Todos los planes incluyen cobertura familiar, financiamiento adaptable, atención 24 horas y planificación anticipada.</p>

    <?php
    $ctaHeading    = 'Asesoría de planes de previsión';
    $ctaSub        = 'Le ayudamos a elegir el plan ideal para su familia, sin compromiso.';
    $ctaTel        = '+584146523319';
    $ctaTelDisplay = '+58 414 652-3319';
    $ctaWa         = '584146523319';
    $ctaWaText     = 'Hola, deseo asesoría sobre los planes de previsión funeraria.';
    require __DIR__ . '/../partials/cta_contacto.php';
    $peInteres = 'un plan de previsión'; $peTipo = 'plan';
    require __DIR__ . '/../partials/cta_pago_electronico.php';
    ?>

    <p class="service-related" style="text-align:center">¿Necesita un servicio inmediato? Conozca nuestros <a href="../servicios/">Servicios Funerarios</a>.</p>
</main>
<?php require __DIR__ . '/../partials/site_footer.php'; ?>
