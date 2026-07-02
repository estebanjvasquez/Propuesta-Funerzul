<?php
/** Hub de Servicios Funerarios (server-rendered, SEO/GEO). */
require __DIR__ . '/../api/lib/public_init.php';

$canonical = site_url('servicios/');
$img       = site_url('servicios/img/sepelio-tradicional.svg');
$title     = 'Servicios Funerarios en Maracaibo | Cremación, Velación y Traslados — Funeraria del Zulia';
$desc      = 'Servicios funerarios integrales en Maracaibo y todo el Estado Zulia: sepelio tradicional, cremación, traslados nacionales y capillas velatorias, con atención 24 horas.';

$servicios = [
    ['slug' => 'sepelio-tradicional.php', 'img' => 'img/sepelio-tradicional.svg', 'name' => 'Sepelio Tradicional',
     'desc' => 'Velación, oficios religiosos y gestión completa, fiel a sus creencias y tradiciones.'],
    ['slug' => 'cremacion.php', 'img' => 'img/cremacion.svg', 'name' => 'Cremación en Maracaibo',
     'desc' => 'Cremación directa o con velación previa, en crematorio propio y con todos los permisos.'],
    ['slug' => 'traslados.php', 'img' => 'img/traslados.svg', 'name' => 'Traslados Nacionales',
     'desc' => 'Traslados seguros desde y hacia el Estado Zulia, con permisos y documentación incluidos.'],
    ['slug' => 'capillas-velatorias.php', 'img' => 'img/capillas-velatorias.svg', 'name' => 'Capillas Velatorias',
     'desc' => 'Salas amplias, climatizadas y privadas para una vigilia serena en Maracaibo.'],
];

// ItemList JSON-LD para la lista de servicios
$itemList = ['@context' => 'https://schema.org', '@type' => 'ItemList',
    'itemListElement' => array_map(fn($i, $s) => [
        '@type' => 'ListItem', 'position' => $i + 1, 'name' => $s['name'],
        'url' => site_url('servicios/' . $s['slug']),
    ], array_keys($servicios), $servicios)];

$head = page_head_meta($title, $desc, $canonical, $img, 'website',
    '<script type="application/ld+json">' . json_encode($itemList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>'
    . breadcrumb_jsonld([
        ['name' => 'Inicio', 'url' => site_url('index.php')],
        ['name' => 'Servicios', 'url' => $canonical],
    ]));

$PAGE = ['base' => '../', 'title' => $title, 'description' => $desc, 'canonical' => $canonical, 'head' => $head];
require __DIR__ . '/../partials/site_header.php';
?>
<main class="container section">
    <nav class="breadcrumb"><a href="../index.php">Inicio</a> › <span>Servicios</span></nav>

    <div class="section-header">
        <h1>Servicios Funerarios en Maracaibo</h1>
        <p>Soluciones integrales para honrar a sus seres queridos con solemnidad y respeto en todo el Estado Zulia. Atención inmediata las 24 horas.</p>
    </div>

    <div class="services-detail-grid">
        <?php foreach ($servicios as $s): ?>
        <a class="service-link-card" href="<?= esc($s['slug']) ?>">
            <img src="<?= esc($s['img']) ?>" alt="<?= esc($s['name']) ?> — Funeraria del Zulia" loading="lazy" width="1200" height="600">
            <div class="service-link-card-body">
                <h2><?= esc($s['name']) ?></h2>
                <p><?= esc($s['desc']) ?></p>
                <span class="service-link-more">Ver detalle →</span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <?php
    $ctaHeading = '¿Necesita atención funeraria ahora?';
    $ctaSub     = 'Llámenos o escríbanos por WhatsApp. Atendemos las 24 horas en Maracaibo y todo el Zulia.';
    $ctaWaText  = 'Hola, quisiera información sobre sus servicios funerarios.';
    require __DIR__ . '/../partials/cta_contacto.php';
    ?>

    <p class="service-related" style="text-align:center">¿Desea organizarlo con anticipación? Conozca nuestros <a href="../planes/">Planes de Previsión Familiar</a>.</p>
</main>
<?php require __DIR__ . '/../partials/site_footer.php'; ?>
