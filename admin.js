// ==========================================================================
// LÓGICA DE ADMINISTRACIÓN (ADMIN.JS) - FUNERARIA DEL ZULIA
// ==========================================================================

// Estado Local
let obituariesList = [];

// Carga Inicial al arrancar el DOM
document.addEventListener("DOMContentLoaded", () => {
    loadData();
    renderTable();
    updateStats();

    // Listener para cancelar / limpiar formulario
    const cancelBtn = document.getElementById("cancelBtn");
    if (cancelBtn) {
        cancelBtn.addEventListener("click", resetForm);
    }
});

// Cargar datos desde LocalStorage
function loadData() {
    obituariesList = JSON.parse(localStorage.getItem("funerzul_obituaries")) || [];
}

// Guardar datos en LocalStorage
function saveChanges() {
    localStorage.setItem("funerzul_obituaries", JSON.stringify(obituariesList));
    updateStats();
}

// Actualizar Tarjetas de Estadísticas en el Dashboard
function updateStats() {
    const total = obituariesList.length;
    const velaciones = obituariesList.filter(ob => ob.serviceType === "Velación").length;
    const cremaciones = obituariesList.filter(ob => ob.serviceType === "Cremación").length;

    document.getElementById("statTotal").innerText = total;
    document.getElementById("statVelacion").innerText = velaciones;
    document.getElementById("statCremacion").innerText = cremaciones;
}

// Renderizar Tabla de Gestión
function renderTable() {
    const tbody = document.getElementById("obituariesTableBody");
    if (!tbody) return;

    tbody.innerHTML = "";

    if (obituariesList.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" style="text-align: center; color: var(--color-text-muted); padding: 30px;">
                    No hay obituarios publicados. Use el formulario de la izquierda para agregar uno.
                </td>
            </tr>
        `;
        return;
    }

    // Listar de más recientes a más antiguos (basado en fecha de muerte)
    const sorted = [...obituariesList].sort((a, b) => new Date(b.deathDate) - new Date(a.deathDate));

    sorted.forEach(ob => {
        const row = document.createElement("tr");

        const dateObj = new Date(ob.deathDate + "T00:00:00");
        const formattedDate = dateObj.toLocaleDateString("es-ES", {
            day: "numeric",
            month: "short",
            year: "numeric"
        });

        row.innerHTML = `
            <td>
                <div class="obituary-row-info">
                    <img src="${ob.photoUrl}" alt="${ob.fullName}" class="obituary-row-img" onerror="this.src='https://placehold.co/80x80/e7e8e9/1a2b48?text=RIP'">
                    <div>
                        <div style="font-weight: 600; color: var(--color-primary);">${escapeHtml(ob.fullName)}</div>
                        <div style="font-size: 11px; color: var(--color-text-muted);">Nacimiento: ${ob.birthYear}</div>
                    </div>
                </div>
            </td>
            <td>${formattedDate}</td>
            <td>
                <span class="obituary-badge" style="position: static; font-size: 10px;">${ob.serviceType}</span>
            </td>
            <td>
                <div class="admin-actions">
                    <button class="btn btn-outline btn-sm" onclick="editObituary('${ob.id}')">Editar</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteObituary('${ob.id}')">Eliminar</button>
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
}

// Selección rápida de Fotos de Muestra
function selectSamplePhoto(element) {
    // Quitar clases seleccionadas de la grilla de muestra
    document.querySelectorAll(".sample-photo-option").forEach(opt => opt.classList.remove("selected"));
    
    // Marcar como seleccionado
    element.classList.add("selected");

    // Copiar URL al campo del formulario
    const url = element.getAttribute("data-url");
    document.getElementById("photoUrl").value = url;
}

// Limpiar formulario y resetear estados
function resetForm() {
    const form = document.getElementById("obituaryForm");
    if (form) form.reset();

    document.getElementById("obituaryId").value = "";
    document.getElementById("formTitle").innerText = "Cargar Nuevo Obituario";
    document.getElementById("submitBtn").innerText = "Guardar Obituario";
    
    // Desmarcar fotos seleccionadas
    document.querySelectorAll(".sample-photo-option").forEach(opt => opt.classList.remove("selected"));

    // Reset file input and preview
    const fileInput = document.getElementById("photoFile");
    if (fileInput) fileInput.value = "";
    const photoUrlInput = document.getElementById("photoUrl");
    if (photoUrlInput) photoUrlInput.value = "";
}

// Manejar carga de archivo de foto del fallecido
function handleFileUpload() {
    const fileInput = document.getElementById("photoFile");
    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        showToast("No se ha seleccionado ninguna foto.");
        return;
    }
    const file = fileInput.files[0];
    const reader = new FileReader();
    reader.onload = function(e) {
        const base64 = e.target.result;
        // Asignar la URL base64 al campo de foto URL
        document.getElementById("photoUrl").value = base64;
        showToast("Foto cargada correctamente.");
    };
    reader.onerror = function() {
        showToast("Error al leer la foto.");
    };
    reader.readAsDataURL(file);
}

