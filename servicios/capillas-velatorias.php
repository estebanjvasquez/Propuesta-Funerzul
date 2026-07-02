<?php
/** Servicio: Capillas Velatorias (server-rendered, SEO/GEO). */
require __DIR__ . '/../api/lib/public_init.php';

$canonical = site_url('servicios/capillas-velatorias.php');
$img       = site_url('servicios/img/capillas-velatorias.svg');
$title     = 'Capillas Velatorias en Maracaibo | Salas de Velación — Funeraria del Zulia';
$desc      = 'Capillas velatorias en Maracaibo: salas amplias, climatizadas y serenas para la velación, con cómodas instalaciones, privacidad para la familia y atención 24 horas en el Estado Zulia.';

$head = page_head_meta($title, $desc, $canonical, $img, 'website',
    service_jsonld('Capillas Velatorias', $desc, $canonical, $img)
    . breadcrumb_jsonld([
        ['name' => 'Inicio', 'url' => site_url('index.php')],
        ['name' => 'Servicios', 'url' => site_url('servicios/')],
        ['name' => 'Capillas Velatorias', 'url' => $canonical],
    ]));

$PAGE = ['base' => '../', 'title' => $title, 'description' => $desc, 'canonical' => $canonical, 'head' => $head];
require __DIR__ . '/../partials/site_header.php';
?>
<main class="container section service-detail">
    <nav class="breadcrumb"><a href="../index.php">Inicio</a> › <a href="./">Servicios</a> › <span>Capillas Velatorias</span></nav>

    <article class="service-detail-body">
        <img src="img/capillas-velatorias.svg" alt="Capillas velatorias climatizadas en Maracaibo — Funeraria del Zulia" class="service-hero-img" width="1200" height="600">

        <h1>Capillas Velatorias en Maracaibo</h1>
        <p class="service-lead">Nuestras <strong>capillas velatorias en Maracaibo</strong> son espacios amplios, climatizados y serenos, diseñados para garantizar el confort y la privacidad de la familia durante la vigilia. Un lugar digno para reunirse, acompañarse y despedir con paz.</p>

        <h2>Comodidades de nuestras salas de velación</h2>
        <ul class="service-list">
            <li>Salas <strong>climatizadas</strong> y ambientadas con sobriedad.</li>
            <li>Espacios cómodos y privados para familiares y acompañantes.</li>
            <li>Área de cafetería y atención durante toda la velación.</li>
            <li>Accesibilidad y estacionamiento.</li>
            <li>Atención y coordinación las 24 horas.</li>
        </ul>

        <h2>Un espacio de paz para el último adiós en el Zulia</h2>
        <p>Sabemos lo importante que es contar con un lugar adecuado para reunir a quienes acompañan a la familia. Nuestras capillas, ubicadas en Maracaibo, ofrecen el entorno sereno que ese momento merece, con personal atento a cada necesidad durante la vigilia.</p>

        <h2>Combine la velación con el servicio que prefiera</h2>
        <p>La velación puede formar parte de un <a href="sepelio-tradicional.php">sepelio tradicional</a> o realizarse como paso previo a la <a href="cremacion.php">cremación</a>. Si el fallecimiento ocurrió en otra ciudad, coordinamos el <a href="traslados.php">traslado</a>. Considere también nuestros <a href="../planes/">planes de previsión</a> para dejarlo todo organizado con anticipación.</p>

        <?php
        $ctaHeading = 'Reserve una capilla velatoria';
        $ctaSub     = 'Disponibilidad y atención inmediata las 24 horas en Maracaibo.';
        $ctaWaText  = 'Hola, deseo información sobre las capillas velatorias.';
        require __DIR__ . '/../partials/cta_contacto.php';
        ?>

        <div class="service-related">
            <h3>Otros servicios</h3>
            <ul>
                <li><a href="sepelio-tradicional.php">Sepelio Tradicional</a></li>
                <li><a href="cremacion.php">Cremación en Maracaibo</a></li>
                <li><a href="traslados.php">Traslados Nacionales</a></li>
                <li><a href="../planes/">Planes de Previsión Familiar</a></li>
            </ul>
        </div>
    </article>
</main>
<?php require __DIR__ . '/../partials/site_footer.php'; ?>
