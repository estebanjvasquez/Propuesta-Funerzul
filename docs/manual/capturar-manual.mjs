/**
 * Regenera las capturas del Manual de Usuario del panel (manual/img/*.png),
 * recorriendo el panel real con Playwright. Úselo cada vez que cambie el panel
 * para que el manual no quede desactualizado.
 *
 * Requisitos (fuera del repo -- este proyecto no tiene package.json a propósito):
 *   - Node 18+ y el módulo "playwright" instalado en otra carpeta
 *     (npm i playwright), con un Chromium descargado (npx playwright install chromium).
 *   - Un usuario ADMIN del panel. Recomendado: uno temporal, que se borra al terminar.
 *
 * Uso (PowerShell):
 *   $env:MANUAL_USER = 'correo'; $env:MANUAL_PASS = 'clave'
 *   $env:MANUAL_BASE = 'https://legadoholding.com/funerzul/'   # opcional
 *   $env:NODE_PATH = 'C:\ruta\a\node_modules'                  # donde está playwright
 *   $env:CHROMIUM_EXE = 'C:\...\chrome.exe'                    # opcional
 *   node docs/manual/capturar-manual.mjs
 *
 * Qué hace en el sitio: crea UN obituario de ejemplo "María Ejemplo (Manual)"
 * en estado Borrador (no se publica) para mostrar el proceso completo y al
 * final le da de baja. Los demás formularios se llenan y se cancelan, sin
 * guardar. Oculta los correos reales en las capturas (repo público).
 * Credenciales: solo por variables de entorno, nunca en archivos.
 */
import { createRequire } from 'node:module';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(process.env.NODE_PATH ? path.join(process.env.NODE_PATH, 'x.js') : import.meta.url);
const { chromium } = require('playwright');

const BASE = (process.env.MANUAL_BASE || 'https://legadoholding.com/funerzul/').replace(/\/?$/, '/');
const USER = process.env.MANUAL_USER;
const PASS = process.env.MANUAL_PASS;
if (!USER || !PASS) { console.error('Faltan MANUAL_USER / MANUAL_PASS.'); process.exit(1); }

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const OUT = path.join(ROOT, 'manual', 'img');
fs.mkdirSync(OUT, { recursive: true });

const DEMO_NAME = 'María Ejemplo (Manual)';
const W = 1366, H = 900;

const browser = await chromium.launch(process.env.CHROMIUM_EXE ? { executablePath: process.env.CHROMIUM_EXE } : {});
const ctx = await browser.newContext({ viewport: { width: W, height: H }, deviceScaleFactor: 1, locale: 'es-VE' });
const page = await ctx.newPage();
page.on('pageerror', e => console.warn('  [error JS en la página]', e.message));
page.on('dialog', d => d.accept());   // confirmaciones nativas (p. ej. "Baja")

// ---------- utilidades ----------
const STYLE = `
  .pw-hl { outline: 3px solid #e11d48 !important; outline-offset: 3px !important;
           box-shadow: 0 0 0 7px rgba(225,29,72,.22) !important; border-radius: 6px; }
  .toast, #toast { display: none !important; }`;
async function addStyle() { await page.addStyleTag({ content: STYLE }); }

