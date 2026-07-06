// ==========================================================================
//  MÓDULO DE PREVISIÓN (admin-prevision.js) — Funeraria del Zulia
//  Administración de planes de previsión: clientes, contratos, beneficiarios,
//  cuotas/pagos, vendedores y comisiones, importación desde otros sistemas.
//  Se apoya en admin.js (API, $, escapeHtml, openModal, toast, State...).
// ==========================================================================

const Prevision = {
    wired: false,
    sub: 'contratos',
    planes: [],
    vendedores: [],
    parentescos: [],
    con: { q: '', estatus: '', morosos: false, offset: 0, limit: 25, total: 0 },
    cli: { q: '', offset: 0, limit: 25, total: 0 },
    comVista: 'pendientes',

    async open() {
        if (!this.wired) { this.wire(); this.wired = true; }
        this.loadStats();
        this.loadCatalogos();
        this.loadSub();
    },

    wire() {
        // Sub-pestañas
        $all('#pvSubNav .filter-btn').forEach(btn => btn.addEventListener('click', () => {
            $all('#pvSubNav .filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            this.sub = btn.dataset.sub;
            $all('.prev-subpanel').forEach(p => p.hidden = true);
            $('#pvSub-' + this.sub).hidden = false;
            this.loadSub();
        }));
        // Contratos
        let t1; $('#pvConSearch').addEventListener('input', () => {
            clearTimeout(t1); t1 = setTimeout(() => { this.con.q = $('#pvConSearch').value.trim(); this.con.offset = 0; pvLoadContratos(); }, 300);
        });
        $('#pvConEstatus').addEventListener('change', () => { this.con.estatus = $('#pvConEstatus').value; this.con.offset = 0; pvLoadContratos(); });
        $('#pvConMorosos').addEventListener('change', () => { this.con.morosos = $('#pvConMorosos').checked; this.con.offset = 0; pvLoadContratos(); });
        $('#pvNewContratoBtn').addEventListener('click', () => pvNuevoContrato());
        // Clientes
        let t2; $('#pvCliSearch').addEventListener('input', () => {
            clearTimeout(t2); t2 = setTimeout(() => { this.cli.q = $('#pvCliSearch').value.trim(); this.cli.offset = 0; pvLoadClientes(); }, 300);
        });
        $('#pvNewClienteBtn').addEventListener('click', () => pvFormCliente());
        // Planes / Vendedores
        $('#pvNewPlanBtn').addEventListener('click', () => pvFormPlan());
        let t3; $('#pvVenSearch').addEventListener('input', () => {
            clearTimeout(t3); t3 = setTimeout(() => pvLoadVendedores(), 300);
        });
        $('#pvNewVendedorBtn').addEventListener('click', () => pvFormVendedor());
        // Comisiones
        $all('#pvComFilters .filter-btn').forEach(btn => btn.addEventListener('click', () => {
            $all('#pvComFilters .filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            this.comVista = btn.dataset.vista;
            pvLoadComisiones();
        }));
        $('#pvComVendedor').addEventListener('change', () => pvLoadComisiones());
        // Importación
        $('#pvImportForm').addEventListener('submit', pvImportar);
        $('#pvImpTipo').addEventListener('change', pvActualizarPlantilla);
        $('#pvImpPlantilla').addEventListener('click', (e) => {
            e.preventDefault();
            window.open('api/prevision_import.php?action=plantilla&tipo=' + $('#pvImpTipo').value, '_blank');
        });
        // Tasa
        $('#pvTasaBtn').addEventListener('click', pvSetTasa);
    },

    loadSub() {
        if (this.sub === 'contratos') pvLoadContratos();
        if (this.sub === 'clientes') pvLoadClientes();
        if (this.sub === 'planes') pvLoadPlanes();
        if (this.sub === 'vendedores') pvLoadVendedores();
        if (this.sub === 'comisiones') pvLoadComisiones();
        if (this.sub === 'importar') pvLoadImportacion();
    },

    async loadStats() {
        try {
            const r = await API.req('prevision_contratos.php?action=stats');
            const s = r.stats;
            $('#pvStatContratos').innerText = s.contratos_activos;
            $('#pvStatClientes').innerText = s.clientes;
            $('#pvStatVencidas').innerText = s.cuotas_vencidas;
            $('#pvStatVencidasMonto').innerText = s.cuotas_vencidas > 0 ? pvMoney(s.monto_vencido, 'USD') : '';
            $('#pvStatCobrado').innerText = pvMoney(s.cobrado_mes_usd, 'USD');
            $('#pvStatCobradoBs').innerText = s.cobrado_mes_bs > 0 ? pvMoney(s.cobrado_mes_bs, 'BS') : '';
            $('#pvStatTasa').innerText = s.tasa_dia > 0 ? pvNum(s.tasa_dia) : '—';
        } catch (e) {
            if (/prev_/.test(e.message) || /doesn'?t exist/i.test(e.message)) {
                toast('Importe database/04_prevision.sql para activar el módulo de Previsión.');
            }
        }
    },

    async loadCatalogos(force = false) {
        if (this.planes.length && !force) return;
        try {
            const [p, v, pa] = await Promise.all([
                API.req('prevision_planes.php?action=list&all=1'),
                API.req('prevision_vendedores.php?action=list&all=1'),
                API.req('prevision_contratos.php?action=parentescos'),
            ]);
            this.planes = p.items; this.vendedores = v.items; this.parentescos = pa.items;
            const sel = $('#pvComVendedor');
            sel.innerHTML = '<option value="">Todos los vendedores</option>' +
                this.vendedores.map(x => `<option value="${x.id}">${escapeHtml(x.nombre)}</option>`).join('');
        } catch (e) { /* módulo sin instalar */ }
    },
};

// ---------- Utilidades ----------
function pvNum(v) { return Number(v || 0).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function pvMoney(v, moneda) { return (moneda === 'BS' ? 'Bs ' : '$ ') + pvNum(v); }
function pvHoy() { return new Date().toISOString().slice(0, 10); }

const PV_ESTATUS_BADGE = {
    activo: 'badge-green', suspendido: 'badge-amber', anulado: 'badge-red',
    renuncia: 'badge-gray', excluido: 'badge-gray', fallecido: 'badge-red',
    pendiente: 'badge-amber', parcial: 'badge-blue', cobrada: 'badge-green', anulada: 'badge-gray',
};
function pvBadge(estatus) {
    return `<span class="status-badge ${PV_ESTATUS_BADGE[estatus] || 'badge-gray'}">${escapeHtml(estatus)}</span>`;
}
const PV_FRECUENCIAS = ['semanal', 'quincenal', 'mensual', 'trimestral', 'semestral', 'anual'];
const PV_FORMAS_CONTRATO = { caja: 'Caja/Taquilla', domiciliacion: 'Domiciliación', transferencia: 'Transferencia', pago_movil: 'Pago móvil', cobrador: 'Cobrador', otro: 'Otro' };
const PV_FORMAS_PAGO = { efectivo: 'Efectivo', transferencia: 'Transferencia', pago_movil: 'Pago móvil', punto: 'Punto de venta', zelle: 'Zelle', divisa: 'Divisa en efectivo', otro: 'Otro' };
const PV_ETAPAS = { semana1: 'Semana 1', fin_mes1: 'Fin de mes 1', mes2: 'Mes 2', mes13: 'Mes 13' };

function pvSelect(id, opciones, valor, vacio) {
    return `<select id="${id}" class="form-control">` +
        (vacio !== undefined ? `<option value="">${escapeHtml(vacio)}</option>` : '') +
        Object.entries(opciones).map(([v, t]) =>
            `<option value="${v}" ${String(valor) === String(v) ? 'selected' : ''}>${escapeHtml(t)}</option>`).join('') +
        `</select>`;
}

function pvPager(el, st, fn) {
    const desde = st.total ? st.offset + 1 : 0;
    const hasta = Math.min(st.offset + st.limit, st.total);
    el.innerHTML = `
        <button class="btn btn-outline btn-sm" ${st.offset <= 0 ? 'disabled' : ''} data-dir="-1">← Anterior</button>
        <span class="prev-pager-info">${desde}–${hasta} de ${st.total}</span>
        <button class="btn btn-outline btn-sm" ${hasta >= st.total ? 'disabled' : ''} data-dir="1">Siguiente →</button>`;
    $all('button', el).forEach(b => b.addEventListener('click', () => {
        st.offset = Math.max(0, st.offset + Number(b.dataset.dir) * st.limit);
        fn();
    }));
}

function pvEsAdmin() { return State.user && State.user.role === 'admin'; }

// ==========================================================================
//  CONTRATOS
// ==========================================================================
async function pvLoadContratos() {
    const st = Prevision.con;
    try {
        const params = new URLSearchParams({ action: 'list', limit: st.limit, offset: st.offset });
        if (st.q) params.set('q', st.q);
        if (st.estatus) params.set('estatus', st.estatus);
        if (st.morosos) params.set('morosos', '1');
        const r = await API.req('prevision_contratos.php?' + params);
        st.total = r.total;
        const tb = $('#pvConBody');
        if (!r.items.length) {
            tb.innerHTML = `<tr><td colspan="8" class="empty-row">No hay contratos. Cree uno o use la pestaña Importar.</td></tr>`;
        } else {
            tb.innerHTML = r.items.map(c => `
                <tr>
                    <td><a href="#" onclick="pvVerContrato(${c.id});return false;"><strong>${escapeHtml(c.numero)}</strong></a><div class="row-sub">${fmtDate(c.fecha_ingreso)}</div></td>
                    <td><div class="row-name">${escapeHtml(c.cliente_nombre || '')}</div><div class="row-sub">${escapeHtml(c.cliente_cedula || '')}</div></td>
                    <td>${escapeHtml(c.plan_nombre || '—')}</td>
                    <td>${escapeHtml(c.vendedor_nombre || '—')}</td>
                    <td>${pvMoney(c.monto_cuota, c.moneda)}<div class="row-sub">${escapeHtml(c.frecuencia_pago)}</div></td>
                    <td>${c.cuotas_vencidas > 0 ? `<span class="status-badge badge-red">${c.cuotas_vencidas} · ${pvMoney(c.saldo_vencido, c.moneda)}</span>` : '—'}</td>
                    <td>${pvBadge(c.estatus)}</td>
                    <td><div class="admin-actions">
                        <button class="btn btn-outline btn-sm" onclick="pvVerContrato(${c.id})">Ver</button>
                        <button class="btn btn-outline btn-sm" onclick="pvEditContrato(${c.id})">Editar</button>
                    </div></td>
                </tr>`).join('');
        }
        pvPager($('#pvConPager'), st, pvLoadContratos);
    } catch (e) { toast(e.message); }
}

function pvContratoFormHtml(c = {}, cliente = null) {
    const planes = Prevision.planes.filter(p => p.activo || p.id === c.plan_id);
    const vends = Prevision.vendedores.filter(v => v.activo || v.id === c.vendedor_id);
    return `
    <form id="pvConForm">
        <input type="hidden" id="pf_id" value="${c.id || ''}">
        <input type="hidden" id="pf_cliente_id" value="${c.cliente_id || (cliente ? cliente.id : '')}">
        <div class="form-grid-2">
            <div class="form-group"><label class="form-label">Nº de contrato</label>
                <input type="text" id="pf_numero" class="form-control" value="${escapeHtml(c.numero || '')}" placeholder="(automático)"></div>
            <div class="form-group"><label class="form-label">Fecha de ingreso *</label>
                <input type="date" id="pf_fecha_ingreso" class="form-control" value="${c.fecha_ingreso || pvHoy()}" required></div>
        </div>
        <div class="form-group"><label class="form-label">Titular (buscar por cédula) *</label>
            <div class="prev-inline">
                <input type="text" id="pf_cedula" class="form-control" placeholder="Cédula del cliente"
                       value="${escapeHtml(cliente ? cliente.cedula : (c.cliente_cedula || '').replace(/^[VEJP]-/, ''))}">
                <button type="button" class="btn btn-outline" onclick="pvBuscarClienteContrato()">Buscar</button>
            </div>
            <p class="setting-help" id="pf_cliente_info">${cliente || c.cliente_nombre
                ? '✓ ' + escapeHtml(cliente ? (cliente.nombre_completo || cliente.nombres) : c.cliente_nombre)
                : 'Si el cliente no existe, créelo primero en la sub-pestaña Clientes.'}</p>
        </div>
        <div class="form-grid-2">
            <div class="form-group"><label class="form-label">Plan</label>
                <select id="pf_plan" class="form-control">
                    <option value="">— Sin plan —</option>
                    ${planes.map(p => `<option value="${p.id}" data-cuota="${p.cuota_mensual}" data-moneda="${p.moneda}" data-inicial="${p.cuota_inicial}"
                        ${c.plan_id === p.id ? 'selected' : ''}>${escapeHtml(p.nombre)} (${pvMoney(p.cuota_mensual, p.moneda)})</option>`).join('')}
                </select></div>
            <div class="form-group"><label class="form-label">Vendedor</label>
                <select id="pf_vendedor" class="form-control">
                    <option value="">— Sin vendedor —</option>
                    ${vends.map(v => `<option value="${v.id}" ${c.vendedor_id === v.id ? 'selected' : ''}>${escapeHtml(v.nombre)}</option>`).join('')}
                </select></div>
        </div>
        <div class="form-grid-2">
            <div class="form-group"><label class="form-label">Moneda</label>
                ${pvSelect('pf_moneda', { USD: 'USD ($)', BS: 'Bolívares (Bs)' }, c.moneda || 'USD')}</div>
            <div class="form-group"><label class="form-label">Monto de la cuota *</label>
                <input type="number" step="0.01" min="0" id="pf_monto_cuota" class="form-control" value="${c.monto_cuota ?? ''}"></div>
        </div>
        <div class="form-grid-2">
            <div class="form-group"><label class="form-label">Frecuencia de pago</label>
                ${pvSelect('pf_frecuencia', Object.fromEntries(PV_FRECUENCIAS.map(f => [f, f])), c.frecuencia_pago || 'mensual')}</div>
            <div class="form-group"><label class="form-label">Forma de cobro</label>
                ${pvSelect('pf_forma', PV_FORMAS_CONTRATO, c.forma_pago || 'caja')}</div>
        </div>
        <div class="form-grid-2">
            <div class="form-group"><label class="form-label">Cuota inicial</label>
                <input type="number" step="0.01" min="0" id="pf_inicial" class="form-control" value="${c.cuota_inicial ?? 0}"></div>
            <div class="form-group"><label class="form-label">Nº de cuotas (0 = indefinido)</label>
                <input type="number" min="0" id="pf_num_cuotas" class="form-control" value="${c.numero_cuotas ?? 0}"></div>
        </div>
        <div class="form-grid-2">
            <div class="form-group"><label class="form-label">Plazo de espera (meses)</label>
                <input type="number" min="0" max="60" id="pf_plazo" class="form-control" value="${c.plazo_espera_meses ?? 4}"></div>
            <div class="form-group"><label class="form-label">% comisión de venta</label>
                <input type="number" step="0.01" min="0" max="100" id="pf_comision" class="form-control" value="${c.comision_venta ?? 0}"></div>
        </div>
        ${!c.id ? `
        <div class="form-grid-2">
            <div class="form-group"><label class="form-label">Generar cuotas al crear</label>
                <input type="number" min="0" max="120" id="pf_gen_cuotas" class="form-control" value="12"></div>
            <div class="form-group"><label class="form-label">Primera cuota</label>
                <input type="date" id="pf_primera_cuota" class="form-control"></div>
        </div>` : ''}
        <details class="prev-details"><summary>Domiciliación bancaria (opcional)</summary>
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">Banco</label>
                    <input type="text" id="pf_banco" class="form-control" value="${escapeHtml(c.banco || '')}"></div>
                <div class="form-group"><label class="form-label">Nº de cuenta</label>
                    <input type="text" id="pf_cuenta" class="form-control" maxlength="24" value="${escapeHtml(c.numero_cuenta || '')}"></div>
            </div>
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">Titular de la cuenta</label>
                    <input type="text" id="pf_titular" class="form-control" value="${escapeHtml(c.titular_cuenta || '')}"></div>
                <div class="form-group"><label class="form-label">Tipo de cuenta</label>
                    ${pvSelect('pf_tipo_cuenta', { corriente: 'Corriente', ahorro: 'Ahorro', otra: 'Otra' }, c.tipo_cuenta || '', '—')}</div>
            </div>
        </details>
        <div class="form-group"><label class="form-label">Comentarios</label>
            <textarea id="pf_comentarios" class="form-control">${escapeHtml(c.comentarios || '')}</textarea></div>
        <p id="pf_error" class="login-error" hidden></p>
        <div class="modal-actions">
            <button type="submit" class="btn btn-primary">${c.id ? 'Guardar cambios' : 'Crear contrato'}</button>
            <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
        </div>
    </form>`;
}

function pvWireContratoForm() {
    $('#pf_plan').addEventListener('change', (e) => {
        const opt = e.target.selectedOptions[0];
        if (opt && opt.dataset.cuota !== undefined) {
            $('#pf_monto_cuota').value = opt.dataset.cuota;
            $('#pf_moneda').value = opt.dataset.moneda;
            if (Number(opt.dataset.inicial) > 0) $('#pf_inicial').value = opt.dataset.inicial;
        }
    });
    $('#pvConForm').addEventListener('submit', pvSubmitContrato);
}

async function pvBuscarClienteContrato() {
    const ced = $('#pf_cedula').value.trim();
    const info = $('#pf_cliente_info');
    if (!ced) { info.innerText = 'Escriba la cédula del titular.'; return; }
    try {
        const r = await API.req('prevision_clientes.php?action=get&cedula=' + encodeURIComponent(ced));
        $('#pf_cliente_id').value = r.item.id;
        info.innerText = '✓ ' + r.item.nombre_completo + ' (' + r.item.documento + ')';
    } catch (e) {
        $('#pf_cliente_id').value = '';
        info.innerText = '✗ ' + e.message + ' Créelo primero en la sub-pestaña Clientes.';
    }
}

async function pvNuevoContrato() {
    await Prevision.loadCatalogos();
    openModal('Nuevo contrato de previsión', pvContratoFormHtml({}));
    pvWireContratoForm();
}

async function pvEditContrato(id) {
    await Prevision.loadCatalogos();
    try {
        const r = await API.req('prevision_contratos.php?action=get&id=' + id);
        openModal('Editar contrato ' + r.item.numero, pvContratoFormHtml(r.item, r.cliente));
        pvWireContratoForm();
    } catch (e) { toast(e.message); }
}

async function pvSubmitContrato(e) {
    e.preventDefault();
    const err = $('#pf_error'); err.hidden = true;
    const id = $('#pf_id').value;
    const json = {
        id: id || undefined,
        numero: $('#pf_numero').value.trim(),
        cliente_id: Number($('#pf_cliente_id').value || 0),
        plan_id: $('#pf_plan').value || null,
        vendedor_id: $('#pf_vendedor').value || null,
        fecha_ingreso: $('#pf_fecha_ingreso').value,
        moneda: $('#pf_moneda').value,
        monto_cuota: $('#pf_monto_cuota').value,
        frecuencia_pago: $('#pf_frecuencia').value,
        forma_pago: $('#pf_forma').value,
        cuota_inicial: $('#pf_inicial').value,
        numero_cuotas: $('#pf_num_cuotas').value,
        plazo_espera_meses: $('#pf_plazo').value,
        comision_venta: $('#pf_comision').value,
        banco: $('#pf_banco').value.trim(),
        numero_cuenta: $('#pf_cuenta').value.trim(),
        titular_cuenta: $('#pf_titular').value.trim(),
        tipo_cuenta: $('#pf_tipo_cuenta').value,
        comentarios: $('#pf_comentarios').value.trim(),
    };
    if (!json.cliente_id) { err.innerText = 'Busque y seleccione el cliente titular por su cédula.'; err.hidden = false; return; }
    if (!id) {
        json.generar_cuotas = $('#pf_gen_cuotas') ? $('#pf_gen_cuotas').value : 0;
        json.primera_cuota = $('#pf_primera_cuota') ? $('#pf_primera_cuota').value : '';
    }
    try {
        const r = await API.req('prevision_contratos.php?action=' + (id ? 'update' : 'create'), { method: 'POST', json });
        closeModal();
        toast(id ? 'Contrato actualizado.' : 'Contrato ' + r.numero + ' creado.');
        pvLoadContratos(); Prevision.loadStats();
        if (!id) pvVerContrato(r.id);
    } catch (ex) { err.innerText = ex.message; err.hidden = false; }
}

// ---------- Detalle del contrato ----------
async function pvVerContrato(id) {
    try {
        const r = await API.req('prevision_contratos.php?action=get&id=' + id);
        const c = r.item;
        const admin = pvEsAdmin();
        const benef = r.beneficiarios.map(b => `
            <tr>
                <td>${escapeHtml(b.nombre_completo)}<div class="row-sub">${b.cedula ? escapeHtml(b.nacionalidad + '-' + b.cedula) : 'No cedulado'}</div></td>
                <td>${escapeHtml(b.parentesco)}</td>
                <td>${b.edad ?? '—'}</td>
                <td>${b.cuota_adicional > 0 ? pvMoney(b.cuota_adicional, c.moneda) : '—'}</td>
                <td>${pvBadge(b.estatus)}</td>
                <td><div class="admin-actions">
                    <button class="btn btn-outline btn-sm" onclick="pvEditBeneficiario(${b.id}, ${c.id})">Editar</button>
                    ${b.estatus === 'activo' ? `
                        <button class="btn btn-outline btn-sm" onclick="pvEstatusBeneficiario(${b.id}, ${c.id}, 'excluido')">Excluir</button>
                        <button class="btn btn-outline btn-sm" onclick="pvEstatusBeneficiario(${b.id}, ${c.id}, 'fallecido')">Defunción</button>`
                        : `<button class="btn btn-outline btn-sm" onclick="pvEstatusBeneficiario(${b.id}, ${c.id}, 'activo')">Reactivar</button>`}
                    ${admin ? `<button class="btn btn-danger btn-sm" onclick="pvDeleteBeneficiario(${b.id}, ${c.id})">Eliminar</button>` : ''}
                </div></td>
            </tr>`).join('') || `<tr><td colspan="6" class="empty-row">Sin beneficiarios.</td></tr>`;

        const cuotas = r.cuotas.map(q => `
            <tr class="${q.vencida ? 'prev-row-vencida' : ''}">
                <td>${q.numero}</td>
                <td>${escapeHtml(q.tipo)}</td>
                <td>${fmtDate(q.fecha_vencimiento)}${q.vencida ? ' <span class="status-badge badge-red">vencida</span>' : ''}</td>
                <td>${pvMoney(q.monto, q.moneda)}</td>
                <td>${pvMoney(q.saldo, q.moneda)}</td>
                <td>${pvBadge(q.estado)}</td>
                <td><div class="admin-actions">
                    ${['pendiente', 'parcial'].includes(q.estado) ? `
                        <button class="btn btn-outline btn-sm" onclick="pvRegistrarPago(${c.id}, ${q.id})">Cobrar</button>
                        <button class="btn btn-outline btn-sm" onclick="pvAnularCuota(${q.id}, ${c.id})">Anular</button>` : ''}
                </div></td>
            </tr>`).join('') || `<tr><td colspan="7" class="empty-row">Sin cuotas generadas.</td></tr>`;

        const pagos = r.pagos.map(g => `
            <tr>
                <td>${fmtDate(g.fecha)}</td>
                <td>${g.cuota_numero ? 'Cuota ' + g.cuota_numero : 'A favor'}</td>
                <td>${pvMoney(g.monto, g.moneda)}</td>
                <td>${escapeHtml(PV_FORMAS_PAGO[g.forma_pago] || g.forma_pago)}${g.referencia ? '<div class="row-sub">Ref. ' + escapeHtml(g.referencia) + '</div>' : ''}</td>
                <td>${escapeHtml(g.recibo || '—')}</td>
                <td>${admin ? `<button class="btn btn-danger btn-sm" onclick="pvDeletePago(${g.id}, ${c.id})">Revertir</button>` : ''}</td>
            </tr>`).join('') || `<tr><td colspan="6" class="empty-row">Sin pagos registrados.</td></tr>`;

        const comis = r.comisiones.map(k => `
            <tr>
                <td>${escapeHtml(PV_ETAPAS[k.etapa] || k.etapa)}</td>
                <td>${escapeHtml(k.vendedor_nombre)}</td>
                <td>${pvMoney(k.monto_usd, 'USD')}<div class="row-sub">${k.monto_bs > 0 ? pvMoney(k.monto_bs, 'BS') : ''}</div></td>
                <td>${fmtDate(k.fecha_pago)}</td>
                <td>${admin ? `<button class="btn btn-danger btn-sm" onclick="pvDeleteComision(${k.id}, ${c.id})">Eliminar</button>` : ''}</td>
            </tr>`).join('') || `<tr><td colspan="5" class="empty-row">Sin comisiones pagadas.</td></tr>`;

        openModal(`Contrato ${escapeHtml(c.numero)} — ${escapeHtml(c.cliente_nombre || '')}`, `
            <div class="prev-detail">
                <div class="prev-kv">
                    <div><span>Titular</span><strong>${escapeHtml(c.cliente_nombre || '')} (${escapeHtml(c.cliente_cedula || '')})</strong></div>
                    <div><span>Plan</span><strong>${escapeHtml(c.plan_nombre || '—')}</strong></div>
                    <div><span>Vendedor</span><strong>${escapeHtml(c.vendedor_nombre || '—')}</strong></div>
                    <div><span>Estatus</span>${pvBadge(c.estatus)}${c.motivo_estatus ? ` <span class="row-sub">${escapeHtml(c.motivo_estatus)}</span>` : ''}</div>
                    <div><span>Ingreso</span><strong>${fmtDate(c.fecha_ingreso)}</strong></div>
                    <div><span>Vigente desde</span><strong>${fmtDate(c.vigente_desde)}</strong></div>
                    <div><span>Cuota</span><strong>${pvMoney(c.monto_cuota, c.moneda)} · ${escapeHtml(c.frecuencia_pago)}</strong></div>
                    <div><span>Forma de cobro</span><strong>${escapeHtml(PV_FORMAS_CONTRATO[c.forma_pago] || c.forma_pago)}</strong></div>
                    <div><span>Cobrado</span><strong>${pvMoney(r.totales.cobrado, c.moneda)}</strong></div>
                    <div><span>Por cobrar</span><strong>${pvMoney(r.totales.por_cobrar, c.moneda)}</strong></div>
                </div>
                <div class="modal-actions prev-actions">
                    <button class="btn btn-primary btn-sm" onclick="pvRegistrarPago(${c.id}, 0)">Registrar pago</button>
                    <button class="btn btn-outline btn-sm" onclick="pvGenerarCuotas(${c.id})">Generar cuotas</button>
                    <button class="btn btn-outline btn-sm" onclick="pvAddBeneficiario(${c.id})">+ Beneficiario</button>
                    <button class="btn btn-outline btn-sm" onclick="pvPagarComisionForm(${c.id}, '', ${c.vendedor_id || 0}, 0)">Pagar comisión</button>
                    <button class="btn btn-outline btn-sm" onclick="pvEditContrato(${c.id})">Editar</button>
                    <button class="btn btn-outline btn-sm" onclick="pvEstatusContrato(${c.id}, '${c.estatus}')">Cambiar estatus</button>
                </div>
                <h4 class="prev-h4">Beneficiarios (${r.beneficiarios.filter(b => b.estatus === 'activo').length} activos)</h4>
                <div class="table-responsive"><table class="admin-table admin-table-compact">
                    <thead><tr><th>Nombre</th><th>Parentesco</th><th>Edad</th><th>Cargo adic.</th><th>Estatus</th><th></th></tr></thead>
                    <tbody>${benef}</tbody></table></div>
                <h4 class="prev-h4">Cuotas</h4>
                <div class="table-responsive prev-scroll"><table class="admin-table admin-table-compact">
                    <thead><tr><th>#</th><th>Tipo</th><th>Vence</th><th>Monto</th><th>Saldo</th><th>Estado</th><th></th></tr></thead>
                    <tbody>${cuotas}</tbody></table></div>
                <h4 class="prev-h4">Pagos</h4>
                <div class="table-responsive prev-scroll"><table class="admin-table admin-table-compact">
                    <thead><tr><th>Fecha</th><th>Aplicado a</th><th>Monto</th><th>Forma</th><th>Recibo</th><th></th></tr></thead>
                    <tbody>${pagos}</tbody></table></div>
                <h4 class="prev-h4">Comisiones del vendedor</h4>
                <div class="table-responsive"><table class="admin-table admin-table-compact">
                    <thead><tr><th>Etapa</th><th>Vendedor</th><th>Monto</th><th>Pagada el</th><th></th></tr></thead>
                    <tbody>${comis}</tbody></table></div>
            </div>`);
    } catch (e) { toast(e.message); }
}

function pvEstatusContrato(id, actual) {
    openModal('Cambiar estatus del contrato', `
        <form id="pvEstForm">
            <div class="form-group"><label class="form-label">Nuevo estatus</label>
                ${pvSelect('pe_estatus', { activo: 'Activo', suspendido: 'Suspendido', anulado: 'Anulado', renuncia: 'Renuncia' }, actual)}</div>
            <div class="form-group"><label class="form-label">Fecha</label>
                <input type="date" id="pe_fecha" class="form-control" value="${pvHoy()}"></div>
            <div class="form-group"><label class="form-label">Motivo</label>
                <input type="text" id="pe_motivo" class="form-control" maxlength="255" placeholder="Motivo del cambio"></div>
            <p class="setting-help">Al anular o registrar la renuncia se anulan las cuotas que quedaban por cobrar.</p>
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Aplicar</button>
                <button type="button" class="btn btn-outline" onclick="pvVerContrato(${id})">Volver</button>
            </div>
        </form>`);
    $('#pvEstForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            await API.req('prevision_contratos.php?action=set_estatus', { method: 'POST', json: {
                id, estatus: $('#pe_estatus').value, fecha: $('#pe_fecha').value, motivo: $('#pe_motivo').value.trim(),
            }});
            toast('Estatus actualizado.'); pvVerContrato(id); pvLoadContratos(); Prevision.loadStats();
        } catch (ex) { toast(ex.message); }
    });
}

