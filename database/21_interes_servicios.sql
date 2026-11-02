-- ============================================================================
-- 21. MEDICIÓN DEL INTERÉS DEL PÚBLICO EN SERVICIOS NUEVOS
-- ============================================================================
-- Ver docs/specs/2026-11-02-interes-servicios-nuevos.md.
--
-- Primer servicio en evaluación: cremación de mascotas (la empresa aún no lo
-- ofrece). Se mide con:
--
-- interest_daily: contadores AGREGADOS por tema, evento y día, sin datos
--   personales (igual que obituary_link_clicks). Tema = clave de un servicio
--   del catálogo (api/lib/interest.php), p. ej. 'cremacion-mascotas', o
--   'faq:<id>' para las preguntas frecuentes. Eventos: view, visitor,
--   faq_open, whatsapp, call, open.
--
-- interest_responses: respuestas del formulario "Quiero que me avisen" de la
--   página del servicio. Los datos de contacto son opcionales; si se dejan, se
--   exige el consentimiento. La IP solo se usa para limitar envíos repetidos.
--
-- faqs.interest_key: enlaza una pregunta frecuente con un servicio en
--   evaluación: sus aperturas se cuentan para ese servicio y la portada muestra
--   debajo de la respuesta el enlace "Quiero que me avisen".
--
-- Idempotente: se puede correr más de una vez.

CREATE TABLE IF NOT EXISTS interest_daily (
  subject     VARCHAR(60)  NOT NULL,                -- 'cremacion-mascotas' | 'faq:12'
  event       VARCHAR(20)  NOT NULL,                -- view | visitor | faq_open | whatsapp | call | open
  event_date  DATE         NOT NULL,
  hits        INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (subject, event, event_date),
  KEY ix_interest_date (event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS interest_responses (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  service_key   VARCHAR(60)  NOT NULL,
  answers       TEXT         NULL,                  -- JSON: {pregunta: opción | [opciones]}
  full_name     VARCHAR(150) NULL,
  phone         VARCHAR(40)  NULL,
  email         VARCHAR(190) NULL,
  comment       VARCHAR(1000) NULL,
  consent       TINYINT(1)   NOT NULL DEFAULT 0,
  source        VARCHAR(120) NULL,                  -- utm_source / referrer resumido
  ip_address    VARCHAR(45)  NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_ir_service (service_key, created_at),
  KEY ix_ir_ip (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'faqs' AND COLUMN_NAME = 'interest_key');
SET @sql := IF(@col = 0,
  'ALTER TABLE faqs ADD COLUMN interest_key VARCHAR(60) NULL AFTER answer',
  'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Pregunta frecuente del servicio en evaluación (al final de la lista).
INSERT INTO faqs (question, answer, interest_key, sort_order, is_active)
SELECT '¿Ofrecen cremación de mascotas?',
       'Todavía no. Estamos evaluando ofrecer en Maracaibo un servicio de cremación de mascotas, con la misma seriedad y respeto con que atendemos a las familias. Si le interesa, cuéntenos qué necesitaría y le avisaremos cuando esté disponible.',
       'cremacion-mascotas', t.siguiente, 1
FROM (SELECT COALESCE(MAX(sort_order), 0) + 10 AS siguiente FROM faqs) AS t
WHERE NOT EXISTS (SELECT 1 FROM faqs WHERE interest_key = 'cremacion-mascotas');
