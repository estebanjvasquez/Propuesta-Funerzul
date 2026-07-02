<?php
if (!defined('OBIT_APP')) { exit('Forbidden'); }
/**
 * Banda de contacto reutilizable (botones Llamar + WhatsApp).
 * Variables opcionales antes del include:
 *   $ctaHeading, $ctaSub, $ctaTel, $ctaTelDisplay, $ctaWa, $ctaWaText
 */
$tel        = $ctaTel        ?? '+584246950136';
$telDisplay = $ctaTelDisplay ?? '+58 424 695-0136';
$wa         = $ctaWa         ?? '584246950136';
$waText     = $ctaWaText     ?? 'Hola, quisiera información sobre sus servicios.';
$heading    = $ctaHeading    ?? '¿Necesita atención ahora?';
$sub        = $ctaSub        ?? 'Estamos disponibles las 24 horas, los 7 días, en Maracaibo y todo el Estado Zulia.';
?>
<div class="cta-band">
    <div class="cta-band-text">
        <h2><?= esc($heading) ?></h2>
        <p><?= esc($sub) ?></p>
    </div>
    <div class="cta-band-actions">
        <a href="tel:<?= esc($tel) ?>" class="btn btn-primary btn-lg">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.01-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>
            Llamar <?= esc($telDisplay) ?>
        </a>
        <a href="https://api.whatsapp.com/send?phone=<?= esc($wa) ?>&amp;text=<?= rawurlencode($waText) ?>" target="_blank" rel="noopener" class="btn btn-whatsapp btn-lg">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.514 2.266 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005 0-3.973-.5-5.743-1.455L0 24zm6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24z"/></svg>
            Escribir por WhatsApp
        </a>
    </div>
</div>
