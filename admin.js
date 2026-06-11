// ==========================================================================
//  PANEL DE ADMINISTRACIÓN (admin.js) — Funeraria del Zulia
//  Consume la API PHP en /api. Sustituye al antiguo sistema localStorage.
// ==========================================================================

const API = {
    base: 'api/',
    csrf: '',
    async req(path, { method = 'GET', json = null, form = null } = {}) {
        const opts = { method, credentials: 'include', headers: {} };
        if (json !== null) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(json); }
        if (form !== null) { opts.body = form; }
        if (method !== 'GET' && this.csrf) { opts.headers['X-CSRF-Token'] = this.csrf; }
        let res, data;
        try {
            res = await fetch(this.base + path, opts);
            data = await res.json();
        } catch (e) {
            throw new Error('No se pudo conectar con el servidor.');
        }
        if (!res.ok || !data.ok) { throw new Error(data.error || ('Error ' + res.status)); }
        return data;
    }
};

const State = { user: null, templates: [], settings: {} };

// ---------- Utilidades ----------
function $(sel, ctx = document) { return ctx.querySelector(sel); }
function $all(sel, ctx = document) { return Array.from(ctx.querySelectorAll(sel)); }

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
}
function fmtDate(d) {
    if (!d) return '—';
    const dt = new Date((String(d).length <= 10 ? d + 'T00:00:00' : d));
    return isNaN(dt) ? d : dt.toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' });
}
function toast(msg) {
    const t = $('#toast');
    t.innerText = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3200);
}
function confirmAction(msg) { return window.confirm(msg); }

// ---------- Modal ----------
function openModal(title, html) {
    $('#modalTitle').innerText = title;
    $('#modalBody').innerHTML = html;
    $('#modal').hidden = false;
    document.body.style.overflow = 'hidden';
}
function closeModal() {
    $('#modal').hidden = true;
    $('#modalBody').innerHTML = '';
    document.body.style.overflow = '';
}

// ==========================================================================
//  AUTENTICACIÓN
// ==========================================================================
async function bootstrapAuth() {
    try {
        const me = await API.req('auth.php?action=me');
        API.csrf = me.csrf || '';
        if (me.user) { onLoggedIn(me.user); }
        else { showLogin(); }
    } catch (e) {
        showLogin();
    }
}

function showLogin() {
    $('#appView').hidden = true;
    $('#loginView').hidden = false;
}

async function onLoggedIn(user) {
    State.user = user;
    $('#loginView').hidden = true;
    $('#appView').hidden = false;
    $('#currentUserName').innerText = `${user.full_name || user.email} · ${user.role}`;
    // Mostrar pestañas exclusivas de admin
    $all('.admin-only').forEach(el => { el.hidden = (user.role !== 'admin'); });
    // Carga inicial
    await Promise.all([loadTemplates(), loadStats()]);
    await loadObituaries();
    refreshPendingBadge();
}

async function handleLogin(e) {
    e.preventDefault();
    const btn = $('#loginBtn');
    const err = $('#loginError');
    err.hidden = true;
    btn.disabled = true; btn.innerText = 'Ingresando...';
    try {
        const r = await API.req('auth.php?action=login', {
            method: 'POST',
            json: { email: $('#loginEmail').value.trim(), password: $('#loginPassword').value }
        });
        API.csrf = r.csrf || '';
        await onLoggedIn(r.user);
    } catch (ex) {
        err.innerText = ex.message;
        err.hidden = false;
    } finally {
        btn.disabled = false; btn.innerText = 'Iniciar sesión';
    }
}

async function handleLogout() {
    try { await API.req('auth.php?action=logout', { method: 'POST' }); } catch (e) {}
    State.user = null;
    location.reload();
}

// ==========================================================================
//  NAVEGACIÓN DE PESTAÑAS
// ==========================================================================
function setupTabs() {
    $all('.admin-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => switchTab(btn.dataset.tab, btn));
    });
}
function switchTab(tab, btn) {
    $all('.admin-tab-btn').forEach(b => b.classList.remove('active'));
    btn?.classList.add('active');
    $all('.admin-tab-panel').forEach(p => p.hidden = true);
    $('#tab-' + tab).hidden = false;
    if (tab === 'condolencias') loadCondolences();
    if (tab === 'plantillas') loadTemplates(true);
    if (tab === 'config') loadSettings();
    if (tab === 'usuarios') loadUsers();
}

