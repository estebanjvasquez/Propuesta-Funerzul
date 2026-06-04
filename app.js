// ==========================================================================
// LÓGICA PRINCIPAL (APP.JS) - FUNERARIA DEL ZULIA
// ==========================================================================

// Datos de Muestra Iniciales
const INITIAL_OBITUARIES = [
    {
        id: "ob-1",
        fullName: "Sra. María Elena González de Pérez",
        birthYear: 1940,
        deathDate: getRelativeDateString(1), // Ayer
        serviceType: "Velación",
        locationName: "Capilla 1 - Sede Principal",
        locationAddress: "Av. Delicias con Calle 77, Maracaibo",
        eventSchedule: "Misa de cuerpo presente: Hoy a las 4:00 PM. Sepelio: Cementerio La Chinita.",
        photoUrl: "https://lh3.googleusercontent.com/aida-public/AB6AXuB1gU520waL1whYt90ih-ayFvzvPO-UHnOL2Zn1gSpANVG86fNdHCgvquYDX05IUQaQNbZuC-p-1F1-eYc_NjNkTxONzGYqAyMlKZujgUdurTVXhgeGgurMuazbbE85ebGsilOqlEN0aNt-5lr1jJmyk_Rx-D6pqgeX8Q-tWsQIW-rFcQaErR7ahJv_OA8737C7_MgS_FOqD6lBj0J0kDHhpmPg6KIMVuuBSJ2R17iw89HKATwLAGGqi-BEBGlrdq1mVRHvO6qFisA",
        biography: "Madre ejemplar, abuela cariñosa y pilar fundamental de nuestra familia. Su luz y legado vivirán por siempre en nuestros corazones.",
        condolences: [
            { id: "c-1", author: "Familia Rincón Villalobos", text: "Acompañamos a la familia en este momento de profundo dolor. Mucha fortaleza.", date: "2026-06-03" },
            { id: "c-2", author: "Dra. Elena Silva", text: "Una gran mujer que dejó una huella imborrable en nuestra comunidad. Paz a su alma.", date: "2026-06-03" }
        ],
        flowers: []
    },
    {
        id: "ob-2",
        fullName: "Sr. José Antonio Rincón",
        birthYear: 1955,
        deathDate: getRelativeDateString(5), // Hace 5 días
        serviceType: "Cremación",
        locationName: "Crematorio Jardines del Sur",
        locationAddress: "Zona Industrial, San Francisco",
        eventSchedule: "Servicio de cremación privado. Recepción de cenizas y condolencias en capilla de 9:00 AM a 2:00 PM.",
        photoUrl: "https://lh3.googleusercontent.com/aida-public/AB6AXuD4wysETBJuqfFrejdGNDb6SxDwIZ5zigW4i56-F68zhQP-5EuBdYfSDtOBRueThxu2ey67DFmIgHsbFqoTOtB9ZDHLslr8-5VEwtwIyH7TXnGKGFpLNJE2BsS0IkO4t8_6zYQrx03jM1SOS9j5BSXSLNbABy8k-5lFNYy3rWWR6SYC_hQ0SLHn5Ow8cIEMcF02hLR2Icl4uGuaAP_6bz-JjfuHq453W7ILqcY6I7U3sMOd7uDcu7M7iwmboogoptHZyq50-3rKDRc",
        biography: "Amigo entrañable, profesional intachable y un ciudadano de oro marabino. Su carisma e integridad siempre nos inspirarán.",
        condolences: [
            { id: "c-3", author: "Ing. Luis Mendoza", text: "Mis más sinceras condolencias por la partida del querido José. Un fuerte abrazo.", date: "2026-06-01" }
        ],
        flowers: []
    },
    {
        id: "ob-3",
        fullName: "Dra. Carmen Luisa Villalobos",
        birthYear: 1962,
        deathDate: getRelativeDateString(18), // Hace 18 días
        serviceType: "Homenaje Póstumo",
        locationName: "Capilla Los Ángeles - Sede Norte",
        locationAddress: "Av. Fuerzas Armadas, Maracaibo",
        eventSchedule: "Oficio conmemorativo el 15 de Junio. Ceremonia e inhumación en estricta intimidad familiar.",
        photoUrl: "https://lh3.googleusercontent.com/aida-public/AB6AXuD2vdWv17AGbSU7dt4LYFRdpVyU6ZUfxOts_TByqNVCmi3xjTZiN4d5FPy999Zw0F5ws0bmrfSxKauIldyIinR8rZg2ZGAl6riTfslzZPlhvZ-xlsrhkw_KDzsX1bh8sQzUGKwOhKO3VjJeqV0N-laDB_OpZWBdyFsPwf3du7waaM_-7MFwt_rBG5OYRJVwck-gYYuoewsbp9zIlYt5oibOlIk0A7mYnf4G4LnIPEDR0_e4JLBLm39-rxik_dtg8J9yRCzUs3tuYgY",
        biography: "Médica pediatra dedicada por entero a la salud de los niños zulianos. Su vocación de servicio y calidez humana serán eternas.",
        condolences: [],
        flowers: []
    },
    {
        id: "ob-4",
        fullName: "Sr. Luis Fernando Osorio",
        birthYear: 1960,
        deathDate: getRelativeDateString(3), // Hace 3 días
        serviceType: "Velación",
        locationName: "Capilla San Judas",
        locationAddress: "Av. Delicias, Maracaibo",
        eventSchedule: "Sepelio: Hoy a las 3:00 PM en el Cementerio Parque la Chinita.",
        photoUrl: "https://lh3.googleusercontent.com/aida-public/AB6AXuADbcbhtPRHER-G6QGu6cI5hxMMOBhS6MuCFA-drR5NvRCXVfjEH6Jc1MNWWcMnIbE981YES2bJ9Jq4FIM9v8OH4JAmGrE-oWTZHYIYOQedONyXGakQRnL_G7S3m93Sd54Np6GdVfvbQdtfylGR_zQMcBmIVNH0piyJhaBATQf4d7ijGPPqPbd4r5D4cJzHG5If1l9KejGRTdWMDf5DlN-5jF_S-pNjxvRd8N7Lqe5tRarp3IxuKMPCy-cWCL2il3XhAfPL_WcJwok",
        biography: "Hombre de valores familiares firmes, amante del Zulia y su cultura. Dejó un vacío inmenso pero nos consuela su gran ejemplo de vida.",
        condolences: [],
        flowers: []
    }
];

