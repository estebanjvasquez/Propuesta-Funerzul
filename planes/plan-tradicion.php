<?php
/** Plan de previsión: Tradición (server-rendered, SEO/GEO). */
require __DIR__ . '/../api/lib/public_init.php';

$canonical = site_url('planes/plan-tradicion.php');
$img       = site_url('planes/img/plan-tradicion.svg');
$title     = 'Plan Tradición de Previsión Funeraria en Maracaibo — Funeraria del Zulia';
$desc      = 'Plan Tradición: servicio funerario completo en Maracaibo para familias que ya poseen bóveda o espacio de inhumación. Incluye sala velatoria, oficios, arreglos florales y gestión administrativa.';

$head = page_head_meta($title, $desc, $canonical, $img, 'website',
    service_jsonld('Plan Tradición de Previsión Funeraria', $desc, $canonical, $img, 'Plan de previsión funeraria')
    . breadcrumb_jsonld([
        ['name' => 'Inicio', 'url' => site_url('index.php')],
        ['name' => 'Planes', 'url' => site_url('planes/')],
        ['name' => 'Plan Tradición', 'url' => $canonical],
    ]));

$PAGE = ['base' => '../', 'title' => $title, 'description' => $desc, 'canonical' => $canonical, 'head' => $head];
require __DIR__ . '/../partials/site_header.php';
?>
<main class="container section service-detail">
    <nav class="breadcrumb"><a href="../index.php">Inicio</a> › <a href="./">Planes</a> › <span>Plan Tradición</span></nav>

    <article class="service-detail-body">
        <img src="img/plan-tradicion.svg" alt="Plan Tradición de previsión funeraria en Maracaibo — Funeraria del Zulia" class="service-hero-img" width="1200" height="600">

        <h1>Plan Tradición de Previsión Funeraria</h1>
        <p class="service-lead">El <strong>Plan Tradición</strong> está pensado para las familias que ya poseen bóveda o espacio para inhumación y desean un <strong>servicio funerario completo</strong>, con todos los detalles cubiertos y la tranquilidad de tenerlo planificado.</p>

        <h2>¿Qué incluye el Plan Tradición?</h2>
        <ul class="service-list">
            <li><strong>Servicio funerario completo</strong></li>
            <li><strong>Sala velatoria y oficios religiosos</strong></li>
            <li><strong>Arreglos florales y cafetería</strong></li>
            <li><strong>Traslados y gestión administrativa</strong></li>
        </ul>

        <h2>Previsión con dignidad en el Estado Zulia</h2>
        <p>Con el Plan Tradición, su familia contará con una <a href="../servicios/capillas-velatorias.php">capilla velatoria</a> digna, oficios conforme a sus creencias y todo el acompañamiento del equipo de Funeraria del Zulia. Incluye <strong>cobertura familiar</strong>, financiamiento adaptable y atención 24 horas.</p>

        <h2>Compare con otros planes</h2>
        <p>Si busca cobertura para la disposición final (cremación o inhumación), el <a href="plan-vanguardia.php">Plan Vanguardia</a> es el más elegido. Para una opción económica, vea el <a href="plan-esencial.php">Plan Esencial</a>; para beneficios ampliados, el <a href="plan-vanguardia-plus.php">Plan Vanguardia Plus</a>.</p>

        <?php
        $ctaHeading    = 'Solicite asesoría sobre el Plan Tradición';
        $ctaSub        = 'Le explicamos coberturas, cuotas y financiamiento sin compromiso.';
        $ctaTel        = '+584146523319';
        $ctaTelDisplay = '+58 414 652-3319';
        $ctaWa         = '584146523319';
        $ctaWaText     = 'Hola, deseo información sobre el Plan Tradición de previsión funeraria.';
        require __DIR__ . '/../partials/cta_contacto.php';
        ?>

        <div class="service-related">
            <h3>Otros planes</h3>
            <ul>
                <li><a href="plan-esencial.php">Plan Esencial</a></li>
                <li><a href="plan-vanguardia.php">Plan Vanguardia</a></li>
                <li><a href="plan-vanguardia-plus.php">Plan Vanguardia Plus</a></li>
                <li><a href="../servicios/">Ver todos los servicios</a></li>
            </ul>
        </div>
    </article>
</main>
<?php require __DIR__ . '/../partials/site_footer.php'; ?>