// ---------- Cuotas ----------
function pvGenerarCuotas(id) {
    openModal('Generar cuotas', `
        <form id="pvGenForm">
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">Cantidad de cuotas *</label>
                    <input type="number" min="1" max="120" id="pg_cantidad" class="form-control" value="12" required></div>
                <div class="form-group"><label class="form-label">Monto por cuota</label>
                    <input type="number" step="0.01" min="0" id="pg_monto" class="form-control" placeholder="(cuota del contrato)"></div>
            </div>
            <div class="form-group"><label class="form-label">Primera fecha de vencimiento</label>
                <input type="date" id="pg_desde" class="form-control">
                <p class="setting-help">Si se deja vacío, continúa después de la última cuota generada.</p></div>
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Generar</button>
                <button type="button" class="btn btn-outline" onclick="pvVerContrato(${id})">Volver</button>
            </div>
        </form>`);
    $('#pvGenForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            const r = await API.req('prevision_contratos.php?action=cuotas_generar', { method: 'POST', json: {
                contrato_id: id, cantidad: $('#pg_cantidad').value, monto: $('#pg_monto').value, desde: $('#pg_desde').value,
            }});
            toast(r.generadas + ' cuotas generadas.'); pvVerContrato(id);
        } catch (ex) { toast(ex.message); }
    });
}

