<?php
/** Crematorios del Zulia — crematorio propio en Maracaibo (server-rendered, SEO/GEO). */
require __DIR__ . '/api/lib/public_init.php';

$canonical = site_url('crematorios-del-zulia.php');
$img       = site_url('img/crematorio.svg');
$title     = 'Crematorios del Zulia | Crematorio Propio en Maracaibo — Funeraria del Zulia';
$desc      = 'Crematorios del Zulia: crematorio propio en Maracaibo, Estado Zulia. Cremación digna, con todos los permisos y acompañamiento las 24 horas, sin recorrer largas distancias.';

// Preguntas frecuentes sobre cremación (FAQPage JSON-LD)
$faqs = [
    ['q' => '¿Qué son los Crematorios del Zulia?',
     'a' => 'Es la unidad de cremación de la Funeraria del Zulia, con crematorio propio en Maracaibo. Atendemos a las familias que prefieren la cremación como forma de disposición final, con dignidad y sin necesidad de recorrer largas distancias.'],
    ['q' => '¿Cuánto tarda una cremación?',
     'a' => 'El proceso de cremación en sí toma entre dos y tres horas. La entrega de las cenizas a la familia se coordina una vez concluido el proceso y verificada toda la documentación.'],
    ['q' => '¿Qué requisitos se necesitan para cremar en el Estado Zulia?',
     'a' => 'Se requieren el Certificado Médico de Defunción (Forma EV-14), el acta de defunción del Registro Civil, el permiso sanitario de cremación y la autorización escrita del familiar responsable. Nuestro equipo gestiona todos estos trámites por usted.'],
    ['q' => '¿Se puede velar antes de la cremación?',
     'a' => 'Sí. Puede optar por una velación previa en nuestras capillas velatorias y, posteriormente, realizar la cremación. También ofrecemos cremación directa, sin velación.'],
    ['q' => '¿Qué se hace con las cenizas?',
     'a' => 'Las cenizas se entregan a la familia en una urna. Puede conservarlas, depositarlas en un cementerio o columbario, o disponer de ellas según sus creencias y la normativa vigente.'],
];

$faqJsonLd = '<script type="application/ld+json">' . json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'FAQPage',
    'mainEntity' => array_map(fn($f) => [
        '@type' => 'Question', 'name' => $f['q'],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
    ], $faqs),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';

$head = page_head_meta($title, $desc, $canonical, $img, 'website',
    service_jsonld('Crematorios del Zulia', $desc, $canonical, $img)
    . $faqJsonLd
    . breadcrumb_jsonld([
        ['name' => 'Inicio', 'url' => site_url('index.php')],
        ['name' => 'Crematorios del Zulia', 'url' => $canonical],
    ]));

