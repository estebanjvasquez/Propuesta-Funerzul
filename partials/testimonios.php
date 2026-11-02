<?php
/**
 * Bloque "Testimonios" (portada y nosotros.php). Espera $testimonios =
 * active_testimonials(); sin filas no imprime nada. Se administran desde el
 * panel (Sitio web → Testimonios); el interruptor está en Configuración.
 *
 * Carrusel (pedido del cliente 2026-11-02: la cuadrícula ocupaba demasiado):
 * una fila con desplazamiento horizontal y scroll-snap, que funciona sin JS;
 * app.js (initTestimonialsCarousel) añade flechas, puntos y avance automático.
 */
if (empty($testimonios)) return;
$tEsc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<section id="testimonios" class="section testimonials-section">
    <div class="container">
        <div class="section-header">
            <h2>Lo que dicen las familias</h2>
            <p>Más de 80 años acompañando a las familias zulianas en sus momentos más sensibles.</p>
        </div>
        <div class="testimonials-carousel" data-carousel aria-roledescription="carrusel" aria-label="Testimonios de las familias">
            <button type="button" class="carousel-btn carousel-prev" data-carousel-prev aria-label="Testimonio anterior" hidden>
                <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M15.4 7.4 14 6l-6 6 6 6 1.4-1.4-4.6-4.6z" fill="currentColor"/></svg>
            </button>
            <div class="testimonials-track" data-carousel-track tabindex="0">
                <?php foreach ($testimonios as $i => $t): ?>
                <figure class="testimonial-card" aria-roledescription="testimonio" aria-label="<?= $i + 1 ?> de <?= count($testimonios) ?>">
                    <svg class="testimonial-mark" viewBox="0 0 32 24" aria-hidden="true"><path d="M0 24V14C0 6 4 1 12 0l1 3c-4 1-6 4-6 8h6v13H0zm19 0V14c0-8 4-13 12-14l1 3c-4 1-6 4-6 8h6v13H19z"/></svg>
                    <blockquote><p><?= $tEsc($t['quote']) ?></p></blockquote>
                    <figcaption>
                        <strong><?= $tEsc($t['author']) ?></strong>
                        <?php if (!empty($t['detail'])): ?><span><?= $tEsc($t['detail']) ?></span><?php endif; ?>
                    </figcaption>
                </figure>
                <?php endforeach; ?>
            </div>
            <button type="button" class="carousel-btn carousel-next" data-carousel-next aria-label="Testimonio siguiente" hidden>
                <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M8.6 16.6 10 18l6-6-6-6-1.4 1.4 4.6 4.6z" fill="currentColor"/></svg>
            </button>
            <div class="carousel-dots" data-carousel-dots hidden></div>
        </div>
    </div>
</section>
