// ==========================================================================
//  LÓGICA PÚBLICA (app.js) — Funeraria del Zulia
//  Consume la API PHP (/api). El home muestra los obituarios destacados/recientes.
// ==========================================================================

const API_BASE = 'api/';

async function apiGet(path) {
    const r = await fetch(API_BASE + path, { credentials: 'include' });
    const d = await r.json();
    if (!r.ok || !d.ok) throw new Error(d.error || ('Error ' + r.status));
    return d;
}
async function apiPost(path, body) {
    const r = await fetch(API_BASE + path, {
        method: 'POST', credentials: 'include',
        headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body)
    });
    const d = await r.json();
    if (!r.ok || !d.ok) throw new Error(d.error || ('Error ' + r.status));
    return d;
}

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
}
function fmtDate(d) {
    if (!d) return '';
    const dt = new Date(String(d).length <= 10 ? d + 'T00:00:00' : d);
    return isNaN(dt) ? d : dt.toLocaleDateString('es-ES', { day: 'numeric', month: 'long', year: 'numeric' });
}

// ---------- Estado ----------
let homeObituaries = [];

// ---------- Inicio ----------
document.addEventListener('DOMContentLoaded', () => {
    const grid = document.getElementById('obituariesGrid');
    if (grid) loadHomeObituaries(grid);

    // Cierre de modales al pulsar fuera
    window.addEventListener('click', (e) => {
        if (e.target.classList && e.target.classList.contains('modal-overlay')) closeModal(e.target.id);
    });
});

async function loadHomeObituaries(grid) {
    grid.innerHTML = obitSkeleton(3);
    try {
        const r = await apiGet('obituaries.php?action=homepage');
        homeObituaries = r.items || [];
        renderHomeObituaries(grid, homeObituaries);
    } catch (e) {
        grid.innerHTML = `<p class="empty-row" style="grid-column:1/-1">No fue posible cargar los obituarios en este momento.</p>`;
    }
}

function obitSkeleton(n) {
    return Array.from({ length: n }).map(() => `
        <article class="obituary-card skeleton-card">
            <div class="obituary-img-container skel"></div>
            <div class="obituary-content">
                <div class="skel skel-line" style="width:40%"></div>
                <div class="skel skel-line" style="width:75%;height:22px"></div>
                <div class="skel skel-line" style="width:90%"></div>
            </div>
        </article>`).join('');
}

function renderHomeObituaries(grid, items) {
    if (!items.length) {
        grid.innerHTML = `<p class="empty-row" style="grid-column:1/-1">Por el momento no hay obituarios publicados.</p>`;
        return;
    }
    grid.innerHTML = items.map(o => obituaryCardHtml(o)).join('');
}

function obituaryCardHtml(o) {
    const url = 'obituario.php?slug=' + encodeURIComponent(o.slug || o.id);
    return `
    <article class="obituary-card">
        <a href="${url}" class="obituary-img-container" aria-label="Ver homenaje a ${escapeHtml(o.full_name)}">
            <img src="${escapeHtml(o.photo_url)}" alt="Retrato de ${escapeHtml(o.full_name)}" class="obituary-img" loading="lazy">
            <span class="obituary-badge">${escapeHtml(o.service_type)}</span>
        </a>
        <div class="obituary-content">
            <div class="obituary-dates">Q.E.P.D. &bull; Falleció el ${fmtDate(o.death_date)}</div>
            <h3 class="obituary-name"><a href="${url}">${escapeHtml(o.full_name)}</a></h3>
            <ul class="obituary-details-list">
                ${o.location_name ? `<li class="obituary-detail-item">
                    <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 110-5 2.5 2.5 0 010 5z"/></svg>
                    <div><span class="obituary-detail-label">${escapeHtml(o.location_name)}</span>
                    <span class="obituary-detail-val">${escapeHtml(o.location_address || '')}</span></div></li>` : ''}
                ${o.event_schedule ? `<li class="obituary-detail-item">
                    <svg viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12.5 7H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
                    <div><span class="obituary-detail-label">Oficios y Sepelio</span>
                    <span class="obituary-detail-val">${escapeHtml(o.event_schedule)}</span></div></li>` : ''}
            </ul>
            <div class="obituary-actions">
                <a class="btn btn-outline" href="${url}">Ver homenaje</a>
                <button class="btn btn-outline" onclick="openCondolencesModal(${o.id})">Condolencias</button>
                <button class="btn btn-text" onclick="shareObituary('${o.slug || o.id}')" title="Compartir">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z"/></svg>
                </button>
            </div>
        </div>
    </article>`;
}