async function pvAnularCuota(id, contratoId) {
    if (!confirmAction('¿Anular esta cuota?')) return;
    try {
        await API.req('prevision_contratos.php?action=cuota_anular', { method: 'POST', json: { id } });
        toast('Cuota anulada.'); pvVerContrato(contratoId); Prevision.loadStats();
    } catch (e) { toast(e.message); }
}

// ---------- Pagos ----------
function pvRegistrarPago(contratoId, cuotaId) {
    openModal('Registrar pago', `
        <form id="pvPagoForm">
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">Fecha *</label>
                    <input type="date" id="pp_fecha" class="form-control" value="${pvHoy()}" required></div>
                <div class="form-group"><label class="form-label">Recibo Nº</label>
                    <input type="text" id="pp_recibo" class="form-control" maxlength="20"></div>
            </div>
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">Monto *</label>
                    <input type="number" step="0.01" min="0.01" id="pp_monto" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Moneda del pago</label>
                    ${pvSelect('pp_moneda', { USD: 'USD ($)', BS: 'Bolívares (Bs)' }, 'USD')}</div>
            </div>
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">Forma de pago</label>
                    ${pvSelect('pp_forma', PV_FORMAS_PAGO, 'efectivo')}</div>
                <div class="form-group"><label class="form-label">Tasa Bs/USD (si aplica)</label>
                    <input type="number" step="0.0001" min="0" id="pp_tasa" class="form-control" placeholder="(tasa del día)"></div>
            </div>
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">Referencia</label>
                    <input type="text" id="pp_ref" class="form-control" maxlength="60"></div>
                <div class="form-group"><label class="form-label">Banco</label>
                    <input type="text" id="pp_banco" class="form-control" maxlength="100"></div>
            </div>
            <div class="form-group"><label class="form-label">Observaciones</label>
                <input type="text" id="pp_obs" class="form-control" maxlength="255"></div>
            <p class="setting-help">${cuotaId ? 'El pago se aplicará a la cuota seleccionada.'
                : 'El pago se aplica a las cuotas pendientes más antiguas; el excedente queda como abono a favor.'}</p>
            <p id="pp_error" class="login-error" hidden></p>
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Registrar pago</button>
                <button type="button" class="btn btn-outline" onclick="pvVerContrato(${contratoId})">Volver</button>
            </div>
        </form>`);
    $('#pvPagoForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const err = $('#pp_error'); err.hidden = true;
        try {
            const r = await API.req('prevision_contratos.php?action=pago_registrar', { method: 'POST', json: {
                contrato_id: contratoId, cuota_id: cuotaId || undefined,
                fecha: $('#pp_fecha').value, recibo: $('#pp_recibo').value.trim(),
                monto: $('#pp_monto').value, moneda: $('#pp_moneda').value,
                forma_pago: $('#pp_forma').value, tasa: $('#pp_tasa').value,
                referencia: $('#pp_ref').value.trim(), banco: $('#pp_banco').value.trim(),
                observaciones: $('#pp_obs').value.trim(),
            }});
            toast('Pago registrado (' + r.aplicadas.length + ' cuota(s) abonada(s)).');
            pvVerContrato(contratoId); Prevision.loadStats();
        } catch (ex) { err.innerText = ex.message; err.hidden = false; }
    });
}