// ==========================================================================
//  ESTADÍSTICAS
// ==========================================================================
async function loadStats() {
    try {
        const r = await API.req('obituaries.php?action=stats');
        const s = r.stats;
        $('#statTotal').innerText = s.total;
        $('#statActive').innerText = s.active;
        $('#statPinned').innerText = s.pinned;
        $('#statPending').innerText = s.pending_condolences;
    } catch (e) {}
}
async function refreshPendingBadge() {
    try {
        const r = await API.req('obituaries.php?action=stats');
        const n = r.stats.pending_condolences;
        const badge = $('#badgePending');
        badge.innerText = n;
        badge.hidden = (n === 0);
    } catch (e) {}
}

// ==========================================================================
//  OBITUARIOS
// ==========================================================================
let obitSearchTimer = null;

async function loadObituaries() {
    const q = $('#obitSearch').value.trim();
    try {
        const r = await API.req('obituaries.php?action=list&scope=admin&limit=100' + (q ? '&q=' + encodeURIComponent(q) : ''));
        renderObitTable(r.items);
    } catch (e) { toast(e.message); }
}

function statusBadge(status) {
    const map = { active: ['Activo', 'badge-green'], inactive: ['Inactivo', 'badge-gray'], draft: ['Borrador', 'badge-amber'] };
    const [txt, cls] = map[status] || [status, 'badge-gray'];
    return `<span class="status-badge ${cls}">${txt}</span>`;
}

function renderObitTable(items) {
    const tb = $('#obitTableBody');
    if (!items.length) {
        tb.innerHTML = `<tr><td colspan="5" class="empty-row">No hay obituarios. Cree el primero con “+ Nuevo obituario”.</td></tr>`;
        return;
    }
    tb.innerHTML = items.map(o => `
        <tr>
            <td>
                <div class="obituary-row-info">
                    <img src="${escapeHtml(o.photo_url)}" alt="" class="obituary-row-img" onerror="this.style.visibility='hidden'">
                    <div>
                        <div class="row-name">${escapeHtml(o.full_name)} ${o.is_pinned ? '<span class="pin-dot" title="Destacado">★</span>' : ''}</div>
                        <div class="row-sub">${o.photo_purged ? 'Foto purgada' : ''}</div>
                    </div>
                </div>
            </td>
            <td>${fmtDate(o.death_date)}</td>
            <td><span class="status-badge badge-blue">${escapeHtml(o.service_type)}</span></td>
            <td>${statusBadge(o.status)}</td>
            <td>
                <div class="admin-actions">
                    <button class="btn btn-outline btn-sm" onclick="togglePin(${o.id}, ${o.is_pinned ? 0 : 1})">${o.is_pinned ? 'Quitar' : 'Fijar'}</button>
                    <button class="btn btn-outline btn-sm" onclick="editObituary(${o.id})">Editar</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteObituary(${o.id}, ${escapeAttr(o.full_name)})">Baja</button>
                </div>
            </td>
        </tr>`).join('');
}

