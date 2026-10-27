/**
 * Manual de Usuario interactivo del panel (pestaña "Manual").
 *
 * Contenido (MANUAL) + visor: índice lateral, buscador, pasos numerados con
 * captura ampliable y etiquetas de rol. Las capturas viven en manual/img/ y se
 * regeneran con docs/manual/capturar-manual.mjs (Playwright) cada vez que
 * cambie el panel; al regenerarlas, suba MANUAL_IMG_VERSION para que el
 * navegador no muestre las viejas.
 *
 * Para agregar o cambiar un proceso, edite solo el arreglo MANUAL: cada
 * capítulo tiene id, título, rol ('todos' | 'admin'), intro, pasos
 * [{ t: texto (admite <strong>/<em>), img?, cap? }] y consejos opcionales.
 */
const MANUAL_IMG_VERSION = '20261027-1';

const MANUAL = [
    {
        id: 'inicio', title: 'Entrar al panel', rol: 'todos',
        intro: 'El panel es la herramienta para publicar y mantener al día el contenido del sitio web de Funeraria del Zulia: obituarios, condolencias, directorio médico, recursos de lectura y preguntas frecuentes.',
        steps: [
            { t: 'Abra en el navegador la dirección del panel (termina en <strong>/admin.html</strong>). Escriba su <strong>correo</strong> y su <strong>contraseña</strong> y pulse <strong>Iniciar sesión</strong>.', img: '01-login', cap: 'Pantalla de inicio de sesión.' },
            { t: 'Al entrar verá los indicadores del día y la lista de obituarios. Arriba a la derecha están los menús <strong>Sitio web</strong> y <strong>Sistema</strong> (este último solo para administradores), su nombre, el botón <strong>Manual</strong> y el botón <strong>Salir</strong>.', img: '02-panel-inicio', cap: 'Vista principal del panel.' },
            { t: 'El menú <strong>Sitio web</strong> lleva a cada sección que se publica en la página: Obituarios, Condolencias, Directorio Médico, Recursos, Preguntas frecuentes y (solo administradores) Plantillas de obituario. El número amarillo indica condolencias por revisar.', img: '03-menu-sitio', cap: 'Menú "Sitio web".' },
            { t: 'El menú <strong>Sistema</strong> (solo administradores) tiene la <strong>Configuración</strong> del sitio y los <strong>Usuarios</strong> del panel.', img: '04-menu-sistema', cap: 'Menú "Sistema".' },
            { t: 'Al terminar, pulse <strong>Salir</strong>. Hágalo siempre en computadoras compartidas.' },
        ],
        tips: [
            'Cada persona debe tener su propio usuario. No comparta su contraseña.',
            'Si olvidó su contraseña, pídale a un administrador que le asigne una nueva (Sistema → Usuarios → Clave).',
        ],
    },
    {
        id: 'obituario-crear', title: 'Publicar un obituario', rol: 'todos',
        intro: 'Cómo crear el homenaje de una persona fallecida. Puede prepararlo como borrador y publicarlo cuando la familia lo apruebe.',
        steps: [
            { t: 'Vaya a <strong>Sitio web → Obituarios</strong> y pulse <strong>+ Nuevo obituario</strong>.', img: '10-obituarios-lista', cap: 'Lista de obituarios y botón para crear uno nuevo.' },
            { t: 'Complete el formulario. Son obligatorios el <strong>nombre completo</strong> y la <strong>fecha de fallecimiento</strong>. Elija el <strong>tipo de servicio</strong> (Acto Velatorio, Cremación, Homenaje Póstumo, Traslado u Otro), el lugar, la dirección y los horarios de los oficios o del sepelio, y escriba la nota recordatoria.', img: '11-obituario-formulario', cap: 'Formulario de un obituario nuevo.' },
            { t: 'Elija el <strong>Estado</strong>: <strong>Activo</strong> se publica en el sitio de inmediato; <strong>Borrador</strong> lo guarda sin publicarlo (ideal para revisarlo con la familia); <strong>Inactivo</strong> lo oculta.' },
            { t: 'La <strong>Plantilla</strong> define el diseño de la página del obituario. Si no elige ninguna se usa la predeterminada.' },
            { t: 'La <strong>Fotografía</strong> es opcional (JPG, PNG o WebP). Si no se sube, la página simplemente no muestra foto.' },
            { t: 'Pulse <strong>Guardar obituario</strong>. Aparecerá en la lista con su estado.', img: '12-obituario-guardado', cap: 'El obituario nuevo, guardado como borrador.' },
        ],
        tips: [
            'La dirección pública del obituario se crea sola a partir del nombre y la fecha.',
            'Para publicar un borrador, ábralo con <strong>Editar</strong> y cambie el estado a <strong>Activo</strong>.',
            'Las fotos se borran automáticamente después de los días configurados (30 por defecto) para ahorrar espacio; el obituario y su texto siguen publicados.',
        ],
    },
    {
        id: 'obituario-acciones', title: 'Editar, destacar o dar de baja un obituario', rol: 'todos',
        intro: 'Cada fila de la lista de obituarios tiene sus botones de acción.',
        steps: [
            { t: 'Ubique el obituario en la lista (puede usar el buscador por nombre). A la derecha están sus acciones.', img: '13-obituario-acciones', cap: 'Botones de acción de un obituario.' },
            { t: '<strong>Fijar / Quitar</strong>: destaca el obituario en la portada del sitio. La portada muestra primero los fijados y después los más recientes.' },
            { t: '<strong>Editar</strong>: abre el mismo formulario para corregir datos, cambiar el estado o reemplazar la foto.', img: '14-obituario-editar', cap: 'Edición de un obituario.' },
            { t: '<strong>Tarjeta ✝</strong> y <strong>Tarjeta 📰</strong>: descargan una imagen para compartir (vea "Tarjetas para compartir").' },
            { t: '<strong>Redes</strong>: enlaces cortos para publicar en redes sociales y clics recibidos (vea "Enlaces para redes").' },
            { t: '<strong>Baja</strong>: retira el obituario del sitio. El sistema pide confirmación. No se borra de la base de datos, pero el panel no tiene un botón para recuperarlo: si se dio de baja por error, pida ayuda al soporte técnico.' },
        ],
        tips: ['Al cambiar la foto de un obituario, el plazo de borrado automático de la foto vuelve a empezar.'],
    },
    {
        id: 'tarjetas', title: 'Tarjetas para compartir', rol: 'todos',
        intro: 'Imágenes verticales (formato historia, 1080×1920) listas para enviar por WhatsApp o publicar en redes, generadas con los datos del obituario.',
        steps: [
            { t: 'En la fila del obituario pulse <strong>Tarjeta ✝</strong> para la versión azul con la cinta conmemorativa. La imagen se descarga a su computadora.', img: '16-tarjeta-cinta', cap: 'Tarjeta "Cinta Conmemorativa".' },
            { t: 'Pulse <strong>Tarjeta 📰</strong> para la esquela blanca tradicional, con la invitación al acto velatorio.', img: '16-tarjeta-esquela', cap: 'Tarjeta "Esquela Familiar".' },
        ],
        tips: [
            'La foto solo aparece si el obituario tiene una foto real subida.',
            'Funciona también con borradores: puede preparar la tarjeta antes de publicar el obituario.',
        ],
    },
    {
        id: 'redes', title: 'Enlaces para redes y clics', rol: 'todos',
        intro: 'Cada obituario tiene un enlace corto por red social. Usarlos permite saber cuántas personas llegaron desde Facebook, WhatsApp o Instagram.',
        steps: [
            { t: 'En la fila del obituario pulse <strong>Redes</strong>. Pulse <strong>Copiar</strong> junto a la red donde va a publicar y pegue el enlace en su publicación o mensaje.', img: '15-redes', cap: 'Enlaces por red, vista previa y clics.' },
            { t: '<strong>Instagram</strong> no permite enlaces en las publicaciones: use su enlace en la biografía o en el sticker de enlace de las historias.' },
            { t: 'La <strong>vista previa</strong> muestra la imagen que verán las personas al recibir el enlace en Facebook o WhatsApp. Se actualiza sola si cambia el nombre, las fechas o la foto.' },
            { t: 'En <strong>Clics en los enlaces</strong> verá cuántas personas abrieron cada enlace en los últimos 30 días y en total. No cuenta robots ni las vistas previas automáticas, y no guarda datos de quien hace clic.' },
        ],
        tips: [
            'Los enlaces solo llevan al obituario mientras esté <strong>Activo</strong>.',
            'Publique enlaces cuando el sitio esté en su dirección definitiva: los de una dirección de prueba dejan de funcionar al mudarse.',
        ],
    },
    {
        id: 'condolencias', title: 'Moderar condolencias', rol: 'todos',
        intro: 'Los mensajes que el público deja en los obituarios llegan como "Por revisar" y no se publican hasta que alguien los apruebe.',
        steps: [
            { t: 'Vaya a <strong>Sitio web → Condolencias</strong>. Use los filtros <strong>Por revisar, Aprobadas, Ocultas y Todas</strong>. Revise cada mensaje y elija: <strong>Aprobar</strong> (se publica), <strong>Ocultar</strong> (deja de mostrarse sin borrarse), <strong>Editar</strong> (corrige nombre o texto) o <strong>Eliminar</strong> (se borra definitivamente).', img: '20-condolencias', cap: 'Moderación de condolencias.' },
        ],
        tips: ['El número amarillo en el menú "Sitio web" indica cuántas condolencias esperan revisión.'],
    },
    {
        id: 'medicos', title: 'Directorio médico', rol: 'todos',
        intro: 'Directorio público de médicos y especialistas que se ofrece como apoyo a las familias.',
        steps: [
            { t: 'Vaya a <strong>Sitio web → Directorio Médico</strong> y pulse <strong>+ Nuevo médico</strong>.', img: '30-medicos-lista', cap: 'Directorio médico.' },
            { t: 'Complete al menos el <strong>nombre</strong> y la <strong>especialidad</strong>. Opcionales: teléfono, correo, centro o clínica, dirección, descripción y foto. Elija el <strong>Estado</strong> (Activo, Borrador o Inactivo) y pulse <strong>Guardar médico</strong>.', img: '31-medico-formulario', cap: 'Formulario de un médico.' },
            { t: 'En la lista, <strong>Destacar</strong> lo muestra primero en el directorio y <strong>Baja</strong> lo retira del sitio.' },
        ],
        tips: ['Las fotos de los médicos no se borran automáticamente.'],
    },
    {
        id: 'recursos', title: 'Recursos de lectura', rol: 'todos',
        intro: 'Artículos y guías para las familias (duelo, trámites, previsión).',
        steps: [
            { t: 'Vaya a <strong>Sitio web → Recursos</strong> y pulse <strong>+ Nuevo recurso</strong>.', img: '40-recursos-lista', cap: 'Lista de recursos.' },
            { t: 'Son obligatorios el <strong>título</strong> y el <strong>contenido</strong>. Puede agregar categoría, extracto, descripción para buscadores e imagen de portada. El contenido admite formato básico: <code>&lt;p&gt;</code> para párrafos, <code>&lt;h2&gt;</code> para subtítulos, <code>&lt;ul&gt;&lt;li&gt;</code> para listas y <code>&lt;strong&gt;</code> para negritas.', img: '41-recurso-formulario', cap: 'Formulario de un recurso.' },
            { t: 'Elija el estado y guarde. La fecha de publicación se fija la primera vez que se activa.' },
        ],
    },
    {
        id: 'faqs', title: 'Preguntas frecuentes', rol: 'todos',
        intro: 'Las preguntas que aparecen en la portada del sitio se administran desde aquí.',
        steps: [
            { t: 'Vaya a <strong>Sitio web → Preguntas frecuentes</strong>. Use <strong>Pausar / Activar</strong> para ocultarlas o mostrarlas sin borrarlas, <strong>Editar</strong> para cambiarlas y <strong>Eliminar</strong> para borrarlas.', img: '50-faqs-lista', cap: 'Lista de preguntas frecuentes.' },
            { t: 'Pulse <strong>+ Nueva pregunta</strong>, escriba la pregunta y la respuesta y elija el <strong>Orden</strong>: el número menor aparece primero en la portada.', img: '51-faq-formulario', cap: 'Formulario de una pregunta.' },
        ],
    },
    {
        id: 'plantillas', title: 'Plantillas de obituario', rol: 'admin',
        intro: 'El diseño con que se muestra cada obituario en el sitio.',
        steps: [
            { t: 'Vaya a <strong>Sitio web → Plantillas de obituario</strong>. <strong>Predeterminar</strong> marca la plantilla que se usa cuando un obituario no tiene una asignada (solo puede haber una).', img: '60-plantillas', cap: 'Plantillas disponibles.' },
            { t: 'Con <strong>Editar</strong> o <strong>+ Nueva plantilla</strong> se define el nombre, la descripción y el contenido HTML. Los marcadores entre llaves se reemplazan por los datos reales: <code>{{full_name}}</code>, <code>{{birth_year}}</code>, <code>{{death_date}}</code>, <code>{{photo_optional}}</code>, <code>{{biography}}</code>, <code>{{service_type}}</code>, <code>{{location_name}}</code>, <code>{{location_address}}</code>, <code>{{event_schedule}}</code>.', img: '61-plantilla-editar', cap: 'Edición de una plantilla.' },
        ],
        tips: ['Use <code>{{photo_optional}}</code> en vez de <code>{{photo}}</code>: así, si no hay foto, no aparece ninguna imagen genérica.', 'Una plantilla inactiva no se ofrece al crear obituarios.'],
    },
    {
        id: 'configuracion', title: 'Configuración del sitio', rol: 'admin',
        intro: 'Ajustes generales. Todos se guardan juntos con el botón "Guardar configuración" al final de la página.',
        steps: [
            { t: 'Vaya a <strong>Sistema → Configuración</strong>. Arriba: la <strong>purga automática de fotos</strong> y sus días de retención, cuántos <strong>obituarios</strong> se muestran en la portada y si las <strong>condolencias</strong> requieren aprobación.', img: '70-config-general', cap: 'Ajustes generales.' },
            { t: '<strong>Secciones del sitio</strong>: apague una sección (Obituarios, Directorio Médico, Recursos, Preguntas Frecuentes) para retirarla del menú, del pie de página y de la portada. El contenido no se borra.', img: '71-config-secciones', cap: 'Secciones activables.' },
            { t: '<strong>Portal de Clientes</strong>: muestra en el sitio el enlace al portal donde los titulares de planes consultan sus contratos y pagos. Manténgalo apagado hasta que el portal esté listo; la URL debe empezar con https://.', img: '72-config-portal', cap: 'Portal de Clientes.' },
            { t: '<strong>Analítica (Google)</strong>: pegue el ID de Google Analytics 4 (formato G-XXXXXXXXXX) y el código de verificación de Search Console. Déjelos vacíos para no usar Google.', img: '73-config-google', cap: 'Google Analytics y Search Console.' },
            { t: 'Pulse <strong>Guardar configuración</strong>.' },
        ],
    },
    {
        id: 'usuarios', title: 'Usuarios del panel', rol: 'admin',
        intro: 'Quién puede entrar al panel y qué puede hacer.',
        steps: [
            { t: 'Vaya a <strong>Sistema → Usuarios</strong>. Por cada usuario: <strong>Editar</strong> (nombre, rol, activar o desactivar el acceso), <strong>Clave</strong> (asignar una contraseña nueva) y <strong>Eliminar</strong>.', img: '80-usuarios', cap: 'Usuarios del panel.' },
            { t: 'Pulse <strong>+ Nuevo usuario</strong>, escriba el nombre, el correo y una contraseña de al menos 8 caracteres, y elija el rol: <strong>Editor</strong> gestiona el contenido (obituarios, condolencias, directorio, recursos, preguntas); <strong>Admin</strong> además gestiona plantillas, configuración y usuarios.', img: '81-usuario-formulario', cap: 'Alta de un usuario.' },
        ],
        tips: [
            'Por seguridad, nadie puede quitarse a sí mismo el rol de administrador ni eliminar su propio usuario.',
            'Desactive (en vez de eliminar) a quien deje de trabajar temporalmente: conserva su historial.',
        ],
    },
    {
        id: 'problemas', title: 'Si algo no funciona', rol: 'todos',
        intro: 'Soluciones a las situaciones más comunes.',
        steps: [
            { t: '<strong>Hice un cambio y no se ve en el sitio:</strong> recargue la página con <strong>Ctrl + F5</strong>. Revise también que el contenido esté en estado <strong>Activo</strong> y que su sección esté encendida en Configuración.' },
            { t: '<strong>No puedo subir una foto:</strong> use JPG, PNG o WebP. Si sigue fallando, avise al soporte técnico.' },
            { t: '<strong>El panel me saca o dice "No autenticado":</strong> su sesión venció. Vuelva a iniciar sesión.' },
            { t: '<strong>Un enlace de redes lleva a la lista de obituarios:</strong> el obituario no está Activo o fue dado de baja.' },
        ],
    },
];

