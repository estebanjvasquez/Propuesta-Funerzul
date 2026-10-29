<?php
/** Hub de Planes de Previsión Familiar (server-rendered, SEO/GEO). */
require __DIR__ . '/../api/lib/public_init.php';

$canonical = site_url('planes/');
$img       = site_url('planes/img/plan-vanguardia.svg');
$title     = 'Planes de Previsión Funeraria en Maracaibo';
$desc      = 'Planes de previsión funeraria en Maracaibo: Tradición, Esencial, Vanguardia y Grupo Selecto, con cobertura en toda Venezuela. Cuotas accesibles y cobertura familiar.';

// Orden y textos pedidos por el cliente (2026-10-27): Tradición, Esencial,
// Vanguardia y Grupo Selecto (en el lugar del Vanguardia Plus, ya retirado).
// Vanguardia y Grupo Selecto (2026-10-29) siguen el "Plan Vanguardia Zulia" y el
// "Plan Vanguardia Grupo Selecto" de Legado Holding (Prevision-Funeraria, tenant
// lh), sin precios. Edad máxima: catálogo de Prevision-Funeraria.
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
     'subtitle' => '(Funeral completo + cremación, bóveda o inhumación)',
     'target' => 'Lo mejor de nuestros planes en el Zulia. Padres hasta 80 años.',
     'features' => ['Servicio funerario completo con ataúd', 'Oficios religiosos y flores naturales', 'Cremación, bóveda aérea o inhumación', '6 miembros de su familia'],
     'age' => 'Edad máxima de ingreso: 65 años (padres hasta 80)'],
    ['slug' => 'plan-grupo-selecto.php', 'img' => 'img/plan-grupo-selecto.svg', 'name' => 'Plan Grupo Selecto', 'featured' => false,
     'subtitle' => '(Cobertura en toda Venezuela)',
     'target' => 'El plan más amplio: usted elige cada componente del servicio.',
     'features' => ['Cobertura en toda Venezuela y traslado entre estados', 'Componentes del funeral a su elección', 'Cremación o bóveda en cementerio privado', 'Titular + 6 familiares'],
     'age' => 'Edad máxima de ingreso: 65 años'],
];

// Tabla comparativa (pedido 2026-10-27). Columnas en el mismo orden que $planes:
// Tradición, Esencial, Vanguardia, Grupo Selecto. Fuente: textos de este sitio +
// descripción detallada de cada plan en el catálogo de Prevision-Funeraria (Esencial
// fdz: "velatorio, embalsamamiento, transporte, asistencia administrativa y
// cremación" -- sin oficios ni arreglo floral; Vanguardia y Grupo Selecto: tenant lh,
// vanguardia-zulia y vanguardia-selecto). true = incluido, false = no incluido,
// string = texto propio. Confirmar con el cliente antes de anunciar (ver docs/BACKLOG.md).
$comparativa = [
    ['Traslado y preparación del cuerpo',          [true, true, true, true]],
    ['Ataúd',                                      [true, false, true, 'A su elección']],
    ['Acto velatorio en capilla',                  [true, true, true, 'A su elección']],
    ['Servicio a domicilio',                       [true, false, true, 'A su elección']],
    ['Oficios religiosos',                         [true, false, true, 'A su elección']],
    ['Arreglo floral',                             [true, false, 'Flores naturales', 'A su elección']],
    ['Cafetería',                                  [true, false, true, 'A su elección']],
    ['Gestión documental (actas y permisos)',      [true, true, true, true]],
    ['Cremación',                                  [false, true, 'A elección (o bóveda aérea o inhumación)', 'A elección (o bóveda en cementerio privado)']],
    ['Bóveda o gastos de inhumación',              ['Requiere bóveda o espacio propio', false, 'Bóveda aérea o gastos de inhumación (o cremación)', 'Bóveda en parcela de cementerio privado (o cremación)']],
    ['Sin cobros extra por sobrepeso o marcapasos', [false, false, false, true]],
    ['Cobertura geográfica',                       ['Estado Zulia', 'Estado Zulia', 'Estado Zulia', 'Toda Venezuela, con traslado entre estados']],
    ['Edad máxima de ingreso',                     ['65 años', '65 años', '65 años (padres hasta 80)', '65 años']],
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

    <section id="comparar" class="plans-compare" aria-labelledby="comparar-titulo">
        <h2 id="comparar-titulo">Compare nuestros planes</h2>
        <div class="plans-compare-scroll">
            <table class="plans-compare-table">
                <thead>
                    <tr>
                        <th scope="col"><span class="sr-only">Qué incluye</span></th>
                        <?php foreach ($planes as $p): ?>
                        <th scope="col"<?= $p['featured'] ? ' class="is-featured"' : '' ?>>
                            <?= esc($p['name']) ?>
                            <span class="plans-compare-sub"><?= esc($p['subtitle']) ?></span>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comparativa as [$fila, $celdas]): ?>
                    <tr>
                        <th scope="row"><?= esc($fila) ?></th>
                        <?php foreach ($celdas as $i => $c): ?>
                        <td<?= !empty($planes[$i]['featured']) ? ' class="is-featured"' : '' ?>>
                            <?php if ($c === true): ?><span class="cmp-yes" aria-hidden="true">✓</span><span class="sr-only">Incluido</span>
                            <?php elseif ($c === false): ?><span class="cmp-no" aria-hidden="true">—</span><span class="sr-only">No incluido</span>
                            <?php else: ?><span class="cmp-text"><?= esc($c) ?></span><?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td></td>
                        <?php foreach ($planes as $p): ?>
                        <td<?= $p['featured'] ? ' class="is-featured"' : '' ?>><a class="btn btn-outline btn-sm" href="<?= esc($p['slug']) ?>">Ver detalle</a></td>
                        <?php endforeach; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="plans-compare-note">Comparativa referencial. Un asesor le confirma coberturas y condiciones antes de contratar.</p>
    </section>

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
