<?php
if (!defined('OBIT_APP')) { exit('Forbidden'); }
/**
 * CTA de "pago electrónico" reutilizable en planes/servicios. No cobra nada:
 * crea una solicitud (lead) en prev_solicitudes_publicas para que un asesor
 * contacte al cliente y complete el pago de forma segura (ver docs/mercantil.md).
 * Variables opcionales antes del include:
 *   $peInteres (nombre del plan/servicio mostrado en la página)
 *   $peTipo    ('plan' | 'servicio')
 */
$peInteresVal = $peInteres ?? 'este plan';
$peTipoVal    = ($peTipo ?? 'plan') === 'servicio' ? 'servicio' : 'plan';
$peApiUrl     = ($base ?? '') . 'api/prevision_solicitudes.php?action=crear';
?>
<div class="cta-band cta-band-pago">
    <div class="cta-band-text">
        <h2>¿Prefiere pagar en línea?</h2>
        <p>Déjenos sus datos y un asesor le contactará para completar el pago de <?= esc($peInteresVal) ?> de forma segura.</p>
    </div>
    <div class="cta-band-actions">
        <button type="button" class="btn btn-outline btn-lg" onclick="openModal('peModal')">Solicitar con pago electrónico</button>
    </div>
</div>

<div id="peModal" class="modal-overlay">
    <div class="modal-window">
        <div class="modal-header">
            <h3>Solicitar pago electrónico</h3>
            <button class="modal-close-btn" onclick="closeModal('peModal')" aria-label="Cerrar modal">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <p style="margin-bottom:16px;">Esto todavía no procesa ningún cobro: un asesor le contactará para confirmar el pago por Botón de Pagos Web o Pago Móvil de forma segura.</p>
            <form id="peForm" onsubmit="handlePeSubmit(event)">
                <input type="text" id="pe_hp" autocomplete="off" tabindex="-1" style="position:absolute;left:-9999px" aria-hidden="true">
                <div class="form-grid-2">
                    <div class="form-group"><label class="form-label" for="pe_nombres">Nombres *</label>
                        <input type="text" id="pe_nombres" class="form-control" required></div>
                    <div class="form-group"><label class="form-label" for="pe_apellidos">Apellidos *</label>
                        <input type="text" id="pe_apellidos" class="form-control" required></div>
                </div>
                <div class="form-grid-2">
                    <div class="form-group"><label class="form-label" for="pe_telefono">Teléfono *</label>
                        <input type="tel" id="pe_telefono" class="form-control" placeholder="0414-1234567" required></div>
                    <div class="form-group"><label class="form-label" for="pe_cedula">Cédula</label>
                        <input type="text" id="pe_cedula" class="form-control" placeholder="V-12345678"></div>
                </div>
                <div class="form-grid-2">
                    <div class="form-group"><label class="form-label" for="pe_email">Correo</label>
                        <input type="email" id="pe_email" class="form-control"></div>
                    <div class="form-group"><label class="form-label" for="pe_metodo">Método preferido</label>
                        <select id="pe_metodo" class="form-control">
                            <option value="boton_web">Botón de Pagos Web</option>
                            <option value="c2p">Pago Móvil C2P</option>
                            <option value="otro">Otro</option>
                        </select></div>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;">Enviar solicitud</button>
            </form>
        </div>
    </div>
</div>

<script>
async function handlePeSubmit(e) {
    e.preventDefault();
    const nombres = document.getElementById('pe_nombres').value.trim();
    const apellidos = document.getElementById('pe_apellidos').value.trim();
    const telefono = document.getElementById('pe_telefono').value.trim();
    if (!nombres || !apellidos || !telefono) return;
    try {
        const r = await fetch(<?= json_encode($peApiUrl) ?>, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tipo: <?= json_encode($peTipoVal) ?>, interes: <?= json_encode($peInteresVal) ?>,
                nombres, apellidos, telefono,
                cedula: document.getElementById('pe_cedula').value.trim(),
                email: document.getElementById('pe_email').value.trim(),
                metodo_pago: document.getElementById('pe_metodo').value,
                hp: document.getElementById('pe_hp').value,
            }),
        });
        const d = await r.json();
        if (!r.ok || !d.ok) throw new Error(d.error || ('Error ' + r.status));
        document.getElementById('peForm').reset();
        closeModal('peModal');
        showToast(d.message || 'Solicitud enviada.');
    } catch (ex) { showToast(ex.message); }
}
</script>
