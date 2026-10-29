<?php
/** Plan de previsión: Tradición (server-rendered, SEO/GEO). */
require __DIR__ . '/../api/lib/public_init.php';

$canonical = site_url('planes/plan-tradicion.php');
$img       = site_url('planes/img/plan-tradicion.svg');
$title     = 'Plan Tradición de Previsión Funeraria | Funeraria del Zulia';
$desc      = 'Plan Tradición: servicio funerario en Maracaibo para familias que poseen bóveda o espacio para inhumación.';

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

        <h1>Plan Tradición de Previsión Funeraria <span class="plan-subtitle-inline">(Servicio funerario)</span></h1>
        <p class="service-lead">El <strong>Plan Tradición</strong> está pensado para las familias que poseen bóveda o espacio para inhumación y desean el <strong>servicio funerario</strong> con todos los detalles cubiertos y la tranquilidad de tenerlo planificado.</p>

        <?php $pePlanSlug = 'tradicion'; require __DIR__ . '/../partials/pf_precio_plan.php'; ?>

        <h2>¿Qué incluye el Plan Tradición?</h2>
        <ul class="service-list">
            <li><strong>Servicio funerario</strong></li>
            <li><strong>Sala velatoria</strong> en nuestras capillas o <strong>servicio a domicilio</strong></li>
            <li><strong>Oficios religiosos católicos</strong></li>
            <li><strong>Arreglos florales y cafetería</strong></li>
            <li><strong>Traslados y gestión administrativa</strong></li>
        </ul>
        <p class="plan-age">Edad máxima de ingreso: 65 años.</p>

        <h2>Previsión con dignidad en el Estado Zulia</h2>
        <p>Con el Plan Tradición, su familia contará con una <a href="../servicios/capillas-velatorias.php">capilla velatoria</a> digna, oficios religiosos católicos y todo el acompañamiento del equipo de Funeraria del Zulia. Incluye <strong>cobertura familiar</strong>, financiamiento adaptable y atención 24 horas.</p>

        <h2>Compare con otros planes</h2>
        <p>Si busca incluir la cremación o los gastos de inhumación, el <a href="plan-vanguardia.php">Plan Vanguardia</a> es el más elegido. Para una opción económica con cremación, vea el <a href="plan-esencial.php">Plan Esencial</a>; y para cobertura en toda Venezuela, el <a href="plan-grupo-selecto.php">Plan Grupo Selecto</a>.</p>

        <?php
        $ctaHeading    = 'Solicite asesoría sobre el Plan Tradición';
        $ctaSub        = 'Le explicamos coberturas, cuotas y financiamiento sin compromiso.';
        $ctaTel        = '+584146523319';
        $ctaTelDisplay = '+58 414 652-3319';
        $ctaWa         = '584146523319';
        $ctaWaText     = 'Hola, deseo información sobre el Plan Tradición de previsión funeraria.';
        require __DIR__ . '/../partials/cta_contacto.php';
        $peInteres = 'Plan Tradición'; $peTipo = 'plan';
        require __DIR__ . '/../partials/cta_pago_electronico.php';
        ?>

        <div class="service-related">
            <h3>Otros planes</h3>
            <ul>
                <li><a href="plan-esencial.php">Plan Esencial</a></li>
                <li><a href="plan-vanguardia.php">Plan Vanguardia</a></li>
                <li><a href="plan-grupo-selecto.php">Plan Grupo Selecto</a></li>
                <li><a href="../servicios/">Ver todos los servicios</a></li>
            </ul>
        </div>
    </article>
</main>
<?php require __DIR__ . '/../partials/site_footer.php'; ?>
