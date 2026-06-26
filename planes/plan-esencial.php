<?php
/** Plan de previsión: Esencial (server-rendered, SEO/GEO). */
require __DIR__ . '/../api/lib/public_init.php';

$canonical = site_url('planes/plan-esencial.php');
$img       = site_url('planes/img/plan-esencial.svg');
$title     = 'Plan Esencial de Previsión Funeraria en Maracaibo — Funeraria del Zulia';
$desc      = 'Plan Esencial: previsión funeraria económica en Maracaibo y el Estado Zulia. Incluye velación y cremación, traslados locales y gestión documental, con cuotas accesibles y cobertura familiar.';

$head = page_head_meta($title, $desc, $canonical, $img, 'website',
    service_jsonld('Plan Esencial de Previsión Funeraria', $desc, $canonical, $img, 'Plan de previsión funeraria')
    . breadcrumb_jsonld([
        ['name' => 'Inicio', 'url' => site_url('index.php')],
        ['name' => 'Planes', 'url' => site_url('planes/')],
        ['name' => 'Plan Esencial', 'url' => $canonical],
    ]));

$PAGE = ['base' => '../', 'title' => $title, 'description' => $desc, 'canonical' => $canonical, 'head' => $head];
require __DIR__ . '/../partials/site_header.php';
?>
<main class="container section service-detail">
    <nav class="breadcrumb"><a href="../index.php">Inicio</a> › <a href="./">Planes</a> › <span>Plan Esencial</span></nav>

    <article class="service-detail-body">
        <img src="img/plan-esencial.svg" alt="Plan Esencial de previsión funeraria en Maracaibo — Funeraria del Zulia" class="service-hero-img" width="1200" height="600">

        <h1>Plan Esencial de Previsión Funeraria</h1>
        <p class="service-lead">El <strong>Plan Esencial</strong> es la protección básica para las familias que buscan una alternativa económica y tranquila. Planifique hoy, con cuotas accesibles, y evite cargas inesperadas a sus seres queridos en el Estado Zulia.</p>

        <h2>¿Qué incluye el Plan Esencial?</h2>
        <ul class="service-list">
            <li><strong>Velación y cremación</strong></li>
            <li><strong>Traslados locales</strong> en el Zulia</li>
            <li><strong>Gestión documental</strong> completa</li>
            <li><strong>Coordinación funeraria</strong> integral</li>
        </ul>

        <h2>¿Por qué contratar previsión funeraria?</h2>
        <p>La previsión funeraria le permite organizar con anticipación todos los servicios, fijando condiciones favorables y protegiendo a su familia de decisiones difíciles en un momento de dolor. Todos nuestros planes incluyen <strong>cobertura familiar</strong>, <strong>financiamiento adaptable</strong>, atención 24 horas y planificación anticipada.</p>

        <h2>Compare con otros planes</h2>
        <p>Si su familia ya cuenta con bóveda o desea un servicio más completo, considere el <a href="plan-tradicion.php">Plan Tradición</a>, el <a href="plan-vanguardia.php">Plan Vanguardia</a> (el más elegido) o el <a href="plan-vanguardia-plus.php">Plan Vanguardia Plus</a>.</p>

        <?php
        $ctaHeading   = 'Solicite asesoría sobre el Plan Esencial';
        $ctaSub       = 'Le explicamos cuotas, coberturas y financiamiento sin compromiso.';
        $ctaTel       = '+584146523319';
        $ctaTelDisplay = '+58 414 652-3319';
        $ctaWa        = '584146523319';
        $ctaWaText    = 'Hola, deseo información sobre el Plan Esencial de previsión funeraria.';
        require __DIR__ . '/../partials/cta_contacto.php';
        ?>

        <div class="service-related">
            <h3>Otros planes</h3>
            <ul>
                <li><a href="plan-tradicion.php">Plan Tradición</a></li>
                <li><a href="plan-vanguardia.php">Plan Vanguardia</a></li>
                <li><a href="plan-vanguardia-plus.php">Plan Vanguardia Plus</a></li>
                <li><a href="../servicios/">Ver todos los servicios</a></li>
            </ul>
        </div>
    </article>
</main>
<?php require __DIR__ . '/../partials/site_footer.php'; ?>
