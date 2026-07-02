<?php
/** Servicio: Traslados Nacionales (server-rendered, SEO/GEO). */
require __DIR__ . '/../api/lib/public_init.php';

$canonical = site_url('servicios/traslados.php');
$img       = site_url('servicios/img/traslados.svg');
$title     = 'Traslados Funerarios Nacionales | Desde y hacia el Zulia — Funeraria del Zulia';
$desc      = 'Traslados funerarios nacionales seguros desde y hacia el Estado Zulia. Coordinamos permisos sanitarios y documentación de extremo a extremo, con atención compasiva las 24 horas.';

$head = page_head_meta($title, $desc, $canonical, $img, 'website',
    service_jsonld('Traslados Funerarios Nacionales', $desc, $canonical, $img)
    . breadcrumb_jsonld([
        ['name' => 'Inicio', 'url' => site_url('index.php')],
        ['name' => 'Servicios', 'url' => site_url('servicios/')],
        ['name' => 'Traslados Nacionales', 'url' => $canonical],
    ]));

$PAGE = ['base' => '../', 'title' => $title, 'description' => $desc, 'canonical' => $canonical, 'head' => $head];
require __DIR__ . '/../partials/site_header.php';
?>
<main class="container section service-detail">
    <nav class="breadcrumb"><a href="../index.php">Inicio</a> › <a href="./">Servicios</a> › <span>Traslados Nacionales</span></nav>

    <article class="service-detail-body">
        <img src="img/traslados.svg" alt="Traslados funerarios nacionales desde y hacia el Estado Zulia — Funeraria del Zulia" class="service-hero-img" width="1200" height="600">

        <h1>Traslados Funerarios Nacionales</h1>
        <p class="service-lead">Ofrecemos <strong>traslados funerarios</strong> seguros y compasivos desde o hacia el <strong>Estado Zulia</strong>, coordinando de extremo a extremo toda la logística, los permisos y la documentación legal, con disponibilidad las 24 horas.</p>

        <h2>¿Qué incluye el servicio de traslado?</h2>
        <ul class="service-list">
            <li>Recolección y traslado del fallecido con vehículos adecuados.</li>
            <li>Gestión de <strong>permisos de traslado</strong> y normativas sanitarias.</li>
            <li>Coordinación entre ciudades y, si aplica, traslados interestatales.</li>
            <li>Preparación y acondicionamiento para el viaje.</li>
            <li>Acompañamiento e información permanente a la familia.</li>
        </ul>

        <h2>Cobertura en todo el Estado Zulia y el país</h2>
        <p>Realizamos traslados desde cualquier municipio del Zulia —San Francisco, Cabimas, Ciudad Ojeda, Santa Bárbara, Machiques y más— hacia nuestras instalaciones en Maracaibo, así como traslados hacia otras regiones del país. Cumplimos estrictamente con la normativa de sanidad vigente en cada etapa.</p>

        <h2>Integre el traslado con otros servicios</h2>
        <p>Una vez en Maracaibo, su familia puede continuar con una <a href="sepelio-tradicional.php">velación tradicional</a>, la <a href="cremacion.php">cremación</a> en nuestro crematorio, o el uso de nuestras <a href="capillas-velatorias.php">capillas velatorias</a>. También puede prever este servicio con un <a href="../planes/">plan de previsión familiar</a>.</p>

        <?php
        $ctaHeading = 'Coordine un traslado ahora';
        $ctaSub     = 'Respuesta inmediata las 24 horas, los 7 días de la semana.';
        $ctaWaText  = 'Hola, necesito coordinar un traslado funerario.';
        require __DIR__ . '/../partials/cta_contacto.php';
        ?>

        <div class="service-related">
            <h3>Otros servicios</h3>
            <ul>
                <li><a href="sepelio-tradicional.php">Sepelio Tradicional</a></li>
                <li><a href="cremacion.php">Cremación en Maracaibo</a></li>
                <li><a href="capillas-velatorias.php">Capillas Velatorias</a></li>
                <li><a href="../planes/">Planes de Previsión Familiar</a></li>
            </ul>
        </div>
    </article>
</main>
<?php require __DIR__ . '/../partials/site_footer.php'; ?>