async function pvDeletePago(id, contratoId) {
    if (!confirmAction('¿Revertir este pago? El saldo de la cuota se restaurará.')) return;
    try {
        await API.req('prevision_contratos.php?action=pago_delete', { method: 'POST', json: { id } });
        toast('Pago revertido.'); pvVerContrato(contratoId); Prevision.loadStats();
    } catch (e) { toast(e.message); }
}

// ---------- Beneficiarios ----------
function pvBenefFormHtml(contratoId, b = {}) {
    return `
    <form id="pvBenForm">
        <input type="hidden" id="pb_id" value="${b.id || ''}">
        <div class="form-grid-2">
            <div class="form-group"><label class="form-label">Parentesco *</label>
                <select id="pb_parentesco" class="form-control" required>
                    ${Prevision.parentescos.map(p => `<option value="${p.id}" ${b.parentesco_id === p.id ? 'selected' : ''}>${escapeHtml(p.nombre)}</option>`).join('')}
                </select></div>
            <div class="form-group"><label class="form-label">Cédula (vacío = no cedulado)</label>
                <div class="prev-inline">
                    ${pvSelect('pb_nacionalidad', { V: 'V', E: 'E' }, b.nacionalidad || 'V')}
                    <input type="text" id="pb_cedula" class="form-control" value="${escapeHtml(b.cedula || '')}">
                </div></div>
        </div>
        <div class="form-grid-2">
            <div class="form-group"><label class="form-label">Nombres *</label>
                <input type="text" id="pb_nombres" class="form-control" value="${escapeHtml(b.nombres || '')}" required></div>
            <div class="form-group"><label class="form-label">Apellidos</label>
                <input type="text" id="pb_apellidos" class="form-control" value="${escapeHtml(b.apellidos || '')}"></div>
        </div>
        <div class="form-grid-2">
            <div class="form-group"><label class="form-label">Fecha de nacimiento</label>
                <input type="date" id="pb_nacimiento" class="form-control" value="${b.fecha_nacimiento || ''}"></div>
            <div class="form-group"><label class="form-label">Sexo</label>
                ${pvSelect('pb_sexo', { M: 'Masculino', F: 'Femenino' }, b.sexo || '', '—')}</div>
        </div>
        <div class="form-grid-2">
            <div class="form-group"><label class="form-label">Cargo adicional (cuota)</label>
                <input type="number" step="0.01" min="0" id="pb_cuota" class="form-control" value="${b.cuota_adicional ?? 0}"></div>
            <div class="form-group"><label class="form-label">Plazo de espera (meses)</label>
                <input type="number" min="0" max="60" id="pb_plazo" class="form-control" value="${b.plazo_espera_meses ?? ''}" placeholder="(el del contrato)"></div>
        </div>
        <div class="form-group"><label class="form-label">Comentarios</label>
            <input type="text" id="pb_comentarios" class="form-control" maxlength="500" value="${escapeHtml(b.comentarios || '')}"></div>
        <p id="pb_error" class="login-error" hidden></p>
        <div class="modal-actions">
            <button type="submit" class="btn btn-primary">${b.id ? 'Guardar cambios' : 'Agregar beneficiario'}</button>
            <button type="button" class="btn btn-outline" onclick="pvVerContrato(${contratoId})">Volver</button>
        </div>
    </form>`;
}

