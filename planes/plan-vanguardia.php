<?php
/** Plan de previsión: Vanguardia (destacado) (server-rendered, SEO/GEO). */
require __DIR__ . '/../api/lib/public_init.php';

// Contenido alineado (2026-10-29) con el "Plan Vanguardia Zulia" de Legado
// Holding (catálogo de Prevision-Funeraria, tenant lh, slug vanguardia-zulia).
// Sin precios: el sitio nunca publica montos, los confirma un asesor -- por eso
// esta página ya no incluye partials/pf_precio_plan.php.
$canonical = site_url('planes/plan-vanguardia.php');
$img       = site_url('planes/img/plan-vanguardia.svg');
$title     = 'Plan Vanguardia de Previsión Funeraria | Funeraria del Zulia';
$desc      = 'Plan Vanguardia: lo mejor de nuestros planes en el Zulia. Servicio funerario completo con ataúd, oficios religiosos y flores naturales, más cremación, bóveda aérea o inhumación. Padres hasta 80 años.';

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

        <h1>Plan Vanguardia de Previsión Funeraria <span class="plan-subtitle-inline">(Funeral completo + cremación, bóveda o inhumación)</span> <span class="inline-badge">Más elegido</span></h1>
        <p class="service-lead">El <strong>Plan Vanguardia</strong> es lo mejor de nuestros planes en el Estado Zulia: el <strong>servicio funerario completo en Funeraria del Zulia</strong>, con oficios religiosos y flores naturales, y además la <strong>cremación, la bóveda aérea o los gastos de inhumación</strong> en cementerio, a elección de la familia. Cubre a <strong>6 miembros de su familia</strong>, con los padres hasta los 80 años.</p>

        <h2>¿Qué incluye el Plan Vanguardia?</h2>
        <ul class="service-list">
            <li><strong>Ataúd incluido</strong></li>
            <li><strong>Traslado</strong> desde el lugar del fallecimiento a la funeraria y de la funeraria al cementerio</li>
            <li><strong>Tanatopraxia</strong> (conservación del cuerpo)</li>
            <li><strong>Sala velatoria por 24 horas</strong> en Funeraria del Zulia o equipo a domicilio</li>
            <li><strong>Oficios religiosos</strong></li>
            <li><strong>Un arreglo de flores naturales</strong></li>
            <li><strong>Carroza fúnebre</strong> al cementerio</li>
            <li><strong>Servicio de cafetería</strong> en la funeraria</li>
            <li><strong>Cremación</strong> en Crematorio del Zulia, <strong>o bóveda aérea</strong>, <strong>o gastos de inhumación</strong> en cementerio, a elección</li>
            <li><strong>Asesoría gratuita</strong> en trámites administrativos</li>
            <li><strong>Cobertura familiar:</strong> 6 miembros de su familia en el Zulia</li>
        </ul>
        <p class="plan-age">Edad máxima de ingreso: 65 años (padres del titular hasta 80 años).</p>

        <h2>Protección integral para toda la familia</h2>
        <p>Con el Plan Vanguardia, su familia queda cubierta de principio a fin: desde el acto velatorio en nuestras <a href="../servicios/capillas-velatorias.php">capillas</a> hasta la <a href="../servicios/cremacion.php">cremación</a>, la bóveda aérea o los gastos de inhumación, con los <a href="../servicios/traslados.php">traslados</a> y la asesoría en trámites incluidos. Financiamiento adaptable y atención 24 horas en Maracaibo y todo el Zulia.</p>

        <h2>Compare con otros planes</h2>
        <p>Si necesita cobertura en toda Venezuela, vea el <a href="plan-grupo-selecto.php">Plan Grupo Selecto</a>. Para opciones más sencillas, el <a href="plan-tradicion.php">Plan Tradición</a> (para familias que poseen bóveda) o el <a href="plan-esencial.php">Plan Esencial</a>. También puede <a href="./#comparar">comparar todos los planes</a>.</p>

        <?php
        $ctaHeading    = 'Solicite asesoría sobre el Plan Vanguardia';
        $ctaSub        = 'Lo mejor de nuestros planes. Le explicamos coberturas y financiamiento sin compromiso.';
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
                <li><a href="plan-tradicion.php">Plan Tradición</a></li>
                <li><a href="plan-esencial.php">Plan Esencial</a></li>
                <li><a href="plan-grupo-selecto.php">Plan Grupo Selecto</a></li>
                <li><a href="../servicios/">Ver todos los servicios</a></li>
            </ul>
        </div>
    </article>
</main>
<?php require __DIR__ . '/../partials/site_footer.php'; ?>
