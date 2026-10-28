<?php
/**
 * Nosotros — historia de Funeraria del Zulia (server-rendered, SEO/GEO).
 * Texto tomado de la página "Nosotros" del WordPress anterior. Se puede apagar
 * desde el panel (Configuración → Secciones del sitio → Nosotros); los
 * testimonios del final tienen su propio interruptor.
 */
require __DIR__ . '/api/lib/public_init.php';

if (!site_section_enabled('nosotros')) {
    http_response_code(404);
    $PAGE = ['title' => 'Sección no disponible | Funeraria del Zulia', 'description' => 'Esta sección no está disponible en este momento.'];
    require __DIR__ . '/partials/site_header.php';
    echo '<main class="container section"><div class="section-header"><h2>Sección no disponible</h2><p>Esta sección no está disponible en este momento.</p></div><div style="text-align:center"><a class="btn btn-primary" href="index.php">Volver al inicio</a></div></main>';
    require __DIR__ . '/partials/site_footer.php';
    exit;
}

$canonical = site_url('nosotros.php');
$img       = site_url('logo.png');
$title     = 'Nosotros | Funeraria del Zulia, desde 1942 en Maracaibo';
$desc      = 'Desde 1942, cuatro generaciones de la familia Villalobos acompañan a las familias zulianas: Funeraria del Zulia, Familias Protegidas y Crematorios del Zulia.';

$aboutJsonLd = '<script type="application/ld+json">' . json_encode([
    '@context'   => 'https://schema.org',
    '@type'      => 'AboutPage',
    'name'       => 'Nosotros',
    'url'        => $canonical,
    'description'=> $desc,
    'mainEntity' => [
        '@type'        => 'FuneralHome',
        'name'         => 'Funeraria del Zulia',
        'url'          => site_url(),
        'foundingDate' => '1942',
        'founders'     => [
            ['@type' => 'Person', 'name' => 'Horacio Villalobos'],
            ['@type' => 'Person', 'name' => 'Tarcila de Villalobos'],
        ],
        'address'      => ['@type' => 'PostalAddress', 'addressLocality' => 'Maracaibo', 'addressRegion' => 'Zulia', 'addressCountry' => 'VE'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';

$head = page_head_meta($title, $desc, $canonical, $img, 'website',
    $aboutJsonLd
    . breadcrumb_jsonld([
        ['name' => 'Inicio', 'url' => site_url('index.php')],
        ['name' => 'Nosotros', 'url' => $canonical],
    ]));

$hitos = [
    ['1942', 'El comienzo', 'En una pequeña casa de la avenida 4 Bella Vista de Maracaibo, Don Horacio Villalobos y su esposa Tarcila inician el negocio familiar, con una exhibición de ataúdes fabricados por artesanos y los equipos necesarios para realizar los actos velatorios a domicilio.'],
    ['1945', 'Servicio las 24 horas', 'Tres años más tarde se mudan, en la misma avenida, a un inmueble más grande para prestar servicio las 24 horas en los hogares marabinos.'],
    ['1962', 'Una nueva visión', 'Euro Villalobos Angulo, hijo de Don Horacio y Doña Tarcila, propone a la ciudad un cambio en el servicio funerario: sustituir el funeral en el domicilio por salones especialmente diseñados para el acto de despedida.'],
    ['1964', 'Las primeras salas velatorias del país', 'Sin auxilio financiero, por lo novedoso de la idea, y solo con recursos propios, en 1963 comienza la construcción de una edificación con tres salas velatorias, cafetería, espacios al aire libre y estacionamiento. Se estrenan un año después, por primera vez no solo en el Zulia sino en todo el territorio nacional.'],
    ['Tercera generación', 'Familias Protegidas', 'Con la tercera generación incorporada a esta labor nace Familias Protegidas C.A., una empresa de previsión para acompañar al mayor número de familias, minimizar sus gastos y apoyarlas con personal calificado en los trámites del funeral.'],
    ['2021', 'Crematorios del Zulia', 'Con una cuarta generación unida a esta noble labor se crea Crematorios del Zulia C.A., para seguir sirviendo después del funeral con la misma calidad, inmediatez e integralidad a quienes eligen la cremación. Con una ubicación privilegiada, es el crematorio más cercano y seguro del estado Zulia.'],
];

$testimonios = active_testimonials();

$PAGE = ['base' => '', 'title' => $title, 'description' => $desc, 'canonical' => $canonical, 'head' => $head];
require __DIR__ . '/partials/site_header.php';
?>
<main>
    <div class="container section service-detail">
        <nav class="breadcrumb"><a href="index.php">Inicio</a> › <span>Nosotros</span></nav>

        <article class="service-detail-body">
            <h1>Nosotros</h1>
            <p class="service-lead">Desde <strong>1942</strong>, cuatro generaciones de la familia Villalobos acompañan a las familias zulianas en sus momentos más sensibles, haciendo de cada despedida <strong>un homenaje a la vida</strong>.</p>

            <h2>Nuestra historia</h2>
            <ol class="about-timeline">
                <?php foreach ($hitos as [$cuando, $titulo, $texto]): ?>
                <li>
                    <span class="about-timeline-year"><?= esc($cuando) ?></span>
                    <div>
                        <h3><?= esc($titulo) ?></h3>
                        <p><?= esc($texto) ?></p>
                    </div>
                </li>
                <?php endforeach; ?>
            </ol>

            <h2>Hoy</h2>
            <p>Más de 80 años después, nos llenamos de gratitud hacia quienes han confiado en nosotros y empeñamos nuestra palabra para estar presentes y honrar el deber de atención en los momentos más sensibles de las familias zulianas, haciendo de sus despedidas un homenaje a la vida.</p>

            <div class="service-related">
                <h3>Conozca nuestros servicios</h3>
                <ul>
                    <li><a href="servicios/">Servicios funerarios</a></li>
                    <li><a href="servicios/capillas-velatorias.php">Capillas Velatorias</a></li>
                    <li><a href="crematorios-del-zulia.php">Crematorios del Zulia</a></li>
                    <li><a href="planes/">Planes de Previsión Familiar</a></li>
                </ul>
            </div>
        </article>
    </div>

    <?php require __DIR__ . '/partials/testimonios.php'; ?>
</main>
<?php require __DIR__ . '/partials/site_footer.php'; ?>