// ---------------------------------------------------------------------------
//  Visor
// ---------------------------------------------------------------------------
let manualRendered = false;

function manualIsAdmin() {
    try { return typeof State !== 'undefined' && State.user && State.user.role === 'admin'; } catch (e) { return false; }
}
function manualText(html) { return String(html).replace(/<[^>]+>/g, ' ').replace(/&[a-z]+;/g, ' ').toLowerCase(); }
function manualImg(name) { return `manual/img/${name}.png?v=${MANUAL_IMG_VERSION}`; }

function renderManual() {
    const root = document.getElementById('manualRoot');
    if (!root) return;
    if (manualRendered) return;
    manualRendered = true;

    const chapters = MANUAL.filter(c => c.rol === 'todos' || manualIsAdmin());
    const badge = c => c.rol === 'admin' ? '<span class="manual-badge manual-badge-admin">Solo administradores</span>' : '';

    root.innerHTML = `
        <div class="manual-layout">
            <aside class="manual-side">
                <label class="sr-only" for="manualSearch">Buscar en el manual</label>
                <input type="search" id="manualSearch" class="form-control" placeholder="Buscar en el manual…" autocomplete="off">
                <nav class="manual-toc" aria-label="Contenido del manual">
                    ${chapters.map((c, i) => `<a href="#man-${c.id}" data-ch="${c.id}"><span class="manual-toc-n">${i + 1}</span>${c.title}${c.rol === 'admin' ? ' <span class="manual-toc-admin" title="Solo administradores">★</span>' : ''}</a>`).join('')}
                </nav>
            </aside>
            <div class="manual-content">
                <header class="manual-head">
                    <h2>Manual de Usuario</h2>
                    <p>Guía paso a paso del panel de Funeraria del Zulia. Haga clic en una imagen para verla en grande.</p>
                </header>
                <p class="manual-empty" id="manualEmpty" hidden>No hay resultados para su búsqueda.</p>
                ${chapters.map((c, i) => `
                <section class="manual-ch" id="man-${c.id}" data-ch="${c.id}">
                    <h3><span class="manual-ch-n">${i + 1}</span>${c.title} ${badge(c)}</h3>
                    <p class="manual-intro">${c.intro}</p>
                    <ol class="manual-steps">
                        ${c.steps.map(s => `
                        <li class="manual-step" data-text="${manualText(c.title + ' ' + s.t).replace(/"/g, '')}">
                            <p>${s.t}</p>
                            ${s.img ? `<figure><button type="button" class="manual-zoom" data-img="${manualImg(s.img)}" data-cap="${(s.cap || '').replace(/"/g, '&quot;')}" aria-label="Ampliar imagen: ${(s.cap || c.title).replace(/"/g, '&quot;')}"><img src="${manualImg(s.img)}" alt="${(s.cap || c.title).replace(/"/g, '&quot;')}" loading="lazy"></button>${s.cap ? `<figcaption>${s.cap}</figcaption>` : ''}</figure>` : ''}
                        </li>`).join('')}
                    </ol>
                    ${c.tips && c.tips.length ? `<div class="manual-tips"><strong>Para tener en cuenta</strong><ul>${c.tips.map(t => `<li>${t}</li>`).join('')}</ul></div>` : ''}
                </section>`).join('')}
            </div>
        </div>
        <div class="manual-lightbox" id="manualLightbox" hidden role="dialog" aria-modal="true" aria-label="Imagen ampliada">
            <button type="button" class="manual-lightbox-close" aria-label="Cerrar">×</button>
            <img alt="">
            <p class="manual-lightbox-cap"></p>
        </div>`;

    // Índice: desplazamiento suave dentro de la página y capítulo activo.
    root.querySelectorAll('.manual-toc a').forEach(a => a.addEventListener('click', e => {
        e.preventDefault();
        document.getElementById('man-' + a.dataset.ch)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }));
    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver(entries => {
            entries.forEach(en => {
                if (!en.isIntersecting) return;
                root.querySelectorAll('.manual-toc a').forEach(a => a.classList.toggle('active', a.dataset.ch === en.target.dataset.ch));
            });
        }, { rootMargin: '-20% 0px -70% 0px' });
        root.querySelectorAll('.manual-ch').forEach(s => io.observe(s));
    }

    // Buscador: filtra pasos y capítulos por texto (sin acentos ni mayúsculas).
    const norm = s => s.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    document.getElementById('manualSearch').addEventListener('input', e => {
        const q = norm(e.target.value.trim());
        let any = false;
        root.querySelectorAll('.manual-ch').forEach(ch => {
            let visible = 0;
            ch.querySelectorAll('.manual-step').forEach(st => {
                const show = !q || norm(st.dataset.text).includes(q);
                st.hidden = !show;
                if (show) visible++;
            });
            ch.hidden = visible === 0;
            root.querySelector(`.manual-toc a[data-ch="${ch.dataset.ch}"]`).hidden = visible === 0;
            if (visible) any = true;
        });
        document.getElementById('manualEmpty').hidden = any;
    });

    // Visor de imágenes.
    const lb = document.getElementById('manualLightbox');
    const closeLb = () => { lb.hidden = true; document.body.style.overflow = ''; };
    root.querySelectorAll('.manual-zoom').forEach(b => b.addEventListener('click', () => {
        lb.querySelector('img').src = b.dataset.img;
        lb.querySelector('img').alt = b.dataset.cap || '';
        lb.querySelector('.manual-lightbox-cap').textContent = b.dataset.cap || '';
        lb.hidden = false;
        document.body.style.overflow = 'hidden';
        lb.querySelector('.manual-lightbox-close').focus();
    }));
    lb.addEventListener('click', e => { if (e.target === lb || e.target.closest('.manual-lightbox-close')) closeLb(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !lb.hidden) closeLb(); });
}