// Helper para calcular fechas relativas al día de hoy para que los filtros funcionen
function getRelativeDateString(daysAgo) {
    const date = new Date();
    date.setDate(date.getDate() - daysAgo);
    return date.toISOString().split('T')[0]; // Formato YYYY-MM-DD
}

// Inicialización de LocalStorage
function initDatabase() {
    if (!localStorage.getItem("funerzul_obituaries")) {
        localStorage.setItem("funerzul_obituaries", JSON.stringify(INITIAL_OBITUARIES));
    }
}

// Obtener obituarios
function getObituaries() {
    return JSON.parse(localStorage.getItem("funerzul_obituaries")) || [];
}

// Guardar obituarios
function saveObituaries(obituaries) {
    localStorage.setItem("funerzul_obituaries", JSON.stringify(obituaries));
}

// Estado Global de la App
let obituariesList = [];
let filteredList = [];
let visibleCount = 3; // Paginación inicial

// Inicialización en DOM
document.addEventListener("DOMContentLoaded", () => {
    initDatabase();
    obituariesList = getObituaries();
    filteredList = [...obituariesList];
    
    // Configurar listeners
    const searchInput = document.getElementById("searchInput");
    if (searchInput) {
        searchInput.addEventListener("input", filterAndRender);
    }

    const typeFilter = document.getElementById("typeFilter");
    if (typeFilter) {
        typeFilter.addEventListener("change", filterAndRender);
    }

    const timeFilters = document.getElementById("timeFilters");
    if (timeFilters) {
        timeFilters.addEventListener("click", (e) => {
            if (e.target.classList.contains("filter-btn")) {
                // Quitar clase activa a todos
                timeFilters.querySelectorAll(".filter-btn").forEach(btn => btn.classList.remove("active"));
                // Añadir clase activa al pulsado
                e.target.classList.add("active");
                filterAndRender();
            }
        });
    }

    const loadMoreBtn = document.getElementById("loadMoreBtn");
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener("click", () => {
            visibleCount += 3;
            renderObituaries();
        });
    }

    // Renderizado Inicial
    filterAndRender();
});

