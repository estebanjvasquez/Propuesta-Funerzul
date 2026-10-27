-- ============================================================================
-- 17. ENLACES CORTOS DE OBITUARIOS PARA REDES + ANALÍTICA (GA4 / SEARCH CONSOLE)
-- ============================================================================
-- Ver docs/specs/2026-10-27-enlaces-redes-y-analitica.md.
--
-- obituary_link_clicks: un contador AGREGADO por obituario, red y día. No
-- guarda IP, navegador ni ningún dato personal. Lo incrementa o.php (enlace
-- corto /o/{id}/{red}); lo lee api/obituaries.php?action=link_stats.
--
-- app_settings: ID de medición de Google Analytics 4 y código de verificación
-- de Google Search Console, vacíos por defecto (= no se inyecta nada en el
-- sitio hasta que el admin los cargue en Configuración).
--
-- Idempotente: se puede correr más de una vez.

CREATE TABLE IF NOT EXISTS obituary_link_clicks (
  obituary_id  BIGINT UNSIGNED NOT NULL,
  source       VARCHAR(16)     NOT NULL,              -- fb | wa | ig | otro | directo
  click_date   DATE            NOT NULL,
  clicks       INT UNSIGNED    NOT NULL DEFAULT 0,
  PRIMARY KEY (obituary_id, source, click_date),
  KEY ix_olc_date (click_date),
  CONSTRAINT fk_olc_obituary FOREIGN KEY (obituary_id) REFERENCES obituaries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO app_settings (setting_key, setting_value, description) VALUES
  ('ga4_measurement_id', '',
     'ID de medición de Google Analytics 4 (formato G-XXXXXXXXXX). Vacío = sin Analytics.'),
  ('google_site_verification', '',
     'Código de verificación de Google Search Console (valor "content" del meta tag). Vacío = sin meta tag.');
