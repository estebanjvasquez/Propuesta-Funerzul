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
    sin: { q: '', estado: '', offset: 0, limit: 25, total: 0 },
    comVista: 'por_calcular',
    cobVista: 'morosos',
    repVista: 'aging',
    msgVista: 'enviar',
    env: { offset: 0, limit: 25, total: 0 },
    cat: { sucursales: [], servicios: [], cobradores: [], rutas: [] },
    msgPlantillas: [],

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
        // Siniestros
        let t4; $('#pvSinSearch').addEventListener('input', () => {
            clearTimeout(t4); t4 = setTimeout(() => { this.sin.q = $('#pvSinSearch').value.trim(); this.sin.offset = 0; pvLoadSiniestros(); }, 300);
        });
        $('#pvSinEstado').addEventListener('change', () => { this.sin.estado = $('#pvSinEstado').value; this.sin.offset = 0; pvLoadSiniestros(); });
        $('#pvNewSiniestroBtn').addEventListener('click', () => pvNuevoSiniestro(''));
        // Cobranza
        $all('#pvCobFilters .filter-btn').forEach(btn => btn.addEventListener('click', () => {
            $all('#pvCobFilters .filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            this.cobVista = btn.dataset.vista;
            pvLoadCobranza();
        }));
        // Reportes
        $all('#pvRepFilters .filter-btn').forEach(btn => btn.addEventListener('click', () => {
            $all('#pvRepFilters .filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            this.repVista = btn.dataset.vista;
            pvLoadReportes();
        }));
        // Mensajes
        $all('#pvMsgFilters .filter-btn').forEach(btn => btn.addEventListener('click', () => {
            $all('#pvMsgFilters .filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            this.msgVista = btn.dataset.vista;
            this.env.offset = 0;
            pvLoadMensajes();
        }));
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
        if (this.sub === 'siniestros') pvLoadSiniestros();
        if (this.sub === 'cobranza') pvLoadCobranza();
        if (this.sub === 'planes') pvLoadPlanes();
        if (this.sub === 'vendedores') pvLoadVendedores();
        if (this.sub === 'comisiones') pvLoadComisiones();
        if (this.sub === 'reportes') pvLoadReportes();
        if (this.sub === 'mensajes') pvLoadMensajes();
        if (this.sub === 'ajustes') pvLoadAjustes();
        if (this.sub === 'catalogos') pvLoadCatalogosTab();
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
            $('#pvStatSiniestros').innerText = s.siniestros_abiertos ?? 0;
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
            try {
                const cat = await API.req('prevision_catalogos.php?action=all');
                this.cat = { sucursales: cat.sucursales, servicios: cat.servicios,
                             cobradores: cat.cobradores, rutas: cat.rutas };
            } catch (e2) { /* v2 sin instalar (importe database/05_prevision_v2.sql) */ }
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
    renuncia: 'badge-gray', excluido: 'badge-gray', fallecido: 'badge-red', finalizado: 'badge-blue',
    pendiente: 'badge-amber', parcial: 'badge-blue', cobrada: 'badge-green', anulada: 'badge-gray',
    abierto: 'badge-amber', liquidado: 'badge-blue', cerrado: 'badge-green', rechazado: 'badge-red',
};
function pvBadge(estatus) {
    return `<span class="status-badge ${PV_ESTATUS_BADGE[estatus] || 'badge-gray'}">${escapeHtml(estatus)}</span>`;
}
const PV_COBERTURA = {
    cubierto: ['badge-green', 'Cubierto'],
    con_observaciones: ['badge-amber', 'Con observaciones'],
    sin_cobertura: ['badge-red', 'Sin cobertura'],
};
function pvCoberturaBadge(c) {
    const [cls, txt] = PV_COBERTURA[c] || ['badge-gray', c];
    return `<span class="status-badge ${cls}">${escapeHtml(txt)}</span>`;
}
const PV_TIPOS_GESTION = { llamada: 'Llamada', visita: 'Visita', whatsapp: 'WhatsApp', sms: 'SMS', email: 'Correo', otro: 'Otro' };
const PV_RESULTADOS_GESTION = { contactado: 'Contactado', no_contactado: 'No contactado', promesa_pago: 'Promesa de pago', reclamo: 'Reclamo', otro: 'Otro' };
const PV_TIPOS_SIN_DETALLE = { servicio: 'Servicio funerario', pago: 'Pago/Indemnización', reintegro: 'Reintegro', otro: 'Otro' };
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
            <div class="form-group"><label class="form-label">Sucursal</label>
                <select id="pf_sucursal" class="form-control">
                    <option value="">—</option>
                    ${Prevision.cat.sucursales.filter(s => Number(s.activo) || s.id == c.sucursal_id).map(s =>
                        `<option value="${s.id}" ${c.sucursal_id == s.id ? 'selected' : ''}>${escapeHtml(s.nombre)}</option>`).join('')}
                </select></div>
            <div class="form-group"><label class="form-label">Ruta de cobro</label>
                <select id="pf_ruta" class="form-control">
                    <option value="">— Sin ruta —</option>
                    ${Prevision.cat.rutas.filter(r => Number(r.activo) || r.id == c.ruta_id).map(r =>
                        `<option value="${r.id}" ${c.ruta_id == r.id ? 'selected' : ''}>${escapeHtml(r.nombre)}${r.cobrador_nombre ? ' · ' + escapeHtml(r.cobrador_nombre) : ''}</option>`).join('')}
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
        sucursal_id: $('#pf_sucursal').value || null,
        ruta_id: $('#pf_ruta').value || null,
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
                    <div><span>Sucursal</span><strong>${escapeHtml(c.sucursal_nombre || '—')}</strong></div>
                    <div><span>Ruta de cobro</span><strong>${escapeHtml(c.ruta_nombre || '—')}</strong></div>
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
                    <button class="btn btn-outline btn-sm" onclick="pvContratoServicioAdd(${c.id})">+ Servicio</button>
                    <button class="btn btn-outline btn-sm" onclick="pvGestionForm(${c.id}, true)">+ Gestión</button>
                    <button class="btn btn-outline btn-sm" onclick="pvMsgEnviarForm(${c.id})">Mensaje</button>
                    <button class="btn btn-outline btn-sm" onclick="pvNuevoSiniestro('${escapeHtml(c.numero)}')">Registrar siniestro</button>
                    <button class="btn btn-outline btn-sm" onclick="pvEditContrato(${c.id})">Editar</button>
                    <button class="btn btn-outline btn-sm" onclick="pvEstatusContrato(${c.id}, '${c.estatus}')">Cambiar estatus</button>
                </div>
                ${(r.siniestros || []).length ? `
                <h4 class="prev-h4">Siniestros</h4>
                <div class="table-responsive"><table class="admin-table admin-table-compact">
                    <thead><tr><th>Fallecido</th><th>Defunción</th><th>Cobertura</th><th>Estado</th><th></th></tr></thead>
                    <tbody>${r.siniestros.map(s => `
                        <tr>
                            <td>${escapeHtml(s.nombre_fallecido)}</td>
                            <td>${fmtDate(s.fecha_defuncion)}</td>
                            <td>${pvCoberturaBadge(s.cobertura)}</td>
                            <td>${pvBadge(s.estado)}</td>
                            <td><button class="btn btn-outline btn-sm" onclick="pvVerSiniestro(${s.id})">Ver</button></td>
                        </tr>`).join('')}</tbody></table></div>` : ''}
                ${(r.servicios || []).length ? `
                <h4 class="prev-h4">Servicios adicionales</h4>
                <div class="table-responsive"><table class="admin-table admin-table-compact">
                    <thead><tr><th>Servicio</th><th>Precio</th><th>Tipo</th><th>Desde</th><th></th></tr></thead>
                    <tbody>${r.servicios.map(s => `
                        <tr>
                            <td>${escapeHtml(s.nombre)}${s.notas ? `<div class="row-sub">${escapeHtml(s.notas)}</div>` : ''}</td>
                            <td>${pvMoney(s.precio, s.moneda)}</td>
                            <td>${s.recurrente ? 'Recurrente' : 'Cargo único'}${s.activo ? '' : ' · <span class="status-badge badge-gray">inactivo</span>'}</td>
                            <td>${fmtDate(s.fecha)}</td>
                            <td><div class="admin-actions">
                                <button class="btn btn-outline btn-sm" onclick="pvContratoServicioToggle(${s.id}, ${c.id}, ${s.activo ? 0 : 1})">${s.activo ? 'Desactivar' : 'Activar'}</button>
                                <button class="btn btn-danger btn-sm" onclick="pvContratoServicioDelete(${s.id}, ${c.id})">Quitar</button>
                            </div></td>
                        </tr>`).join('')}</tbody></table></div>` : ''}
                ${(r.gestiones || []).length ? `
                <h4 class="prev-h4">Gestiones de cobranza recientes</h4>
                <div class="table-responsive"><table class="admin-table admin-table-compact">
                    <thead><tr><th>Fecha</th><th>Tipo</th><th>Resultado</th><th>Notas</th></tr></thead>
                    <tbody>${r.gestiones.map(g => `
                        <tr>
                            <td>${fmtDate(g.fecha)}</td>
                            <td>${escapeHtml(PV_TIPOS_GESTION[g.tipo] || g.tipo)}</td>
                            <td>${escapeHtml(PV_RESULTADOS_GESTION[g.resultado] || g.resultado)}${g.promesa_fecha ? `<div class="row-sub">Promete pagar el ${fmtDate(g.promesa_fecha)}${g.promesa_monto ? ' (' + pvMoney(g.promesa_monto, c.moneda) + ')' : ''}</div>` : ''}</td>
                            <td class="row-sub">${escapeHtml(g.notas || '—')}</td>
                        </tr>`).join('')}</tbody></table></div>` : ''}
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
                ${pvSelect('pe_estatus', { activo: 'Activo', suspendido: 'Suspendido', anulado: 'Anulado', renuncia: 'Renuncia', finalizado: 'Finalizado (servicio cumplido)' }, actual)}</div>
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
            <div class="form-group"><label class="form-label">Sucursal</label>
                <select id="pw_sucursal" class="form-control">
                    <option value="">—</option>
                    ${Prevision.cat.sucursales.filter(s => Number(s.activo) || s.id == v.sucursal_id).map(s =>
                        `<option value="${s.id}" ${v.sucursal_id == s.id ? 'selected' : ''}>${escapeHtml(s.nombre)}</option>`).join('')}
                </select></div>
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
            sucursal_id: $('#pw_sucursal').value || null,
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
    const vq = vid ? '&vendedor_id=' + vid : '';
    const v = Prevision.comVista;
    try {
        if (v === 'por_calcular') {
            const r = await API.req('prevision_vendedores.php?action=comisiones_pendientes' + vq);
            cont.innerHTML = `
                <div class="admin-toolbar prev-h3">
                    <p class="setting-help">${r.items.length} etapa(s) vencida(s) sin generar. Al calcular, quedan en estado <strong>calculada</strong> para su revisión.</p>
                    ${r.items.length ? `<button class="btn btn-primary btn-sm" onclick="pvComCalcularTodas('${vid || ''}')">⚙ Calcular todas</button>` : ''}
                </div>
                <div class="table-responsive"><table class="admin-table">
                    <thead><tr><th>Contrato</th><th>Vendedor</th><th>Etapa</th><th>Vencida desde</th><th>Sugerido</th><th>Acciones</th></tr></thead>
                    <tbody>${r.items.map(x => `
                        <tr>
                            <td><a href="#" onclick="pvVerContrato(${x.contrato_id});return false;"><strong>${escapeHtml(x.contrato_numero)}</strong></a></td>
                            <td>${escapeHtml(x.vendedor_nombre)}</td>
                            <td><span class="status-badge badge-gray">${escapeHtml(PV_ETAPAS[x.etapa] || x.etapa)}</span></td>
                            <td>${fmtDate(x.vencida_desde)}</td>
                            <td>${x.monto_sugerido > 0 ? pvMoney(x.monto_sugerido, x.moneda) : '—'}</td>
                            <td><button class="btn btn-outline btn-sm"
                                onclick="pvComCalcular(${x.contrato_id}, '${x.etapa}')">Calcular</button></td>
                        </tr>`).join('') || `<tr><td colspan="6" class="empty-row">No hay etapas vencidas por generar. 🎉</td></tr>`}
                    </tbody></table></div>`;
        } else if (v === 'por_aprobar' || v === 'por_pagar') {
            const estado = v === 'por_aprobar' ? 'calculada' : 'aprobada';
            const r = await API.req('prevision_vendedores.php?action=comisiones_estado&estado=' + estado + vq);
            const ids = r.items.map(k => k.id);
            const accion = v === 'por_aprobar'
                ? `<button class="btn btn-primary btn-sm" onclick="pvComAprobar([${ids.join(',')}])">✓ Aprobar todas</button>`
                : '';
            cont.innerHTML = `
                <div class="admin-toolbar prev-h3">
                    <p class="setting-help">${r.items.length} comisión(es) · total sugerido <strong>${pvMoney(r.total_usd, 'USD')}</strong>.
                    ${v === 'por_aprobar' ? 'Verifique el monto y apruebe.' : 'Apruébelas están listas para enviar a pagar.'}</p>
                    ${r.items.length ? accion : ''}
                </div>
                <div class="table-responsive"><table class="admin-table">
                    <thead><tr><th>Contrato</th><th>Vendedor</th><th>Etapa</th><th>Base</th><th>%</th><th>Monto</th><th>Acciones</th></tr></thead>
                    <tbody>${r.items.map(k => `
                        <tr>
                            <td><a href="#" onclick="pvVerContrato(${k.contrato_id});return false;"><strong>${escapeHtml(k.contrato_numero)}</strong></a>
                                ${k.fecha_calculo ? `<div class="row-sub">calc. ${fmtDate(k.fecha_calculo)}</div>` : ''}</td>
                            <td>${escapeHtml(k.vendedor_nombre)}</td>
                            <td><span class="status-badge ${v === 'por_aprobar' ? 'badge-amber' : 'badge-blue'}">${escapeHtml(PV_ETAPAS[k.etapa] || k.etapa)}</span></td>
                            <td>${pvMoney(k.base_monto, k.moneda)}</td>
                            <td>${pvNum(k.porcentaje)}%</td>
                            <td><strong>${pvMoney(k.monto_calculado, k.moneda)}</strong></td>
                            <td><div class="admin-actions">
                                ${v === 'por_aprobar'
                                    ? `<button class="btn btn-outline btn-sm" onclick="pvComAjustar(${k.id}, ${k.monto_calculado})">Ajustar</button>
                                       <button class="btn btn-primary btn-sm" onclick="pvComAprobar([${k.id}])">Aprobar</button>`
                                    : `<button class="btn btn-primary btn-sm" onclick="pvPagarComisionForm(0, '', 0, 0, ${k.id}, ${k.monto_calculado})">Enviar a pagar</button>`}
                                <button class="btn btn-danger btn-sm" onclick="pvComAnular(${k.id})">Anular</button>
                            </div></td>
                        </tr>`).join('') || `<tr><td colspan="7" class="empty-row">No hay comisiones ${v === 'por_aprobar' ? 'por aprobar' : 'por pagar'}.</td></tr>`}
                    </tbody></table></div>`;
        } else if (v === 'pagadas') {
            const r = await API.req('prevision_vendedores.php?action=comisiones' + vq);
            cont.innerHTML = `
                <div class="table-responsive"><table class="admin-table">
                    <thead><tr><th>Fecha</th><th>Contrato</th><th>Vendedor</th><th>Etapa</th><th>Monto USD</th><th>Monto Bs</th><th>Aprobó</th><th></th></tr></thead>
                    <tbody>${r.items.map(k => `
                        <tr>
                            <td>${fmtDate(k.fecha_pago)}</td>
                            <td><a href="#" onclick="pvVerContrato(${k.contrato_id});return false;">${escapeHtml(k.contrato_numero)}</a></td>
                            <td>${escapeHtml(k.vendedor_nombre)}</td>
                            <td>${escapeHtml(PV_ETAPAS[k.etapa] || k.etapa)}</td>
                            <td>${pvMoney(k.monto_usd, 'USD')}</td>
                            <td>${k.monto_bs > 0 ? pvMoney(k.monto_bs, 'BS') : '—'}</td>
                            <td class="row-sub">${escapeHtml(k.aprobador || '—')}</td>
                            <td>${pvEsAdmin() ? `<button class="btn btn-danger btn-sm" onclick="pvDeleteComision(${k.id}, 0)">Eliminar</button>` : ''}</td>
                        </tr>`).join('') || `<tr><td colspan="8" class="empty-row">Sin comisiones pagadas.</td></tr>`}
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

async function pvComCalcular(contratoId, etapa) {
    try {
        const r = await API.req('prevision_vendedores.php?action=comision_calcular', { method: 'POST', json: { contrato_id: contratoId, etapa } });
        toast(r.generadas ? 'Comisión calculada.' : 'La etapa no está vencida o ya fue generada.');
        pvLoadComisiones();
    } catch (e) { toast(e.message); }
}

async function pvComCalcularTodas(vid) {
    if (!confirmAction('¿Calcular todas las comisiones vencidas pendientes de generar?')) return;
    try {
        const r = await API.req('prevision_vendedores.php?action=comision_calcular', { method: 'POST', json: { all: 1, vendedor_id: vid || undefined } });
        toast(`${r.generadas} comisión(es) calculada(s). Revíselas en "Por aprobar".`);
        pvLoadComisiones();
    } catch (e) { toast(e.message); }
}

async function pvComAprobar(ids) {
    if (!ids || !ids.length) { toast('No hay comisiones para aprobar.'); return; }
    if (!confirmAction(`¿Aprobar ${ids.length} comisión(es)? Quedarán listas para enviar a pagar.`)) return;
    try {
        const r = await API.req('prevision_vendedores.php?action=comision_aprobar', { method: 'POST', json: { ids } });
        toast(`${r.aprobadas} comisión(es) aprobada(s).`);
        pvLoadComisiones();
    } catch (e) { toast(e.message); }
}

async function pvComAnular(id) {
    if (!confirmAction('¿Anular esta comisión? Podrá volver a calcularla luego.')) return;
    try {
        await API.req('prevision_vendedores.php?action=comision_anular', { method: 'POST', json: { id } });
        toast('Comisión anulada.'); pvLoadComisiones();
    } catch (e) { toast(e.message); }
}

function pvComAjustar(id, actual) {
    openModal('Ajustar monto de la comisión', `
        <form id="pvComAjForm">
            <div class="form-group"><label class="form-label">Monto calculado (USD)</label>
                <input type="number" step="0.01" min="0" id="ca_monto" class="form-control" value="${actual || ''}" required></div>
            <div class="form-group"><label class="form-label">Comentario</label>
                <input type="text" id="ca_comentario" class="form-control" maxlength="200"></div>
            <p id="ca_error" class="login-error" hidden></p>
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Guardar</button>
                <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
            </div>
        </form>`);
    $('#pvComAjForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const err = $('#ca_error'); err.hidden = true;
        try {
            await API.req('prevision_vendedores.php?action=comision_actualizar', { method: 'POST', json: {
                id, monto_calculado: $('#ca_monto').value, comentario: $('#ca_comentario').value.trim(),
            }});
            closeModal(); toast('Monto ajustado.'); pvLoadComisiones();
        } catch (ex) { err.innerText = ex.message; err.hidden = false; }
    });
}

// Formulario de pago. Con comisionId paga una comisión ya generada (flujo);
// sin él, registra un pago directo por contrato+etapa (detalle del contrato).
function pvPagarComisionForm(contratoId, etapa, vendedorId, sugerido, comisionId, calculado) {
    const monto = calculado || sugerido || '';
    openModal(comisionId ? 'Enviar comisión a pagar' : 'Pagar comisión', `
        <form id="pvComForm">
            ${comisionId ? '' : `
            <div class="form-group"><label class="form-label">Etapa *</label>
                ${pvSelect('pk_etapa', PV_ETAPAS, etapa || 'semana1')}</div>`}
            <div class="form-group"><label class="form-label">Fecha de pago</label>
                <input type="date" id="pk_fecha" class="form-control" value="${pvHoy()}"></div>
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">Monto USD</label>
                    <input type="number" step="0.01" min="0" id="pk_usd" class="form-control" value="${monto}"></div>
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
                <button type="submit" class="btn btn-primary">${comisionId ? 'Registrar pago' : 'Registrar pago de comisión'}</button>
                <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
            </div>
        </form>`);
    $('#pvComForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const err = $('#pk_error'); err.hidden = true;
        try {
            const json = {
                fecha_pago: $('#pk_fecha').value,
                monto_usd: $('#pk_usd').value, monto_bs: $('#pk_bs').value, tasa: $('#pk_tasa').value,
                comentario: $('#pk_comentario').value.trim(),
            };
            if (comisionId) { json.id = comisionId; }
            else { json.contrato_id = contratoId; json.vendedor_id = vendedorId || undefined; json.etapa = $('#pk_etapa').value; }
            await API.req('prevision_vendedores.php?action=comision_pagar', { method: 'POST', json });
            closeModal(); toast('Comisión pagada.');
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

// ==========================================================================
//  SINIESTROS / RECLAMOS
// ==========================================================================
async function pvLoadSiniestros() {
    const st = Prevision.sin;
    try {
        const params = new URLSearchParams({ action: 'list', limit: st.limit, offset: st.offset });
        if (st.q) params.set('q', st.q);
        if (st.estado) params.set('estado', st.estado);
        const r = await API.req('prevision_siniestros.php?' + params);
        st.total = r.total;
        const tb = $('#pvSinBody');
        if (!r.items.length) {
            tb.innerHTML = `<tr><td colspan="8" class="empty-row">Sin siniestros registrados.</td></tr>`;
        } else {
            tb.innerHTML = r.items.map(s => `
                <tr>
                    <td><strong>#${s.id}</strong><div class="row-sub">${fmtDate(s.fecha_reporte)}</div></td>
                    <td><div class="row-name">${escapeHtml(s.nombre_fallecido)}</div>
                        <div class="row-sub">${escapeHtml(s.parentesco || '')}${s.es_titular ? ' (titular)' : ''}</div></td>
                    <td><a href="#" onclick="pvVerContrato(${s.contrato_id});return false;">${escapeHtml(s.contrato_numero)}</a>
                        <div class="row-sub">${escapeHtml(s.cliente_nombre || '')}</div></td>
                    <td>${fmtDate(s.fecha_defuncion)}</td>
                    <td>${pvCoberturaBadge(s.cobertura)}</td>
                    <td>${s.monto_total > 0 ? pvMoney(s.monto_total, s.moneda) : '—'}</td>
                    <td>${pvBadge(s.estado)}</td>
                    <td><button class="btn btn-outline btn-sm" onclick="pvVerSiniestro(${s.id})">Ver</button></td>
                </tr>`).join('');
        }
        pvPager($('#pvSinPager'), st, pvLoadSiniestros);
    } catch (e) { toast(e.message); }
}

function pvNuevoSiniestro(numeroPrefill) {
    openModal('Registrar siniestro — Paso 1 de 2', `
        <form id="pvSinPrepForm">
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">Nº de contrato *</label>
                    <input type="text" id="ps_numero" class="form-control" value="${escapeHtml(numeroPrefill || '')}" required></div>
                <div class="form-group"><label class="form-label">Fecha de defunción *</label>
                    <input type="date" id="ps_fecha" class="form-control" value="${pvHoy()}" required></div>
            </div>
            <p class="setting-help">El sistema verificará la cobertura del contrato (estatus, plazo de espera y solvencia) para cada beneficiario.</p>
            <p id="ps_error" class="login-error" hidden></p>
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Continuar</button>
                <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
            </div>
        </form>`);
    $('#pvSinPrepForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const err = $('#ps_error'); err.hidden = true;
        try {
            const r = await API.req('prevision_siniestros.php?action=preparar&numero=' +
                encodeURIComponent($('#ps_numero').value.trim()) + '&fecha=' + $('#ps_fecha').value);
            pvSiniestroPaso2(r);
        } catch (ex) { err.innerText = ex.message; err.hidden = false; }
    });
}

function pvSiniestroPaso2(prep) {
    const opciones = prep.beneficiarios.map((b, i) => `
        <label class="prev-radio-row">
            <input type="radio" name="ps_benef" value="${b.id}" ${i === 0 ? 'checked' : ''}>
            <span><strong>${escapeHtml(b.nombre)}</strong> · ${escapeHtml(b.parentesco)}${b.es_titular ? ' (titular)' : ''}
                ${b.cedula ? ' · ' + escapeHtml(b.cedula) : ''}${b.edad !== null ? ' · ' + b.edad + ' años' : ''}</span>
            ${pvCoberturaBadge(b.cobertura)}
        </label>
        <ul class="prev-checks prev-checks-mini">${b.checks.map(ch => `
            <li class="${ch.ok ? 'prev-check-ok' : 'prev-check-bad'}">${ch.ok ? '✓' : '✗'} ${escapeHtml(ch.detalle)}</li>`).join('')}
        </ul>`).join('');

    openModal(`Registrar siniestro — Contrato ${escapeHtml(prep.contrato.numero)}`, `
        <form id="pvSinForm">
            <p class="setting-help">Titular: <strong>${escapeHtml(prep.contrato.cliente_nombre || '')}</strong> ·
               Defunción: <strong>${fmtDate(prep.fecha)}</strong></p>
            <div class="form-group"><label class="form-label">¿Quién falleció? *</label>
                ${opciones || '<p class="prev-error-text">Este contrato no tiene beneficiarios disponibles.</p>'}
            </div>
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">Reportado por</label>
                    <input type="text" id="ps_reporta" class="form-control" maxlength="100"></div>
                <div class="form-group"><label class="form-label">Teléfono de contacto</label>
                    <input type="text" id="ps_telefono" class="form-control" maxlength="20"></div>
            </div>
            <div class="form-group"><label class="form-label">Observaciones</label>
                <textarea id="ps_obs" class="form-control" maxlength="500"></textarea></div>
            <p id="ps_error2" class="login-error" hidden></p>
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Registrar siniestro</button>
                <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
            </div>
        </form>`);
    $('#pvSinForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const err = $('#ps_error2'); err.hidden = true;
        const sel = document.querySelector('input[name="ps_benef"]:checked');
        if (!sel) { err.innerText = 'Seleccione el beneficiario fallecido.'; err.hidden = false; return; }
        try {
            const r = await API.req('prevision_siniestros.php?action=create', { method: 'POST', json: {
                contrato_id: prep.contrato.id, beneficiario_id: Number(sel.value),
                fecha_defuncion: prep.fecha,
                reportado_por: $('#ps_reporta').value.trim(),
                telefono_reporta: $('#ps_telefono').value.trim(),
                observaciones: $('#ps_obs').value.trim(),
            }});
            toast('Siniestro registrado.');
            Prevision.loadStats();
            if (Prevision.sub === 'siniestros') pvLoadSiniestros();
            pvVerSiniestro(r.id);
        } catch (ex) { err.innerText = ex.message; err.hidden = false; }
    });
}

async function pvVerSiniestro(id) {
    try {
        const r = await API.req('prevision_siniestros.php?action=get&id=' + id);
        const s = r.item;
        const admin = pvEsAdmin();
        const abierto = ['abierto', 'liquidado'].includes(s.estado);

        const checks = (s.validacion || []).map(ch => `
            <li class="${ch.ok ? 'prev-check-ok' : 'prev-check-bad'}">${ch.ok ? '✓' : '✗'}
                <strong>${escapeHtml(ch.check)}:</strong> ${escapeHtml(ch.detalle)}</li>`).join('');

        const detalles = r.detalles.map(d => `
            <tr>
                <td>${escapeHtml(PV_TIPOS_SIN_DETALLE[d.tipo] || d.tipo)}</td>
                <td>${escapeHtml(d.descripcion)}${d.proveedor ? `<div class="row-sub">${escapeHtml(d.proveedor)}</div>` : ''}</td>
                <td>${pvMoney(d.monto, d.moneda)}</td>
                <td>${d.pagado ? `<span class="status-badge badge-green">pagado ${fmtDate(d.fecha_pago)}</span>` : '<span class="status-badge badge-amber">por pagar</span>'}</td>
                <td><div class="admin-actions">
                    ${abierto ? `<button class="btn btn-outline btn-sm" onclick="pvSinDetallePagado(${d.id}, ${s.id}, ${d.pagado ? 0 : 1})">${d.pagado ? 'No pagado' : 'Pagado'}</button>` : ''}
                    ${abierto ? `<button class="btn btn-danger btn-sm" onclick="pvSinDetalleDelete(${d.id}, ${s.id})">Quitar</button>` : ''}
                </div></td>
            </tr>`).join('') || `<tr><td colspan="5" class="empty-row">Sin partidas registradas. Agregue el servicio o pago a liquidar.</td></tr>`;

        openModal(`Siniestro #${s.id} — ${escapeHtml(s.nombre_fallecido)}`, `
            <div class="prev-detail">
                <div class="prev-kv">
                    <div><span>Fallecido</span><strong>${escapeHtml(s.nombre_fallecido)}${s.es_titular ? ' (titular)' : ''}</strong></div>
                    <div><span>Parentesco</span><strong>${escapeHtml(s.parentesco || '—')}</strong></div>
                    <div><span>Contrato</span><strong><a href="#" onclick="pvVerContrato(${s.contrato_id});return false;">${escapeHtml(s.contrato_numero)}</a> · ${escapeHtml(s.cliente_nombre || '')}</strong></div>
                    <div><span>Plan</span><strong>${escapeHtml(s.plan_nombre || '—')}</strong></div>
                    <div><span>Defunción</span><strong>${fmtDate(s.fecha_defuncion)}</strong></div>
                    <div><span>Reportado</span><strong>${fmtDate(s.fecha_reporte)}${s.reportado_por ? ' por ' + escapeHtml(s.reportado_por) : ''}${s.telefono_reporta ? ' (' + escapeHtml(s.telefono_reporta) + ')' : ''}</strong></div>
                    <div><span>Cobertura</span>${pvCoberturaBadge(s.cobertura)}</div>
                    <div><span>Estado</span>${pvBadge(s.estado)}${s.motivo_rechazo ? ` <span class="row-sub">${escapeHtml(s.motivo_rechazo)}</span>` : ''}</div>
                    <div><span>Monto liquidado</span><strong>${pvMoney(s.monto_total, s.moneda)}</strong></div>
                </div>
                ${s.observaciones ? `<p class="setting-help">${escapeHtml(s.observaciones)}</p>` : ''}
                <h4 class="prev-h4">Validación de cobertura (al registrar)</h4>
                <ul class="prev-checks">${checks || '<li>Sin datos de validación.</li>'}</ul>
                <div class="modal-actions prev-actions">
                    ${abierto ? `<button class="btn btn-primary btn-sm" onclick="pvSinDetalleForm(${s.id}, '${s.moneda}')">+ Servicio / pago</button>` : ''}
                    ${s.estado === 'abierto' ? `<button class="btn btn-outline btn-sm" onclick="pvSiniestroEstado(${s.id}, 'liquidado', ${s.es_titular ? 1 : 0})">Marcar liquidado</button>` : ''}
                    ${abierto ? `<button class="btn btn-outline btn-sm" onclick="pvSiniestroEstado(${s.id}, 'cerrado', ${s.es_titular ? 1 : 0})">Cerrar expediente</button>` : ''}
                    ${abierto ? `<button class="btn btn-outline btn-sm" onclick="pvSiniestroEstado(${s.id}, 'rechazado', ${s.es_titular ? 1 : 0})">Rechazar</button>` : ''}
                    ${!abierto ? `<button class="btn btn-outline btn-sm" onclick="pvSiniestroEstado(${s.id}, 'abierto', ${s.es_titular ? 1 : 0})">Reabrir</button>` : ''}
                    ${admin ? `<button class="btn btn-danger btn-sm" onclick="pvSiniestroDelete(${s.id})">Eliminar expediente</button>` : ''}
                </div>
                <h4 class="prev-h4">Liquidación (servicios y pagos)</h4>
                <div class="table-responsive"><table class="admin-table admin-table-compact">
                    <thead><tr><th>Tipo</th><th>Descripción</th><th>Monto</th><th>Estado</th><th></th></tr></thead>
                    <tbody>${detalles}</tbody></table></div>
            </div>`);
    } catch (e) { toast(e.message); }
}

function pvSiniestroEstado(id, estado, esTitular) {
    if (estado === 'rechazado') {
        openModal('Rechazar siniestro', `
            <form id="pvSinRechazo">
                <div class="form-group"><label class="form-label">Motivo del rechazo *</label>
                    <input type="text" id="psr_motivo" class="form-control" maxlength="255" required></div>
                <div class="modal-actions">
                    <button type="submit" class="btn btn-danger">Rechazar</button>
                    <button type="button" class="btn btn-outline" onclick="pvVerSiniestro(${id})">Volver</button>
                </div>
            </form>`);
        $('#pvSinRechazo').addEventListener('submit', async (e) => {
            e.preventDefault();
            try {
                await API.req('prevision_siniestros.php?action=set_estado', { method: 'POST', json: {
                    id, estado: 'rechazado', motivo: $('#psr_motivo').value.trim(),
                }});
                toast('Siniestro rechazado.'); pvVerSiniestro(id); Prevision.loadStats();
                if (Prevision.sub === 'siniestros') pvLoadSiniestros();
            } catch (ex) { toast(ex.message); }
        });
        return;
    }
    const avisos = {
        liquidado: '¿Marcar el siniestro como liquidado (servicios/pagos ejecutados)?',
        cerrado: esTitular
            ? 'Al cerrar el expediente del TITULAR, el contrato pasará a FINALIZADO y se anularán las cuotas pendientes. ¿Continuar?'
            : '¿Cerrar definitivamente el expediente?',
        abierto: '¿Reabrir el expediente?',
    };
    if (!confirmAction(avisos[estado] || '¿Cambiar el estado?')) return;
    API.req('prevision_siniestros.php?action=set_estado', { method: 'POST', json: { id, estado } })
        .then(() => {
            toast('Estado actualizado.'); pvVerSiniestro(id); Prevision.loadStats();
            if (Prevision.sub === 'siniestros') pvLoadSiniestros();
        })
        .catch(e => toast(e.message));
}

function pvSinDetalleForm(sinId, moneda) {
    openModal('Agregar servicio / pago al siniestro', `
        <form id="pvSinDetForm">
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">Tipo</label>
                    ${pvSelect('pd_tipo', PV_TIPOS_SIN_DETALLE, 'servicio')}</div>
                <div class="form-group"><label class="form-label">Monto *</label>
                    <input type="number" step="0.01" min="0" id="pd_monto" class="form-control" required></div>
            </div>
            <div class="form-group"><label class="form-label">Descripción *</label>
                <input type="text" id="pd_desc" class="form-control" maxlength="200" placeholder="Servicio de cremación, ataúd, traslado..." required></div>
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">Proveedor (si aplica)</label>
                    <input type="text" id="pd_prov" class="form-control" maxlength="150"></div>
                <div class="form-group"><label class="form-label">Moneda</label>
                    ${pvSelect('pd_moneda', { USD: 'USD ($)', BS: 'Bolívares (Bs)' }, moneda || 'USD')}</div>
            </div>
            <div class="form-group"><label class="form-label">Notas</label>
                <input type="text" id="pd_notas" class="form-control" maxlength="255"></div>
            <p id="pd_error" class="login-error" hidden></p>
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Agregar</button>
                <button type="button" class="btn btn-outline" onclick="pvVerSiniestro(${sinId})">Volver</button>
            </div>
        </form>`);
    $('#pvSinDetForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const err = $('#pd_error'); err.hidden = true;
        try {
            await API.req('prevision_siniestros.php?action=detalle_add', { method: 'POST', json: {
                siniestro_id: sinId, tipo: $('#pd_tipo').value,
                descripcion: $('#pd_desc').value.trim(), proveedor: $('#pd_prov').value.trim(),
                moneda: $('#pd_moneda').value, monto: $('#pd_monto').value,
                notas: $('#pd_notas').value.trim(),
            }});
            toast('Partida agregada.'); pvVerSiniestro(sinId);
        } catch (ex) { err.innerText = ex.message; err.hidden = false; }
    });
}

async function pvSinDetallePagado(id, sinId, pagado) {
    try {
        await API.req('prevision_siniestros.php?action=detalle_pagado', { method: 'POST', json: { id, pagado } });
        pvVerSiniestro(sinId);
    } catch (e) { toast(e.message); }
}

async function pvSinDetalleDelete(id, sinId) {
    if (!confirmAction('¿Quitar esta partida de la liquidación?')) return;
    try {
        await API.req('prevision_siniestros.php?action=detalle_delete', { method: 'POST', json: { id } });
        pvVerSiniestro(sinId);
    } catch (e) { toast(e.message); }
}

async function pvSiniestroDelete(id) {
    if (!confirmAction('¿Eliminar el expediente completo? El beneficiario se restaurará como activo.')) return;
    try {
        await API.req('prevision_siniestros.php?action=delete', { method: 'POST', json: { id } });
        closeModal(); toast('Expediente eliminado.'); Prevision.loadStats();
        if (Prevision.sub === 'siniestros') pvLoadSiniestros();
    } catch (e) { toast(e.message); }
}

// ==========================================================================
//  COBRANZA (morosos, gestiones, auto-lapsado, hoja de cobro)
// ==========================================================================
async function pvLoadCobranza() {
    const cont = $('#pvCobContent');
    try {
        if (Prevision.cobVista === 'morosos') {
            const r = await API.req('prevision_cobranza.php?action=morosos');
            cont.innerHTML = `
                <div class="table-responsive"><table class="admin-table">
                    <thead><tr><th>Contrato</th><th>Cliente</th><th>Teléfono</th><th>Vencidas</th><th>Saldo</th><th>Mora</th><th>Última gestión</th><th>Acciones</th></tr></thead>
                    <tbody>${r.items.map(m => `
                        <tr>
                            <td><a href="#" onclick="pvVerContrato(${m.contrato_id});return false;"><strong>${escapeHtml(m.numero)}</strong></a> ${pvBadge(m.estatus)}</td>
                            <td>${escapeHtml(m.cliente_nombre)}</td>
                            <td>${escapeHtml(m.telefono || '—')}</td>
                            <td><span class="status-badge badge-red">${m.cuotas_vencidas}</span></td>
                            <td>${pvMoney(m.saldo_vencido, m.moneda)}</td>
                            <td>${m.dias_mora} días</td>
                            <td>${m.ultima_gestion ? fmtDate(m.ultima_gestion) : '<span class="row-sub">nunca</span>'}</td>
                            <td><div class="admin-actions">
                                <button class="btn btn-outline btn-sm" onclick="pvGestionForm(${m.contrato_id}, false)">Gestión</button>
                                <button class="btn btn-outline btn-sm" onclick="pvMsgEnviarForm(${m.contrato_id})">Mensaje</button>
                                <button class="btn btn-outline btn-sm" onclick="pvRegistrarPago(${m.contrato_id}, 0)">Cobrar</button>
                            </div></td>
                        </tr>`).join('') || `<tr><td colspan="8" class="empty-row">No hay contratos con cuotas vencidas. 🎉</td></tr>`}
                    </tbody></table></div>`;
        } else if (Prevision.cobVista === 'gestiones') {
            const r = await API.req('prevision_cobranza.php?action=gestiones');
            cont.innerHTML = `
                <div class="table-responsive"><table class="admin-table">
                    <thead><tr><th>Fecha</th><th>Contrato</th><th>Cliente</th><th>Tipo</th><th>Resultado</th><th>Notas</th><th></th></tr></thead>
                    <tbody>${r.items.map(g => `
                        <tr>
                            <td>${fmtDate(g.fecha)}</td>
                            <td><a href="#" onclick="pvVerContrato(${g.contrato_id});return false;">${escapeHtml(g.contrato_numero)}</a></td>
                            <td>${escapeHtml(g.cliente_nombre || '')}</td>
                            <td>${escapeHtml(PV_TIPOS_GESTION[g.tipo] || g.tipo)}</td>
                            <td>${escapeHtml(PV_RESULTADOS_GESTION[g.resultado] || g.resultado)}${g.promesa_fecha ? `<div class="row-sub">Promesa: ${fmtDate(g.promesa_fecha)}${g.promesa_monto ? ' · ' + pvNum(g.promesa_monto) : ''}</div>` : ''}</td>
                            <td class="row-sub">${escapeHtml(g.notas || '—')}</td>
                            <td>${pvEsAdmin() ? `<button class="btn btn-danger btn-sm" onclick="pvGestionDelete(${g.id})">Eliminar</button>` : ''}</td>
                        </tr>`).join('') || `<tr><td colspan="7" class="empty-row">Sin gestiones registradas.</td></tr>`}
                    </tbody></table></div>`;
        } else if (Prevision.cobVista === 'lapsado') {
            const cfg = await API.req('prevision_cobranza.php?action=lapsado_config');
            cont.innerHTML = `
                <div class="admin-card settings-card">
                    <div class="setting-row">
                        <div><strong>Suspensión automática de morosos</strong>
                            <p class="setting-help">El cron diario suspende los contratos activos que acumulen el número de cuotas vencidas indicado.
                            Programe en cPanel: <code>api/cron/prevision_lapsar.php</code></p></div>
                        <label class="switch"><input type="checkbox" id="pvLapEnabled" ${cfg.enabled ? 'checked' : ''} ${pvEsAdmin() ? '' : 'disabled'}><span class="slider"></span></label>
                    </div>
                    <div class="form-group"><label class="form-label">Cuotas vencidas para suspender</label>
                        <input type="number" min="1" max="24" id="pvLapCuotas" class="form-control prev-select" value="${cfg.cuotas}" ${pvEsAdmin() ? '' : 'disabled'}></div>
                    <div class="setting-actions">
                        ${pvEsAdmin() ? `<button class="btn btn-primary" onclick="pvLapsadoGuardar()">Guardar configuración</button>` : ''}
                        <button class="btn btn-outline" onclick="pvLapsadoPreview()">Vista previa</button>
                        ${pvEsAdmin() ? `<button class="btn btn-danger" onclick="pvLapsadoEjecutar()">Ejecutar ahora</button>` : ''}
                    </div>
                </div>
                <div id="pvLapResult"></div>`;
        } else if (Prevision.cobVista === 'hoja') {
            const rutas = Prevision.cat.rutas.filter(r => Number(r.activo));
            cont.innerHTML = `
                <div class="admin-card settings-card">
                    <div class="form-group"><label class="form-label">Ruta de cobro</label>
                        <select id="pvHojaRuta" class="form-control prev-select">
                            ${rutas.map(r => `<option value="${r.id}">${escapeHtml(r.nombre)}${r.cobrador_nombre ? ' · ' + escapeHtml(r.cobrador_nombre) : ''} (${r.contratos} contratos)</option>`).join('')}
                        </select></div>
                    <div class="setting-actions">
                        <button class="btn btn-primary" onclick="pvHojaGenerar()">Generar hoja de cobro</button>
                    </div>
                    ${rutas.length ? '' : '<p class="setting-help">No hay rutas activas. Créelas en la sub-pestaña Catálogos y asígnelas a los contratos.</p>'}
                </div>
                <div id="pvHojaResult"></div>`;
        }
    } catch (e) { toast(e.message); }
}

function pvGestionForm(contratoId, fromDetail) {
    openModal('Registrar gestión de cobranza', `
        <form id="pvGesForm">
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">Fecha</label>
                    <input type="date" id="pg_fecha2" class="form-control" value="${pvHoy()}"></div>
                <div class="form-group"><label class="form-label">Tipo de contacto</label>
                    ${pvSelect('pg_tipo', PV_TIPOS_GESTION, 'llamada')}</div>
            </div>
            <div class="form-group"><label class="form-label">Resultado</label>
                ${pvSelect('pg_resultado', PV_RESULTADOS_GESTION, 'contactado')}</div>
            <div class="form-grid-2" id="pg_promesa_row" hidden>
                <div class="form-group"><label class="form-label">Fecha prometida</label>
                    <input type="date" id="pg_promesa_fecha" class="form-control"></div>
                <div class="form-group"><label class="form-label">Monto prometido</label>
                    <input type="number" step="0.01" min="0" id="pg_promesa_monto" class="form-control"></div>
            </div>
            <div class="form-group"><label class="form-label">Notas</label>
                <textarea id="pg_notas" class="form-control" maxlength="500"></textarea></div>
            <p id="pg_error" class="login-error" hidden></p>
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Guardar gestión</button>
                <button type="button" class="btn btn-outline" onclick="${fromDetail ? `pvVerContrato(${contratoId})` : 'closeModal()'}">Volver</button>
            </div>
        </form>`);
    $('#pg_resultado').addEventListener('change', () => {
        $('#pg_promesa_row').hidden = $('#pg_resultado').value !== 'promesa_pago';
    });
    $('#pvGesForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const err = $('#pg_error'); err.hidden = true;
        try {
            await API.req('prevision_cobranza.php?action=gestion_add', { method: 'POST', json: {
                contrato_id: contratoId, fecha: $('#pg_fecha2').value,
                tipo: $('#pg_tipo').value, resultado: $('#pg_resultado').value,
                promesa_fecha: $('#pg_promesa_fecha').value, promesa_monto: $('#pg_promesa_monto').value,
                notas: $('#pg_notas').value.trim(),
            }});
            toast('Gestión registrada.');
            if (fromDetail) pvVerContrato(contratoId);
            else { closeModal(); if (Prevision.sub === 'cobranza') pvLoadCobranza(); }
        } catch (ex) { err.innerText = ex.message; err.hidden = false; }
    });
}

async function pvGestionDelete(id) {
    if (!confirmAction('¿Eliminar esta gestión?')) return;
    try {
        await API.req('prevision_cobranza.php?action=gestion_delete', { method: 'POST', json: { id } });
        toast('Gestión eliminada.'); pvLoadCobranza();
    } catch (e) { toast(e.message); }
}

async function pvLapsadoGuardar() {
    try {
        const r = await API.req('prevision_cobranza.php?action=lapsado_config_set', { method: 'POST', json: {
            enabled: $('#pvLapEnabled').checked ? 1 : 0, cuotas: $('#pvLapCuotas').value,
        }});
        toast('Configuración guardada (' + (r.enabled ? 'activado' : 'desactivado') + ', ' + r.cuotas + ' cuotas).');
    } catch (e) { toast(e.message); }
}

function pvLapsadoTabla(items) {
    return `
        <div class="table-responsive"><table class="admin-table">
            <thead><tr><th>Contrato</th><th>Cliente</th><th>Cuotas vencidas</th><th>Saldo</th><th>En mora desde</th></tr></thead>
            <tbody>${items.map(c => `
                <tr>
                    <td><a href="#" onclick="pvVerContrato(${c.contrato_id});return false;"><strong>${escapeHtml(c.numero)}</strong></a></td>
                    <td>${escapeHtml(c.cliente_nombre)}</td>
                    <td><span class="status-badge badge-red">${c.cuotas_vencidas}</span></td>
                    <td>${pvMoney(c.saldo_vencido, c.moneda)}</td>
                    <td>${fmtDate(c.vencida_desde)}</td>
                </tr>`).join('') || `<tr><td colspan="5" class="empty-row">Ningún contrato alcanza el umbral. 🎉</td></tr>`}
            </tbody></table></div>`;
}

async function pvLapsadoPreview() {
    try {
        const r = await API.req('prevision_cobranza.php?action=lapsado_preview&cuotas=' + $('#pvLapCuotas').value);
        $('#pvLapResult').innerHTML = `
            <h4 class="prev-h4">Vista previa: ${r.items.length} contrato(s) se suspenderían con ≥ ${r.cuotas} cuotas vencidas</h4>
            ${pvLapsadoTabla(r.items)}`;
    } catch (e) { toast(e.message); }
}

async function pvLapsadoEjecutar() {
    if (!confirmAction('¿Suspender AHORA todos los contratos que alcanzan el umbral de cuotas vencidas?')) return;
    try {
        const r = await API.req('prevision_cobranza.php?action=lapsado_ejecutar', { method: 'POST', json: {
            cuotas: $('#pvLapCuotas').value,
        }});
        toast(r.suspendidos + ' contrato(s) suspendido(s).');
        $('#pvLapResult').innerHTML = `
            <h4 class="prev-h4">Suspendidos: ${r.suspendidos}</h4>${pvLapsadoTabla(r.items)}`;
        Prevision.loadStats();
    } catch (e) { toast(e.message); }
}

async function pvHojaGenerar() {
    const rutaId = $('#pvHojaRuta') ? $('#pvHojaRuta').value : '';
    if (!rutaId) return toast('Seleccione una ruta.');
    try {
        const r = await API.req('prevision_cobranza.php?action=hoja_cobro&ruta_id=' + rutaId);
        window.pvHojaData = r;
        $('#pvHojaResult').innerHTML = `
            <div class="admin-toolbar prev-h3">
                <h3>Ruta ${escapeHtml(r.ruta.nombre)}${r.ruta.cobrador ? ' — ' + escapeHtml(r.ruta.cobrador) : ''}</h3>
                <button class="btn btn-outline" onclick="pvHojaImprimir()">🖨 Imprimir</button>
            </div>
            <div class="table-responsive"><table class="admin-table admin-table-compact">
                <thead><tr><th>Contrato</th><th>Cliente</th><th>Dirección</th><th>Teléfono</th><th>Cuota</th><th>Vencidas</th><th>Saldo vencido</th></tr></thead>
                <tbody>${r.items.map(x => `
                    <tr class="${x.cuotas_vencidas > 0 ? 'prev-row-vencida' : ''}">
                        <td><strong>${escapeHtml(x.numero)}</strong></td>
                        <td>${escapeHtml(x.cliente_nombre)}</td>
                        <td class="row-sub">${escapeHtml(x.direccion || '—')}</td>
                        <td>${escapeHtml(x.telefono || '—')}</td>
                        <td>${pvMoney(x.monto_cuota, x.moneda)} <span class="row-sub">${escapeHtml(x.frecuencia)}</span></td>
                        <td>${x.cuotas_vencidas || '—'}</td>
                        <td>${x.saldo_vencido > 0 ? pvMoney(x.saldo_vencido, x.moneda) : '—'}</td>
                    </tr>`).join('') || `<tr><td colspan="7" class="empty-row">La ruta no tiene contratos asignados.</td></tr>`}
                </tbody></table></div>`;
    } catch (e) { toast(e.message); }
}

function pvHojaImprimir() {
    const r = window.pvHojaData;
    if (!r) return;
    const filas = r.items.map(x => `
        <tr>
            <td>${escapeHtml(x.numero)}</td><td>${escapeHtml(x.cliente_nombre)}</td>
            <td>${escapeHtml(x.direccion || '')}</td><td>${escapeHtml(x.telefono || '')}</td>
            <td style="text-align:right">${pvMoney(x.monto_cuota, x.moneda)}</td>
            <td style="text-align:center">${x.cuotas_vencidas || ''}</td>
            <td style="text-align:right">${x.saldo_vencido > 0 ? pvMoney(x.saldo_vencido, x.moneda) : ''}</td>
            <td style="width:90px"></td>
        </tr>`).join('');
    const w = window.open('', '_blank');
    w.document.write(`<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">
        <title>Hoja de cobro — Ruta ${escapeHtml(r.ruta.nombre)}</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; margin: 24px; }
            h1 { font-size: 16px; margin: 0 0 4px; } .sub { color: #555; margin: 0 0 16px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #999; padding: 4px 6px; text-align: left; }
            th { background: #eee; }
        </style></head><body>
        <h1>FUNERARIA DEL ZULIA — Hoja de cobro</h1>
        <p class="sub">Ruta: <strong>${escapeHtml(r.ruta.nombre)}</strong>
           ${r.ruta.zona ? ' · Zona: ' + escapeHtml(r.ruta.zona) : ''}
           ${r.ruta.dia_cobro ? ' · Día: ' + escapeHtml(r.ruta.dia_cobro) : ''}
           ${r.ruta.cobrador ? ' · Cobrador: ' + escapeHtml(r.ruta.cobrador) : ''}
           · Emitida: ${fmtDate(pvHoy())}</p>
        <table><thead><tr>
            <th>Contrato</th><th>Cliente</th><th>Dirección</th><th>Teléfono</th>
            <th>Cuota</th><th>Venc.</th><th>Saldo vencido</th><th>Cobrado / Firma</th>
        </tr></thead><tbody>${filas}</tbody></table>
        <script>window.print();<\/script></body></html>`);
    w.document.close();
}

// ==========================================================================
//  SERVICIOS ADICIONALES DE UN CONTRATO
// ==========================================================================
function pvContratoServicioAdd(contratoId) {
    const servicios = Prevision.cat.servicios.filter(s => s.activo);
    if (!servicios.length) {
        return toast('No hay servicios en el catálogo. Créelos en la sub-pestaña Catálogos.');
    }
    openModal('Agregar servicio adicional', `
        <form id="pvCsForm">
            <div class="form-group"><label class="form-label">Servicio *</label>
                <select id="cs_servicio" class="form-control">
                    ${servicios.map(s => `<option value="${s.id}" data-precio="${s.precio}">${escapeHtml(s.nombre)} (${pvMoney(s.precio, s.moneda)}${s.recurrente ? ' recurrente' : ''})</option>`).join('')}
                </select></div>
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">Precio pactado</label>
                    <input type="number" step="0.01" min="0" id="cs_precio" class="form-control" value="${servicios[0].precio}"></div>
                <div class="form-group"><label class="form-label">Desde</label>
                    <input type="date" id="cs_fecha" class="form-control" value="${pvHoy()}"></div>
            </div>
            <div class="form-group"><label class="form-label">Notas</label>
                <input type="text" id="cs_notas" class="form-control" maxlength="255"></div>
            <p id="cs_error" class="login-error" hidden></p>
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Agregar servicio</button>
                <button type="button" class="btn btn-outline" onclick="pvVerContrato(${contratoId})">Volver</button>
            </div>
        </form>`);
    $('#cs_servicio').addEventListener('change', (e) => {
        $('#cs_precio').value = e.target.selectedOptions[0].dataset.precio;
    });
    $('#pvCsForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const err = $('#cs_error'); err.hidden = true;
        try {
            await API.req('prevision_catalogos.php?action=contrato_servicio_add', { method: 'POST', json: {
                contrato_id: contratoId, servicio_id: $('#cs_servicio').value,
                precio: $('#cs_precio').value, fecha: $('#cs_fecha').value,
                notas: $('#cs_notas').value.trim(),
            }});
            toast('Servicio agregado.'); pvVerContrato(contratoId);
        } catch (ex) { err.innerText = ex.message; err.hidden = false; }
    });
}

async function pvContratoServicioToggle(id, contratoId, activo) {
    try {
        await API.req('prevision_catalogos.php?action=contrato_servicio_toggle', { method: 'POST', json: { id, activo } });
        pvVerContrato(contratoId);
    } catch (e) { toast(e.message); }
}

async function pvContratoServicioDelete(id, contratoId) {
    if (!confirmAction('¿Quitar este servicio del contrato?')) return;
    try {
        await API.req('prevision_catalogos.php?action=contrato_servicio_delete', { method: 'POST', json: { id } });
        toast('Servicio quitado.'); pvVerContrato(contratoId);
    } catch (e) { toast(e.message); }
}

// ==========================================================================
//  CATÁLOGOS (sucursales, servicios, cobradores, rutas)
// ==========================================================================
const PV_CAT = {
    sucursales: {
        titulo: 'Sucursales', accionLista: 'sucursales', accionSave: 'sucursal_save',
        accionToggle: 'sucursal_toggle', accionDelete: 'sucursal_delete',
        columnas: ['Nombre', 'Dirección', 'Teléfono', 'Contratos'],
        fila: s => `<td><strong>${escapeHtml(s.nombre)}</strong></td><td class="row-sub">${escapeHtml(s.direccion || '—')}</td>
                    <td>${escapeHtml(s.telefono || '—')}</td><td>${s.contratos ?? 0}</td>`,
        usado: s => Number(s.contratos) > 0,
        campos: s => `
            <div class="form-group"><label class="form-label">Nombre *</label>
                <input type="text" id="cf_nombre" class="form-control" value="${escapeHtml(s.nombre || '')}" required></div>
            <div class="form-group"><label class="form-label">Dirección</label>
                <input type="text" id="cf_direccion" class="form-control" maxlength="255" value="${escapeHtml(s.direccion || '')}"></div>
            <div class="form-group"><label class="form-label">Teléfono</label>
                <input type="text" id="cf_telefono" class="form-control" maxlength="20" value="${escapeHtml(s.telefono || '')}"></div>`,
        leer: () => ({ nombre: $('#cf_nombre').value.trim(), direccion: $('#cf_direccion').value.trim(),
                       telefono: $('#cf_telefono').value.trim() }),
    },
    servicios: {
        titulo: 'Servicios adicionales', accionLista: 'servicios', accionSave: 'servicio_save',
        accionToggle: 'servicio_toggle', accionDelete: 'servicio_delete',
        columnas: ['Nombre', 'Precio', 'Tipo', ''],
        fila: s => `<td><strong>${escapeHtml(s.nombre)}</strong>${s.descripcion ? `<div class="row-sub">${escapeHtml(s.descripcion)}</div>` : ''}</td>
                    <td>${pvMoney(s.precio, s.moneda)}</td><td>${s.recurrente ? 'Recurrente (por cuota)' : 'Cargo único'}</td><td></td>`,
        usado: () => false,
        campos: s => `
            <div class="form-group"><label class="form-label">Nombre *</label>
                <input type="text" id="cf_nombre" class="form-control" value="${escapeHtml(s.nombre || '')}" placeholder="Bóveda, cremación, traslado..." required></div>
            <div class="form-group"><label class="form-label">Descripción</label>
                <input type="text" id="cf_descripcion" class="form-control" maxlength="255" value="${escapeHtml(s.descripcion || '')}"></div>
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">Moneda</label>
                    ${pvSelect('cf_moneda', { USD: 'USD ($)', BS: 'Bolívares (Bs)' }, s.moneda || 'USD')}</div>
                <div class="form-group"><label class="form-label">Precio</label>
                    <input type="number" step="0.01" min="0" id="cf_precio" class="form-control" value="${s.precio ?? 0}"></div>
            </div>
            <div class="setting-row"><div><strong>Recurrente</strong><p class="setting-help">Se suma a cada cuota (si no, es un cargo único).</p></div>
                <label class="switch"><input type="checkbox" id="cf_recurrente" ${s.recurrente ? 'checked' : ''}><span class="slider"></span></label></div>`,
        leer: () => ({ nombre: $('#cf_nombre').value.trim(), descripcion: $('#cf_descripcion').value.trim(),
                       moneda: $('#cf_moneda').value, precio: $('#cf_precio').value,
                       recurrente: $('#cf_recurrente').checked ? 1 : 0 }),
    },
    cobradores: {
        titulo: 'Cobradores', accionLista: 'cobradores', accionSave: 'cobrador_save',
        accionToggle: 'cobrador_toggle', accionDelete: 'cobrador_delete',
        columnas: ['Nombre', 'Cédula', 'Teléfono', 'Rutas'],
        fila: c => `<td><strong>${escapeHtml(c.nombre)}</strong></td><td>${escapeHtml(c.cedula || '—')}</td>
                    <td>${escapeHtml(c.telefono || '—')}</td><td>${c.rutas ?? 0}</td>`,
        usado: c => Number(c.rutas) > 0,
        campos: c => `
            <div class="form-group"><label class="form-label">Nombre *</label>
                <input type="text" id="cf_nombre" class="form-control" value="${escapeHtml(c.nombre || '')}" required></div>
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">Cédula</label>
                    <input type="text" id="cf_cedula" class="form-control" maxlength="15" value="${escapeHtml(c.cedula || '')}"></div>
                <div class="form-group"><label class="form-label">Teléfono</label>
                    <input type="text" id="cf_telefono" class="form-control" maxlength="20" value="${escapeHtml(c.telefono || '')}"></div>
            </div>`,
        leer: () => ({ nombre: $('#cf_nombre').value.trim(), cedula: $('#cf_cedula').value.trim(),
                       telefono: $('#cf_telefono').value.trim() }),
    },
    rutas: {
        titulo: 'Rutas de cobranza', accionLista: 'rutas', accionSave: 'ruta_save',
        accionToggle: 'ruta_toggle', accionDelete: 'ruta_delete',
        columnas: ['Nombre', 'Zona', 'Día', 'Cobrador', 'Contratos'],
        fila: r => `<td><strong>${escapeHtml(r.nombre)}</strong></td><td class="row-sub">${escapeHtml(r.zona || '—')}</td>
                    <td>${escapeHtml(r.dia_cobro || '—')}</td><td>${escapeHtml(r.cobrador_nombre || '—')}</td><td>${r.contratos ?? 0}</td>`,
        usado: r => Number(r.contratos) > 0,
        campos: r => `
            <div class="form-group"><label class="form-label">Nombre *</label>
                <input type="text" id="cf_nombre" class="form-control" value="${escapeHtml(r.nombre || '')}" required></div>
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">Zona / sector</label>
                    <input type="text" id="cf_zona" class="form-control" maxlength="150" value="${escapeHtml(r.zona || '')}"></div>
                <div class="form-group"><label class="form-label">Día de cobro</label>
                    <input type="text" id="cf_dia" class="form-control" maxlength="20" value="${escapeHtml(r.dia_cobro || '')}" placeholder="lunes, 1 y 15..."></div>
            </div>
            <div class="form-group"><label class="form-label">Cobrador</label>
                <select id="cf_cobrador" class="form-control">
                    <option value="">—</option>
                    ${Prevision.cat.cobradores.filter(c => Number(c.activo) || c.id == r.cobrador_id).map(c =>
                        `<option value="${c.id}" ${r.cobrador_id == c.id ? 'selected' : ''}>${escapeHtml(c.nombre)}</option>`).join('')}
                </select></div>`,
        leer: () => ({ nombre: $('#cf_nombre').value.trim(), zona: $('#cf_zona').value.trim(),
                       dia_cobro: $('#cf_dia').value.trim(), cobrador_id: $('#cf_cobrador').value || null }),
    },
};

async function pvLoadCatalogosTab() {
    const cont = $('#pvCatContent');
    try {
        const datos = {};
        for (const tipo of Object.keys(PV_CAT)) {
            datos[tipo] = (await API.req('prevision_catalogos.php?action=' + PV_CAT[tipo].accionLista)).items;
        }
        window.pvCatData = datos;
        cont.innerHTML = Object.entries(PV_CAT).map(([tipo, cfg]) => `
            <div class="admin-toolbar prev-h3">
                <h3>${cfg.titulo}</h3>
                <button class="btn btn-primary btn-sm" onclick="pvCatForm('${tipo}', 0)">+ Agregar</button>
            </div>
            <div class="table-responsive"><table class="admin-table admin-table-compact">
                <thead><tr>${cfg.columnas.map(c => `<th>${c}</th>`).join('')}<th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>${datos[tipo].map(item => `
                    <tr>
                        ${cfg.fila(item)}
                        <td>${Number(item.activo) ? '<span class="status-badge badge-green">activo</span>' : '<span class="status-badge badge-gray">inactivo</span>'}</td>
                        <td><div class="admin-actions">
                            <button class="btn btn-outline btn-sm" onclick="pvCatForm('${tipo}', ${item.id})">Editar</button>
                            <button class="btn btn-outline btn-sm" onclick="pvCatToggle('${tipo}', ${item.id}, ${Number(item.activo) ? 0 : 1})">${Number(item.activo) ? 'Desactivar' : 'Activar'}</button>
                            ${pvEsAdmin() && !cfg.usado(item) ? `<button class="btn btn-danger btn-sm" onclick="pvCatDelete('${tipo}', ${item.id})">Eliminar</button>` : ''}
                        </div></td>
                    </tr>`).join('') || `<tr><td colspan="${cfg.columnas.length + 2}" class="empty-row">Sin registros.</td></tr>`}
                </tbody></table></div>`).join('');
    } catch (e) {
        cont.innerHTML = `<p class="setting-help">Importe <strong>database/05_prevision_v2.sql</strong> para activar sucursales, servicios, siniestros y cobranza.</p>`;
    }
}

function pvCatForm(tipo, id) {
    const cfg = PV_CAT[tipo];
    const item = id ? (window.pvCatData[tipo] || []).find(x => Number(x.id) === Number(id)) || {} : {};
    openModal((id ? 'Editar — ' : 'Nuevo — ') + cfg.titulo, `
        <form id="pvCatFormEl">
            ${cfg.campos(item)}
            <p id="cf_error" class="login-error" hidden></p>
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Guardar</button>
                <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
            </div>
        </form>`);
    $('#pvCatFormEl').addEventListener('submit', async (e) => {
        e.preventDefault();
        const err = $('#cf_error'); err.hidden = true;
        try {
            const json = cfg.leer();
            if (id) json.id = id;
            await API.req('prevision_catalogos.php?action=' + cfg.accionSave, { method: 'POST', json });
            closeModal(); toast('Guardado.');
            pvLoadCatalogosTab(); Prevision.loadCatalogos(true);
        } catch (ex) { err.innerText = ex.message; err.hidden = false; }
    });
}

async function pvCatToggle(tipo, id, activo) {
    try {
        await API.req('prevision_catalogos.php?action=' + PV_CAT[tipo].accionToggle, { method: 'POST', json: { id, activo } });
        pvLoadCatalogosTab(); Prevision.loadCatalogos(true);
    } catch (e) { toast(e.message); }
}

async function pvCatDelete(tipo, id) {
    if (!confirmAction('¿Eliminar definitivamente este registro?')) return;
    try {
        await API.req('prevision_catalogos.php?action=' + PV_CAT[tipo].accionDelete, { method: 'POST', json: { id } });
        toast('Eliminado.'); pvLoadCatalogosTab(); Prevision.loadCatalogos(true);
    } catch (e) { toast(e.message); }
}

// ==========================================================================
//  REPORTES (aging CxC, producción, cobranza, cartera)
// ==========================================================================
function pvRepPeriodoHtml() {
    const inicioMes = pvHoy().slice(0, 8) + '01';
    return `
        <div class="admin-toolbar prev-inline">
            <div class="form-group"><label class="form-label">Desde</label>
                <input type="date" id="pvRepDesde" class="form-control prev-select" value="${inicioMes}"></div>
            <div class="form-group"><label class="form-label">Hasta</label>
                <input type="date" id="pvRepHasta" class="form-control prev-select" value="${pvHoy()}"></div>
            <button class="btn btn-primary" onclick="pvRepCargar()">Generar</button>
        </div>`;
}

function pvRepCsvLink(params) {
    return `<div class="setting-actions"><button class="btn btn-outline btn-sm"
        onclick="window.open('api/prevision_reportes.php?${params}&formato=csv', '_blank')">⬇ Descargar CSV</button></div>`;
}

async function pvLoadReportes() {
    const cont = $('#pvRepContent');
    const v = Prevision.repVista;
    cont.innerHTML = (v === 'produccion' || v === 'cobranza') ? pvRepPeriodoHtml() + '<div id="pvRepResult"></div>'
                                                              : '<div id="pvRepResult"></div>';
    pvRepCargar();
}

async function pvRepCargar() {
    const v = Prevision.repVista;
    const res = $('#pvRepResult');
    const desde = $('#pvRepDesde') ? $('#pvRepDesde').value : '';
    const hasta = $('#pvRepHasta') ? $('#pvRepHasta').value : '';
    const periodo = desde ? `&desde=${desde}&hasta=${hasta}` : '';
    try {
        if (v === 'aging') {
            const r = await API.req('prevision_reportes.php?action=aging');
            const tot = Object.entries(r.totales || {});
            res.innerHTML = `
                ${pvRepCsvLink('action=aging')}
                <div class="table-responsive"><table class="admin-table admin-table-compact">
                    <thead><tr><th>Contrato</th><th>Cliente</th><th>Plan</th><th>Al día</th><th>1-30</th><th>31-60</th><th>61-90</th><th>+90</th><th>Total</th></tr></thead>
                    <tbody>
                    ${r.items.map(i => `
                        <tr class="${i.d90_mas > 0 ? 'prev-row-vencida' : ''}">
                            <td><a href="#" onclick="pvVerContrato(${i.contrato_id});return false;">${escapeHtml(i.numero)}</a> ${pvBadge(i.estatus)}</td>
                            <td>${escapeHtml(i.cliente_nombre)}</td>
                            <td class="row-sub">${escapeHtml(i.plan_nombre || '—')}</td>
                            <td>${i.al_dia ? pvMoney(i.al_dia, i.moneda) : '—'}</td>
                            <td>${i.d1_30 ? pvMoney(i.d1_30, i.moneda) : '—'}</td>
                            <td>${i.d31_60 ? pvMoney(i.d31_60, i.moneda) : '—'}</td>
                            <td>${i.d61_90 ? pvMoney(i.d61_90, i.moneda) : '—'}</td>
                            <td>${i.d90_mas ? pvMoney(i.d90_mas, i.moneda) : '—'}</td>
                            <td><strong>${pvMoney(i.total, i.moneda)}</strong></td>
                        </tr>`).join('') || '<tr><td colspan="9" class="empty-row">No hay saldos por cobrar. 🎉</td></tr>'}
                    ${tot.map(([m, t]) => `
                        <tr>
                            <td colspan="3"><strong>TOTAL ${m}</strong></td>
                            <td><strong>${pvMoney(t.al_dia, m)}</strong></td>
                            <td><strong>${pvMoney(t.d1_30, m)}</strong></td>
                            <td><strong>${pvMoney(t.d31_60, m)}</strong></td>
                            <td><strong>${pvMoney(t.d61_90, m)}</strong></td>
                            <td><strong>${pvMoney(t.d90_mas, m)}</strong></td>
                            <td><strong>${pvMoney(t.total, m)}</strong></td>
                        </tr>`).join('')}
                    </tbody></table></div>`;
        } else if (v === 'produccion') {
            const r = await API.req('prevision_reportes.php?action=produccion' + periodo);
            res.innerHTML = `
                ${pvRepCsvLink('action=produccion' + periodo)}
                <div class="table-responsive"><table class="admin-table">
                    <thead><tr><th>Vendedor</th><th>Contratos</th><th>Activos</th><th>Iniciales USD</th><th>Iniciales Bs</th><th>Cuotas USD</th><th>Cuotas Bs</th></tr></thead>
                    <tbody>${r.items.map(i => `
                        <tr>
                            <td><strong>${escapeHtml(i.vendedor_nombre)}</strong></td>
                            <td>${i.contratos}</td><td>${i.activos}</td>
                            <td>${pvMoney(i.iniciales_usd, 'USD')}</td><td>${pvMoney(i.iniciales_bs, 'BS')}</td>
                            <td>${pvMoney(i.cuotas_usd, 'USD')}</td><td>${pvMoney(i.cuotas_bs, 'BS')}</td>
                        </tr>`).join('') || '<tr><td colspan="7" class="empty-row">Sin contratos en el período.</td></tr>'}
                    </tbody></table></div>`;
        } else if (v === 'cobranza') {
            const r = await API.req('prevision_reportes.php?action=cobranza' + periodo);
            res.innerHTML = `
                ${pvRepCsvLink('action=cobranza' + periodo)}
                <h3 class="prev-h4">Por forma de pago</h3>
                <div class="table-responsive"><table class="admin-table admin-table-compact">
                    <thead><tr><th>Forma de pago</th><th>Abonos</th><th>Total USD</th><th>Total Bs</th></tr></thead>
                    <tbody>${r.formas.map(f => `
                        <tr><td>${escapeHtml(PV_FORMAS_PAGO[f.forma_pago] || f.forma_pago)}</td>
                            <td>${f.operaciones}</td>
                            <td>${pvMoney(f.total_usd, 'USD')}</td><td>${pvMoney(f.total_bs, 'BS')}</td></tr>`).join('')
                        || '<tr><td colspan="4" class="empty-row">Sin pagos en el período.</td></tr>'}
                    </tbody></table></div>
                <h3 class="prev-h4">Por día</h3>
                <div class="table-responsive prev-scroll"><table class="admin-table admin-table-compact">
                    <thead><tr><th>Fecha</th><th>Total USD</th><th>Total Bs</th></tr></thead>
                    <tbody>${r.dias.map(d => `
                        <tr><td>${fmtDate(d.fecha)}</td>
                            <td>${pvMoney(d.total_usd, 'USD')}</td><td>${pvMoney(d.total_bs, 'BS')}</td></tr>`).join('')
                        || '<tr><td colspan="3" class="empty-row">Sin pagos en el período.</td></tr>'}
                    </tbody></table></div>`;
        } else if (v === 'cartera') {
            const r = await API.req('prevision_reportes.php?action=cartera');
            res.innerHTML = `
                ${pvRepCsvLink('action=cartera')}
                <div class="table-responsive"><table class="admin-table admin-table-compact">
                    <thead><tr><th>Plan</th><th>Total</th><th>Activos</th><th>Suspendidos</th><th>Anulados</th><th>Renuncias</th><th>Finalizados</th><th>Facturación (activos)</th></tr></thead>
                    <tbody>${r.items.map(i => `
                        <tr>
                            <td><strong>${escapeHtml(i.plan_nombre)}</strong></td>
                            <td>${i.total}</td><td>${i.activos}</td><td>${i.suspendidos}</td>
                            <td>${i.anulados}</td><td>${i.renuncias}</td><td>${i.finalizados}</td>
                            <td>${pvMoney(i.facturacion, i.moneda)}</td>
                        </tr>`).join('') || '<tr><td colspan="8" class="empty-row">Sin contratos registrados.</td></tr>'}
                    </tbody></table></div>`;
        }
    } catch (e) { res.innerHTML = ''; toast(e.message); }
}

// ==========================================================================
//  MENSAJES (WhatsApp / SMS con proveedor configurable)
// ==========================================================================
const PV_MSG_CANAL = { whatsapp: 'WhatsApp', sms: 'SMS' };
const PV_MSG_PROVEEDOR = {
    manual: 'Manual (solo registrar / abrir WhatsApp Web)',
    whatsapp_cloud: 'WhatsApp Cloud API (Meta)',
    twilio: 'Twilio',
    http: 'API HTTP genérica (gateway local)',
};
const PV_MSG_VARIABLES = '{{cliente}} {{contrato}} {{plan}} {{monto_cuota}} {{cuotas_vencidas}} {{saldo_vencido}} {{moneda}} {{empresa}} {{fecha}}';
PV_ESTATUS_BADGE.enviado = 'badge-green';
PV_ESTATUS_BADGE.fallido = 'badge-red';
PV_ESTATUS_BADGE.manual = 'badge-blue';
PV_ESTATUS_BADGE.aplicado = 'badge-green';
PV_ESTATUS_BADGE.revertido = 'badge-gray';

async function pvMsgPlantillas(force = false) {
    if (Prevision.msgPlantillas.length && !force) return Prevision.msgPlantillas;
    const r = await API.req('prevision_mensajes.php?action=plantillas');
    Prevision.msgPlantillas = r.items;
    return r.items;
}

function pvMsgPlantillaSelect(id, canal, valor) {
    const items = Prevision.msgPlantillas.filter(p => p.activo && p.canal === canal);
    return `<select id="${id}" class="form-control">
        <option value="">— Texto libre —</option>
        ${items.map(p => `<option value="${p.id}" ${String(valor) === String(p.id) ? 'selected' : ''}>${escapeHtml(p.nombre)}</option>`).join('')}
    </select>`;
}

async function pvLoadMensajes() {
    const cont = $('#pvMsgContent');
    const v = Prevision.msgVista;
    try {
        if (v === 'enviar') {
            await pvMsgPlantillas();
            cont.innerHTML = `
                <div class="admin-card settings-card">
                    <h3 class="prev-h4">Envío masivo a morosos</h3>
                    <p class="setting-help">Envía la plantilla seleccionada a todos los contratos con cuotas vencidas (máx. 300 por corrida). En modo <strong>manual</strong> los mensajes quedan registrados y se generan enlaces de WhatsApp para enviarlos uno a uno.</p>
                    <div class="form-grid-2">
                        <div class="form-group"><label class="form-label">Canal</label>
                            ${pvSelect('pm_canal', PV_MSG_CANAL, 'whatsapp')}</div>
                        <div class="form-group"><label class="form-label">Cuotas vencidas mínimas</label>
                            <input type="number" min="1" max="24" id="pm_min" class="form-control" value="1"></div>
                    </div>
                    <div class="form-group"><label class="form-label">Plantilla *</label>
                        <span id="pm_plantilla_wrap">${pvMsgPlantillaSelect('pm_plantilla', 'whatsapp', '')}</span></div>
                    <div class="setting-actions">
                        <button class="btn btn-primary" onclick="pvMsgEnviarMorosos()">Enviar a morosos</button>
                    </div>
                    <p class="setting-help">Para enviar a un cliente puntual use el botón <strong>Mensaje</strong> en Cobranza → Morosos, o desde el detalle del contrato.</p>
                </div>
                <div id="pvMsgResult"></div>`;
            $('#pm_canal').addEventListener('change', () => {
                $('#pm_plantilla_wrap').innerHTML = pvMsgPlantillaSelect('pm_plantilla', $('#pm_canal').value, '');
            });
        } else if (v === 'plantillas') {
            const items = await pvMsgPlantillas(true);
            cont.innerHTML = `
                <div class="admin-toolbar prev-h3">
                    <h3>Plantillas de mensajes</h3>
                    <button class="btn btn-primary btn-sm" onclick="pvMsgPlantillaForm(0)">+ Nueva plantilla</button>
                </div>
                <p class="setting-help">Variables disponibles: <code>${PV_MSG_VARIABLES}</code></p>
                <div class="table-responsive"><table class="admin-table admin-table-compact">
                    <thead><tr><th>Nombre</th><th>Canal</th><th>Mensaje</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>${items.map(p => `
                        <tr>
                            <td><strong>${escapeHtml(p.nombre)}</strong></td>
                            <td>${PV_MSG_CANAL[p.canal] || p.canal}</td>
                            <td class="row-sub">${escapeHtml(p.cuerpo.length > 120 ? p.cuerpo.slice(0, 120) + '…' : p.cuerpo)}</td>
                            <td>${p.activo ? '<span class="status-badge badge-green">activa</span>' : '<span class="status-badge badge-gray">inactiva</span>'}</td>
                            <td><div class="admin-actions">
                                <button class="btn btn-outline btn-sm" onclick="pvMsgPlantillaForm(${p.id})">Editar</button>
                                <button class="btn btn-outline btn-sm" onclick="pvMsgPlantillaToggle(${p.id}, ${p.activo ? 0 : 1})">${p.activo ? 'Desactivar' : 'Activar'}</button>
                                ${pvEsAdmin() ? `<button class="btn btn-danger btn-sm" onclick="pvMsgPlantillaDelete(${p.id})">Eliminar</button>` : ''}
                            </div></td>
                        </tr>`).join('') || '<tr><td colspan="5" class="empty-row">Sin plantillas.</td></tr>'}
                    </tbody></table></div>`;
        } else if (v === 'historial') {
            const st = Prevision.env;
            const params = new URLSearchParams({ action: 'envios', limit: st.limit, offset: st.offset });
            const r = await API.req('prevision_mensajes.php?' + params);
            st.total = r.total;
            cont.innerHTML = `
                <div class="table-responsive"><table class="admin-table admin-table-compact">
                    <thead><tr><th>Fecha</th><th>Contrato</th><th>Cliente</th><th>Canal</th><th>Teléfono</th><th>Mensaje</th><th>Estado</th></tr></thead>
                    <tbody>${r.items.map(m => `
                        <tr>
                            <td>${fmtDate(m.created_at)}</td>
                            <td>${m.contrato_id ? `<a href="#" onclick="pvVerContrato(${m.contrato_id});return false;">${escapeHtml(m.contrato_numero || '')}</a>` : '—'}</td>
                            <td>${escapeHtml(m.cliente_nombre || '—')}</td>
                            <td>${PV_MSG_CANAL[m.canal] || m.canal}</td>
                            <td>${escapeHtml(m.destinatario)}</td>
                            <td class="row-sub">${escapeHtml(m.cuerpo.length > 90 ? m.cuerpo.slice(0, 90) + '…' : m.cuerpo)}</td>
                            <td>${pvBadge(m.estado)}${m.error ? `<div class="row-sub">${escapeHtml(m.error)}</div>` : ''}</td>
                        </tr>`).join('') || '<tr><td colspan="7" class="empty-row">Sin mensajes registrados.</td></tr>'}
                    </tbody></table></div>
                <div class="prev-pager" id="pvEnvPager"></div>`;
            pvPager($('#pvEnvPager'), st, pvLoadMensajes);
        } else if (v === 'config') {
            if (!pvEsAdmin()) { cont.innerHTML = '<p class="setting-help">Solo el administrador puede ver la configuración de mensajería.</p>'; return; }
            const r = await API.req('prevision_mensajes.php?action=config');
            const c = r.config;
            const sec = (k, label, ph) => `
                <div class="form-group"><label class="form-label">${label}</label>
                    <input type="password" id="mc_${k}" class="form-control" autocomplete="new-password"
                           data-set="${c['prev_msg_' + k] === '__set__' ? '1' : '0'}"
                           placeholder="${c['prev_msg_' + k] === '__set__' ? '•••••• (ya configurado — dejar vacío para mantener)' : (ph || '')}"></div>`;
            const txt = (k, label, ph) => `
                <div class="form-group"><label class="form-label">${label}</label>
                    <input type="text" id="mc_${k}" class="form-control" value="${escapeHtml(c['prev_msg_' + k] || '')}" placeholder="${ph || ''}"></div>`;
            cont.innerHTML = `
                <form id="pvMsgConfigForm" class="admin-card settings-card">
                    <h3 class="prev-h4">Proveedor por canal</h3>
                    <p class="setting-help">Mientras no tenga proveedor contratado deje <strong>Manual</strong>: los mensajes quedan registrados y WhatsApp se abre listo para enviar. Cuando contrate el servicio, seleccione el proveedor y cargue sus credenciales aquí — sin tocar código.</p>
                    <div class="form-grid-2">
                        <div class="form-group"><label class="form-label">WhatsApp</label>
                            ${pvSelect('mc_proveedor_whatsapp', PV_MSG_PROVEEDOR, c.prev_msg_proveedor_whatsapp || 'manual')}</div>
                        <div class="form-group"><label class="form-label">SMS</label>
                            ${pvSelect('mc_proveedor_sms', { manual: PV_MSG_PROVEEDOR.manual, twilio: PV_MSG_PROVEEDOR.twilio, http: PV_MSG_PROVEEDOR.http }, c.prev_msg_proveedor_sms || 'manual')}</div>
                    </div>
                    <div class="form-grid-2">
                        ${txt('empresa', 'Nombre de la empresa ({{empresa}})', 'Funeraria del Zulia')}
                        ${txt('pais', 'Código de país para teléfonos', '58')}
                    </div>
                    <h3 class="prev-h4">WhatsApp Cloud API (Meta)</h3>
                    <div class="form-grid-2">
                        ${sec('wa_token', 'Token de acceso permanente')}
                        ${txt('wa_phone_id', 'Phone Number ID', '1234567890')}
                    </div>
                    <h3 class="prev-h4">Twilio</h3>
                    <div class="form-grid-2">
                        ${txt('twilio_sid', 'Account SID', 'ACxxxxxxxx')}
                        ${sec('twilio_token', 'Auth Token')}
                        ${txt('twilio_from_sms', 'Número origen SMS', '+1786...')}
                        ${txt('twilio_from_wa', 'Número origen WhatsApp', '+1415...')}
                    </div>
                    <h3 class="prev-h4">API HTTP genérica (gateway local de SMS u otro)</h3>
                    <p class="setting-help">POST envía JSON <code>{to, message, channel}</code>. En la URL puede usar <code>{to}</code> y <code>{message}</code> (útil con método GET). El token va como <code>Authorization: Bearer</code>.</p>
                    <div class="form-grid-2">
                        ${txt('http_url', 'URL del servicio', 'https://api.miproveedor.com/send')}
                        <div class="form-group"><label class="form-label">Método</label>
                            ${pvSelect('mc_http_metodo', { POST: 'POST (JSON)', GET: 'GET (marcadores en URL)' }, c.prev_msg_http_metodo || 'POST')}</div>
                        ${sec('http_token', 'Token / API key')}
                    </div>
                    <div class="setting-actions">
                        <button type="submit" class="btn btn-primary">Guardar configuración</button>
                        <button type="button" class="btn btn-outline" onclick="pvMsgTest()">Enviar mensaje de prueba</button>
                    </div>
                </form>`;
            $('#pvMsgConfigForm').addEventListener('submit', pvMsgConfigGuardar);
        }
    } catch (e) {
        cont.innerHTML = `<p class="setting-help">Importe <strong>database/06_prevision_v3.sql</strong> para activar mensajes, ajustes de tarifas y reportes.</p>`;
    }
}

async function pvMsgConfigGuardar(e) {
    e.preventDefault();
    const val = k => $('#mc_' + k) ? $('#mc_' + k).value.trim() : '';
    const secreto = k => {
        const el = $('#mc_' + k);
        return el.value !== '' ? el.value : (el.dataset.set === '1' ? '__set__' : '');
    };
    try {
        await API.req('prevision_mensajes.php?action=config_set', { method: 'POST', json: {
            prev_msg_proveedor_whatsapp: val('proveedor_whatsapp'),
            prev_msg_proveedor_sms: val('proveedor_sms'),
            prev_msg_empresa: val('empresa'),
            prev_msg_pais: val('pais'),
            prev_msg_wa_token: secreto('wa_token'),
            prev_msg_wa_phone_id: val('wa_phone_id'),
            prev_msg_twilio_sid: val('twilio_sid'),
            prev_msg_twilio_token: secreto('twilio_token'),
            prev_msg_twilio_from_sms: val('twilio_from_sms'),
            prev_msg_twilio_from_wa: val('twilio_from_wa'),
            prev_msg_http_url: val('http_url'),
            prev_msg_http_metodo: val('http_metodo'),
            prev_msg_http_token: secreto('http_token'),
        }});
        toast('Configuración de mensajería guardada.');
        pvLoadMensajes();
    } catch (ex) { toast(ex.message); }
}

function pvMsgTest() {
    openModal('Mensaje de prueba', `
        <form id="pvMsgTestForm">
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">Canal</label>
                    ${pvSelect('mt_canal', PV_MSG_CANAL, 'whatsapp')}</div>
                <div class="form-group"><label class="form-label">Teléfono</label>
                    <input type="text" id="mt_tel" class="form-control" placeholder="0412-1234567" required></div>
            </div>
            <p class="setting-help">Guarde la configuración antes de probar. El resultado queda en el Historial.</p>
            <p id="mt_error" class="login-error" hidden></p>
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Enviar prueba</button>
                <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
            </div>
        </form>`);
    $('#pvMsgTestForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const err = $('#mt_error'); err.hidden = true;
        try {
            const r = await API.req('prevision_mensajes.php?action=test', { method: 'POST', json: {
                canal: $('#mt_canal').value, telefono: $('#mt_tel').value.trim(),
            }});
            if (r.wa_link) window.open(r.wa_link, '_blank');
            closeModal();
            toast(r.estado === 'enviado' ? 'Mensaje de prueba enviado por ' + r.proveedor + '.'
                : r.estado === 'manual' ? 'Registrado en modo manual' + (r.wa_link ? ' (se abrió WhatsApp).' : '.')
                : 'Falló el envío: ' + (r.error || ''));
        } catch (ex) { err.innerText = ex.message; err.hidden = false; }
    });
}

function pvMsgPlantillaForm(id) {
    const p = id ? Prevision.msgPlantillas.find(x => Number(x.id) === Number(id)) || {} : {};
    openModal(id ? 'Editar plantilla' : 'Nueva plantilla', `
        <form id="pvMsgPlaForm">
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">Nombre *</label>
                    <input type="text" id="mp_nombre" class="form-control" maxlength="120" value="${escapeHtml(p.nombre || '')}" required></div>
                <div class="form-group"><label class="form-label">Canal</label>
                    ${pvSelect('mp_canal', PV_MSG_CANAL, p.canal || 'whatsapp')}</div>
            </div>
            <div class="form-group"><label class="form-label">Mensaje *</label>
                <textarea id="mp_cuerpo" class="form-control" rows="5" maxlength="2000" required>${escapeHtml(p.cuerpo || '')}</textarea></div>
            <p class="setting-help">Variables: <code>${PV_MSG_VARIABLES}</code></p>
            <p id="mp_error" class="login-error" hidden></p>
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Guardar</button>
                <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
            </div>
        </form>`);
    $('#pvMsgPlaForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const err = $('#mp_error'); err.hidden = true;
        try {
            await API.req('prevision_mensajes.php?action=plantilla_save', { method: 'POST', json: {
                id: id || 0, nombre: $('#mp_nombre').value.trim(),
                canal: $('#mp_canal').value, cuerpo: $('#mp_cuerpo').value.trim(),
            }});
            closeModal(); toast('Plantilla guardada.');
            await pvMsgPlantillas(true);
            pvLoadMensajes();
        } catch (ex) { err.innerText = ex.message; err.hidden = false; }
    });
}

async function pvMsgPlantillaToggle(id, activo) {
    try {
        await API.req('prevision_mensajes.php?action=plantilla_toggle', { method: 'POST', json: { id, activo } });
        await pvMsgPlantillas(true);
        pvLoadMensajes();
    } catch (e) { toast(e.message); }
}

async function pvMsgPlantillaDelete(id) {
    if (!confirmAction('¿Eliminar esta plantilla?')) return;
    try {
        await API.req('prevision_mensajes.php?action=plantilla_delete', { method: 'POST', json: { id } });
        toast('Plantilla eliminada.');
        await pvMsgPlantillas(true);
        pvLoadMensajes();
    } catch (e) { toast(e.message); }
}

async function pvMsgEnviarForm(contratoId) {
    try { await pvMsgPlantillas(); } catch (e) { toast('Importe database/06_prevision_v3.sql para activar los mensajes.'); return; }
    openModal('Enviar mensaje al cliente', `
        <form id="pvMsgEnvForm">
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">Canal</label>
                    ${pvSelect('me_canal', PV_MSG_CANAL, 'whatsapp')}</div>
                <div class="form-group"><label class="form-label">Teléfono (vacío = el del cliente)</label>
                    <input type="text" id="me_tel" class="form-control" placeholder="0412-1234567"></div>
            </div>
            <div class="form-group"><label class="form-label">Plantilla</label>
                <span id="me_plantilla_wrap">${pvMsgPlantillaSelect('me_plantilla', 'whatsapp', '')}</span></div>
            <div class="form-group"><label class="form-label">Texto libre (si no usa plantilla)</label>
                <textarea id="me_cuerpo" class="form-control" rows="4" maxlength="2000" placeholder="Puede usar ${PV_MSG_VARIABLES}"></textarea></div>
            <p id="me_error" class="login-error" hidden></p>
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Enviar</button>
                <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
            </div>
        </form>`);
    $('#me_canal').addEventListener('change', () => {
        $('#me_plantilla_wrap').innerHTML = pvMsgPlantillaSelect('me_plantilla', $('#me_canal').value, '');
    });
    $('#pvMsgEnvForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const err = $('#me_error'); err.hidden = true;
        try {
            const r = await API.req('prevision_mensajes.php?action=enviar', { method: 'POST', json: {
                contrato_id: contratoId, canal: $('#me_canal').value,
                plantilla_id: $('#me_plantilla').value || 0,
                cuerpo: $('#me_cuerpo').value.trim(), telefono: $('#me_tel').value.trim(),
            }});
            if (r.wa_link) window.open(r.wa_link, '_blank');
            closeModal();
            toast(r.estado === 'enviado' ? 'Mensaje enviado.'
                : r.estado === 'manual' ? 'Mensaje registrado' + (r.wa_link ? ' (se abrió WhatsApp).' : '.')
                : 'Falló el envío: ' + (r.error || ''));
        } catch (ex) { err.innerText = ex.message; err.hidden = false; }
    });
}

async function pvMsgEnviarMorosos() {
    const plantilla = $('#pm_plantilla').value;
    if (!plantilla) { toast('Seleccione la plantilla a enviar.'); return; }
    if (!confirmAction('¿Enviar la plantilla seleccionada a todos los contratos morosos?')) return;
    const res = $('#pvMsgResult');
    res.innerHTML = '<p class="setting-help">Enviando…</p>';
    try {
        const r = await API.req('prevision_mensajes.php?action=enviar_morosos', { method: 'POST', json: {
            canal: $('#pm_canal').value, plantilla_id: plantilla, min_cuotas: $('#pm_min').value,
        }});
        res.innerHTML = `
            <div class="prev-import-result">
                <p><strong>Resultado:</strong> ${r.enviados} enviados · ${r.manuales} en modo manual · ${r.fallidos} fallidos · ${r.sin_telefono} sin teléfono.</p>
            </div>
            <div class="table-responsive"><table class="admin-table admin-table-compact">
                <thead><tr><th>Contrato</th><th>Cliente</th><th>Estado</th><th></th></tr></thead>
                <tbody>${r.items.map(i => `
                    <tr>
                        <td>${escapeHtml(i.contrato)}</td>
                        <td>${escapeHtml(i.cliente)}</td>
                        <td>${pvBadge(i.estado)}${i.error ? `<div class="row-sub">${escapeHtml(i.error)}</div>` : ''}</td>
                        <td>${i.wa_link ? `<a class="btn btn-outline btn-sm" href="${escapeHtml(i.wa_link)}" target="_blank" rel="noopener">Abrir WhatsApp</a>` : ''}</td>
                    </tr>`).join('') || '<tr><td colspan="4" class="empty-row">No hay contratos morosos con ese criterio.</td></tr>'}
                </tbody></table></div>`;
    } catch (e) { res.innerHTML = ''; toast(e.message); }
}

// ==========================================================================
//  AJUSTES MASIVOS DE TARIFAS
// ==========================================================================
async function pvLoadAjustes() {
    const cont = $('#pvAjuContent');
    const planes = Prevision.planes.filter(p => p.activo);
    cont.innerHTML = `
        <div class="admin-card settings-card">
            <div class="form-group"><label class="form-label">Descripción del ajuste *</label>
                <input type="text" id="aj_desc" class="form-control" maxlength="255" placeholder="Aumento tarifario ${new Date().getFullYear()}"></div>
            <div class="form-grid-2">
                <div class="form-group"><label class="form-label">Tipo</label>
                    ${pvSelect('aj_tipo', { porcentaje: 'Porcentaje (%)', monto: 'Monto fijo (+/-)' }, 'porcentaje')}</div>
                <div class="form-group"><label class="form-label">Valor (negativo = rebaja)</label>
                    <input type="number" step="0.01" id="aj_valor" class="form-control" placeholder="10 = +10%"></div>
                <div class="form-group"><label class="form-label">Plan</label>
                    <select id="aj_plan" class="form-control"><option value="">Todos los planes</option>
                        ${planes.map(p => `<option value="${p.id}">${escapeHtml(p.nombre)}</option>`).join('')}</select></div>
                <div class="form-group"><label class="form-label">Moneda</label>
                    ${pvSelect('aj_moneda', { USD: 'Solo USD ($)', BS: 'Solo Bolívares (Bs)' }, '', 'Ambas monedas')}</div>
                <div class="form-group"><label class="form-label">Redondeo</label>
                    ${pvSelect('aj_redondeo', { centimos: 'A céntimos (0,01)', entero: 'Al entero' }, 'centimos')}</div>
            </div>
            <div class="setting-row"><div><strong>Actualizar también la cuota de los planes</strong>
                <p class="setting-help">Cambia la cuota mensual del catálogo de planes.</p></div>
                <label class="switch"><input type="checkbox" id="aj_planes_chk"><span class="slider"></span></label></div>
            <div class="setting-row"><div><strong>Actualizar cuotas pendientes ya generadas</strong>
                <p class="setting-help">Solo cuotas programadas futuras sin abonos.</p></div>
                <label class="switch"><input type="checkbox" id="aj_cuotas_chk"><span class="slider"></span></label></div>
            <div class="setting-actions">
                <button class="btn btn-outline" onclick="pvAjustePreview()">Vista previa</button>
                ${pvEsAdmin() ? '<button class="btn btn-danger" onclick="pvAjusteAplicar()">Aplicar ajuste</button>' : ''}
            </div>
        </div>
        <div id="pvAjuPreview"></div>
        <div id="pvAjuHistorial"></div>`;
    pvAjusteHistorial();
}

function pvAjusteJson() {
    return {
        descripcion: $('#aj_desc').value.trim(),
        tipo: $('#aj_tipo').value,
        valor: $('#aj_valor').value,
        redondeo: $('#aj_redondeo').value,
        plan_id: $('#aj_plan').value || 0,
        moneda: $('#aj_moneda').value,
        aplicar_planes: $('#aj_planes_chk').checked ? 1 : 0,
        aplicar_cuotas: $('#aj_cuotas_chk').checked ? 1 : 0,
    };
}

async function pvAjustePreview() {
    const box = $('#pvAjuPreview');
    try {
        const r = await API.req('prevision_ajustes.php?action=preview', { method: 'POST', json: pvAjusteJson() });
        const filas = r.contratos.slice(0, 100);
        box.innerHTML = `
            <div class="prev-import-result">
                <p><strong>Vista previa:</strong> ${r.contratos.length} contrato(s) afectado(s)
                ${r.planes.length ? ` · ${r.planes.length} plan(es)` : ''}
                ${r.cuotas_afectadas ? ` · ${r.cuotas_afectadas} cuota(s) pendiente(s)` : ''}.
                Nada se ha guardado todavía.</p>
            </div>
            ${r.planes.length ? `
            <h3 class="prev-h4">Planes</h3>
            <div class="table-responsive"><table class="admin-table admin-table-compact">
                <thead><tr><th>Plan</th><th>Cuota actual</th><th>Cuota nueva</th></tr></thead>
                <tbody>${r.planes.map(p => `
                    <tr><td>${escapeHtml(p.nombre)}</td>
                        <td>${pvMoney(p.valor_anterior, p.moneda)}</td>
                        <td><strong>${pvMoney(p.valor_nuevo, p.moneda)}</strong></td></tr>`).join('')}
                </tbody></table></div>` : ''}
            <h3 class="prev-h4">Contratos ${r.contratos.length > 100 ? '(primeros 100)' : ''}</h3>
            <div class="table-responsive prev-scroll"><table class="admin-table admin-table-compact">
                <thead><tr><th>Contrato</th><th>Cliente</th><th>Plan</th><th>Cuota actual</th><th>Cuota nueva</th></tr></thead>
                <tbody>${filas.map(c => `
                    <tr><td><strong>${escapeHtml(c.numero)}</strong></td>
                        <td>${escapeHtml(c.cliente_nombre)}</td>
                        <td class="row-sub">${escapeHtml(c.plan_nombre || '—')}</td>
                        <td>${pvMoney(c.valor_anterior, c.moneda)}</td>
                        <td><strong>${pvMoney(c.valor_nuevo, c.moneda)}</strong></td></tr>`).join('')
                    || '<tr><td colspan="5" class="empty-row">Ningún contrato coincide con los filtros.</td></tr>'}
                </tbody></table></div>`;
    } catch (e) { box.innerHTML = ''; toast(e.message); }
}

async function pvAjusteAplicar() {
    const json = pvAjusteJson();
    if (!json.descripcion) { toast('Indique la descripción del ajuste.'); return; }
    if (!confirmAction('¿Aplicar el ajuste a todos los contratos de la vista previa? Podrá revertirlo desde el historial.')) return;
    try {
        const r = await API.req('prevision_ajustes.php?action=aplicar', { method: 'POST', json });
        toast(`Ajuste aplicado a ${r.afectados} contrato(s).`);
        $('#pvAjuPreview').innerHTML = '';
        pvAjusteHistorial();
        Prevision.loadCatalogos(true);
    } catch (e) { toast(e.message); }
}

async function pvAjusteHistorial() {
    const box = $('#pvAjuHistorial');
    try {
        const r = await API.req('prevision_ajustes.php?action=list');
        box.innerHTML = `
            <h3 class="prev-h4">Historial de ajustes</h3>
            <div class="table-responsive"><table class="admin-table admin-table-compact">
                <thead><tr><th>Fecha</th><th>Descripción</th><th>Ajuste</th><th>Alcance</th><th>Contratos</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>${r.items.map(a => `
                    <tr>
                        <td>${fmtDate(a.created_at)}</td>
                        <td><strong>${escapeHtml(a.descripcion)}</strong><div class="row-sub">${escapeHtml(a.usuario || '')}</div></td>
                        <td>${a.tipo === 'porcentaje' ? (a.valor > 0 ? '+' : '') + pvNum(a.valor) + ' %' : (a.valor > 0 ? '+' : '') + pvNum(a.valor)}</td>
                        <td class="row-sub">${escapeHtml(a.plan_nombre || 'Todos los planes')}${a.moneda ? ' · ' + a.moneda : ''}${a.aplicar_planes ? ' · planes' : ''}${a.aplicar_cuotas ? ' · cuotas' : ''}</td>
                        <td>${a.afectados}</td>
                        <td>${pvBadge(a.estado)}${a.revertido_en ? `<div class="row-sub">${fmtDate(a.revertido_en)}</div>` : ''}</td>
                        <td><div class="admin-actions">
                            <button class="btn btn-outline btn-sm" onclick="pvAjusteVer(${a.id})">Detalle</button>
                            ${pvEsAdmin() && a.estado === 'aplicado' ? `<button class="btn btn-danger btn-sm" onclick="pvAjusteRevertir(${a.id})">Revertir</button>` : ''}
                        </div></td>
                    </tr>`).join('') || '<tr><td colspan="7" class="empty-row">Sin ajustes aplicados.</td></tr>'}
                </tbody></table></div>`;
    } catch (e) {
        box.innerHTML = `<p class="setting-help">Importe <strong>database/06_prevision_v3.sql</strong> para activar los ajustes de tarifas.</p>`;
    }
}

async function pvAjusteVer(id) {
    try {
        const r = await API.req('prevision_ajustes.php?action=get&id=' + id);
        const a = r.item;
        openModal('Detalle del ajuste', `
            <div class="prev-kv">
                <div><span>Descripción</span><strong>${escapeHtml(a.descripcion)}</strong></div>
                <div><span>Ajuste</span><strong>${a.tipo === 'porcentaje' ? pvNum(a.valor) + ' %' : pvNum(a.valor)}</strong></div>
                <div><span>Estado</span>${pvBadge(a.estado)}</div>
                <div><span>Registros</span><strong>${r.detalles.length}</strong></div>
            </div>
            <div class="table-responsive prev-scroll"><table class="admin-table admin-table-compact">
                <thead><tr><th>Tipo</th><th>Referencia</th><th>Anterior</th><th>Nuevo</th></tr></thead>
                <tbody>${r.detalles.map(d => `
                    <tr><td>${escapeHtml(d.objeto)}</td><td>${escapeHtml(d.referencia || String(d.objeto_id))}</td>
                        <td>${pvNum(d.valor_anterior)}</td><td><strong>${pvNum(d.valor_nuevo)}</strong></td></tr>`).join('')}
                </tbody></table></div>
            <div class="modal-actions"><button class="btn btn-outline" onclick="closeModal()">Cerrar</button></div>`);
    } catch (e) { toast(e.message); }
}

async function pvAjusteRevertir(id) {
    if (!confirmAction('¿Revertir este ajuste? Se restaurarán las cuotas anteriores de contratos, planes y cuotas pendientes sin abonos.')) return;
    try {
        const r = await API.req('prevision_ajustes.php?action=revertir', { method: 'POST', json: { id } });
        toast(`Ajuste revertido (${r.revertidos} registros restaurados).`);
        pvAjusteHistorial();
        Prevision.loadCatalogos(true);
    } catch (e) { toast(e.message); }
}

// Exponer el módulo para el hook de pestañas de admin.js
window.Prevision = Prevision;