// Filtrado de Datos
function filterAndRender() {
    const searchVal = document.getElementById("searchInput")?.value.toLowerCase().trim() || "";
    const selectedType = document.getElementById("typeFilter")?.value || "all";
    const selectedTimeBtn = document.querySelector("#timeFilters .filter-btn.active");
    const selectedTime = selectedTimeBtn ? selectedTimeBtn.getAttribute("data-time") : "all";

    // 1. Filtrar por búsqueda
    filteredList = obituariesList.filter(ob => {
        const matchesName = ob.fullName.toLowerCase().includes(searchVal);
        const matchesDate = ob.deathDate.includes(searchVal);
        const matchesLoc = ob.locationName.toLowerCase().includes(searchVal);
        return matchesName || matchesDate || matchesLoc;
    });

    // 2. Filtrar por Tipo de Servicio
    if (selectedType !== "all") {
        filteredList = filteredList.filter(ob => ob.serviceType === selectedType);
    }

    // 3. Filtrar por Tiempo (Semana/Mes)
    if (selectedTime !== "all") {
        const today = new Date();
        filteredList = filteredList.filter(ob => {
            const death = new Date(ob.deathDate + "T00:00:00"); // Evitar problemas de zona horaria
            const diffTime = Math.abs(today - death);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            if (selectedTime === "week") {
                return diffDays <= 7;
            } else if (selectedTime === "month") {
                return today.getMonth() === death.getMonth() && today.getFullYear() === death.getFullYear();
            }
            return true;
        });
    }

    // Resetear conteo visible en cada filtrado
    visibleCount = 3;
    renderObituaries();
}

// Renderización de Tarjetas en Grid
function renderObituaries() {
    const grid = document.getElementById("obituariesGrid");
    if (!grid) return;

    grid.innerHTML = "";

    if (filteredList.length === 0) {
        grid.innerHTML = `
            <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--color-text-muted);">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor" style="opacity: 0.3; margin-bottom: 16px;">
                    <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                </svg>
                <p style="font-size: 16px; font-weight: 500;">No se encontraron obituarios cargados que coincidan.</p>
                <p style="font-size: 14px; margin-top: 8px;">Pruebe cambiando su criterio de búsqueda o filtros.</p>
            </div>
        `;
        document.getElementById("loadMoreContainer").style.display = "none";
        return;
    }

    // Mostrar sólo hasta el visibleCount
    const itemsToRender = filteredList.slice(0, visibleCount);

    itemsToRender.forEach(ob => {
        const card = document.createElement("article");
        card.className = "obituary-card";

        // Formatear la fecha
        const dateObj = new Date(ob.deathDate + "T00:00:00");
        const formattedDate = dateObj.toLocaleDateString("es-ES", {
            day: "numeric",
            month: "long",
            year: "numeric"
        });

        card.innerHTML = `
            <div class="obituary-img-container">
                <img src="${ob.photoUrl}" alt="Retrato de ${ob.fullName}" class="obituary-img" onerror="this.src='https://placehold.co/400x300/e7e8e9/1a2b48?text=En+Memoria'">
                <span class="obituary-badge">${ob.serviceType}</span>
            </div>
            <div class="obituary-content">
                <div class="obituary-dates">Q.E.P.D. &bull; Falleció el ${formattedDate}</div>
                <h3 class="obituary-name">${ob.fullName}</h3>
                
                <ul class="obituary-details-list">
                    <li class="obituary-detail-item">
                        <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                        <div>
                            <span class="obituary-detail-label">${ob.locationName}</span>
                            <span class="obituary-detail-val">${ob.locationAddress}</span>
                        </div>
                    </li>
                    <li class="obituary-detail-item">
                        <svg viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
                        <div>
                            <span class="obituary-detail-label">Oficios y Sepelio</span>
                            <span class="obituary-detail-val">${ob.eventSchedule}</span>
                        </div>
                    </li>
                </ul>

                <div class="obituary-actions">
                    <button class="btn btn-outline" onclick="openFlowersModal('${ob.id}')">
                        <span style="font-size: 16px;">🌸</span> Flores
                    </button>
                    <button class="btn btn-outline" onclick="openCondolencesModal('${ob.id}')">
                        <span style="font-size: 16px;">✍️</span> Condolencias
                    </button>
                    <button class="btn btn-text" onclick="shareObituary('${ob.id}', '${ob.fullName}')" title="Compartir">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z"/>
                        </svg>
                    </button>
                </div>
            </div>
        `;
        grid.appendChild(card);
    });

    // Controlar botón de "Cargar más"
    const loadMoreContainer = document.getElementById("loadMoreContainer");
    if (loadMoreContainer) {
        if (visibleCount >= filteredList.length) {
            loadMoreContainer.style.display = "none";
        } else {
            loadMoreContainer.style.display = "block";
        }
    }
}

