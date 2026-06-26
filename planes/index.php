<?php
/** Hub de Planes de Previsión Familiar (server-rendered, SEO/GEO). */
require __DIR__ . '/../api/lib/public_init.php';

$canonical = site_url('planes/');
$img       = site_url('planes/img/plan-vanguardia.svg');
$title     = 'Planes de Previsión Funeraria en Maracaibo — Funeraria del Zulia';
$desc      = 'Planes de previsión funeraria familiar en Maracaibo y el Estado Zulia: Esencial, Tradición, Vanguardia y Vanguardia Plus. Cuotas accesibles, cobertura familiar y atención 24 horas.';

$planes = [
    ['slug' => 'plan-esencial.php', 'img' => 'img/plan-esencial.svg', 'name' => 'Plan Esencial', 'featured' => false,
     'target' => 'Protección básica para familias que buscan una alternativa económica.',
     'features' => ['Velación y cremación', 'Traslados locales', 'Gestión documental', 'Coordinación funeraria']],
    ['slug' => 'plan-tradicion.php', 'img' => 'img/plan-tradicion.svg', 'name' => 'Plan Tradición', 'featured' => false,
     'target' => 'Para familias que ya poseen bóveda o espacio para inhumación.',
     'features' => ['Servicio funerario completo', 'Sala velatoria y oficios', 'Arreglos florales y cafetería', 'Traslados y gestión']],
    ['slug' => 'plan-vanguardia.php', 'img' => 'img/plan-vanguardia.svg', 'name' => 'Plan Vanguardia', 'featured' => true,
     'target' => 'Cobertura integral para disposición final, con cremación o inhumación.',
     'features' => ['Servicio funerario completo', 'Cremación o inhumación', 'Cobertura ampliada', 'Acompañamiento integral']],
    ['slug' => 'plan-vanguardia-plus.php', 'img' => 'img/plan-vanguardia-plus.svg', 'name' => 'Plan Vanguardia Plus', 'featured' => false,
     'target' => 'Cobertura ampliada con modalidad de pago anual y beneficios especiales.',
     'features' => ['Cobertura ampliada e integral', 'Cremación o inhumación', 'Beneficios especiales', 'Renovación anual']],
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
                <p class="plan-target"><?= esc($p['target']) ?></p>
                <ul class="plan-features">
                    <?php foreach ($p['features'] as $f): ?><li><?= esc($f) ?></li><?php endforeach; ?>
                </ul>
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
    ?>

    <p class="service-related" style="text-align:center">¿Necesita un servicio inmediato? Conozca nuestros <a href="../servicios/">Servicios Funerarios</a>.</p>
</main>
<?php require __DIR__ . '/../partials/site_footer.php'; ?>