function obituaryFormHtml(o = {}) {
    const tplOptions = State.templates.map(t =>
        `<option value="${t.id}" ${o.template_id === t.id ? 'selected' : ''}>${escapeHtml(t.name)}${t.is_default ? ' (predeterminada)' : ''}</option>`
    ).join('');
    return `
    <form id="obitForm">
        <input type="hidden" id="of_id" value="${o.id || ''}">
        <input type="hidden" id="of_photo_path" value="${escapeHtml(o.photo_url && !o.photo_purged ? o.photo_url : '')}">
        <div class="form-group">
            <label class="form-label">Nombre completo del fallecido *</label>
            <input type="text" id="of_full_name" class="form-control" value="${escapeHtml(o.full_name || '')}" required>
        </div>
        <div class="form-grid-2">
            <div class="form-group"><label class="form-label">Año de nacimiento</label>
                <input type="number" id="of_birth_year" class="form-control" min="1900" max="2026" value="${o.birth_year || ''}"></div>
            <div class="form-group"><label class="form-label">Fecha de fallecimiento *</label>
                <input type="date" id="of_death_date" class="form-control" value="${o.death_date || ''}" required></div>
        </div>
        <div class="form-grid-2">
            <div class="form-group"><label class="form-label">Tipo de servicio</label>
                <select id="of_service_type" class="form-control">
                    ${['Velación', 'Cremación', 'Homenaje Póstumo', 'Traslado', 'Otro'].map(t => `<option ${o.service_type === t ? 'selected' : ''}>${t}</option>`).join('')}
                </select></div>
            <div class="form-group"><label class="form-label">Estado</label>
                <select id="of_status" class="form-control">
                    <option value="active" ${o.status === 'active' ? 'selected' : ''}>Activo</option>
                    <option value="draft" ${o.status === 'draft' ? 'selected' : ''}>Borrador</option>
                    <option value="inactive" ${o.status === 'inactive' ? 'selected' : ''}>Inactivo</option>
                </select></div>
        </div>
        <div class="form-group"><label class="form-label">Lugar de velación / cremación</label>
            <input type="text" id="of_location_name" class="form-control" value="${escapeHtml(o.location_name || '')}"></div>
        <div class="form-group"><label class="form-label">Dirección del lugar</label>
            <input type="text" id="of_location_address" class="form-control" value="${escapeHtml(o.location_address || '')}"></div>
        <div class="form-group"><label class="form-label">Oficios / Sepelio (fecha y hora)</label>
            <input type="text" id="of_event_schedule" class="form-control" value="${escapeHtml(o.event_schedule || '')}"></div>
        <div class="form-group"><label class="form-label">Biografía / nota recordatoria</label>
            <textarea id="of_biography" class="form-control">${escapeHtml(o.biography || '')}</textarea></div>
        <div class="form-group"><label class="form-label">Plantilla</label>
            <select id="of_template_id" class="form-control"><option value="">— Predeterminada —</option>${tplOptions}</select></div>
        <div class="form-group">
            <label class="form-label">Fotografía</label>
            <div class="photo-uploader">
                <img id="of_photo_preview" class="photo-preview" src="${escapeHtml(o.photo_url || '')}" alt="" ${o.photo_url ? '' : 'hidden'}>
                <div class="photo-uploader-controls">
                    <input type="file" id="of_photo_file" accept="image/jpeg,image/png,image/webp" class="form-control">
                    <p class="setting-help">JPG, PNG o WebP. Se sube al disco del servidor al guardar.</p>
                </div>
            </div>
        </div>
        <p id="of_error" class="login-error" hidden></p>
        <div class="modal-actions">
            <button type="submit" class="btn btn-primary" id="of_submit">Guardar obituario</button>
            <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
        </div>
    </form>`;
}

async function newObituary() {
    openModal('Nuevo obituario', obituaryFormHtml({}));
    wireObitForm();
}
async function editObituary(id) {
    try {
        const r = await API.req('obituaries.php?action=get&id=' + id);
        openModal('Editar obituario', obituaryFormHtml(r.item));
        wireObitForm();
    } catch (e) { toast(e.message); }
}

function wireObitForm() {
    const file = $('#of_photo_file');
    file.addEventListener('change', () => {
        if (file.files[0]) {
            const url = URL.createObjectURL(file.files[0]);
            const prev = $('#of_photo_preview');
            prev.src = url; prev.hidden = false;
        }
    });
    $('#obitForm').addEventListener('submit', submitObituary);
}

async function submitObituary(e) {
    e.preventDefault();
    const btn = $('#of_submit');
    const err = $('#of_error');
    err.hidden = true;
    btn.disabled = true; btn.innerText = 'Guardando...';
    try {
        // 1) Subir foto si se seleccionó una nueva
        let photoPath = $('#of_photo_path').value;
        const file = $('#of_photo_file').files[0];
        if (file) {
            const fd = new FormData();
            fd.append('photo', file);
            const up = await API.req('upload.php', { method: 'POST', form: fd });
            photoPath = up.path;
        }
        // 2) Crear o actualizar
        const id = $('#of_id').value;
        const payload = {
            id: id || undefined,
            full_name: $('#of_full_name').value.trim(),
            birth_year: $('#of_birth_year').value,
            death_date: $('#of_death_date').value,
            service_type: $('#of_service_type').value,
            status: $('#of_status').value,
            location_name: $('#of_location_name').value.trim(),
            location_address: $('#of_location_address').value.trim(),
            event_schedule: $('#of_event_schedule').value.trim(),
            biography: $('#of_biography').value.trim(),
            template_id: $('#of_template_id').value,
            photo_path: photoPath || ''
        };
        await API.req('obituaries.php?action=' + (id ? 'update' : 'create'), { method: 'POST', json: payload });
        closeModal();
        toast(id ? 'Obituario actualizado.' : 'Obituario creado.');
        await loadObituaries();
        loadStats();
    } catch (ex) {
        err.innerText = ex.message; err.hidden = false;
    } finally {
        btn.disabled = false; btn.innerText = 'Guardar obituario';
    }
}