// Gestión de Modales
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add("active");
        document.body.style.overflow = "hidden"; // Desactivar scroll
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove("active");
        document.body.style.overflow = ""; // Reactivar scroll
    }
}

// Cierre de modal al pulsar fuera de la ventana
window.addEventListener("click", (e) => {
    if (e.target.classList.contains("modal-overlay")) {
        closeModal(e.target.id);
    }
});

// --- SUBSISTEMA DE CONDOLENCIAS ---

function openCondolencesModal(deceasedId) {
    const ob = obituariesList.find(item => item.id === deceasedId);
    if (!ob) return;

    // Poblar campos del formulario oculto
    document.getElementById("condolenceDeceasedId").value = deceasedId;
    document.getElementById("condolencesDeceasedInfo").innerText = `Mensajes en memoria de: ${ob.fullName}`;
    
    // Resetear formulario
    document.getElementById("condolenceForm").reset();

    // Renderizar lista de condolencias actuales
    renderCondolencesList(ob);

    openModal("condolencesModal");
}

function renderCondolencesList(obituary) {
    const container = document.getElementById("condolenceMessagesContainer");
    if (!container) return;

    container.innerHTML = "";

    const messages = obituary.condolences || [];

    if (messages.length === 0) {
        container.innerHTML = `<p class="condolence-empty">No hay mensajes publicados aún. Sea el primero en dejar sus condolencias.</p>`;
        return;
    }

    // Ordenar de más reciente a más antiguo
    const sorted = [...messages].reverse();

    sorted.forEach(msg => {
        const item = document.createElement("div");
        item.className = "condolence-item";
        
        const dateObj = new Date(msg.date + "T00:00:00");
        const formattedDate = dateObj.toLocaleDateString("es-ES", {
            day: "numeric",
            month: "short",
            year: "numeric"
        });

        item.innerHTML = `
            <div class="author">
                <span>${escapeHtml(msg.author)}</span>
                <span class="date">${formattedDate}</span>
            </div>
            <div>${escapeHtml(msg.text)}</div>
        `;
        container.appendChild(item);
    });
}

