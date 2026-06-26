<?php
/** Servicio: Cremación en Maracaibo (server-rendered, SEO/GEO). */
require __DIR__ . '/../api/lib/public_init.php';

$canonical = site_url('servicios/cremacion.php');
$img       = site_url('servicios/img/cremacion.svg');
$title     = 'Cremación en Maracaibo | Servicio de Cremación 24/7 — Funeraria del Zulia';
$desc      = 'Servicio de cremación en Maracaibo, Estado Zulia, con crematorio propio. Cremación directa o con velación previa, gestión completa de permisos y acompañamiento las 24 horas.';

$head = page_head_meta($title, $desc, $canonical, $img, 'website',
    service_jsonld('Cremación en Maracaibo', $desc, $canonical, $img)
    . breadcrumb_jsonld([
        ['name' => 'Inicio', 'url' => site_url('index.php')],
        ['name' => 'Servicios', 'url' => site_url('servicios/')],
        ['name' => 'Cremación', 'url' => $canonical],
    ]));

$PAGE = ['base' => '../', 'title' => $title, 'description' => $desc, 'canonical' => $canonical, 'head' => $head];
require __DIR__ . '/../partials/site_header.php';
?>
<main class="container section service-detail">
    <nav class="breadcrumb"><a href="../index.php">Inicio</a> › <a href="./">Servicios</a> › <span>Cremación</span></nav>

    <article class="service-detail-body">
        <img src="img/cremacion.svg" alt="Servicio de cremación en Maracaibo — Funeraria del Zulia" class="service-hero-img" width="1200" height="600">

        <h1>Servicio de Cremación en Maracaibo</h1>
        <p class="service-lead">En Funeraria del Zulia ofrecemos un servicio de <strong>cremación en Maracaibo</strong> con crematorio propio, realizado con la máxima transparencia, dignidad y cumplimiento normativo. Acompañamos a su familia en cada paso, las 24 horas del día, en toda el área del Estado Zulia.</p>

        <h2>¿Qué incluye nuestro servicio de cremación?</h2>
        <ul class="service-list">
            <li><strong>Cremación directa</strong> o con <strong>velación previa</strong> en nuestras capillas.</li>
            <li>Gestión completa del <strong>permiso sanitario de cremación</strong> y la documentación legal.</li>
            <li>Cofre para cremación y <strong>urna</strong> para la entrega de las cenizas.</li>
            <li>Traslado del fallecido hacia nuestras instalaciones en Maracaibo.</li>
            <li>Acompañamiento y asesoría a la familia durante todo el proceso.</li>
        </ul>

        <h2>Requisitos para la cremación en el Estado Zulia</h2>
        <p>Para realizar una cremación se requieren el <strong>Certificado Médico de Defunción (Forma EV-14)</strong>, el <strong>acta de defunción</strong> emitida por el Registro Civil, el <strong>permiso sanitario de cremación</strong> de la autoridad competente y la <strong>autorización escrita</strong> del familiar responsable. Nuestro equipo coordina la totalidad de estos trámites por usted, para que solo se ocupe de despedir a su ser querido.</p>

        <h2>Cremación con respeto y cercanía en Maracaibo y todo el Zulia</h2>
        <p>Atendemos a familias de Maracaibo, San Francisco, Cabimas, Ciudad Ojeda y todo el Estado Zulia. Si el fallecimiento ocurrió fuera de la ciudad, gestionamos el <a href="traslados.php">traslado</a> cumpliendo las normativas sanitarias vigentes. También puede optar por una <a href="sepelio-tradicional.php">velación previa</a> en nuestras <a href="capillas-velatorias.php">capillas velatorias</a> antes de la cremación.</p>

        <?php
        $ctaHeading = 'Solicite información sobre la cremación';
        $ctaSub     = 'Atención inmediata las 24 horas en Maracaibo y todo el Estado Zulia.';
        $ctaWaText  = 'Hola, deseo información sobre el servicio de cremación en Maracaibo.';
        require __DIR__ . '/../partials/cta_contacto.php';
        ?>

        <div class="service-related">
            <h3>Otros servicios</h3>
            <ul>
                <li><a href="sepelio-tradicional.php">Sepelio Tradicional</a></li>
                <li><a href="traslados.php">Traslados Nacionales</a></li>
                <li><a href="capillas-velatorias.php">Capillas Velatorias</a></li>
                <li><a href="../planes/">Planes de Previsión Familiar</a></li>
            </ul>
        </div>
    </article>
</main>
<?php require __DIR__ . '/../partials/site_footer.php'; ?>
