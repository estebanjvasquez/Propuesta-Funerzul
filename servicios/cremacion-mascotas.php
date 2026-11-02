<?php
/**
 * Cremación de mascotas: servicio EN EVALUACIÓN (la empresa aún no lo ofrece).
 * La página existe para medir el interés del público: visitas, búsquedas en
 * Google (Search Console), clics de contacto y el formulario "Quiero que me
 * avisen". Ver docs/specs/2026-11-02-interes-servicios-nuevos.md.
 * Si se decide no ofrecerlo, retirar la página y su entrada del sitemap.
 */
require __DIR__ . '/../api/lib/public_init.php';
require __DIR__ . '/../api/lib/interest.php';
interest_count_page_view('cremacion-mascotas'); // antes de imprimir nada (pone una cookie)

$canonical = site_url('servicios/cremacion-mascotas.php');
$img       = site_url('servicios/img/cremacion-mascotas.svg');
$title     = 'Cremación de Mascotas en Maracaibo (próximamente) | Funeraria del Zulia';
$desc      = 'Funeraria del Zulia evalúa ofrecer cremación de mascotas en Maracaibo: individual con entrega de cenizas, retiro a domicilio y urnas. Cuéntenos qué necesitaría.';

$head = page_head_meta($title, $desc, $canonical, $img, 'website',
    breadcrumb_jsonld([
        ['name' => 'Inicio', 'url' => site_url('index.php')],
        ['name' => 'Servicios', 'url' => site_url('servicios/')],
        ['name' => 'Cremación de mascotas', 'url' => $canonical],
    ]));

$base = '../';
$PAGE = ['base' => $base, 'title' => $title, 'description' => $desc, 'canonical' => $canonical, 'head' => $head];
require __DIR__ . '/../partials/site_header.php';
$waText = 'Hola, me interesa el servicio de cremación de mascotas.';
?>
<main class="container section service-detail">
    <nav class="breadcrumb"><a href="../index.php">Inicio</a> › <a href="./">Servicios</a> › <span>Cremación de mascotas</span></nav>

    <article class="service-detail-body">
        <img src="img/cremacion-mascotas.svg" alt="Cremación de mascotas en Maracaibo — Funeraria del Zulia" class="service-hero-img" width="1200" height="600">

        <p class="service-soon-badge">Próximamente · servicio en evaluación</p>
        <h1>Cremación de Mascotas en Maracaibo</h1>
        <p class="service-lead">Una mascota es parte de la familia, y despedirla también merece respeto. En Funeraria del Zulia estamos evaluando ofrecer un servicio de <strong>cremación de mascotas en Maracaibo</strong>, con la misma seriedad y el mismo trato humano con que acompañamos a las familias zulianas desde 1942.</p>
        <p><strong>Todavía no prestamos este servicio.</strong> Antes de ofrecerlo queremos saber qué necesitan las familias. Si responde las preguntas de abajo, nos ayuda a diseñarlo, y si nos deja un dato de contacto, le avisaremos cuando esté disponible.</p>

        <h2>Lo que estamos considerando</h2>
        <ul class="service-list">
            <li><strong>Cremación individual</strong>, con entrega de las cenizas a la familia.</li>
            <li><strong>Cremación colectiva</strong>, una opción más económica sin entrega de cenizas.</li>
            <li><strong>Retiro de la mascota</strong> en el hogar o en la clínica veterinaria.</li>
            <li><strong>Urnas y recuerdos</strong>: huella, relicario o joya con cenizas.</li>
            <li>Un <strong>espacio para despedirse</strong> de la mascota antes de la cremación.</li>
            <li>Incluir a la mascota en un <strong>plan de previsión</strong> familiar.</li>
        </ul>

        <?php $interestKey = 'cremacion-mascotas'; require __DIR__ . '/../partials/interes_servicio.php'; ?>

        <div class="cta-band">
            <div class="cta-band-text">
                <h2>¿Prefiere conversarlo?</h2>
                <p>Escríbanos o llámenos: le contamos en qué punto está el servicio. Para los servicios funerarios de su familia, atendemos las 24 horas.</p>
            </div>
            <div class="cta-band-actions">
                <a href="tel:+584246950136" class="btn btn-primary btn-lg" data-interest-event="call">Llamar +58 424 695-0136</a>
                <a href="https://api.whatsapp.com/send?phone=584246950136&amp;text=<?= rawurlencode($waText) ?>" target="_blank" rel="noopener" class="btn btn-whatsapp btn-lg" data-interest-event="whatsapp">Escribir por WhatsApp</a>
            </div>
        </div>

        <div class="service-related">
            <h3>Nuestros servicios</h3>
            <ul>
                <li><a href="cremacion.php">Cremación en Maracaibo</a></li>
                <li><a href="sepelio-tradicional.php">Sepelio Tradicional</a></li>
                <li><a href="capillas-velatorias.php">Capillas Velatorias</a></li>
                <li><a href="../planes/">Planes de Previsión Familiar</a></li>
            </ul>
        </div>
    </article>
</main>
<?php require __DIR__ . '/../partials/site_footer.php'; ?>