async function togglePin(id, pinned) {
    try {
        await API.req('obituaries.php?action=pin', { method: 'POST', json: { id, pinned: !!pinned } });
        toast(pinned ? 'Obituario fijado en la portada.' : 'Obituario quitado de destacados.');
        loadObituaries(); loadStats();
    } catch (e) { toast(e.message); }
}

async function deleteObituary(id, name) {
    if (!confirmAction(`¿Dar de baja el obituario de ${name}? Dejará de mostrarse en el sitio (se puede restaurar).`)) return;
    try {
        await API.req('obituaries.php?action=delete', { method: 'POST', json: { id } });
        toast('Obituario dado de baja.');
        loadObituaries(); loadStats();
    } catch (e) { toast(e.message); }
}

// ==========================================================================
//  CONDOLENCIAS
// ==========================================================================
let condStatus = 'pending';

function setupCondFilters() {
    $all('#condFilters .filter-btn').forEach(b => {
        b.addEventListener('click', () => {
            $all('#condFilters .filter-btn').forEach(x => x.classList.remove('active'));
            b.classList.add('active');
            condStatus = b.dataset.status;
            loadCondolences();
        });
    });
}

async function loadCondolences() {
    try {
        const r = await API.req('condolences.php?action=admin_list&status=' + condStatus);
        renderCondList(r.items);
    } catch (e) { toast(e.message); }
}

function renderCondList(items) {
    const c = $('#condList');
    if (!items.length) { c.innerHTML = `<p class="empty-row">No hay condolencias en esta categoría.</p>`; return; }
    c.innerHTML = items.map(m => `
        <div class="cond-card">
            <div class="cond-card-head">
                <div>
                    <strong>${escapeHtml(m.author_name)}</strong>
                    <span class="cond-meta">en homenaje a ${escapeHtml(m.obituary_name)} · ${fmtDate(m.created_at)}</span>
                </div>
                ${statusBadgeCond(m.status)}
            </div>
            <p class="cond-msg">${escapeHtml(m.message)}</p>
            <div class="admin-actions">
                ${m.status !== 'approved' ? `<button class="btn btn-primary btn-sm" onclick="moderateCond(${m.id},'approved')">Aprobar</button>` : ''}
                ${m.status !== 'hidden' ? `<button class="btn btn-outline btn-sm" onclick="moderateCond(${m.id},'hidden')">Ocultar</button>` : ''}
                <button class="btn btn-outline btn-sm" onclick="editCondolence(${m.id}, ${escapeAttr(m.author_name)}, ${escapeAttr(m.message)})">Editar</button>
                <button class="btn btn-danger btn-sm" onclick="deleteCondolence(${m.id})">Eliminar</button>
            </div>
        </div>`).join('');
}
function statusBadgeCond(s) {
    const map = { pending: ['Por revisar', 'badge-amber'], approved: ['Aprobada', 'badge-green'], hidden: ['Oculta', 'badge-gray'] };
    const [t, c] = map[s] || [s, 'badge-gray'];
    return `<span class="status-badge ${c}">${t}</span>`;
}
// JSON seguro para incrustar como argumento dentro de un atributo onclick="..."
function escapeAttr(s) { return escapeHtml(JSON.stringify(String(s ?? ''))); }

