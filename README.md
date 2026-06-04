# Funeraria del Zulia – "Propuesta Funerzul"

## Descripción
Este proyecto es un sitio web estático, **sin backend**, que ofrece:
- Información de servicios funerarios en Maracaibo (velación, cremación, traslados, capillas).
- Sección de **obituarios** y homenajes, gestionados desde un **panel administrativo**.
- Formulario de condolencias y simulación de envío de flores.
- Botón flotante de contacto vía WhatsApp con el número proporcionado.
- Diseño premium, sobrio y solemne (tema “Serenity & Legacy”), con colores, tipografías y micro‑animaciones modernas.

## Tecnologías
- **HTML5**, **CSS3** (vanilla), **JavaScript** (vanilla). 
- **Live Server** (para desarrollo local). 
- **localStorage** como “base de datos” para los obituarios.

## Estructura del proyecto
```
Propuesta Funerzul/
│   .cpanel.yml          # Configuración de despliegue para cPanel
│   README.md            # ¡Este archivo!
│   index.html           # Página principal (SPA)
│   admin.html           # Panel de administración
│   app.js               # Lógica de la página principal
│   admin.js             # Lógica del panel admin
│   styles.css           # Hoja de estilos globales
│   uploads/             # Carpeta preparada para futuros uploads de fotos
│
└─ assets/ (opcional)   # Imágenes y fuentes personalizadas
```

## Instalación y ejecución local
1. **Clonar el repositorio**
   ```bash
   git clone <repo-url>
   cd "Propuesta Funerzul"
   ```
2. **Instalar `live-server`** (si no lo tienes)
   ```bash
   npm install -g live-server
   ```
3. **Ejecutar**
   ```bash
   live-server
   ```
   Se abrirá el sitio en `http://127.0.0.1:8080` (puede variar el puerto).

## Uso del panel administrativo
- Accede a `admin.html` desde el mismo dominio.
- Desde el formulario puedes **crear**, **editar** y **eliminar** obituarios.
- Puedes **cargar una foto** del fallecido mediante URL o usando el botón **Cargar** para subir una imagen (se almacena como Base64 en `localStorage`).
- También puedes escoger una foto de muestra predefinida.

## Despliegue en cPanel
1. Asegúrate de que el archivo **`.cpanel.yml`** está presente en la raíz del proyecto (ya creado).
2. El contenido de `.cpanel.yml` copia los archivos necesarios al directorio remoto:
   ```yaml
   ---
   deployment:
     tasks:
       - export DEPLOYPATH=/home/legadoholding/public_html/funerzul
       - /bin/mkdir -p $DEPLOYPATH
       - /bin/cp -R index.html styles.css app.js admin.js admin.html uploads $DEPLOYPATH
   ```
3. En el panel de **cPanel → Git™ Version Control**, configura el repositorio y usa la opción *Deploy*.
   - cPanel leerá `.cpanel.yml` y ejecutará los comandos de despliegue.
4. Verifica que el sitio está accesible en `https://<tu-dominio>/funerzul`.

## Personalización y ampliaciones
- **Backend real**: sustituir `localStorage` por una base de datos (MySQL, Firebase, etc.) y crear un endpoint para subir archivos permanentemente.
- **Temas**: cambiar la paleta de colores o tipografía editando `styles.css`.
- **SEO**: los encabezados, meta‑descripciones y JSON‑LD ya están incorporados; puedes añadir más páginas estáticas si lo deseas.

## Licencia
Este proyecto está bajo la licencia **MIT** – puedes usar, modificar y distribuir libremente.

---
*Desarrollado con 🤝 por **Antigravity** y **Esteban Vasquez**.*
