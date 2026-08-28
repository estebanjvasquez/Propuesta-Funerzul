<?php
if (!defined('OBIT_APP')) { exit('Forbidden'); }
/**
 * CTA de "pago electrónico" reutilizable en planes/servicios. No cobra nada:
 * crea una solicitud (lead) en prev_solicitudes_publicas para que un asesor
 * contacte al cliente y complete el pago de forma segura (ver docs/mercantil.md).
 * Desde la Fase B de la migración a Prevision-Funeraria (ver
 * docs/specs/2026-08-28-migracion-a-prevision-funeraria.md), el mismo envío
 * también se reenvía a Prevision-Funeraria del lado servidor (mejor esfuerzo,
 * nunca bloquea ni rompe el guardado local — ver api/prevision_solicitudes.php).
 *
 * Fase C (mismo documento): si el servicio está marcado `es_emergencia` en el
 * catálogo de Prevision-Funeraria, este partial NO muestra el formulario de
 * lead — muestra un botón directo a WhatsApp, igual que ya exige el backend
 * de Prevision-Funeraria (rechaza con 400 un lead contra un servicio así).
 * Hoy (2026-08-28) el catálogo de servicios de Prevision-Funeraria para el
 * tenant `fdz` está vacío, así que esta rama todavía no se activa en
 * producción — queda lista para cuando el staff cargue servicios ahí.
 *
 * Variables opcionales antes del include:
 *   $peInteres      (nombre del plan/servicio mostrado en la página)
 *   $peTipo         ('plan' | 'servicio')
 *   $pePlanSlug     (slug del plan en Prevision-Funeraria, si esta página es un plan)
 *   $peServicioSlug (slug del servicio en Prevision-Funeraria, si es un servicio)
 */
require_once __DIR__ . '/../api/lib/prevision_funeraria.php';

$peInteresVal      = $peInteres ?? 'este plan';
$peTipoVal         = ($peTipo ?? 'plan') === 'servicio' ? 'servicio' : 'plan';
$peApiUrl          = ($base ?? '') . 'api/prevision_solicitudes.php?action=crear';
$pePlanSlugVal     = $pePlanSlug ?? null;
$peServicioSlugVal = $peServicioSlug ?? null;

// Fase C: solo puede haber emergencia si es un servicio con slug conocido y la
// integración está habilitada; si PF no responde o no está configurado, nunca
// se bloquea el formulario normal (defecto = comportamiento de hoy).
$peWaEmergencia = null;
if ($peTipoVal === 'servicio' && $peServicioSlugVal && pf_habilitado()) {
    $svc = pf_find_servicio_by_slug($peServicioSlugVal);
    if (!empty($svc['es_emergencia'])) {
        $peWaEmergencia = pf_whatsapp_emergencia() ?: '584246950136';
    }
}
?>
<?php if ($peWaEmergencia): ?>
<div class="cta-band cta-band-emergencia">
    <div class="cta-band-text">
        <h2>Esto requiere atención inmediata</h2>
        <p><?= esc($peInteresVal) ?> es un servicio de urgencia — escríbanos ahora mismo por WhatsApp, un asesor le responde de inmediato, sin formularios.</p>
    </div>
    <div class="cta-band-actions">
        <a href="https://api.whatsapp.com/send?phone=<?= esc($peWaEmergencia) ?>&amp;text=<?= rawurlencode('Hola, necesito ' . $peInteresVal . ' con urgencia.') ?>" target="_blank" rel="noopener" class="btn btn-whatsapp btn-lg">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.514 2.266 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005 0-3.973-.5-5.743-1.455L0 24zm6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24z"/></svg>
            Escribir por WhatsApp ahora
        </a>
    </div>
</div>
<?php else: ?>
<div class="cta-band cta-band-pago">
    <div class="cta-band-text">
        <h2>¿Prefiere pagar en línea?</h2>
        <p>Déjenos sus datos y un asesor le contactará para completar el pago de <?= esc($peInteresVal) ?> de forma segura.</p>
    </div>
    <div class="cta-band-actions">
        <button type="button" class="btn btn-secondary btn-lg" onclick="openModal('peModal')">Solicitar con pago electrónico</button>
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
                plan_slug: <?= json_encode($pePlanSlugVal) ?>,
                servicio_slug: <?= json_encode($peServicioSlugVal) ?>,
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
<?php endif; ?>
