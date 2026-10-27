<?php
if (!defined('OBIT_APP')) { exit('Forbidden'); }
/**
 * Google Analytics 4 + verificación de Google Search Console en el <head> de
 * las páginas públicas (partials/site_header.php e index.php; nunca en
 * admin.html). Solo imprime algo si el admin cargó los IDs en Configuración
 * → "Analítica (Google)". Ver google_tracking_ids() en api/lib/helpers.php y
 * docs/specs/2026-10-27-enlaces-redes-y-analitica.md.
 */
$googleIds = function_exists('google_tracking_ids') ? google_tracking_ids() : ['ga4' => null, 'gsc' => null];
?>
<?php if ($googleIds['gsc']): ?>
    <meta name="google-site-verification" content="<?= htmlspecialchars($googleIds['gsc'], ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
<?php if ($googleIds['ga4']): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($googleIds['ga4'], ENT_QUOTES, 'UTF-8') ?>"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', <?= json_encode($googleIds['ga4']) ?>);
    </script>
<?php endif; ?>