async function moderateCond(id, status) {
    try {
        await API.req('condolences.php?action=moderate', { method: 'POST', json: { id, status } });
        toast('Condolencia ' + (status === 'approved' ? 'aprobada' : 'oculta') + '.');
        loadCondolences(); refreshPendingBadge(); loadStats();
    } catch (e) { toast(e.message); }
}
function editCondolence(id, author, message) {
    openModal('Editar condolencia', `
        <form id="condForm">
            <input type="hidden" id="cf_id" value="${id}">
            <div class="form-group"><label class="form-label">Autor</label>
                <input type="text" id="cf_author" class="form-control" value="${escapeHtml(author)}"></div>
            <div class="form-group"><label class="form-label">Mensaje</label>
                <textarea id="cf_message" class="form-control">${escapeHtml(message)}</textarea></div>
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Guardar</button>
                <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
            </div>
        </form>`);
    $('#condForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            await API.req('condolences.php?action=update', { method: 'POST', json: { id: $('#cf_id').value, author_name: $('#cf_author').value.trim(), message: $('#cf_message').value.trim() } });
            closeModal(); toast('Condolencia actualizada.'); loadCondolences();
        } catch (ex) { toast(ex.message); }
    });
}
async function deleteCondolence(id) {
    if (!confirmAction('¿Eliminar esta condolencia de forma permanente?')) return;
    try { await API.req('condolences.php?action=delete', { method: 'POST', json: { id } }); toast('Eliminada.'); loadCondolences(); refreshPendingBadge(); }
    catch (e) { toast(e.message); }
}

// ==========================================================================
//  PLANTILLAS
// ==========================================================================
async function loadTemplates(render = false) {
    try {
        const r = await API.req('templates.php?action=list');
        State.templates = r.items;
        if (render) renderTemplates(r.items);
    } catch (e) { if (render) toast(e.message); }
}
function renderTemplates(items) {
    const c = $('#tplList');
    c.innerHTML = items.map(t => `
        <div class="tpl-card">
            <div class="tpl-card-head">
                <strong>${escapeHtml(t.name)} ${t.is_default ? '<span class="status-badge badge-green">Predeterminada</span>' : ''} ${!t.is_active ? '<span class="status-badge badge-gray">Inactiva</span>' : ''}</strong>
            </div>
            <p class="setting-help">${escapeHtml(t.description || '')}</p>
            <div class="admin-actions">
                <button class="btn btn-outline btn-sm" onclick="editTemplate(${t.id})">Editar</button>
                ${!t.is_default ? `<button class="btn btn-outline btn-sm" onclick="setDefaultTemplate(${t.id})">Predeterminar</button>` : ''}
                ${!t.is_default ? `<button class="btn btn-danger btn-sm" onclick="deleteTemplate(${t.id})">Eliminar</button>` : ''}
            </div>
        </div>`).join('');
}
function templateFormHtml(t = {}) {
    return `
    <form id="tplForm">
        <input type="hidden" id="tf_id" value="${t.id || ''}">
        <div class="form-group"><label class="form-label">Nombre *</label>
            <input type="text" id="tf_name" class="form-control" value="${escapeHtml(t.name || '')}" required></div>
        <div class="form-group"><label class="form-label">Descripción</label>
            <input type="text" id="tf_desc" class="form-control" value="${escapeHtml(t.description || '')}"></div>
        <div class="form-group"><label class="form-label">Contenido HTML *</label>
            <textarea id="tf_body" class="form-control code-area" required>${escapeHtml(t.body_html || '')}</textarea>
            <p class="setting-help">Marcadores: {{full_name}}, {{birth_year}}, {{death_date}}, {{photo}}, {{biography}}, {{service_type}}, {{location_name}}, {{event_schedule}}</p></div>
        <div class="form-group"><label class="form-label">CSS (opcional)</label>
            <textarea id="tf_styles" class="form-control code-area">${escapeHtml(t.styles || '')}</textarea></div>
        <div class="form-group"><label class="switch-inline"><input type="checkbox" id="tf_active" ${t.is_active !== false ? 'checked' : ''}> Activa</label></div>
        <p id="tf_error" class="login-error" hidden></p>
        <div class="modal-actions">
            <button type="submit" class="btn btn-primary">Guardar plantilla</button>
            <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
        </div>
    </form>`;
}
function newTemplate() { openModal('Nueva plantilla', templateFormHtml({})); wireTplForm(); }
async function editTemplate(id) {
    try { const r = await API.req('templates.php?action=get&id=' + id); openModal('Editar plantilla', templateFormHtml(r.item)); wireTplForm(); }
    catch (e) { toast(e.message); }
}
function wireTplForm() {
    $('#tplForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const err = $('#tf_error'); err.hidden = true;
        try {
            const id = $('#tf_id').value;
            await API.req('templates.php?action=' + (id ? 'update' : 'create'), {
                method: 'POST',
                json: { id: id || undefined, name: $('#tf_name').value.trim(), description: $('#tf_desc').value.trim(), body_html: $('#tf_body').value, styles: $('#tf_styles').value, is_active: $('#tf_active').checked }
            });
            closeModal(); toast('Plantilla guardada.'); loadTemplates(true);
        } catch (ex) { err.innerText = ex.message; err.hidden = false; }
    });
}
async function setDefaultTemplate(id) {
    try { await API.req('templates.php?action=set_default', { method: 'POST', json: { id } }); toast('Plantilla predeterminada actualizada.'); loadTemplates(true); }
    catch (e) { toast(e.message); }
}
async function deleteTemplate(id) {
    if (!confirmAction('¿Eliminar esta plantilla?')) return;
    try { await API.req('templates.php?action=delete', { method: 'POST', json: { id } }); toast('Plantilla eliminada.'); loadTemplates(true); }
    catch (e) { toast(e.message); }
}