function pvWireBenefForm(contratoId, benId) {
    $('#pvBenForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const err = $('#pb_error'); err.hidden = true;
        const json = {
            id: benId || undefined, contrato_id: contratoId,
            parentesco_id: $('#pb_parentesco').value,
            nacionalidad: $('#pb_nacionalidad').value,
            cedula: $('#pb_cedula').value.trim(),
            nombres: $('#pb_nombres').value.trim(),
            apellidos: $('#pb_apellidos').value.trim(),
            fecha_nacimiento: $('#pb_nacimiento').value,
            sexo: $('#pb_sexo').value,
            cuota_adicional: $('#pb_cuota').value,
            plazo_espera_meses: $('#pb_plazo').value,
            comentarios: $('#pb_comentarios').value.trim(),
        };
        try {
            await API.req('prevision_contratos.php?action=' + (benId ? 'beneficiario_update' : 'beneficiario_add'),
                          { method: 'POST', json });
            toast('Beneficiario guardado.'); pvVerContrato(contratoId);
        } catch (ex) { err.innerText = ex.message; err.hidden = false; }
    });
}

async function pvAddBeneficiario(contratoId) {
    await Prevision.loadCatalogos();
    openModal('Agregar beneficiario', pvBenefFormHtml(contratoId));
    pvWireBenefForm(contratoId, null);
}

async function pvEditBeneficiario(id, contratoId) {
    await Prevision.loadCatalogos();
    try {
        const r = await API.req('prevision_contratos.php?action=get&id=' + contratoId);
        const b = r.beneficiarios.find(x => x.id === id);
        if (!b) return toast('Beneficiario no encontrado.');
        openModal('Editar beneficiario', pvBenefFormHtml(contratoId, b));
        pvWireBenefForm(contratoId, id);
    } catch (e) { toast(e.message); }
}

async function pvEstatusBeneficiario(id, contratoId, estatus) {
    const nombres = { excluido: 'excluir', fallecido: 'registrar la defunción de', activo: 'reactivar' };
    if (!confirmAction(`¿Desea ${nombres[estatus] || 'cambiar'} este beneficiario?`)) return;
    try {
        await API.req('prevision_contratos.php?action=beneficiario_estatus',
                      { method: 'POST', json: { id, estatus, fecha: pvHoy() } });
        toast('Beneficiario actualizado.'); pvVerContrato(contratoId);
    } catch (e) { toast(e.message); }
}

async function pvDeleteBeneficiario(id, contratoId) {
    if (!confirmAction('¿Eliminar definitivamente este beneficiario?')) return;
    try {
        await API.req('prevision_contratos.php?action=beneficiario_delete', { method: 'POST', json: { id } });
        toast('Beneficiario eliminado.'); pvVerContrato(contratoId);
    } catch (e) { toast(e.message); }
}

// ==========================================================================
//  CLIENTES
// ==========================================================================
async function pvLoadClientes() {
    const st = Prevision.cli;
    try {
        const params = new URLSearchParams({ action: 'list', limit: st.limit, offset: st.offset });
        if (st.q) params.set('q', st.q);
        const r = await API.req('prevision_clientes.php?' + params);
        st.total = r.total;
        const tb = $('#pvCliBody');
        if (!r.items.length) {
            tb.innerHTML = `<tr><td colspan="6" class="empty-row">No hay clientes. Cree uno o use la pestaña Importar.</td></tr>`;
        } else {
            tb.innerHTML = r.items.map(c => `
                <tr>
                    <td><strong>${escapeHtml(c.documento)}</strong></td>
                    <td><div class="row-name">${escapeHtml(c.nombre_completo)}</div>${c.edad ? `<div class="row-sub">${c.edad} años</div>` : ''}</td>
                    <td>${escapeHtml(c.telefono_celular || c.telefono_habitacion || '—')}</td>
                    <td>${escapeHtml(c.ciudad || '—')}</td>
                    <td>${c.contratos ?? 0}</td>
                    <td><div class="admin-actions">
                        <button class="btn btn-outline btn-sm" onclick="pvVerCliente(${c.id})">Ver</button>
                        <button class="btn btn-outline btn-sm" onclick="pvEditCliente(${c.id})">Editar</button>
                        <button class="btn btn-danger btn-sm" onclick="pvDeleteCliente(${c.id}, ${escapeAttr(c.nombre_completo)})">Baja</button>
                    </div></td>
                </tr>`).join('');
        }
        pvPager($('#pvCliPager'), st, pvLoadClientes);
    } catch (e) { toast(e.message); }
}

function pvClienteFormHtml(c = {}) {
    return `
    <form id="pvCliForm">
        <input type="hidden" id="pc_id" value="${c.id || ''}">
        <div class="form-grid-2">
            <div class="form-group"><label class="form-label">Cédula / RIF *</label>
                <div class="prev-inline">
                    ${pvSelect('pc_nacionalidad', { V: 'V', E: 'E', J: 'J', P: 'P' }, c.nacionalidad || 'V')}
                    <input type="text" id="pc_cedula" class="form-control" value="${escapeHtml(c.cedula || '')}" required>
                </div></div>
            <div class="form-group"><label class="form-label">Fecha de nacimiento</label>
                <input type="date" id="pc_nacimiento" class="form-control" value="${c.fecha_nacimiento || ''}"></div>
        </div>
        <div class="form-grid-2">
            <div class="form-group"><label class="form-label">Nombres *</label>
                <input type="text" id="pc_nombres" class="form-control" value="${escapeHtml(c.nombres || '')}" required></div>
            <div class="form-group"><label class="form-label">Apellidos</label>
                <input type="text" id="pc_apellidos" class="form-control" value="${escapeHtml(c.apellidos || '')}"></div>
        </div>
        <div class="form-grid-2">
            <div class="form-group"><label class="form-label">Sexo</label>
                ${pvSelect('pc_sexo', { M: 'Masculino', F: 'Femenino' }, c.sexo || '', '—')}</div>
            <div class="form-group"><label class="form-label">Estado civil</label>
                <input type="text" id="pc_edocivil" class="form-control" maxlength="30" value="${escapeHtml(c.estado_civil || '')}"></div>
        </div>
        <div class="form-grid-2">
            <div class="form-group"><label class="form-label">Teléfono celular</label>
                <input type="text" id="pc_celular" class="form-control" maxlength="20" value="${escapeHtml(c.telefono_celular || '')}"></div>
            <div class="form-group"><label class="form-label">Teléfono habitación</label>
                <input type="text" id="pc_habitacion" class="form-control" maxlength="20" value="${escapeHtml(c.telefono_habitacion || '')}"></div>
        </div>
        <div class="form-group"><label class="form-label">Correo electrónico</label>
            <input type="email" id="pc_email" class="form-control" value="${escapeHtml(c.email || '')}"></div>
        <div class="form-group"><label class="form-label">Dirección</label>
            <input type="text" id="pc_direccion" class="form-control" maxlength="255" value="${escapeHtml(c.direccion || '')}"></div>
        <div class="form-grid-2">
            <div class="form-group"><label class="form-label">Ciudad</label>
                <input type="text" id="pc_ciudad" class="form-control" value="${escapeHtml(c.ciudad || '')}"></div>
            <div class="form-group"><label class="form-label">Estado</label>
                <input type="text" id="pc_estado" class="form-control" value="${escapeHtml(c.estado || 'Zulia')}"></div>
        </div>
        <div class="form-grid-2">
            <div class="form-group"><label class="form-label">Empleador</label>
                <input type="text" id="pc_empleador" class="form-control" value="${escapeHtml(c.empleador || '')}"></div>
            <div class="form-group"><label class="form-label">Profesión / oficio</label>
                <input type="text" id="pc_profesion" class="form-control" value="${escapeHtml(c.profesion || '')}"></div>
        </div>
        <div class="form-group"><label class="form-label">Información adicional</label>
            <textarea id="pc_info" class="form-control">${escapeHtml(c.info_adicional || '')}</textarea></div>
        <p id="pc_error" class="login-error" hidden></p>
        <div class="modal-actions">
            <button type="submit" class="btn btn-primary">${c.id ? 'Guardar cambios' : 'Crear cliente'}</button>
            <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
        </div>
    </form>`;
}

