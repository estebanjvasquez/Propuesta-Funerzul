<?php
if (!defined('OBIT_APP')) { exit('Forbidden'); }
/**
 * Formulario "Quiero que me avisen" de un servicio en evaluación
 * (docs/specs/2026-11-02-interes-servicios-nuevos.md). Las preguntas salen de
 * INTEREST_SERVICES (api/lib/interest.php). Variables antes del include:
 *   $interestKey  clave del servicio (obligatoria)
 *   $base         prefijo relativo a la raíz del sitio ('' o '../')
 * También cuenta los clics de los enlaces de la página que lleven
 * data-interest-event="whatsapp|call".
 */
require_once __DIR__ . '/../api/lib/interest.php';
$isvc   = INTEREST_SERVICES[$interestKey];
$iApi   = ($base ?? '') . 'api/interest.php';
?>
<section class="interest-box" id="avisenme" aria-labelledby="interestTitle">
    <h2 id="interestTitle">Cuéntenos qué necesitaría</h2>
    <p class="interest-lead">Sus respuestas nos ayudan a decidir cómo ofrecer este servicio. Toma menos de un minuto. Los datos de contacto son <strong>opcionales</strong>: déjelos solo si quiere que le avisemos cuando esté disponible.</p>

    <form id="interestForm" class="interest-form" novalidate>
        <input type="text" name="website" autocomplete="off" tabindex="-1" class="interest-hp" aria-hidden="true">

        <?php foreach ($isvc['questions'] as $q => $qd): ?>
        <fieldset class="interest-q">
            <legend><?= esc($qd['label']) ?></legend>
            <div class="interest-options">
                <?php foreach ($qd['options'] as $ok => $ol): ?>
                <label class="interest-option">
                    <input type="<?= $qd['multi'] ? 'checkbox' : 'radio' ?>" name="<?= esc($q) ?>" value="<?= esc($ok) ?>">
                    <span><?= esc($ol) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <?php endforeach; ?>

        <fieldset class="interest-q">
            <legend>¿Quiere que le avisemos? <span class="interest-optional">(opcional)</span></legend>
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label" for="if_name">Nombre</label>
                    <input type="text" id="if_name" name="full_name" class="form-control" maxlength="150" autocomplete="name"></div>
                <div class="form-group"><label class="form-label" for="if_phone">Teléfono o WhatsApp</label>
                    <input type="tel" id="if_phone" name="phone" class="form-control" maxlength="40" placeholder="0414-1234567" autocomplete="tel"></div>
            </div>
            <div class="form-group"><label class="form-label" for="if_email">Correo</label>
                <input type="email" id="if_email" name="email" class="form-control" maxlength="190" autocomplete="email"></div>
            <div class="form-group"><label class="form-label" for="if_comment">¿Algo más que quiera decirnos?</label>
                <textarea id="if_comment" name="comment" class="form-control" rows="3" maxlength="1000"></textarea></div>
            <label class="interest-consent">
                <input type="checkbox" name="consent" value="1">
                <span>Autorizo a Funeraria del Zulia a contactarme sobre este servicio. Mis datos no se comparten con terceros.</span>
            </label>
        </fieldset>

        <button type="submit" class="btn btn-primary btn-lg">Enviar mis respuestas</button>
        <p class="interest-msg" id="interestMsg" role="status" hidden></p>
    </form>
</section>

<script>
(function () {
    const API = <?= json_encode($iApi) ?>;
    const KEY = <?= json_encode($interestKey) ?>;
    const post = (action, body) => fetch(API + '?action=' + action, {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body), keepalive: true,
    });
    const once = (k) => { try { if (sessionStorage.getItem(k)) return false; sessionStorage.setItem(k, '1'); } catch (e) {} return true; };
    const ga = (accion) => { if (typeof window.gtag === 'function') window.gtag('event', 'interes_servicio', { servicio: KEY, accion }); };

    // Clics en WhatsApp / llamar: una vez por sesión y tipo.
    document.querySelectorAll('[data-interest-event]').forEach(a => a.addEventListener('click', () => {
        const ev = a.dataset.interestEvent;
        if (once('fz_i_' + KEY + '_' + ev)) post('track', { subject: KEY, event: ev });
        ga(ev);
    }));

    // Origen de la visita: utm_source o el sitio desde el que llegó.
    const params = new URLSearchParams(location.search);
    let source = params.get('utm_source') || '';
    if (!source && document.referrer) { try { const h = new URL(document.referrer).hostname; if (h !== location.hostname) source = h; } catch (e) {} }

    const form = document.getElementById('interestForm');
    const msg = document.getElementById('interestMsg');
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        const answers = {};
        form.querySelectorAll('.interest-q input[type=radio]:checked').forEach(i => { answers[i.name] = i.value; });
        form.querySelectorAll('.interest-q input[type=checkbox]:checked').forEach(i => {
            if (i.name === 'consent') return;
            (answers[i.name] = answers[i.name] || []).push(i.value);
        });
        const btn = form.querySelector('button[type=submit]');
        btn.disabled = true;
        msg.hidden = true;
        try {
            const r = await post('respond', {
                service: KEY, answers, source: source.slice(0, 120),
                full_name: fd.get('full_name'), phone: fd.get('phone'), email: fd.get('email'),
                comment: fd.get('comment'), consent: fd.get('consent') === '1', website: fd.get('website'),
            });
            const d = await r.json();
            if (!r.ok || !d.ok) throw new Error(d.error || 'No se pudo enviar.');
            ga('respuesta');
            form.innerHTML = '<p class="interest-thanks"><strong>¡Gracias!</strong> Recibimos sus respuestas.' +
                ((fd.get('phone') || fd.get('email')) ? ' Le avisaremos en cuanto el servicio esté disponible.' : '') + '</p>';
        } catch (err) {
            msg.textContent = err.message;
            msg.hidden = false;
            btn.disabled = false;
        }
    });
})();
</script>
