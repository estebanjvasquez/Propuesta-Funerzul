<?php
/** Plan de previsión: Vanguardia Plus (server-rendered, SEO/GEO). */
require __DIR__ . '/../api/lib/public_init.php';

$canonical = site_url('planes/plan-vanguardia-plus.php');
$img       = site_url('planes/img/plan-vanguardia-plus.svg');
$title     = 'Plan Vanguardia Plus de Previsión Funeraria en Maracaibo — Funeraria del Zulia';
$desc      = 'Plan Vanguardia Plus: cobertura ampliada de previsión funeraria con modalidad de pago anual y beneficios especiales, cremación o inhumación y servicio integral en Maracaibo, Estado Zulia.';

$head = page_head_meta($title, $desc, $canonical, $img, 'website',
    service_jsonld('Plan Vanguardia Plus de Previsión Funeraria', $desc, $canonical, $img, 'Plan de previsión funeraria')
    . breadcrumb_jsonld([
        ['name' => 'Inicio', 'url' => site_url('index.php')],
        ['name' => 'Planes', 'url' => site_url('planes/')],
        ['name' => 'Plan Vanguardia Plus', 'url' => $canonical],
    ]));

$PAGE = ['base' => '../', 'title' => $title, 'description' => $desc, 'canonical' => $canonical, 'head' => $head];
require __DIR__ . '/../partials/site_header.php';
?>
<main class="container section service-detail">
    <nav class="breadcrumb"><a href="../index.php">Inicio</a> › <a href="./">Planes</a> › <span>Plan Vanguardia Plus</span></nav>

    <article class="service-detail-body">
        <img src="img/plan-vanguardia-plus.svg" alt="Plan Vanguardia Plus de previsión funeraria en Maracaibo — Funeraria del Zulia" class="service-hero-img" width="1200" height="600">

        <h1>Plan Vanguardia Plus de Previsión Funeraria</h1>
        <p class="service-lead">El <strong>Plan Vanguardia Plus</strong> es nuestra cobertura más completa, con modalidad de <strong>pago anual</strong> y <strong>beneficios especiales</strong>. Servicio integral con cremación o inhumación, para las familias del Zulia que desean lo mejor en previsión.</p>

        <h2>¿Qué incluye el Plan Vanguardia Plus?</h2>
        <ul class="service-list">
            <li><strong>Cobertura ampliada y servicio integral</strong></li>
            <li><strong>Cremación o inhumación</strong> a elección</li>
            <li><strong>Beneficios especiales</strong></li>
            <li><strong>Renovación anual</strong></li>
        </ul>

        <h2>La máxima tranquilidad para su familia</h2>
        <p>El Plan Vanguardia Plus reúne todas las ventajas del <a href="plan-vanguardia.php">Plan Vanguardia</a> y suma beneficios adicionales con una práctica modalidad anual. Incluye cobertura familiar, financiamiento adaptable, atención 24 horas y todo el acompañamiento de Funeraria del Zulia en Maracaibo y el Estado Zulia.</p>

        <h2>Compare con otros planes</h2>
        <p>Vea también el <a href="plan-vanguardia.php">Plan Vanguardia</a> (el más elegido), el <a href="plan-tradicion.php">Plan Tradición</a> y el <a href="plan-esencial.php">Plan Esencial</a> para elegir la opción ideal para su familia.</p>

        <?php
        $ctaHeading    = 'Solicite asesoría sobre el Plan Vanguardia Plus';
        $ctaSub        = 'Cobertura ampliada con beneficios especiales. Le asesoramos sin compromiso.';
        $ctaTel        = '+584146523319';
        $ctaTelDisplay = '+58 414 652-3319';
        $ctaWa         = '584146523319';
        $ctaWaText     = 'Hola, deseo asesoría sobre el Plan Vanguardia Plus de previsión funeraria.';
        require __DIR__ . '/../partials/cta_contacto.php';
        ?>

        <div class="service-related">
            <h3>Otros planes</h3>
            <ul>
                <li><a href="plan-esencial.php">Plan Esencial</a></li>
                <li><a href="plan-tradicion.php">Plan Tradición</a></li>
                <li><a href="plan-vanguardia.php">Plan Vanguardia</a></li>
                <li><a href="../servicios/">Ver todos los servicios</a></li>
            </ul>
        </div>
    </article>
</main>
<?php require __DIR__ . '/../partials/site_footer.php'; ?>
