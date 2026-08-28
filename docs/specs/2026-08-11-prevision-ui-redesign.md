# Rediseño de interfaz — Módulo de Previsión (admin)

> **Archivado (2026-08-28):** el módulo PHP de Previsión (`#tab-prevision`,
> `admin-prevision.js`) fue retirado (ver
> [`2026-08-28-fase-e-corte-admin-prevision.md`](2026-08-28-fase-e-corte-admin-prevision.md)).
> Documento histórico.

## Resumen

El módulo de Previsión (`#tab-prevision` en `admin.html`, renderizado por
`admin-prevision.js`) es la interfaz más grande y compleja del panel. Su navegación
era un muro de 13 botones-píldora en 4 grupos (`.prev-subnav`) sobre 6 tarjetas de KPI
planas e idénticas: denso y difícil de escanear para el personal.

Resultado esperado: una **consola de dos columnas** (barra lateral de navegación +
área de contenido) al estilo de portales de autogestión limpios (referencia: Avianca
"gestiona tu reserva"), con KPIs jerarquizados e íconos y superficies (toolbars,
tablas, badges, cards) más aireadas — **sin cambiar la funcionalidad** ni el cableado.

Usuario afectado: personal administrativo/vendedores que operan el módulo.

## Alcance

Incluye:

- `admin.html`: clase `prev-console` en la sección; KPIs con ícono SVG inline y
  acentos semánticos (`is-danger` en "Cuotas vencidas", `is-success` en "Cobrado este
  mes"); wrapper `.prev-console-body` con `<aside class="prev-console-nav">` (contiene
  el `#pvSubNav` existente, ahora con íconos por item) y `<div class="prev-console-content">`
  (contiene los 13 `.prev-subpanel`); cache-buster `styles.css?v=20260811`.
- `styles.css`: bloque nuevo scopeado bajo `.prev-console` (layout de consola, sidebar,
  KPIs, tablas/toolbars/pager). Reutiliza las variables de `:root`; sin paleta nueva.
- `docs/specs/2026-08-11-prevision-ui-redesign.md` (este archivo).

No incluye:

- `admin-prevision.js`: **sin cambios**. Se conservan `#pvSubNav`,
  `.filter-btn[data-sub]`, los ids `#pvSub-*`, y los ids de KPIs (`#pvStat*`).
- Otras pestañas del panel (obituarios, condolencias, config, usuarios). Todo el
  rediseño va scopeado bajo `.prev-console` para no filtrar estilos.
- Endpoints, tablas y migraciones: ninguno afectado.

## Reglas de negocio

Ninguna cambia. Es un cambio puramente de presentación (HTML estructural + CSS).

## Riesgos

- **Fuga de estilos**: `.stat-card`, `.admin-table`, `.filter-btn`, `.admin-toolbar`
  son compartidos con otras pestañas. Mitigado: todas las reglas nuevas están
  prefijadas con `.prev-console`. El restyle de la navegación apunta solo a
  `#pvSubNav .filter-btn`, así que los filtros-píldora *dentro* de los subpaneles
  (cobranza, comisiones, reportes, mensajes, solicitudes) no se alteran.
- **Ruptura de JS**: mitigado — no se tocan ids/clases/`data-*` enlazados por
  `admin-prevision.js` (`wire()` L36-43, `loadSub()` L116-130).
- cPanel/carga directa: sin dependencias nuevas, sin build. Sin impacto SEO (panel
  `noindex`). Sin impacto en pagos ni auditoría.

## Plan

1. CSS scopeado `.prev-console` en `styles.css`. ✔
2. Reestructurar `#tab-prevision` en `admin.html` (wrappers + íconos + acentos). ✔
3. Subir cache-buster. ✔
4. Verificación manual en navegador. ✔ (ver abajo)
5. `graphify update .`

## Verificación

- Rol: personal/admin autenticado.
- Pantalla: Panel → **Previsión**.
- Pruebas:
  - Estructura confirmada: 13 `.filter-btn[data-sub]` y 13 `.prev-subpanel`; wrappers
    `prev-console-body/nav/content` balanceados (74 `<div>`/74 `</div>` en la sección).
  - Preview visual autocontenido (mismo CSS y markup) validado en el navegador:
    KPIs con jerarquía/acentos, barra lateral con íconos y estado activo, tabla más
    limpia; navegación alterna el item activo.
  - Pendiente en entorno con backend/DB: click en los 13 items → cada subpanel
    aparece/oculta y dispara su carga (`loadSub`); filtros internos siguen como
    píldoras; viewport ≤768px (nav se vuelve tira horizontal); otras pestañas
    visualmente iguales.

## Documentación a actualizar

- [x] `docs/specs/2026-08-11-prevision-ui-redesign.md`
- [ ] `README.md` — no requiere (cambio sólo visual del panel interno).