function pvFormCliente(c = {}) {
    openModal(c.id ? 'Editar cliente' : 'Nuevo cliente', pvClienteFormHtml(c));
    $('#pvCliForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const err = $('#pc_error'); err.hidden = true;
        const id = $('#pc_id').value;
        const json = {
            id: id || undefined,
            nacionalidad: $('#pc_nacionalidad').value,
            cedula: $('#pc_cedula').value.trim(),
            nombres: $('#pc_nombres').value.trim(),
            apellidos: $('#pc_apellidos').value.trim(),
            fecha_nacimiento: $('#pc_nacimiento').value,
            sexo: $('#pc_sexo').value,
            estado_civil: $('#pc_edocivil').value.trim(),
            telefono_celular: $('#pc_celular').value.trim(),
            telefono_habitacion: $('#pc_habitacion').value.trim(),
            email: $('#pc_email').value.trim(),
            direccion: $('#pc_direccion').value.trim(),
            ciudad: $('#pc_ciudad').value.trim(),
            estado: $('#pc_estado').value.trim(),
            empleador: $('#pc_empleador').value.trim(),
            profesion: $('#pc_profesion').value.trim(),
            info_adicional: $('#pc_info').value.trim(),
        };
        try {
            await API.req('prevision_clientes.php?action=' + (id ? 'update' : 'create'), { method: 'POST', json });
            closeModal(); toast('Cliente guardado.'); pvLoadClientes(); Prevision.loadStats();
        } catch (ex) { err.innerText = ex.message; err.hidden = false; }
    });
}

async function pvEditCliente(id) {
    try {
        const r = await API.req('prevision_clientes.php?action=get&id=' + id);
        pvFormCliente(r.item);
    } catch (e) { toast(e.message); }
}

async function pvVerCliente(id) {
    try {
        const r = await API.req('prevision_clientes.php?action=get&id=' + id);
        const c = r.item;
        const contratos = r.contratos.map(x => `
            <tr>
                <td><a href="#" onclick="pvVerContrato(${x.id});return false;"><strong>${escapeHtml(x.numero)}</strong></a></td>
                <td>${escapeHtml(x.plan_nombre || '—')}</td>
                <td>${pvMoney(x.monto_cuota, x.moneda)}</td>
                <td>${fmtDate(x.fecha_ingreso)}</td>
                <td>${pvBadge(x.estatus)}</td>
            </tr>`).join('') || `<tr><td colspan="5" class="empty-row">Sin contratos.</td></tr>`;
        openModal(`Cliente ${escapeHtml(c.nombre_completo)}`, `
            <div class="prev-detail">
                <div class="prev-kv">
                    <div><span>Documento</span><strong>${escapeHtml(c.documento)}</strong></div>
                    <div><span>Nacimiento</span><strong>${fmtDate(c.fecha_nacimiento)}${c.edad ? ' (' + c.edad + ' años)' : ''}</strong></div>
                    <div><span>Teléfonos</span><strong>${escapeHtml([c.telefono_celular, c.telefono_habitacion, c.telefono_oficina].filter(Boolean).join(' / ') || '—')}</strong></div>
                    <div><span>Correo</span><strong>${escapeHtml(c.email || '—')}</strong></div>
                    <div><span>Dirección</span><strong>${escapeHtml([c.direccion, c.ciudad, c.estado].filter(Boolean).join(', ') || '—')}</strong></div>
                    <div><span>Empleador</span><strong>${escapeHtml(c.empleador || '—')}</strong></div>
                </div>
                <div class="modal-actions prev-actions">
                    <button class="btn btn-outline btn-sm" onclick="pvEditCliente(${c.id})">Editar</button>
                    <button class="btn btn-primary btn-sm" onclick="closeModal(); pvNuevoContratoParaCliente(${c.id})">+ Nuevo contrato</button>
                </div>
                <h4 class="prev-h4">Contratos</h4>
                <div class="table-responsive"><table class="admin-table admin-table-compact">
                    <thead><tr><th>Número</th><th>Plan</th><th>Cuota</th><th>Ingreso</th><th>Estatus</th></tr></thead>
                    <tbody>${contratos}</tbody></table></div>
            </div>`);
    } catch (e) { toast(e.message); }
}

async function pvNuevoContratoParaCliente(clienteId) {
    await Prevision.loadCatalogos();
    try {
        const r = await API.req('prevision_clientes.php?action=get&id=' + clienteId);
        openModal('Nuevo contrato de previsión', pvContratoFormHtml({}, r.item));
        pvWireContratoForm();
    } catch (e) { toast(e.message); }
}

async function pvDeleteCliente(id, nombre) {
    if (!confirmAction(`¿Dar de baja al cliente ${nombre}? (baja lógica, se puede restaurar)`)) return;
    try {
        await API.req('prevision_clientes.php?action=delete', { method: 'POST', json: { id } });
        toast('Cliente dado de baja.'); pvLoadClientes(); Prevision.loadStats();
    } catch (e) { toast(e.message); }
}

// ==========================================================================
//  PLANES
// ==========================================================================
async function pvLoadPlanes() {
    try {
        const r = await API.req('prevision_planes.php?action=list&all=1');
        Prevision.planes = r.items;
        const tb = $('#pvPlanBody');
        tb.innerHTML = r.items.map(p => `
            <tr>
                <td>${escapeHtml(p.codigo || '—')}</td>
                <td><div class="row-name">${escapeHtml(p.nombre)}</div>${p.descripcion ? `<div class="row-sub">${escapeHtml(p.descripcion)}</div>` : ''}</td>
                <td>${pvMoney(p.cuota_mensual, p.moneda)}</td>
                <td>${p.cuota_inicial > 0 ? pvMoney(p.cuota_inicial, p.moneda) : '—'}</td>
                <td>${p.contratos ?? 0}</td>
                <td>${p.activo ? '<span class="status-badge badge-green">activo</span>' : '<span class="status-badge badge-gray">inactivo</span>'}</td>
                <td><div class="admin-actions">
                    <button class="btn btn-outline btn-sm" onclick="pvEditPlan(${p.id})">Editar</button>
                    <button class="btn btn-outline btn-sm" onclick="pvTogglePlan(${p.id}, ${p.activo ? 0 : 1})">${p.activo ? 'Desactivar' : 'Activar'}</button>
                    ${pvEsAdmin() && !p.contratos ? `<button class="btn btn-danger btn-sm" onclick="pvDeletePlan(${p.id})">Eliminar</button>` : ''}
                </div></td>
            </tr>`).join('') || `<tr><td colspan="7" class="empty-row">No hay planes.</td></tr>`;
    } catch (e) { toast(e.message); }
}

function pvFormPlan(p = {}) {
    openModal(p.id ? 'Editar plan' : 'Nuevo plan', `
        <form id="pvPlanForm">
            <input type="hidden" id="pl_id" value="${p.id || ''}">
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">Nombre *</label>
                    <input type="text" id="pl_nombre" class="form-control" value="${escapeHtml(p.nombre || '')}" required></div>
                <div class="form-group"><label class="form-label">Código</label>
                    <input type="text" id="pl_codigo" class="form-control" maxlength="20" value="${escapeHtml(p.codigo || '')}"></div>
            </div>
            <div class="form-group"><label class="form-label">Descripción</label>
                <input type="text" id="pl_descripcion" class="form-control" maxlength="255" value="${escapeHtml(p.descripcion || '')}"></div>
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">Moneda</label>
                    ${pvSelect('pl_moneda', { USD: 'USD ($)', BS: 'Bolívares (Bs)' }, p.moneda || 'USD')}</div>
                <div class="form-group"><label class="form-label">Cuota mensual *</label>
                    <input type="number" step="0.01" min="0" id="pl_cuota" class="form-control" value="${p.cuota_mensual ?? ''}" required></div>
            </div>
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">Cuota inicial</label>
                    <input type="number" step="0.01" min="0" id="pl_inicial" class="form-control" value="${p.cuota_inicial ?? 0}"></div>
                <div class="form-group"><label class="form-label">Máx. beneficiarios (0 = sin límite)</label>
                    <input type="number" min="0" max="255" id="pl_max" class="form-control" value="${p.max_beneficiarios ?? 0}"></div>
            </div>
            <div class="form-group"><label class="form-label">Cobertura del servicio (monto)</label>
                <input type="number" step="0.01" min="0" id="pl_monto_serv" class="form-control" value="${p.monto_servicio ?? 0}"></div>
            <div class="setting-row"><div><strong>Activo</strong></div>
                <label class="switch"><input type="checkbox" id="pl_activo" ${p.activo === false ? '' : 'checked'}><span class="slider"></span></label></div>
            <p id="pl_error" class="login-error" hidden></p>
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Guardar plan</button>
                <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
            </div>
        </form>`);
    $('#pvPlanForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const err = $('#pl_error'); err.hidden = true;
        const id = $('#pl_id').value;
        const json = {
            id: id || undefined,
            nombre: $('#pl_nombre').value.trim(),
            codigo: $('#pl_codigo').value.trim(),
            descripcion: $('#pl_descripcion').value.trim(),
            moneda: $('#pl_moneda').value,
            cuota_mensual: $('#pl_cuota').value,
            cuota_inicial: $('#pl_inicial').value,
            max_beneficiarios: $('#pl_max').value,
            monto_servicio: $('#pl_monto_serv').value,
            activo: $('#pl_activo').checked ? 1 : 0,
        };
        try {
            await API.req('prevision_planes.php?action=' + (id ? 'update' : 'create'), { method: 'POST', json });
            closeModal(); toast('Plan guardado.'); pvLoadPlanes(); Prevision.loadCatalogos(true);
        } catch (ex) { err.innerText = ex.message; err.hidden = false; }
    });
}

async function pvEditPlan(id) {
    try {
        const r = await API.req('prevision_planes.php?action=get&id=' + id);
        pvFormPlan(r.item);
    } catch (e) { toast(e.message); }
}

async function pvTogglePlan(id, activo) {
    try {
        await API.req('prevision_planes.php?action=toggle', { method: 'POST', json: { id, activo } });
        toast(activo ? 'Plan activado.' : 'Plan desactivado.'); pvLoadPlanes(); Prevision.loadCatalogos(true);
    } catch (e) { toast(e.message); }
}

