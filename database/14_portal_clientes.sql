-- ============================================================================
-- 14. ENLACE AL PORTAL DE CLIENTES (PREVISION-FUNERARIA) ACTIVABLE DESDE EL PANEL
-- ============================================================================
-- No agrega tablas: usa app_settings (ya existe desde 01_schema.sql). Siembra
-- el interruptor APAGADO (0) -- el portal del titular vive en
-- Prevision-Funeraria y se enciende desde Configuración cuando esté listo -- y
-- la URL por defecto del portal del tenant fdz, editable desde el panel.
-- Ver client_portal_url() en api/lib/helpers.php y api/settings.php, y
-- docs/specs/2026-10-27-autenticacion-staff-y-portal-clientes.md.

INSERT IGNORE INTO app_settings (setting_key, setting_value, description) VALUES
  ('client_portal_enabled', '0',
     'Activa (1) / desactiva (0) el enlace "Portal de Clientes" en el menú y el pie de página del sitio público.'),
  ('client_portal_url', 'https://app.previsionfuneraria.com/portal/login?empresa=fdz',
     'URL del portal de clientes (portal del titular en Prevision-Funeraria). Debe empezar con https://.');
