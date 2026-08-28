<?php
if (!defined('OBIT_APP')) { exit('Forbidden'); }
/**
 * Fase A del plan de migración a Prevision-Funeraria (ver
 * docs/specs/2026-08-28-migracion-a-prevision-funeraria.md): muestra la
 * cuota mensual real del plan, leída del catálogo público de Prevision-Funeraria
 * (tenant fdz), si la integración está habilitada y el plan existe con ese slug.
 *
 * Si 'prevision_funeraria.enabled' es false en config.php, o la API no responde,
 * o el slug no existe todavía en PF, este partial no imprime nada — la página
 * sigue exactamente igual que antes de esta integración.
 *
 * Variable requerida antes del include:
 *   $pePlanSlug  slug del plan en Prevision-Funeraria (ej. 'esencial')
 */
require_once __DIR__ . '/../api/lib/prevision_funeraria.php';

$pfPlan = (!empty($pePlanSlug) && pf_habilitado()) ? pf_find_plan_by_slug($pePlanSlug) : null;
if ($pfPlan):
    $pfMoneda = $pfPlan['moneda'] ?? 'USD';
?>
<div class="plan-price-badge">
    <span class="plan-price-amount"><?= esc(pf_fmt_precio((int)($pfPlan['precio_mensual_centavos'] ?? 0), $pfMoneda)) ?></span>
    <span class="plan-price-label">/ mes</span>
    <?php if (!empty($pfPlan['cuota_inicial_centavos'])): ?>
        <p class="plan-price-note">
            Cuota inicial: <?= esc(pf_fmt_precio((int)$pfPlan['cuota_inicial_centavos'], $pfMoneda)) ?><?= !empty($pfPlan['cuota_inicial_concepto']) ? ' — ' . esc($pfPlan['cuota_inicial_concepto']) : '' ?>
        </p>
    <?php endif; ?>
    <p class="plan-price-note plan-price-source">Precio informativo, sujeto a validación con un asesor.</p>
</div>
<?php endif; ?>
