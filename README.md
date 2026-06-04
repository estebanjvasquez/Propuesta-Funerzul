# Funeraria del Zulia – "Propuesta Funerzul"

## Descripción
Sitio web estático premium para **Funeraria del Zulia** en Maracaibo, Estado Zulia. Sin backend, con almacenamiento local (`localStorage`).

### Características principales
- **Servicios funerarios**: velación, cremación, traslados nacionales, capillas velatorias.
- **Obituarios y homenajes**: sección dinámica con búsqueda, filtros por tiempo y tipo de servicio.
- **Panel administrativo**: crear, editar y eliminar obituarios con carga de fotos (Base64 en localStorage).
- **Libro de condolencias**: formulario para dejar mensajes de acompañamiento.
- **Simulación de envío de flores**: selección de arreglos florales con tarjeta personalizada.
- **Contacto vía WhatsApp**: botón flotante con número +58 424 695 0136.
- **Mapa interactivo**: ubicación en Google Maps (coordenadas: 10.6585, -71.6047) con enlace directo.
- **Barra de emergencias 24/7**: franja superior con teléfono de contacto inmediato.
- **SEO optimizado**: meta tags, JSON-LD (schema FuneralService), estructura semántica HTML5.
- **Diseño premium**: tema "Serenity & Legacy" con tipografías Inter y Playfair Display, micro-animaciones y modo solemne.

## Tecnologías
- **HTML5**, **CSS3** (vanilla), **JavaScript** (vanilla)
- **Live Server** para desarrollo local
- **localStorage** como almacenamiento de datos para obituarios

## Estructura del proyecto
```
Propuesta Funerzul/
│   .cpanel.yml          # Configuración de despliegue para cPanel
│   README.md            # Este archivo
│   index.html           # Página principal
│   admin.html           # Panel de administración de obituarios
│   app.js               # Lógica de la página principal
│   admin.js             # Lógica del panel admin
│   styles.css           # Hoja de estilos globales (tokens, componentes)
│   logo.png             # Logo de Funeraria del Zulia (header)
│   favicon.png          # Favicon para pestaña del navegador
│   uploads/             # Carpeta para futuros uploads de fotos
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
- Accede a `admin.html` desde el navegador o desde el enlace "Panel Admin" en la barra de navegación.
- Desde el formulario puedes **crear**, **editar** y **eliminar** obituarios.
- Puedes **cargar una foto** del fallecido mediante URL o usando el botón **Cargar** para subir una imagen (se almacena como Base64 en `localStorage`).
- También puedes escoger una foto de muestra predefinida.

## Despliegue en cPanel
1. Asegúrate de que el archivo **`.cpanel.yml`** está presente en la raíz del proyecto.
2. El contenido de `.cpanel.yml` copia los archivos necesarios al directorio remoto:
   ```yaml
   ---
   deployment:
     tasks:
       - export DEPLOYPATH=/home/legadoholding/public_html/funerzul
       - /bin/mkdir -p $DEPLOYPATH
       - /bin/cp -R index.html styles.css app.js admin.js admin.html favicon.png logo.png uploads $DEPLOYPATH
   ```
3. En **cPanel → Git™ Version Control**, configura el repositorio y usa la opción *Deploy*.
4. Verifica que el sitio está accesible en `https://<tu-dominio>/funerzul`.

## Personalización
- **Backend real**: sustituir `localStorage` por una base de datos (MySQL, Firebase, etc.) y crear un endpoint para subir archivos permanentemente.
- **Temas**: cambiar la paleta de colores o tipografía editando las variables CSS en `styles.css`.
- **SEO**: los encabezados, meta-descripciones y JSON-LD ya están incorporados; puedes añadir más páginas si lo deseas.
- **Logo**: reemplazar `logo.png` y `favicon.png` con las versiones actualizadas de la marca.

## Licencia
Este proyecto está bajo la licencia **MIT** – puedes usar, modificar y distribuir libremente.

---
*Desarrollado con 🤝 por **Antigravity** y **Esteban Vasquez**.*
