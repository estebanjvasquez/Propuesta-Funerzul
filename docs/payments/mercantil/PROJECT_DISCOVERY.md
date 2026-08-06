# Descubrimiento

## Stack

- Backend: PHP 8 "vanilla" (sin framework), PDO/MySQL (`api/lib/db.php`), sin ORM.
- Frontend público: HTML/PHP server-rendered (`index.php`, `servicios/*.php`, `planes/*.php`) + `app.js` (fetch a `api/`).
- Panel admin: `admin.html` + `admin.js` + `admin-prevision.js` (SPA ligera, sin build step, un solo `<script>` grande por módulo).
- Hosting: cPanel compartido, despliegue por `.cpanel.yml` (lista blanca de archivos/carpetas), dominio `https://www.funerariadelzulia.com`.
- Sesión: PHP nativa (`session_start()`), cookie `obit_sess`, CSRF por token de sesión (`require_csrf()` en `api/lib/auth.php`).
- Secretos: `api/config.php` (fuera del repo, `.gitignore`), copiado de `api/config.example.php`. No hay `.env`.
- Auditoría: tabla `audit_log` + función `audit()` (`api/lib/helpers.php`) — ya se usa en todas las mutaciones de previsión.

## Flujo de pedidos (previsión funeraria)

- Producto: planes de previsión funeraria (pre-necesidad) — no es una tienda con carrito. Un contrato (`prev_contratos`) se crea manualmente por un vendedor/staff desde el panel admin (`pvNuevoContrato()` en `admin-prevision.js`), tras capturar cliente, beneficiarios y condiciones.
- Al crear el contrato se generan `prev_cuotas` (cuotas por cobrar) según la frecuencia de pago.
- Los pagos hoy se registran manualmente por staff (`pvRegistrarPago()` → `api/prevision_contratos.php:case 'pago_registrar'`), aplicándose FIFO a las cuotas pendientes. `forma_pago` ya incluye `'pago_movil'`, `'transferencia'`, `'punto'`, etc. — es decir, el sistema ya sabe representar un pago móvil, solo que hoy se teclea después de que el cliente paga por fuera (no hay pasarela).
- El sitio público (`servicios/*.php`, `planes/*.php`) es contenido SEO puro: no hay carrito ni checkout, solo CTA de Llamar/WhatsApp (`partials/cta_contacto.php`).

## Modelo de datos relevante

- `prev_contratos`, `prev_cuotas`, `prev_pagos` (`database/04_prevision.sql`).
- `prev_planes` (catálogo de planes, con `codigo` — coincide con las páginas `planes/plan-*.php`).
- `prev_servicios` / `prev_contrato_servicios` (`database/05_prevision_v2.sql`) — catálogo interno de cargos adicionales (bóveda, cremación, traslado) vinculados a un contrato; **no** corresponden 1:1 a las páginas públicas de `servicios/` (que son categorías de contenido SEO, no un catálogo con precio).

## Autenticación / roles

- `users.role` en `'admin' | 'editor'` (`require_role('admin','editor')`, `is_staff()`, `is_admin()` en `api/lib/auth.php`).

## Infraestructura

- Sin colas, sin cron externo más allá de `api/cron/*.php` (llamado por cron de cPanel o URL con `cron_secret`).
- Sin observabilidad dedicada (métricas/alertas) — solo `error_log()` y `audit_log`.

## Gestión de secretos

- Array PHP en `api/config.php`, nunca en el repo. Cualquier credencial de Mercantil debe añadirse ahí, no en código ni en `.env`.

## Riesgos

- No hay concepto de "orden" ni "carrito" público — construir un checkout público completo y autónomo (crear un contrato vinculante sin revisión humana) es un cambio de producto mayor con implicaciones legales (KYC, cédula, beneficiarios) que excede el pedido actual.
- `prev_pagos` es el libro mayor que ya alimenta reportes de cobranza; cualquier pago electrónico debe terminar insertando ahí para no duplicar lógica de reportes.

## Cambios necesarios

- Ver plan de implementación (capa de pagos desacoplada + tablas nuevas + endpoints + UI). No se modifica el modelo de datos existente de previsión, solo se añade.

## Decisiones pendientes

- Confirmar con el usuario si el "checkout público" debe, a futuro, crear el contrato automáticamente o siempre pasar por revisión de un asesor (por ahora se implementó como captura de lead/solicitud, no como creación automática de contrato).