async function pvDeletePlan(id) {
    if (!confirmAction('¿Eliminar definitivamente este plan?')) return;
    try {
        await API.req('prevision_planes.php?action=delete', { method: 'POST', json: { id } });
        toast('Plan eliminado.'); pvLoadPlanes(); Prevision.loadCatalogos(true);
    } catch (e) { toast(e.message); }
}

// ==========================================================================
//  VENDEDORES
// ==========================================================================
async function pvLoadVendedores() {
    try {
        const q = $('#pvVenSearch').value.trim();
        const r = await API.req('prevision_vendedores.php?action=list&all=1' + (q ? '&q=' + encodeURIComponent(q) : ''));
        Prevision.vendedores = r.items;
        const tb = $('#pvVenBody');
        tb.innerHTML = r.items.map(v => `
            <tr>
                <td>${escapeHtml(v.cedula || '—')}</td>
                <td><div class="row-name">${escapeHtml(v.nombre)}</div>${v.fecha_retiro ? `<div class="row-sub">Retirado ${fmtDate(v.fecha_retiro)}</div>` : ''}</td>
                <td>${escapeHtml(v.telefono1 || '—')}</td>
                <td>${pvNum(v.comision_semanal)}% / ${pvNum(v.comision_mensual)}% / ${pvNum(v.comision_anual)}%</td>
                <td>${v.contratos ?? 0}</td>
                <td>${v.activo ? '<span class="status-badge badge-green">activo</span>' : '<span class="status-badge badge-gray">retirado</span>'}</td>
                <td><div class="admin-actions">
                    <button class="btn btn-outline btn-sm" onclick="pvEditVendedor(${v.id})">Editar</button>
                    ${v.activo
                        ? `<button class="btn btn-outline btn-sm" onclick="pvRetirarVendedor(${v.id}, ${escapeAttr(v.nombre)})">Retirar</button>`
                        : `<button class="btn btn-outline btn-sm" onclick="pvReactivarVendedor(${v.id})">Reactivar</button>`}
                    ${pvEsAdmin() && !v.contratos ? `<button class="btn btn-danger btn-sm" onclick="pvDeleteVendedor(${v.id})">Eliminar</button>` : ''}
                </div></td>
            </tr>`).join('') || `<tr><td colspan="7" class="empty-row">No hay vendedores.</td></tr>`;
    } catch (e) { toast(e.message); }
}

function pvFormVendedor(v = {}) {
    openModal(v.id ? 'Editar vendedor' : 'Nuevo vendedor', `
        <form id="pvVenForm">
            <input type="hidden" id="pw_id" value="${v.id || ''}">
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">Nombre *</label>
                    <input type="text" id="pw_nombre" class="form-control" value="${escapeHtml(v.nombre || '')}" required></div>
                <div class="form-group"><label class="form-label">Cédula</label>
                    <input type="text" id="pw_cedula" class="form-control" maxlength="15" value="${escapeHtml(v.cedula || '')}"></div>
            </div>
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">Teléfono 1</label>
                    <input type="text" id="pw_tel1" class="form-control" maxlength="20" value="${escapeHtml(v.telefono1 || '')}"></div>
                <div class="form-group"><label class="form-label">Teléfono 2</label>
                    <input type="text" id="pw_tel2" class="form-control" maxlength="20" value="${escapeHtml(v.telefono2 || '')}"></div>
            </div>
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">Correo</label>
                    <input type="email" id="pw_email" class="form-control" value="${escapeHtml(v.email || '')}"></div>
                <div class="form-group"><label class="form-label">Fecha de ingreso</label>
                    <input type="date" id="pw_ingreso" class="form-control" value="${v.fecha_ingreso || pvHoy()}"></div>
            </div>
            <div class="form-group"><label class="form-label">Porcentajes de comisión (semanal / mensual / anual)</label>
                <div class="prev-inline">
                    <input type="number" step="0.01" min="0" max="100" id="pw_com_s" class="form-control" value="${v.comision_semanal ?? 0}">
                    <input type="number" step="0.01" min="0" max="100" id="pw_com_m" class="form-control" value="${v.comision_mensual ?? 0}">
                    <input type="number" step="0.01" min="0" max="100" id="pw_com_a" class="form-control" value="${v.comision_anual ?? 0}">
                </div></div>
            <details class="prev-details"><summary>Datos bancarios para el pago de comisiones</summary>
                <div class="form-grid-2">
                    <div class="form-group"><label class="form-label">Banco</label>
                        <input type="text" id="pw_banco" class="form-control" value="${escapeHtml(v.banco || '')}"></div>
                    <div class="form-group"><label class="form-label">Nº de cuenta</label>
                        <input type="text" id="pw_cuenta" class="form-control" maxlength="24" value="${escapeHtml(v.numero_cuenta || '')}"></div>
                </div>
                <div class="form-grid-2">
                    <div class="form-group"><label class="form-label">Titular</label>
                        <input type="text" id="pw_titular" class="form-control" value="${escapeHtml(v.titular_cuenta || '')}"></div>
                    <div class="form-group"><label class="form-label">Cédula del titular</label>
                        <input type="text" id="pw_ced_cuenta" class="form-control" maxlength="15" value="${escapeHtml(v.cedula_cuenta || '')}"></div>
                </div>
            </details>
            <div class="form-group"><label class="form-label">Notas</label>
                <input type="text" id="pw_notas" class="form-control" maxlength="500" value="${escapeHtml(v.notas || '')}"></div>
            <p id="pw_error" class="login-error" hidden></p>
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Guardar vendedor</button>
                <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
            </div>
        </form>`);
    $('#pvVenForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const err = $('#pw_error'); err.hidden = true;
        const id = $('#pw_id').value;
        const json = {
            id: id || undefined,
            nombre: $('#pw_nombre').value.trim(),
            cedula: $('#pw_cedula').value.trim(),
            telefono1: $('#pw_tel1').value.trim(),
            telefono2: $('#pw_tel2').value.trim(),
            email: $('#pw_email').value.trim(),
            fecha_ingreso: $('#pw_ingreso').value,
            comision_semanal: $('#pw_com_s').value,
            comision_mensual: $('#pw_com_m').value,
            comision_anual: $('#pw_com_a').value,
            banco: $('#pw_banco').value.trim(),
            numero_cuenta: $('#pw_cuenta').value.trim(),
            titular_cuenta: $('#pw_titular').value.trim(),
            cedula_cuenta: $('#pw_ced_cuenta').value.trim(),
            notas: $('#pw_notas').value.trim(),
        };
        try {
            await API.req('prevision_vendedores.php?action=' + (id ? 'update' : 'create'), { method: 'POST', json });
            closeModal(); toast('Vendedor guardado.'); pvLoadVendedores(); Prevision.loadCatalogos(true);
        } catch (ex) { err.innerText = ex.message; err.hidden = false; }
    });
}

async function pvEditVendedor(id) {
    try {
        const r = await API.req('prevision_vendedores.php?action=get&id=' + id);
        pvFormVendedor(r.item);
    } catch (e) { toast(e.message); }
}

async function pvRetirarVendedor(id, nombre) {
    if (!confirmAction(`¿Registrar el retiro del vendedor ${nombre}?`)) return;
    try {
        await API.req('prevision_vendedores.php?action=retirar', { method: 'POST', json: { id } });
        toast('Vendedor retirado.'); pvLoadVendedores(); Prevision.loadCatalogos(true);
    } catch (e) { toast(e.message); }
}

async function pvReactivarVendedor(id) {
    try {
        await API.req('prevision_vendedores.php?action=reactivar', { method: 'POST', json: { id } });
        toast('Vendedor reactivado.'); pvLoadVendedores(); Prevision.loadCatalogos(true);
    } catch (e) { toast(e.message); }
}

async function pvDeleteVendedor(id) {
    if (!confirmAction('¿Eliminar definitivamente este vendedor?')) return;
    try {
        await API.req('prevision_vendedores.php?action=delete', { method: 'POST', json: { id } });
        toast('Vendedor eliminado.'); pvLoadVendedores(); Prevision.loadCatalogos(true);
    } catch (e) { toast(e.message); }
}

