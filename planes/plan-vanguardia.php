<?php
/** Plan de previsión: Vanguardia (destacado) (server-rendered, SEO/GEO). */
require __DIR__ . '/../api/lib/public_init.php';

$canonical = site_url('planes/plan-vanguardia.php');
$img       = site_url('planes/img/plan-vanguardia.svg');
$title     = 'Plan Vanguardia de Previsión Funeraria | Funeraria del Zulia';
$desc      = 'Plan Vanguardia: servicio funerario completo en Maracaibo con cremación o gastos de inhumación incluidos. El plan más elegido del Zulia.';

$head = page_head_meta($title, $desc, $canonical, $img, 'website',
    service_jsonld('Plan Vanguardia de Previsión Funeraria', $desc, $canonical, $img, 'Plan de previsión funeraria')
    . breadcrumb_jsonld([
        ['name' => 'Inicio', 'url' => site_url('index.php')],
        ['name' => 'Planes', 'url' => site_url('planes/')],
        ['name' => 'Plan Vanguardia', 'url' => $canonical],
    ]));

$PAGE = ['base' => '../', 'title' => $title, 'description' => $desc, 'canonical' => $canonical, 'head' => $head];
require __DIR__ . '/../partials/site_header.php';
?>
<main class="container section service-detail">
    <nav class="breadcrumb"><a href="../index.php">Inicio</a> › <a href="./">Planes</a> › <span>Plan Vanguardia</span></nav>

    <article class="service-detail-body">
        <img src="img/plan-vanguardia.svg" alt="Plan Vanguardia de previsión funeraria en Maracaibo — Funeraria del Zulia" class="service-hero-img" width="1200" height="600">

        <h1>Plan Vanguardia de Previsión Funeraria <span class="plan-subtitle-inline">(Funeral + cremación / gastos de inhumación)</span> <span class="inline-badge">Más elegido</span></h1>
        <p class="service-lead">El <strong>Plan Vanguardia</strong> incluye el servicio funerario completo y, además, la <strong>cremación o los gastos de inhumación</strong>, a elección de la familia. Es el plan más elegido por las familias del Estado Zulia que buscan tranquilidad total.</p>

        <?php $pePlanSlug = 'vanguardia'; require __DIR__ . '/../partials/pf_precio_plan.php'; ?>

        <h2>¿Qué incluye el Plan Vanguardia?</h2>
        <ul class="service-list">
            <li><strong>Servicio funerario completo</strong> (todo lo del Plan Tradición)</li>
            <li><strong>Cremación o gastos de inhumación</strong> en cementerio, a elección</li>
            <li><strong>Acompañamiento integral</strong> a la familia</li>
        </ul>
        <p class="plan-age">Edad máxima de ingreso: 65 años.</p>

        <h2>Protección integral para toda la familia</h2>
        <p>Con el Plan Vanguardia, su familia queda cubierta de principio a fin: desde el acto velatorio en nuestras <a href="../servicios/capillas-velatorias.php">capillas</a> hasta la <a href="../servicios/cremacion.php">cremación</a> o los gastos de inhumación, con la gestión documental y los traslados incluidos. Cobertura familiar, financiamiento adaptable y atención 24 horas en Maracaibo y todo el Zulia.</p>

        <h2>Compare con otros planes</h2>
        <p>Para opciones más sencillas, vea el <a href="plan-tradicion.php">Plan Tradición</a> (para familias que poseen bóveda) o el <a href="plan-esencial.php">Plan Esencial</a>.</p>

        <?php
        $ctaHeading    = 'Solicite asesoría sobre el Plan Vanguardia';
        $ctaSub        = 'El plan más completo. Le explicamos coberturas y financiamiento sin compromiso.';
        $ctaTel        = '+584146523319';
        $ctaTelDisplay = '+58 414 652-3319';
        $ctaWa         = '584146523319';
        $ctaWaText     = 'Hola, deseo asesoría sobre el Plan Vanguardia de previsión funeraria.';
        require __DIR__ . '/../partials/cta_contacto.php';
        $peInteres = 'Plan Vanguardia'; $peTipo = 'plan';
        require __DIR__ . '/../partials/cta_pago_electronico.php';
        ?>

        <div class="service-related">
            <h3>Otros planes</h3>
            <ul>
                <li><a href="plan-esencial.php">Plan Esencial</a></li>
                <li><a href="plan-tradicion.php">Plan Tradición</a></li>
                <li><a href="../servicios/">Ver todos los servicios</a></li>
            </ul>
        </div>
    </article>
</main>
<?php require __DIR__ . '/../partials/site_footer.php'; ?>
