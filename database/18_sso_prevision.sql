-- ============================================================================
-- 18. "ENTRAR CON PREVISIÓN FUNERARIA" (INICIO DE SESIÓN ÚNICO DEL PANEL)
-- ============================================================================
-- Ver docs/specs/2026-10-28-sso-prevision-funeraria.md.
--
-- users.auth_source: 'local' = cuenta propia de este panel (correo + clave;
--   desde ahora solo cuentas de emergencia), 'pf' = entra con su cuenta de
--   Prevision-Funeraria (empresa fdz); sus perfiles vienen de los roles de PF.
-- users.pf_user_id: id de la cuenta en PF (control.usuarios.id).
-- auth_throttle: límite de intentos del login local (5 fallos en 15 min por
--   IP o por correo -> bloqueo de 15 min). El correo se guarda como SHA-256.
-- app_settings.pf_panel_url: botón "Previsión (FDZ)" de la cabecera del panel.
--
-- Importar ANTES de desplegar el código que la usa. Idempotente.

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'auth_source');
SET @sql := IF(@col = 0,
  'ALTER TABLE users
     ADD COLUMN auth_source ENUM(''local'',''pf'') NOT NULL DEFAULT ''local'' AFTER role,
     ADD COLUMN pf_user_id BIGINT UNSIGNED NULL AFTER auth_source,
     ADD UNIQUE KEY uq_users_pf_user (pf_user_id)',
  'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

CREATE TABLE IF NOT EXISTS auth_throttle (
  throttle_key  VARCHAR(100) NOT NULL,                -- 'ip:<ip>' | 'em:<sha256 del correo>'
  failures      INT UNSIGNED NOT NULL DEFAULT 0,
  window_start  DATETIME     NOT NULL,
  locked_until  DATETIME     NULL,
  PRIMARY KEY (throttle_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO app_settings (setting_key, setting_value, description) VALUES
  ('pf_panel_url', 'https://app.previsionfuneraria.com/?empresa=fdz',
     'Enlace "Previsión (FDZ)" en la cabecera del panel (sistema de Previsión Funeraria, empresa fdz). Vacío = sin enlace.');