function handleCondolenceSubmit(e) {
    e.preventDefault();
    const deceasedId = document.getElementById("condolenceDeceasedId").value;
    const author = document.getElementById("condolenceAuthor").value.trim();
    const text = document.getElementById("condolenceText").value.trim();

    if (!deceasedId || !author || !text) return;

    // Buscar y actualizar el registro en la lista
    const index = obituariesList.findIndex(item => item.id === deceasedId);
    if (index === -1) return;

    const newCondolence = {
        id: "cond-" + Date.now(),
        author,
        text,
        date: new Date().toISOString().split('T')[0]
    };

    if (!obituariesList[index].condolences) {
        obituariesList[index].condolences = [];
    }

    obituariesList[index].condolences.push(newCondolence);

    // Guardar en LocalStorage
    saveObituaries(obituariesList);

    // Re-renderizar lista
    renderCondolencesList(obituariesList[index]);
    
    // Limpiar input de texto
    document.getElementById("condolenceText").value = "";

    showToast("Mensaje de condolencia publicado con éxito");
}

// --- SUBSISTEMA DE OFRENDAS DE FLORES ---

let selectedFlowerType = "Corona Imperial";
let selectedFlowerPrice = "$120";

function openFlowersModal(deceasedId) {
    const ob = obituariesList.find(item => item.id === deceasedId);
    if (!ob) return;

    document.getElementById("flowersDeceasedId").value = deceasedId;
    document.getElementById("flowersDeceasedInfo").innerText = `Flores y Ofrendas para: ${ob.fullName}`;
    
    // Resetear formulario
    document.getElementById("flowersForm").reset();

    // Resetear selección por defecto
    const defaultCard = document.querySelector(".flower-option-card[data-flower='Corona Imperial']");
    if (defaultCard) {
        selectFlowerOption(defaultCard);
    }

    openModal("flowersModal");
}

function selectFlowerOption(element) {
    // Quitar clases seleccionadas
    document.querySelectorAll(".flower-option-card").forEach(card => card.classList.remove("selected"));
    
    // Añadir clase seleccionada
    element.classList.add("selected");

    selectedFlowerType = element.getAttribute("data-flower");
    selectedFlowerPrice = element.getAttribute("data-price");
}

function handleFlowersSubmit(e) {
    e.preventDefault();
    const deceasedId = document.getElementById("flowersDeceasedId").value;
    const sender = document.getElementById("flowersSender").value.trim();
    const message = document.getElementById("flowersMessage").value.trim();

    if (!deceasedId || !sender || !message) return;

    const index = obituariesList.findIndex(item => item.id === deceasedId);
    if (index === -1) return;

    const newFlowerOffering = {
        id: "fl-" + Date.now(),
        sender,
        flowerType: selectedFlowerType,
        price: selectedFlowerPrice,
        message,
        date: new Date().toISOString().split('T')[0]
    };

    if (!obituariesList[index].flowers) {
        obituariesList[index].flowers = [];
    }

    obituariesList[index].flowers.push(newFlowerOffering);

    // Guardar
    saveObituaries(obituariesList);
    closeModal("flowersModal");
    showToast(`Ofrenda floral (${selectedFlowerType}) registrada (Simulada)`);
}

// --- COMPARTIR HOMENAJE ---

function shareObituary(id, name) {
    const shareUrl = `${window.location.origin}/obituarios?id=${id}`;
    const text = `Homenaje fúnebre a la memoria de ${name} en Funeraria del Zulia`;

    if (navigator.share) {
        navigator.share({
            title: 'Homenaje Fúnebre',
            text: text,
            url: shareUrl,
        }).catch(err => console.log(err));
    } else {
        // Fallback: Copiar al portapapeles
        navigator.clipboard.writeText(shareUrl).then(() => {
            showToast("Enlace de homenaje copiado al portapapeles");
        }).catch(err => {
            showToast("No se pudo copiar el enlace");
        });
    }
}

// --- UTILIDADES GLOBALES ---

// Notificación Toast
function showToast(message) {
    const toast = document.getElementById("toast");
    if (!toast) return;

    toast.innerText = message;
    toast.classList.add("show");

    setTimeout(() => {
        toast.classList.remove("show");
    }, 3000);
}

// Escapar HTML para evitar XSS
function escapeHtml(unsafe) {
    return unsafe
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}