$PAGE = ['base' => '', 'title' => $title, 'description' => $desc, 'canonical' => $canonical, 'head' => $head];
require __DIR__ . '/partials/site_header.php';
?>
<main class="container section service-detail">
    <nav class="breadcrumb"><a href="index.php">Inicio</a> › <span>Crematorios del Zulia</span></nav>

    <article class="service-detail-body">
        <img src="img/crematorio.svg" alt="Crematorios del Zulia — crematorio propio en Maracaibo" class="service-hero-img" width="1200" height="600">

        <h1>Crematorios del Zulia</h1>
        <p class="service-lead">Desde 1942 hacemos de cada despedida un homenaje a la vida. En <strong>Crematorios del Zulia</strong> servimos a las familias que prefieren la <strong>cremación</strong> como forma de disposición final, con <strong>crematorio propio en Maracaibo</strong> y sin que tengan que recorrer largas distancias para lograrlo.</p>

        <h2>Un crematorio propio, cercano y seguro</h2>
        <p>La necesidad de continuar acompañando a las familias después del funeral, garantizando <strong>calidad, cercanía y seguridad</strong>, nos llevó a consolidar nuestro servicio de cremación en el Estado Zulia. Contar con crematorio propio nos permite ofrecer un proceso <strong>transparente, digno y ágil</strong>, cumpliendo cada norma sanitaria y legal, dentro de la misma ciudad.</p>

        <h2>¿Qué incluye el servicio de cremación?</h2>
        <ul class="service-list">
            <li><strong>Cremación directa</strong> o con <strong>velación previa</strong> en nuestras capillas velatorias.</li>
            <li>Gestión completa del <strong>permiso sanitario de cremación</strong> y toda la documentación legal.</li>
            <li>Cofre para cremación y <strong>urna</strong> para la entrega de las cenizas.</li>
            <li>Traslado del fallecido hacia nuestras instalaciones en Maracaibo.</li>
            <li>Acompañamiento y asesoría a la familia durante todo el proceso, las 24 horas.</li>
        </ul>

        <h2>El proceso de cremación, paso a paso</h2>
        <ol class="service-list">
            <li><strong>Atención inicial y trámites:</strong> coordinamos el retiro del fallecido y reunimos la documentación necesaria.</li>
            <li><strong>Velación (opcional):</strong> si la familia lo desea, se realiza una vigilia en nuestras capillas antes de la cremación.</li>
            <li><strong>Permiso de cremación:</strong> tramitamos el permiso sanitario ante la autoridad competente.</li>
            <li><strong>Cremación:</strong> el proceso se realiza en nuestro crematorio bajo estricto control y respeto.</li>
            <li><strong>Entrega de cenizas:</strong> las cenizas se entregan a la familia en una urna, listas para su conservación o destino final.</li>
        </ol>

        <h2>Ventajas de elegir la cremación</h2>
        <ul class="service-list">
            <li>Una alternativa <strong>digna y serena</strong>, respetuosa de sus creencias y voluntad.</li>
            <li>Mayor <strong>flexibilidad</strong> para conservar o disponer de las cenizas según su deseo.</li>
            <li>Todo el proceso <strong>en Maracaibo</strong>, sin recorrer largas distancias.</li>
            <li>Acompañamiento profesional y <strong>atención inmediata las 24 horas</strong>.</li>
        </ul>

        <h2>Requisitos para la cremación</h2>
        <p>Para realizar una cremación se requieren el <strong>Certificado Médico de Defunción (Forma EV-14)</strong>, el <strong>acta de defunción</strong> emitida por el Registro Civil, el <strong>permiso sanitario de cremación</strong> de la autoridad competente y la <strong>autorización escrita</strong> del familiar responsable. Nuestro equipo coordina la totalidad de estos trámites por usted, para que solo se ocupe de despedir a su ser querido.</p>

        <?php
        $ctaHeading = 'Solicite información sobre Crematorios del Zulia';
        $ctaSub     = 'Atención inmediata las 24 horas en Maracaibo y todo el Estado Zulia.';
        $ctaWaText  = 'Hola, deseo información sobre el servicio de cremación en Crematorios del Zulia.';
        require __DIR__ . '/partials/cta_contacto.php';
        ?>

        <h2>Preguntas frecuentes sobre la cremación</h2>
        <div class="faq-list">
            <?php foreach ($faqs as $f): ?>
            <details class="faq-item">
                <summary>
                    <?= esc($f['q']) ?>
                    <svg class="arrow-icon" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                </summary>
                <div class="faq-content"><?= esc($f['a']) ?></div>
            </details>
            <?php endforeach; ?>
        </div>

        <div class="service-related">
            <h3>También le puede interesar</h3>
            <ul>
                <li><a href="servicios/cremacion.php">Servicio de Cremación</a></li>
                <li><a href="servicios/capillas-velatorias.php">Capillas Velatorias</a></li>
                <li><a href="servicios/traslados.php">Traslados Nacionales</a></li>
                <li><a href="planes/">Planes de Previsión Familiar</a></li>
            </ul>
        </div>
    </article>
</main>
<?php require __DIR__ . '/partials/site_footer.php'; ?>
