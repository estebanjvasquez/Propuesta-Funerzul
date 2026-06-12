<?php
if (!defined('OBIT_APP')) { exit('Forbidden'); }
$P = $PAGE ?? [];
$title     = $P['title'] ?? 'Funeraria del Zulia';
$desc      = $P['description'] ?? 'Servicios funerarios en Maracaibo, Estado Zulia. Atención inmediata 24/7.';
$canonical = $P['canonical'] ?? '';
$headExtra = $P['head'] ?? '';
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title) ?></title>
    <meta name="description" content="<?= esc($desc) ?>">
    <?php if ($canonical): ?><link rel="canonical" href="<?= esc($canonical) ?>"><?php endif; ?>
    <link rel="icon" href="favicon.png" type="image/png">
    <link rel="stylesheet" href="styles.css?v=20260612">
    <?= $headExtra ?>
</head>
<body>
    <div class="emergency-bar">
        <div class="container">
            <div class="info">
                <span class="emergency-pulse" aria-hidden="true"></span>
                <span class="emergency-text">Atención Inmediata 24/7 en Maracaibo y todo el Zulia</span>
            </div>
            <a href="tel:+584246950136" class="phone-link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.01-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>
                Llamar Ahora: +58 424 695-0136
            </a>
        </div>
    </div>

    <header class="header">
        <div class="container">
            <a href="index.html" class="logo" aria-label="Funeraria del Zulia - Inicio">
                <img src="logo-horizontal-white.png" alt="Funeraria del Zulia" class="header-logo-img">
            </a>
            <nav>
                <ul class="nav-links">
                    <li><a href="index.html#servicios">Servicios</a></li>
                    <li><a href="index.html#prevision">Previsión</a></li>
                    <li><a href="obituarios.php" class="active">Obituarios</a></li>
                    <li><a href="index.html#preguntas">Preguntas Frecuentes</a></li>
                    <li><a href="index.html#contacto">Contacto</a></li>
                </ul>
            </nav>
            <div class="header-actions">
                <a href="tel:+584246950136" class="btn btn-secondary btn-sm">Emergencias 24/7</a>
            </div>
        </div>
    </header>
