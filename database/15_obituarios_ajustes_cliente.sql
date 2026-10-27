-- ============================================================================
-- 15. OBITUARIOS: "VELACIÓN" -> "ACTO VELATORIO", LIMPIEZA DE FOTOS FALSAS
--     Y "EN MEMORIA DE" EN LA PLANTILLA PREDETERMINADA
-- ============================================================================
-- Pedidos del cliente del 2026-10-27:
--
-- 1) El tipo de servicio "Velación" pasa a llamarse "Acto Velatorio". Se
--    agrega el valor nuevo al ENUM, se migran las filas y queda como valor por
--    defecto. "Velación" se CONSERVA en el ENUM solo por compatibilidad (el
--    código ya no lo ofrece y api/obituaries.php lo convierte al nombre
--    nuevo), así la migración y el despliegue pueden aplicarse en cualquier
--    orden sin romper el guardado.
--
-- 2) Obituarios sin foto que mostraban el logo: al editar un obituario sin
--    foto, el panel guardaba la imagen genérica (placeholder) como si fuera
--    su foto. El código ya no lo hace; esto limpia las filas afectadas.
--    Usa el valor configurado en app_settings (o el de fábrica).
--
-- 3) "Al obituario le falta 'En memoria de'": se agrega a la plantilla
--    predeterminada "Clásico Sobrio" (id 1) antes del nombre. Solo si todavía
--    no lo tiene, y respetando cualquier otro cambio que el admin le haya hecho
--    a la plantilla desde el panel (REPLACE sobre el <h1> del nombre).
--
-- 4) "Allí hay un logo que no va": las plantillas con {{photo}} muestran la
--    imagen genérica con el logo cuando el obituario no tiene foto (o se
--    purgó). Pasan a {{photo_optional}}, que no muestra nada en ese caso.
--
-- Importar DESPUÉS de 14_portal_clientes.sql. Idempotente: se puede correr
-- más de una vez sin efectos adicionales.

ALTER TABLE obituaries
  MODIFY service_type ENUM('Acto Velatorio','Velación','Cremación','Homenaje Póstumo','Traslado','Otro')
  NOT NULL DEFAULT 'Acto Velatorio';

UPDATE obituaries SET service_type = 'Acto Velatorio' WHERE service_type = 'Velación';

UPDATE obituaries
SET photo_path = NULL, photo_uploaded_at = NULL, photo_purge_at = NULL
WHERE photo_path = COALESCE(
  (SELECT setting_value FROM app_settings WHERE setting_key = 'photo_placeholder_path'),
  'uploads/obituarios/_placeholder.webp'
);

UPDATE obituary_templates
SET body_html = REPLACE(body_html, '<h1>{{full_name}}</h1>', '<p class="obit-kicker">En memoria de</p><h1>{{full_name}}</h1>')
WHERE id = 1
  AND body_html LIKE '%<h1>{{full_name}}</h1>%'
  AND body_html NOT LIKE '%En memoria de%';

UPDATE obituary_templates
SET body_html = REPLACE(body_html, '{{photo}}', '{{photo_optional}}')
WHERE body_html LIKE '%{{photo}}%';
