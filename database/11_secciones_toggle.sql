-- ============================================================================
-- 11. SECCIONES DEL SITIO PÚBLICO ACTIVABLES/DESACTIVABLES DESDE EL PANEL
-- ============================================================================
-- No agrega tablas: usa app_settings (ya existe desde 01_schema.sql). Solo
-- siembra las 4 claves con su valor por defecto (1 = activa) para que el
-- panel de Configuración las muestre encendidas desde el primer momento,
-- en vez de aparecer apagadas hasta que el admin las toque una vez.
-- Ver site_section_enabled() en api/lib/helpers.php y api/settings.php.

INSERT IGNORE INTO app_settings (setting_key, setting_value, description) VALUES
  ('section_obituarios_enabled', '1',
     'Activa (1) / desactiva (0) la sección de Obituarios en el sitio público (portada, obituarios.php, obituario.php, sitemap).'),
  ('section_directorio_medico_enabled', '1',
     'Activa (1) / desactiva (0) el Directorio Médico en el sitio público (directorio-medico.php, medico.php, enlaces y sitemap).'),
  ('section_recursos_enabled', '1',
     'Activa (1) / desactiva (0) los Recursos de Lectura en el sitio público (recursos.php, recurso.php, enlaces y sitemap).'),
  ('section_faqs_enabled', '1',
     'Activa (1) / desactiva (0) la sección de Preguntas Frecuentes en la portada.');
