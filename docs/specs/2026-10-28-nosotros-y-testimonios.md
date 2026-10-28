---
Estado: **Implementada (2026-10-28)**, sin probar contra PHP/MySQL real.
Requiere `database/19_nosotros_testimonios.sql`.
---

# Página "Nosotros" y Testimonios

## Pedido (2026-10-28)

El WordPress que se reemplaza en `www.funerariadelzulia.com` (ver
`2026-10-28-migracion-dominio-final.md`) tiene una página "Nosotros" y 10
testimonios. El usuario pidió:

- una página **Nosotros** nueva;
- **Testimonios** que se puedan **encender y apagar** y **administrar desde el
  panel** para agregar nuevos, **incluyendo los actuales**.

## Qué se hizo

| Pieza | Detalle |
|---|---|
| `nosotros.php` | Historia tomada del WordPress (texto del cliente, en forma de línea de tiempo: 1942, 1945, 1962, 1964, Familias Protegidas, 2021, hoy). JSON-LD `AboutPage` + `FuneralHome` con `foundingDate` 1942. Al final, los testimonios. Interruptor `section_nosotros_enabled`: apagada da 404 con "Sección no disponible" (igual que Obituarios) y desaparece del menú, el pie y el sitemap. |
| `testimonials` (migración 19) | `quote`, `author`, `detail` opcional, `sort_order`, `is_active`. Semilla: los **7 testimonios distintos** del WordPress (había 10 entradas, 3 repetidas). |
| `partials/testimonios.php` | Bloque "Lo que dicen las familias" (tarjetas). Va en la portada (antes de Preguntas Frecuentes, ancla `#testimonios`) y en Nosotros. Interruptor `section_testimonios_enabled`; sin testimonios activos no se muestra. No lleva marcado de reseñas (`Review`): Google no lo acepta para reseñas que publica la propia empresa. |
| `api/testimonials.php` | Igual que `faqs.php`: list / get / create / update / toggle / delete. Permiso `sitio.contenido.write` (perfil Contenido). |
| Panel | Sitio web → **Testimonios** (tabla, alta/edición, pausar, eliminar). Configuración → Secciones del sitio: **Nosotros** y **Testimonios**. Capítulo nuevo en el Manual. |
| Menú | Enlace "Nosotros" en la cabecera y el pie, junto a Crematorio. |
| Redirecciones | `/nosotros/` → `nosotros.php`; `/testimonial/…` → `/#testimonios` (`.htaccess`). |

## Arreglos de paso (afectaban a todo el sitio)

- **Menú en celular:** por debajo de 768px el menú se ocultaba y el botón ☰ de
  la portada no hacía nada (no tenía código); las páginas interiores ni siquiera
  tenían el botón. Ahora hay un menú desplegable en todas las páginas
  (`app.js`). Como la cantidad de enlaces depende de las secciones encendidas
  (hasta 10), el script **mide** si caben en una línea y, si no, los recoge en
  el botón también en escritorio. Con 10 enlaces no cabían ni a 1440px.
- **Margen lateral en móvil:** en las páginas interiores (`container section`,
  17 archivos) el texto tocaba el borde de la pantalla, porque
  `.section { padding: 80px 0 }` anulaba el margen lateral de `.container`.
  Corregido en `styles.css`.