// ---------- Modales ----------
function openModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.add('active'); document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.remove('active'); document.body.style.overflow = ''; }
}

// ---------- Condolencias (público) ----------
async function openCondolencesModal(obituaryId) {
    const ob = homeObituaries.find(o => o.id === obituaryId);
    document.getElementById('condolenceDeceasedId').value = obituaryId;
    document.getElementById('condolencesDeceasedInfo').innerText = ob ? `Mensajes en memoria de: ${ob.full_name}` : 'Mensajes de acompañamiento';
    document.getElementById('condolenceForm').reset();
    await renderCondolencesList(obituaryId);
    openModal('condolencesModal');
}

async function renderCondolencesList(obituaryId) {
    const c = document.getElementById('condolenceMessagesContainer');
    c.innerHTML = '<p class="condolence-empty">Cargando...</p>';
    try {
        const r = await apiGet('condolences.php?action=list&obituary_id=' + obituaryId);
        if (!r.items.length) { c.innerHTML = `<p class="condolence-empty">Aún no hay mensajes. Sea el primero en dejar sus condolencias.</p>`; return; }
        c.innerHTML = r.items.map(m => `
            <div class="condolence-item">
                <div class="author"><span>${escapeHtml(m.author_name)}</span><span class="date">${fmtDate(m.created_at)}</span></div>
                <div>${escapeHtml(m.message)}</div>
            </div>`).join('');
    } catch (e) { c.innerHTML = `<p class="condolence-empty">No se pudieron cargar los mensajes.</p>`; }
}

async function handleCondolenceSubmit(e) {
    e.preventDefault();
    const obituaryId = parseInt(document.getElementById('condolenceDeceasedId').value, 10);
    const author = document.getElementById('condolenceAuthor').value.trim();
    const text = document.getElementById('condolenceText').value.trim();
    if (!obituaryId || !author || !text) return;
    try {
        const r = await apiPost('condolences.php?action=create', { obituary_id: obituaryId, author_name: author, message: text });
        showToast(r.message || 'Mensaje enviado.');
        document.getElementById('condolenceText').value = '';
        document.getElementById('condolenceAuthor').value = '';
        await renderCondolencesList(obituaryId);
    } catch (ex) { showToast(ex.message); }
}

// ---------- Flores (simulado, sin backend de pago) ----------
let selectedFlowerType = 'Corona Imperial', selectedFlowerPrice = '$120';
function openFlowersModal(obituaryId) {
    const ob = homeObituaries.find(o => o.id === obituaryId);
    document.getElementById('flowersDeceasedId').value = obituaryId;
    document.getElementById('flowersDeceasedInfo').innerText = ob ? `Flores y Ofrendas para: ${ob.full_name}` : 'Flores y Ofrendas';
    document.getElementById('flowersForm').reset();
    const def = document.querySelector(".flower-option-card[data-flower='Corona Imperial']");
    if (def) selectFlowerOption(def);
    openModal('flowersModal');
}
function selectFlowerOption(el) {
    document.querySelectorAll('.flower-option-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    selectedFlowerType = el.getAttribute('data-flower');
    selectedFlowerPrice = el.getAttribute('data-price');
}
function handleFlowersSubmit(e) {
    e.preventDefault();
    closeModal('flowersModal');
    showToast(`Ofrenda floral (${selectedFlowerType}) registrada. Un asesor le contactará.`);
}

// ---------- Compartir ----------
function shareObituary(slug) {
    const ob = homeObituaries.find(o => (o.slug || String(o.id)) === String(slug));
    const name = ob ? ob.full_name : 'un ser querido';
    const url = `${location.origin}${location.pathname.replace(/[^/]*$/, '')}obituario.php?slug=${encodeURIComponent(slug)}`;
    const text = `Homenaje fúnebre a la memoria de ${name} — Funeraria del Zulia`;
    if (navigator.share) {
        navigator.share({ title: 'Homenaje Fúnebre', text, url }).catch(() => {});
    } else {
        navigator.clipboard.writeText(url).then(() => showToast('Enlace copiado al portapapeles')).catch(() => showToast('No se pudo copiar el enlace'));
    }
}

// ---------- Toast ----------
function showToast(message) {
    const t = document.getElementById('toast');
    if (!t) return;
    t.innerText = message;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3200);
}