// Controlador de Envío de Formulario (Creación y Edición)
function handleFormSubmit(e) {
    e.preventDefault();

    const id = document.getElementById("obituaryId").value;
    const fullName = document.getElementById("fullName").value.trim();
    const birthYear = parseInt(document.getElementById("birthYear").value);
    const deathDate = document.getElementById("deathDate").value;
    const serviceType = document.getElementById("serviceType").value;
    const locationName = document.getElementById("locationName").value.trim();
    const locationAddress = document.getElementById("locationAddress").value.trim();
    const eventSchedule = document.getElementById("eventSchedule").value.trim();
    const biography = document.getElementById("biography").value.trim();
    const photoUrl = document.getElementById("photoUrl").value.trim();

    if (!fullName || !birthYear || !deathDate || !serviceType || !locationName || !eventSchedule || !biography || !photoUrl) {
        showToast("Por favor complete todos los campos requeridos.");
        return;
    }

    if (id) {
        // --- EDICIÓN ---
        const index = obituariesList.findIndex(ob => ob.id === id);
        if (index !== -1) {
            // Actualizar manteniendo comentarios e imágenes de flores existentes
            obituariesList[index] = {
                ...obituariesList[index],
                fullName,
                birthYear,
                deathDate,
                serviceType,
                locationName,
                locationAddress,
                eventSchedule,
                biography,
                photoUrl
            };
            showToast("Homenaje actualizado correctamente.");
        }
    } else {
        // --- CREACIÓN ---
        const newObituary = {
            id: "ob-" + Date.now(),
            fullName,
            birthYear,
            deathDate,
            serviceType,
            locationName,
            locationAddress,
            eventSchedule,
            biography,
            photoUrl,
            condolences: [],
            flowers: []
        };
        obituariesList.push(newObituary);
        showToast("Nuevo obituario publicado con éxito.");
    }

    saveChanges();
    renderTable();
    resetForm();
}

// Cargar datos en el formulario para editar
function editObituary(id) {
    const ob = obituariesList.find(item => item.id === id);
    if (!ob) return;

    // Poblar campos
    document.getElementById("obituaryId").value = ob.id;
    document.getElementById("fullName").value = ob.fullName;
    document.getElementById("birthYear").value = ob.birthYear;
    document.getElementById("deathDate").value = ob.deathDate;
    document.getElementById("serviceType").value = ob.serviceType;
    document.getElementById("locationName").value = ob.locationName;
    document.getElementById("locationAddress").value = ob.locationAddress;
    document.getElementById("eventSchedule").value = ob.eventSchedule;
    document.getElementById("biography").value = ob.biography;
    document.getElementById("photoUrl").value = ob.photoUrl;

    // Ajustar textos de los botones del formulario
    document.getElementById("formTitle").innerText = `Editar Homenaje a: ${ob.fullName}`;
    document.getElementById("submitBtn").innerText = "Guardar Cambios";

    // Marcar foto de muestra si coincide
    document.querySelectorAll(".sample-photo-option").forEach(opt => {
        if (opt.getAttribute("data-url") === ob.photoUrl) {
            opt.classList.add("selected");
        } else {
            opt.classList.remove("selected");
        }
    });

    // Scroll hasta el formulario
    document.getElementById("formTitle").scrollIntoView({ behavior: 'smooth' });
}

// Eliminar obituario
function deleteObituary(id) {
    const ob = obituariesList.find(item => item.id === id);
    if (!ob) return;

    if (confirm(`¿Está seguro de que desea eliminar permanentemente el obituario de ${ob.fullName}? Esta acción no se puede deshacer.`)) {
        obituariesList = obituariesList.filter(item => item.id !== id);
        saveChanges();
        renderTable();
        
        // Si estábamos editando este difunto, limpiar el formulario
        if (document.getElementById("obituaryId").value === id) {
            resetForm();
        }

        showToast("Obituario eliminado con éxito.");
    }
}

// --- UTILIDADES ---

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

// Escapar HTML contra XSS
function escapeHtml(unsafe) {
    return unsafe
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}
