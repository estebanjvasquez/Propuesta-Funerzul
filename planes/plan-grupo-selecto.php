<?php
/** Plan de previsión: Grupo Selecto (server-rendered, SEO/GEO). */
require __DIR__ . '/../api/lib/public_init.php';

// Contenido tomado (2026-10-29) del "Plan Vanguardia Grupo Selecto" de Legado
// Holding (catálogo de Prevision-Funeraria, tenant lh, slug vanguardia-selecto),
// publicado aquí como "Grupo Selecto". Sin precios ni montos de cuota inicial:
// los confirma un asesor. No existe todavía en el catálogo de fdz, por eso no
// usa partials/pf_precio_plan.php.
$canonical = site_url('planes/plan-grupo-selecto.php');
$img       = site_url('planes/img/plan-grupo-selecto.svg');
$title     = 'Plan Grupo Selecto de Previsión Funeraria | Funeraria del Zulia';
$desc      = 'Plan Grupo Selecto: el plan más amplio, con cobertura en toda Venezuela y traslado entre estados. Usted elige cada componente del servicio y entre cremación o bóveda en cementerio privado.';

$head = page_head_meta($title, $desc, $canonical, $img, 'website',
    service_jsonld('Plan Grupo Selecto de Previsión Funeraria', $desc, $canonical, $img, 'Plan de previsión funeraria')
    . breadcrumb_jsonld([
        ['name' => 'Inicio', 'url' => site_url('index.php')],
        ['name' => 'Planes', 'url' => site_url('planes/')],
        ['name' => 'Plan Grupo Selecto', 'url' => $canonical],
    ]));

$PAGE = ['base' => '../', 'title' => $title, 'description' => $desc, 'canonical' => $canonical, 'head' => $head];
require __DIR__ . '/../partials/site_header.php';
?>
<main class="container section service-detail">
    <nav class="breadcrumb"><a href="../index.php">Inicio</a> › <a href="./">Planes</a> › <span>Plan Grupo Selecto</span></nav>

    <article class="service-detail-body">
        <img src="img/plan-grupo-selecto.svg" alt="Plan Grupo Selecto de previsión funeraria con cobertura en toda Venezuela — Funeraria del Zulia" class="service-hero-img" width="1200" height="600">

        <h1>Plan Grupo Selecto de Previsión Funeraria <span class="plan-subtitle-inline">(Cobertura en toda Venezuela)</span> <span class="inline-badge">El más completo</span></h1>
        <p class="service-lead">El <strong>Plan Grupo Selecto</strong> es nuestro plan más amplio: <strong>cobertura en toda Venezuela</strong> con traslado entre estados, y la familia elige <strong>todos los componentes del servicio</strong> y la disposición final, <strong>cremación o bóveda en cementerio privado</strong>. Cubre al titular y a 6 familiares.</p>

        <h2>¿Qué incluye el Plan Grupo Selecto?</h2>
        <ul class="service-list">
            <li><strong>Cobertura en toda Venezuela</strong>, con traslado entre estados incluido</li>
            <li><strong>Todos los componentes del funeral a su elección</strong>: sala velatoria, arreglos florales y ataúd</li>
            <li><strong>Cremación o bóveda</strong> en parcelas de cementerio privado, a elección</li>
            <li><strong>Sin cobros extra por sobrepeso</strong> en Crematorio del Zulia</li>
            <li><strong>Sin cobros extra por extracción de marcapasos</strong></li>
            <li><strong>Cobertura familiar:</strong> titular + 6 familiares</li>
        </ul>
        <p class="plan-age">Edad máxima de ingreso: 65 años.</p>
        <p>El plan se contrata con una cuota inicial única más la mensualidad. Un asesor le indica los montos vigentes antes de contratar.</p>

        <h2>Libertad de elección, en cualquier lugar del país</h2>
        <p>Con el Plan Grupo Selecto su familia no depende de dónde ocurra el fallecimiento: el servicio se presta en toda Venezuela y nos encargamos del <a href="../servicios/traslados.php">traslado</a> entre estados. Usted decide cada detalle del servicio y elige entre la <a href="../servicios/cremacion.php">cremación</a> o una bóveda en parcelas de cementerio privado.</p>

        <h2>Compare con otros planes</h2>
        <p>Si su familia vive en el Zulia, vea el <a href="plan-vanguardia.php">Plan Vanguardia</a>. Para opciones más sencillas, el <a href="plan-tradicion.php">Plan Tradición</a> o el <a href="plan-esencial.php">Plan Esencial</a>. También puede <a href="./#comparar">comparar todos los planes</a>.</p>

        <?php
        $ctaHeading    = 'Solicite asesoría sobre el Plan Grupo Selecto';
        $ctaSub        = 'Cobertura en toda Venezuela. Le explicamos coberturas, cuota inicial y financiamiento sin compromiso.';
        $ctaTel        = '+584146523319';
        $ctaTelDisplay = '+58 414 652-3319';
        $ctaWa         = '584146523319';
        $ctaWaText     = 'Hola, deseo asesoría sobre el Plan Grupo Selecto de previsión funeraria.';
        require __DIR__ . '/../partials/cta_contacto.php';
        $peInteres = 'Plan Grupo Selecto'; $peTipo = 'plan';
        require __DIR__ . '/../partials/cta_pago_electronico.php';
        ?>

        <div class="service-related">
            <h3>Otros planes</h3>
            <ul>
                <li><a href="plan-tradicion.php">Plan Tradición</a></li>
                <li><a href="plan-esencial.php">Plan Esencial</a></li>
                <li><a href="plan-vanguardia.php">Plan Vanguardia</a></li>
                <li><a href="../servicios/">Ver todos los servicios</a></li>
            </ul>
        </div>
    </article>
</main>
<?php require __DIR__ . '/../partials/site_footer.php'; ?>