// ==========================================================================
//  CONFIGURACIÓN
// ==========================================================================
async function loadSettings() {
    try {
        const r = await API.req('settings.php?action=get');
        const s = r.settings;
        State.settings = s;
        $('#set_photo_purge_enabled').checked = (s.photo_purge_enabled?.value === '1');
        $('#set_photo_retention_days').value = s.photo_retention_days?.value ?? 30;
        $('#set_homepage_recent_count').value = s.homepage_recent_count?.value ?? 3;
        $('#set_condolence_moderation').checked = (s.condolence_moderation?.value === '1');
    } catch (e) { toast(e.message); }
}
async function saveSettings(e) {
    e.preventDefault();
    try {
        await API.req('settings.php?action=update', {
            method: 'POST',
            json: {
                settings: {
                    photo_purge_enabled: $('#set_photo_purge_enabled').checked,
                    photo_retention_days: $('#set_photo_retention_days').value,
                    homepage_recent_count: $('#set_homepage_recent_count').value,
                    condolence_moderation: $('#set_condolence_moderation').checked
                }
            }
        });
        toast('Configuración guardada.');
    } catch (ex) { toast(ex.message); }
}

// ==========================================================================
//  USUARIOS (solo admin)
// ==========================================================================
async function loadUsers() {
    try { const r = await API.req('users.php?action=list'); renderUsers(r.items); }
    catch (e) { toast(e.message); }
}
function renderUsers(items) {
    $('#usersTableBody').innerHTML = items.map(u => `
        <tr>
            <td>${escapeHtml(u.full_name || '—')}</td>
            <td>${escapeHtml(u.email)}</td>
            <td><span class="status-badge ${u.role === 'admin' ? 'badge-blue' : 'badge-gray'}">${u.role}</span></td>
            <td>${u.is_active ? '<span class="status-badge badge-green">Activo</span>' : '<span class="status-badge badge-gray">Inactivo</span>'}</td>
            <td>${u.last_login_at ? fmtDate(u.last_login_at) : '—'}</td>
            <td><div class="admin-actions">
                <button class="btn btn-outline btn-sm" onclick="editUser(${u.id}, ${escapeAttr(u.full_name)}, '${u.email}', '${u.role}', ${u.is_active ? 1 : 0})">Editar</button>
                <button class="btn btn-outline btn-sm" onclick="changePassword(${u.id})">Clave</button>
                <button class="btn btn-danger btn-sm" onclick="deleteUser(${u.id})">Eliminar</button>
            </div></td>
        </tr>`).join('');
}
function userFormHtml(u = {}, isNew = true) {
    return `
    <form id="userForm">
        <input type="hidden" id="uf_id" value="${u.id || ''}">
        <div class="form-group"><label class="form-label">Nombre completo</label>
            <input type="text" id="uf_name" class="form-control" value="${escapeHtml(u.full_name || '')}"></div>
        <div class="form-group"><label class="form-label">Correo *</label>
            <input type="email" id="uf_email" class="form-control" value="${escapeHtml(u.email || '')}" ${isNew ? '' : 'disabled'} required></div>
        ${isNew ? `<div class="form-group"><label class="form-label">Contraseña * (mín. 8)</label>
            <input type="text" id="uf_password" class="form-control" minlength="8" required></div>` : ''}
        <div class="form-grid-2">
            <div class="form-group"><label class="form-label">Rol</label>
                <select id="uf_role" class="form-control">
                    <option value="editor" ${u.role === 'editor' ? 'selected' : ''}>Editor</option>
                    <option value="admin" ${u.role === 'admin' ? 'selected' : ''}>Admin</option>
                </select></div>
            ${!isNew ? `<div class="form-group"><label class="form-label">Estado</label>
                <select id="uf_active" class="form-control"><option value="1" ${u.is_active ? 'selected' : ''}>Activo</option><option value="0" ${!u.is_active ? 'selected' : ''}>Inactivo</option></select></div>` : ''}
        </div>
        <p id="uf_error" class="login-error" hidden></p>
        <div class="modal-actions">
            <button type="submit" class="btn btn-primary">Guardar</button>
            <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
        </div>
    </form>`;
}
function newUser() { openModal('Nuevo usuario', userFormHtml({}, true)); wireUserForm(true); }
function editUser(id, name, email, role, active) {
    openModal('Editar usuario', userFormHtml({ id, full_name: name, email, role, is_active: !!active }, false));
    wireUserForm(false);
}
function wireUserForm(isNew) {
    $('#userForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const err = $('#uf_error'); err.hidden = true;
        try {
            if (isNew) {
                await API.req('users.php?action=create', { method: 'POST', json: { full_name: $('#uf_name').value.trim(), email: $('#uf_email').value.trim(), password: $('#uf_password').value, role: $('#uf_role').value } });
            } else {
                await API.req('users.php?action=update', { method: 'POST', json: { id: $('#uf_id').value, full_name: $('#uf_name').value.trim(), role: $('#uf_role').value, is_active: $('#uf_active').value === '1' } });
            }
            closeModal(); toast('Usuario guardado.'); loadUsers();
        } catch (ex) { err.innerText = ex.message; err.hidden = false; }
    });
}
function changePassword(id) {
    openModal('Cambiar contraseña', `
        <form id="pwForm">
            <div class="form-group"><label class="form-label">Nueva contraseña (mín. 8)</label>
                <input type="text" id="pw_val" class="form-control" minlength="8" required></div>
            <div class="modal-actions"><button type="submit" class="btn btn-primary">Cambiar</button>
                <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button></div>
        </form>`);
    $('#pwForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        try { await API.req('users.php?action=set_password', { method: 'POST', json: { id, password: $('#pw_val').value } }); closeModal(); toast('Contraseña actualizada.'); }
        catch (ex) { toast(ex.message); }
    });
}
async function deleteUser(id) {
    if (!confirmAction('¿Eliminar este usuario?')) return;
    try { await API.req('users.php?action=delete', { method: 'POST', json: { id } }); toast('Usuario eliminado.'); loadUsers(); }
    catch (e) { toast(e.message); }
}

// ==========================================================================
//  INICIALIZACIÓN
// ==========================================================================
document.addEventListener('DOMContentLoaded', () => {
    $('#loginForm').addEventListener('submit', handleLogin);
    $('#logoutBtn').addEventListener('click', handleLogout);
    $('#modalClose').addEventListener('click', closeModal);
    $('#modal').addEventListener('click', (e) => { if (e.target.id === 'modal') closeModal(); });
    setupTabs();
    setupCondFilters();
    $('#newObitBtn').addEventListener('click', newObituary);
    $('#newTplBtn').addEventListener('click', newTemplate);
    $('#newUserBtn').addEventListener('click', newUser);
    $('#settingsForm').addEventListener('submit', saveSettings);
    $('#obitSearch').addEventListener('input', () => { clearTimeout(obitSearchTimer); obitSearchTimer = setTimeout(loadObituaries, 300); });
    bootstrapAuth();
});