/** Oculta correos reales (texto y campos) salvo los de ejemplo. */
async function maskPII() {
    await page.evaluate(() => {
        const re = /[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/g;
        const keep = m => /@ejemplo\.com$/i.test(m);
        const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        let n;
        while ((n = w.nextNode())) {
            if (n.nodeValue.includes('@')) n.nodeValue = n.nodeValue.replace(re, m => keep(m) ? m : 'usuario@ejemplo.com');
        }
        document.querySelectorAll('input').forEach(i => {
            if (i.value.includes('@') && !keep(i.value)) i.value = 'usuario@ejemplo.com';
        });
    });
}

/**
 * Captura. opts.el = selector/locator a recortar (con margen); sin él, la ventana.
 * opts.hl = selectores/locators a resaltar con un recuadro rojo.
 */
async function shot(name, opts = {}) {
    const hl = [];
    for (const h of [].concat(opts.hl || [])) {
        const loc = typeof h === 'string' ? page.locator(h).first() : h;
        if (await loc.count() && await loc.isVisible()) { await loc.evaluate(e => e.classList.add('pw-hl')); hl.push(loc); }
    }
    await maskPII();
    await page.waitForTimeout(250);
    const file = path.join(OUT, name + '.png');
    if (opts.el) {
        const loc = typeof opts.el === 'string' ? page.locator(opts.el).first() : opts.el;
        await loc.scrollIntoViewIfNeeded();
        const b = await loc.boundingBox();
        const pad = opts.pad ?? 16;
        const vp = page.viewportSize();
        const clip = {
            x: Math.max(0, b.x - pad), y: Math.max(0, b.y - pad),
            width: Math.min(vp.width - Math.max(0, b.x - pad), b.width + pad * 2),
            height: Math.min(vp.height - Math.max(0, b.y - pad), b.height + pad * 2),
        };
        await page.screenshot({ path: file, clip });
    } else {
        await page.screenshot({ path: file, clip: opts.clip });
    }
    for (const loc of hl) await loc.evaluate(e => e.classList.remove('pw-hl')).catch(() => {});
    console.log('  ✓', name);
}

async function tall(h = 1500) { await page.setViewportSize({ width: W, height: h }); }
async function normal() { await page.setViewportSize({ width: W, height: H }); }

async function openTab(tab) {
    const btn = page.locator(`.admin-tab-btn[data-tab="${tab}"]`).first();
    const menu = await btn.evaluate(b => b.closest('.admin-menu')?.querySelector('.admin-menu-btn')?.dataset.menu ?? null);
    if (menu) await page.click(`.admin-menu-btn[data-menu="${menu}"]`);
    await btn.click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(500);
}

async function openModalWith(clickTarget, waitSel) {
    await clickTarget.click();
    await page.waitForSelector('#modal:not([hidden])');
    if (waitSel) await page.waitForSelector(waitSel);
    await page.waitForTimeout(300);
}
async function closeModal() {
    await page.evaluate(() => window.closeModal && window.closeModal());
    await page.waitForTimeout(200);
}
const modal = '#modal .admin-modal';

// ---------- 1. Inicio de sesión ----------
console.log('1. Inicio de sesión');
await page.goto(BASE + 'admin.html', { waitUntil: 'networkidle' });
await addStyle();
await page.waitForSelector('#loginView:not([hidden])');
if (await page.isVisible('#ssoBtn')) {
    // Con "Entrar con Previsión Funeraria" activo: se fotografía esa pantalla y se
    // entra con una cuenta local de emergencia (MANUAL_USER debe ser una de ellas).
    await shot('01-login', { el: '#loginForm', pad: 40, hl: '#ssoBtn' });
    await page.goto(BASE + 'admin.html?emergencia=1', { waitUntil: 'networkidle' });
    await addStyle();
    await page.waitForSelector('#loginEmail', { state: 'visible' });
} else {
    await page.fill('#loginEmail', 'su.correo@ejemplo.com');
    await page.fill('#loginPassword', 'contraseña');
    await shot('01-login', { el: '#loginForm', pad: 40, hl: '#loginBtn' });
}
await page.fill('#loginEmail', USER);
await page.fill('#loginPassword', PASS);
await page.click('#loginBtn');
await page.waitForSelector('#loginView', { state: 'hidden', timeout: 20000 });
await page.waitForSelector('#obitTableBody tr');
await page.waitForLoadState('networkidle');

await shot('02-panel-inicio', { hl: ['.admin-menu-btn[data-menu="sitio"]', '.admin-menu-btn[data-menu="sistema"]', '.admin-tab-btn[data-tab="manual"]', '#logoutBtn'] });
await page.click('.admin-menu-btn[data-menu="sitio"]');
await shot('03-menu-sitio', { clip: { x: 0, y: 0, width: W, height: 360 }, hl: '#menu-sitio' });
await page.click('.admin-menu-btn[data-menu="sistema"]');
await shot('04-menu-sistema', { clip: { x: 0, y: 0, width: W, height: 220 }, hl: '#menu-sistema' });
await page.mouse.click(5, 600);

// ---------- 2. Obituarios ----------
console.log('2. Obituarios');
await openTab('obituarios');
await page.waitForSelector('#obitTableBody tr');
await shot('10-obituarios-lista', { hl: '#newObitBtn' });

await tall(1600);
await openModalWith(page.locator('#newObitBtn'), '#of_full_name');
await page.fill('#of_full_name', DEMO_NAME);
await page.fill('#of_birth_year', '1950');
await page.fill('#of_death_date', new Date().toISOString().slice(0, 10));
await page.selectOption('#of_service_type', { label: 'Acto Velatorio' }).catch(() => {});
await page.selectOption('#of_status', 'draft');
await page.fill('#of_location_name', 'Capilla San José, Funeraria del Zulia');
await page.fill('#of_location_address', 'Calle 84 No. 3F-70, Sector Valle Frío, Maracaibo');
await page.fill('#of_event_schedule', 'Acto velatorio: 9:00 a.m. · Sepelio: 3:00 p.m., Cementerio El Cuadrado');
await page.fill('#of_biography', 'Texto de ejemplo para el manual. Aquí va la nota recordatoria escrita junto a la familia.');
await shot('11-obituario-formulario', { el: modal, hl: ['#of_status', '#of_submit'] });
await page.click('#of_submit');
await page.waitForSelector('#modal', { state: 'hidden' });
await normal();
await page.waitForSelector(`#obitTableBody tr:has-text("${DEMO_NAME}")`);
await page.waitForTimeout(400);
const demoRow = page.locator('#obitTableBody tr', { hasText: DEMO_NAME }).first();
await shot('12-obituario-guardado', { el: '#tab-obituarios .table-responsive, #tab-obituarios table', hl: demoRow });
await shot('13-obituario-acciones', { el: demoRow, pad: 10, hl: demoRow.locator('.admin-actions') });

await tall(1600);
await openModalWith(demoRow.locator('button', { hasText: 'Editar' }), '#of_full_name');
await shot('14-obituario-editar', { el: modal, hl: '#of_status' });
await closeModal();
await normal();

// Redes: sobre un obituario ACTIVO existente (el de ejemplo está en borrador).
const activeRow = page.locator('#obitTableBody tr', { has: page.locator('.status-badge', { hasText: /activo/i }) })
    .filter({ hasNotText: DEMO_NAME }).first();
if (await activeRow.count()) {
    await tall(1700);
    await openModalWith(activeRow.locator('button', { hasText: 'Redes' }), '.social-link-row');
    await page.waitForSelector('#socialStats table, #socialStats p:not(:has-text("Cargando"))');
    await page.waitForFunction(() => { const i = document.querySelector('.social-og-preview'); return !i || i.complete; });
    await shot('15-redes', { el: modal, hl: page.locator('.social-link-copy button').first() });
    await closeModal();
    await normal();

    // Tarjetas descargables: se piden con la sesión del panel y se muestran reducidas.
    const id = await activeRow.locator('button', { hasText: 'Redes' }).getAttribute('onclick').then(s => s.match(/\d+/)[0]);
    for (const style of ['cinta', 'esquela']) {
        const res = await ctx.request.get(`${BASE}api/obituary_card.php?id=${id}&style=${style}`);
        if (res.ok()) {
            const p2 = await ctx.newPage();
            await p2.setViewportSize({ width: 405, height: 720 });
            await p2.setContent(`<body style="margin:0"><img style="width:405px;display:block" src="data:image/png;base64,${(await res.body()).toString('base64')}"></body>`);
            await p2.waitForTimeout(300);
            await p2.screenshot({ path: path.join(OUT, `16-tarjeta-${style}.png`) });
            await p2.close();
            console.log('  ✓', `16-tarjeta-${style}`);
        }
    }
}

// ---------- 3. Condolencias ----------
console.log('3. Condolencias');
await openTab('condolencias');
await shot('20-condolencias', { hl: ['#condFilters', page.locator('#tab-condolencias button', { hasText: 'Aprobar' }).first()] });

// ---------- 4. Directorio médico ----------
console.log('4. Directorio médico');
await openTab('medicos');
await shot('30-medicos-lista', { hl: '#newDoctorBtn' });
await tall(1600);
await openModalWith(page.locator('#newDoctorBtn'), '#df_full_name');
await page.fill('#df_full_name', 'Dra. Ana Ejemplo');
await page.fill('#df_specialty', 'Psicología del duelo');
await page.fill('#df_phone', '+58 414 000-0000');
await page.fill('#df_email', 'ana@ejemplo.com');
await page.fill('#df_location_name', 'Centro Médico de Ejemplo');
await page.fill('#df_bio', 'Acompañamiento a familias en proceso de duelo.');
await shot('31-medico-formulario', { el: modal, hl: '#df_status' });
await closeModal();
await normal();

// ---------- 5. Recursos de lectura ----------
console.log('5. Recursos');
await openTab('recursos');
await shot('40-recursos-lista', { hl: '#newArticleBtn' });
await tall(1700);
await openModalWith(page.locator('#newArticleBtn'), '#af_title');
await page.fill('#af_title', 'Cómo acompañar a un ser querido en el duelo');
await page.fill('#af_category', 'Duelo');
await page.fill('#af_excerpt', 'Ideas sencillas para estar presentes en los primeros días.');
await page.fill('#af_content', '<p>Escuchar sin juzgar...</p>\n<h2>Los primeros días</h2>\n<ul><li>Ofrezca ayuda concreta.</li></ul>');
await shot('41-recurso-formulario', { el: modal, hl: '#af_content' });
await closeModal();
await normal();

// ---------- 6. Preguntas frecuentes ----------
console.log('6. Preguntas frecuentes');
await openTab('faqs');
await shot('50-faqs-lista', { hl: '#newFaqBtn' });
await tall(1300);
await openModalWith(page.locator('#newFaqBtn'), '#ff_question');
await page.fill('#ff_question', '¿Pregunta de ejemplo?');
await page.fill('#ff_answer', 'Respuesta de ejemplo. Escriba aquí una respuesta clara y breve.');
await page.fill('#ff_order', '70');
await shot('51-faq-formulario', { el: modal, hl: '#ff_order' });
await closeModal();
await normal();

// ---------- 7. Plantillas (admin) ----------
console.log('7. Plantillas');
await openTab('plantillas');
await shot('60-plantillas', { hl: '#newTplBtn' });
await tall(1800);
await openModalWith(page.locator('#tab-plantillas button', { hasText: 'Editar' }).first(), '#tf_name');
await shot('61-plantilla-editar', { el: modal, hl: '#tf_body' });
await closeModal();
await normal();

// ---------- 8. Configuración (admin) ----------
console.log('8. Configuración');
await openTab('config');
await page.waitForSelector('#set_photo_retention_days');
await tall(2600);
await page.waitForTimeout(400);
// Recorta el formulario por subsecciones (h3.settings-subheading).
const parts = await page.evaluate(() => {
    const form = document.querySelector('#settingsForm').getBoundingClientRect();
    const hs = [...document.querySelectorAll('#settingsForm .settings-subheading')].map(h => h.getBoundingClientRect().top);
    const cuts = [form.top, ...hs, form.bottom];
    return { x: form.left, w: form.width, cuts };
});
const names = ['70-config-general', '71-config-secciones', '72-config-portal', '73-config-google'];
for (let i = 0; i < parts.cuts.length - 1 && i < names.length; i++) {
    const top = parts.cuts[i] - 8, bottom = parts.cuts[i + 1] + (i === parts.cuts.length - 2 ? 8 : -4);
    await shot(names[i], { clip: { x: parts.x - 12, y: top, width: parts.w + 24, height: bottom - top } });
}
await normal();

// ---------- 9. Usuarios (admin) ----------
console.log('9. Usuarios');
await openTab('usuarios');
await shot('80-usuarios', { hl: '#newUserBtn' });
await tall(1300);
await openModalWith(page.locator('#newUserBtn'), '#uf_email');
await page.fill('#uf_name', 'Nombre Apellido');
await page.fill('#uf_email', 'nuevo.usuario@ejemplo.com');
await page.fill('#uf_password', 'una-clave-segura');
await page.selectOption('#uf_role', 'editor').catch(() => {});
await shot('81-usuario-formulario', { el: modal, hl: '#uf_role' });
await closeModal();
await normal();

// ---------- Limpieza: baja del obituario de ejemplo ----------
console.log('Limpieza');
await openTab('obituarios');
await page.waitForSelector('#obitTableBody tr');
const demo = page.locator('#obitTableBody tr', { hasText: DEMO_NAME });
const n = await demo.count();
for (let i = 0; i < n; i++) {
    await page.locator('#obitTableBody tr', { hasText: DEMO_NAME }).first().locator('button', { hasText: 'Baja' }).click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(600);
}
console.log(`  ✓ baja de ${n} obituario(s) de ejemplo`);

await page.click('#logoutBtn').catch(() => {});
await browser.close();
console.log('Listo:', OUT);