// ==========================================================================
//  COMISIONES
// ==========================================================================
async function pvLoadComisiones() {
    const cont = $('#pvComContent');
    const vid = $('#pvComVendedor').value;
    try {
        if (Prevision.comVista === 'pendientes') {
            const r = await API.req('prevision_vendedores.php?action=comisiones_pendientes' + (vid ? '&vendedor_id=' + vid : ''));
            cont.innerHTML = `
                <div class="table-responsive"><table class="admin-table">
                    <thead><tr><th>Contrato</th><th>Vendedor</th><th>Etapa</th><th>Vencida desde</th><th>Sugerido</th><th>Acciones</th></tr></thead>
                    <tbody>${r.items.map(x => `
                        <tr>
                            <td><a href="#" onclick="pvVerContrato(${x.contrato_id});return false;"><strong>${escapeHtml(x.contrato_numero)}</strong></a></td>
                            <td>${escapeHtml(x.vendedor_nombre)}</td>
                            <td><span class="status-badge badge-amber">${escapeHtml(PV_ETAPAS[x.etapa] || x.etapa)}</span></td>
                            <td>${fmtDate(x.vencida_desde)}</td>
                            <td>${x.monto_sugerido > 0 ? pvMoney(x.monto_sugerido, x.moneda) : '—'}</td>
                            <td><button class="btn btn-primary btn-sm"
                                onclick="pvPagarComisionForm(${x.contrato_id}, '${x.etapa}', ${x.vendedor_id}, ${x.monto_sugerido})">Pagar</button></td>
                        </tr>`).join('') || `<tr><td colspan="6" class="empty-row">No hay comisiones por pagar. 🎉</td></tr>`}
                    </tbody></table></div>`;
        } else if (Prevision.comVista === 'pagadas') {
            const r = await API.req('prevision_vendedores.php?action=comisiones' + (vid ? '&vendedor_id=' + vid : ''));
            cont.innerHTML = `
                <div class="table-responsive"><table class="admin-table">
                    <thead><tr><th>Fecha</th><th>Contrato</th><th>Vendedor</th><th>Etapa</th><th>Monto USD</th><th>Monto Bs</th><th></th></tr></thead>
                    <tbody>${r.items.map(k => `
                        <tr>
                            <td>${fmtDate(k.fecha_pago)}</td>
                            <td><a href="#" onclick="pvVerContrato(${k.contrato_id});return false;">${escapeHtml(k.contrato_numero)}</a></td>
                            <td>${escapeHtml(k.vendedor_nombre)}</td>
                            <td>${escapeHtml(PV_ETAPAS[k.etapa] || k.etapa)}</td>
                            <td>${pvMoney(k.monto_usd, 'USD')}</td>
                            <td>${k.monto_bs > 0 ? pvMoney(k.monto_bs, 'BS') : '—'}</td>
                            <td>${pvEsAdmin() ? `<button class="btn btn-danger btn-sm" onclick="pvDeleteComision(${k.id}, 0)">Eliminar</button>` : ''}</td>
                        </tr>`).join('') || `<tr><td colspan="7" class="empty-row">Sin comisiones pagadas.</td></tr>`}
                    </tbody></table></div>`;
        } else {
            const r = await API.req('prevision_vendedores.php?action=comisiones_resumen');
            cont.innerHTML = `
                <div class="table-responsive"><table class="admin-table">
                    <thead><tr><th>Vendedor</th><th>Contratos</th><th>Activos</th><th>Pagos</th><th>Total USD</th><th>Total Bs</th></tr></thead>
                    <tbody>${r.items.map(v => `
                        <tr>
                            <td><div class="row-name">${escapeHtml(v.nombre)}</div><div class="row-sub">${escapeHtml(v.cedula || '')}</div></td>
                            <td>${v.contratos}</td>
                            <td>${v.contratos_activos}</td>
                            <td>${v.pagos}</td>
                            <td>${pvMoney(v.comisiones_usd, 'USD')}</td>
                            <td>${v.comisiones_bs > 0 ? pvMoney(v.comisiones_bs, 'BS') : '—'}</td>
                        </tr>`).join('') || `<tr><td colspan="6" class="empty-row">Sin vendedores.</td></tr>`}
                    </tbody></table></div>`;
        }
    } catch (e) { toast(e.message); }
}

function pvPagarComisionForm(contratoId, etapa, vendedorId, sugerido) {
    openModal('Pagar comisión', `
        <form id="pvComForm">
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">Etapa *</label>
                    ${pvSelect('pk_etapa', PV_ETAPAS, etapa || 'semana1')}</div>
                <div class="form-group"><label class="form-label">Fecha de pago</label>
                    <input type="date" id="pk_fecha" class="form-control" value="${pvHoy()}"></div>
            </div>
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">Monto USD</label>
                    <input type="number" step="0.01" min="0" id="pk_usd" class="form-control" value="${sugerido || ''}"></div>
                <div class="form-group"><label class="form-label">Monto Bs</label>
                    <input type="number" step="0.01" min="0" id="pk_bs" class="form-control"></div>
            </div>
            <div class="form-group"><label class="form-label">Tasa Bs/USD</label>
                <input type="number" step="0.0001" min="0" id="pk_tasa" class="form-control" placeholder="(tasa del día)">
                <p class="setting-help">Indique el monto en una moneda; con la tasa se calcula la otra automáticamente.</p></div>
            <div class="form-group"><label class="form-label">Comentario</label>
                <input type="text" id="pk_comentario" class="form-control" maxlength="200"></div>
            <p id="pk_error" class="login-error" hidden></p>
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Registrar pago de comisión</button>
                <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
            </div>
        </form>`);
    $('#pvComForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const err = $('#pk_error'); err.hidden = true;
        try {
            await API.req('prevision_vendedores.php?action=comision_pagar', { method: 'POST', json: {
                contrato_id: contratoId, vendedor_id: vendedorId || undefined,
                etapa: $('#pk_etapa').value, fecha_pago: $('#pk_fecha').value,
                monto_usd: $('#pk_usd').value, monto_bs: $('#pk_bs').value, tasa: $('#pk_tasa').value,
                comentario: $('#pk_comentario').value.trim(),
            }});
            closeModal(); toast('Comisión registrada.');
            if (Prevision.sub === 'comisiones') pvLoadComisiones();
            Prevision.loadStats();
        } catch (ex) { err.innerText = ex.message; err.hidden = false; }
    });
}

async function pvDeleteComision(id, contratoId) {
    if (!confirmAction('¿Eliminar este pago de comisión?')) return;
    try {
        await API.req('prevision_vendedores.php?action=comision_delete', { method: 'POST', json: { id } });
        toast('Comisión eliminada.');
        if (contratoId) pvVerContrato(contratoId); else pvLoadComisiones();
    } catch (e) { toast(e.message); }
}

// ==========================================================================
//  IMPORTACIÓN
// ==========================================================================
async function pvLoadImportacion() {
    try {
        const r = await API.req('prevision_import.php?action=lotes');
        $('#pvImpLotesBody').innerHTML = r.items.map(l => `
            <tr>
                <td>${fmtDate(l.fecha)}</td>
                <td>${escapeHtml(l.tipo)}</td>
                <td class="row-sub">${escapeHtml(l.archivo || '—')}</td>
                <td>${l.total_filas}</td>
                <td>${l.insertados}</td>
                <td>${l.actualizados}</td>
                <td>${l.rechazados > 0 ? `<span class="status-badge badge-red">${l.rechazados}</span>` : '0'}</td>
                <td>${l.simulacion ? '<span class="status-badge badge-blue">simulación</span>' : '<span class="status-badge badge-green">real</span>'}</td>
            </tr>`).join('') || `<tr><td colspan="8" class="empty-row">Sin importaciones registradas.</td></tr>`;
    } catch (e) { /* módulo sin instalar */ }
}

function pvActualizarPlantilla() { /* el enlace se resuelve al hacer clic */ }

async function pvImportar(e) {
    e.preventDefault();
    const file = $('#pvImpFile').files[0];
    if (!file) return toast('Seleccione el archivo CSV.');
    const btn = $('#pvImpBtn');
    btn.disabled = true; btn.innerText = 'Procesando...';
    const fd = new FormData();
    fd.append('archivo', file);
    fd.append('tipo', $('#pvImpTipo').value);
    fd.append('simulacion', $('#pvImpSim').checked ? '1' : '0');
    try {
        const r = await API.req('prevision_import.php?action=importar', { method: 'POST', form: fd });
        const errores = (r.errores || []).map(x => `<li>Fila ${x.fila}: ${escapeHtml(x.error)}</li>`).join('');
        $('#pvImpResult').innerHTML = `
            <div class="admin-card prev-import-result">
                <h4>${r.simulacion ? 'Simulación completada (no se guardó nada)' : 'Importación completada'}</h4>
                <p><strong>${r.total_filas}</strong> filas ·
                   <strong>${r.insertados}</strong> nuevas ·
                   <strong>${r.actualizados}</strong> actualizadas ·
                   <strong class="${r.rechazados ? 'prev-error-text' : ''}">${r.rechazados}</strong> rechazadas</p>
                ${errores ? `<ul class="prev-error-list">${errores}</ul>` : ''}
                ${r.simulacion && !r.rechazados ? '<p>✓ Todo válido. Desactive el modo simulación y vuelva a importar para guardar.</p>' : ''}
            </div>`;
        pvLoadImportacion();
        if (!r.simulacion) { Prevision.loadStats(); Prevision.loadCatalogos(true); }
    } catch (ex) {
        $('#pvImpResult').innerHTML = `<div class="admin-card prev-import-result"><p class="prev-error-text">${escapeHtml(ex.message)}</p></div>`;
    } finally {
        btn.disabled = false; btn.innerText = 'Importar archivo';
    }
}

// ==========================================================================
//  TASA DEL DÍA
// ==========================================================================
function pvSetTasa() {
    openModal('Tasa de cambio del día', `
        <form id="pvTasaForm">
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">Fecha</label>
                    <input type="date" id="pt_fecha" class="form-control" value="${pvHoy()}"></div>
                <div class="form-group"><label class="form-label">Tasa (Bs por USD) *</label>
                    <input type="number" step="0.0001" min="0.0001" id="pt_tasa" class="form-control" required></div>
            </div>
            <p class="setting-help">La tasa se usa para convertir pagos en bolívares de contratos en divisas y para las comisiones.</p>
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Guardar tasa</button>
                <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
            </div>
        </form>`);
    $('#pvTasaForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            await API.req('prevision_contratos.php?action=tasa_set', { method: 'POST', json: {
                fecha: $('#pt_fecha').value, tasa: $('#pt_tasa').value,
            }});
            closeModal(); toast('Tasa registrada.'); Prevision.loadStats();
        } catch (ex) { toast(ex.message); }
    });
}

// Exponer el módulo para el hook de pestañas de admin.js
window.Prevision = Prevision;
