<?php
/** Servicio: Sepelio Tradicional (server-rendered, SEO/GEO). */
require __DIR__ . '/../api/lib/public_init.php';

$canonical = site_url('servicios/sepelio-tradicional.php');
$img       = site_url('servicios/img/sepelio-tradicional.svg');
$title     = 'Sepelio Tradicional en Maracaibo | Velación y Oficios — Funeraria del Zulia';
$desc      = 'Servicio de sepelio tradicional en Maracaibo, Estado Zulia: velación en capilla, oficios religiosos, arreglos florales y gestión completa, con respeto a sus creencias y atención 24/7.';

$head = page_head_meta($title, $desc, $canonical, $img, 'website',
    service_jsonld('Sepelio Tradicional', $desc, $canonical, $img)
    . breadcrumb_jsonld([
        ['name' => 'Inicio', 'url' => site_url('index.php')],
        ['name' => 'Servicios', 'url' => site_url('servicios/')],
        ['name' => 'Sepelio Tradicional', 'url' => $canonical],
    ]));

$PAGE = ['base' => '../', 'title' => $title, 'description' => $desc, 'canonical' => $canonical, 'head' => $head];
require __DIR__ . '/../partials/site_header.php';
?>
<main class="container section service-detail">
    <nav class="breadcrumb"><a href="../index.php">Inicio</a> › <a href="./">Servicios</a> › <span>Sepelio Tradicional</span></nav>

    <article class="service-detail-body">
        <img src="img/sepelio-tradicional.svg" alt="Sepelio tradicional y velación en Maracaibo — Funeraria del Zulia" class="service-hero-img" width="1200" height="600">

        <h1>Sepelio Tradicional en Maracaibo</h1>
        <p class="service-lead">El <strong>sepelio tradicional</strong> de Funeraria del Zulia ofrece una despedida solemne y respetuosa, fiel a sus creencias y tradiciones familiares. Acompañamos a las familias de Maracaibo y todo el Estado Zulia con cercanía humana y atención las 24 horas.</p>

        <h2>¿Qué incluye el sepelio tradicional?</h2>
        <ul class="service-list">
            <li><strong>Velación</strong> en nuestras capillas climatizadas y serenas.</li>
            <li>Cofre (ataúd) y preparación del fallecido con respeto.</li>
            <li><strong>Oficios religiosos</strong> según sus creencias.</li>
            <li>Arreglos florales, cafetería y atención a los acompañantes.</li>
            <li>Traslados locales y gestión administrativa y documental.</li>
        </ul>

        <h2>Una despedida digna en el Estado Zulia</h2>
        <p>Comprendemos que cada familia vive el duelo de manera única. Por eso adaptamos el servicio a sus tradiciones, ofreciendo un espacio de paz y recogimiento para reunir a familiares y amigos. Nuestro equipo coordina cada detalle —desde la <a href="capillas-velatorias.php">sala velatoria</a> hasta los oficios— para que usted solo se ocupe de acompañar y recordar.</p>

        <h2>Atención inmediata en Maracaibo y todo el Zulia</h2>
        <p>Atendemos en Maracaibo, San Francisco, Cabimas, Ciudad Ojeda y todo el Estado Zulia. Si lo desea, también ofrecemos <a href="cremacion.php">cremación</a> y <a href="traslados.php">traslados nacionales</a>, así como <a href="../planes/">planes de previsión</a> para organizar el servicio con anticipación.</p>

        <?php
        $ctaHeading = 'Coordine un sepelio con acompañamiento';
        $ctaSub     = 'Estamos disponibles las 24 horas en Maracaibo y todo el Estado Zulia.';
        $ctaWaText  = 'Hola, deseo información sobre el servicio de sepelio tradicional.';
        require __DIR__ . '/../partials/cta_contacto.php';
        ?>

        <div class="service-related">
            <h3>Otros servicios</h3>
            <ul>
                <li><a href="cremacion.php">Cremación en Maracaibo</a></li>
                <li><a href="traslados.php">Traslados Nacionales</a></li>
                <li><a href="capillas-velatorias.php">Capillas Velatorias</a></li>
                <li><a href="../planes/">Planes de Previsión Familiar</a></li>
            </ul>
        </div>
    </article>
</main>
<?php require __DIR__ . '/../partials/site_footer.php'; ?>
