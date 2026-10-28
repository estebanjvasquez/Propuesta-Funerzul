<?php
/**
 * Bloque "Testimonios" (portada y nosotros.php). Espera $testimonios =
 * active_testimonials(); sin filas no imprime nada. Se administran desde el
 * panel (Sitio web → Testimonios); el interruptor está en Configuración.
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
        <div class="testimonials-grid">
            <?php foreach ($testimonios as $t): ?>
            <figure class="testimonial-card">
                <svg class="testimonial-mark" viewBox="0 0 32 24" aria-hidden="true"><path d="M0 24V14C0 6 4 1 12 0l1 3c-4 1-6 4-6 8h6v13H0zm19 0V14c0-8 4-13 12-14l1 3c-4 1-6 4-6 8h6v13H19z"/></svg>
                <blockquote><p><?= $tEsc($t['quote']) ?></p></blockquote>
                <figcaption>
                    <strong><?= $tEsc($t['author']) ?></strong>
                    <?php if (!empty($t['detail'])): ?><span><?= $tEsc($t['detail']) ?></span><?php endif; ?>
                </figcaption>
            </figure>
            <?php endforeach; ?>
        </div>
    </div>
</section>
