-- ============================================================================
--  FUNERARIA DEL ZULIA — Sistema de Obituarios en Línea
--  Fase 5: Directorio Médico + Recursos de Lectura
--  Importar en: cPanel -> phpMyAdmin -> [tu base de datos] -> Importar
--  Ejecutar DESPUÉS de 01_schema.sql. Es idempotente (CREATE TABLE IF NOT EXISTS).
--  Motor: InnoDB, charset utf8mb4. Control de acceso y auditoría: en PHP.
-- ============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

-- ----------------------------------------------------------------------------
-- 9. DIRECTORIO MÉDICO  (médicos/especialistas con contacto)
--    La foto vive en el DISCO del servidor; aquí solo la ruta. Sin purga.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS doctors (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug                VARCHAR(200) NULL,                 -- URL amigable para SEO
  full_name           VARCHAR(200) NOT NULL,
  specialty           VARCHAR(120) NOT NULL DEFAULT '',  -- p.ej. 'Cardiología'
  bio                 TEXT NULL,                         -- descripción / trayectoria
  phone               VARCHAR(40) NULL,
  email               VARCHAR(190) NULL,
  location_name       VARCHAR(200) NULL,                 -- centro / clínica
  location_address    VARCHAR(255) NULL,                 -- dirección o zona
  photo_path          VARCHAR(255) NULL,                 -- p.ej. 'uploads/doctors/doc_123.webp'
  photo_original_name VARCHAR(255) NULL,
  -- Visibilidad / destacados
  is_featured         TINYINT(1) NOT NULL DEFAULT 0,
  feature_order       INT NULL,
  status              ENUM('draft','active','inactive') NOT NULL DEFAULT 'active',
  -- SEO / GEO
  meta_description    VARCHAR(320) NULL,
  view_count          INT NOT NULL DEFAULT 0,
  -- Auditoría básica
  created_by          BIGINT UNSIGNED NULL,
  updated_by          BIGINT UNSIGNED NULL,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at          DATETIME NULL,                     -- borrado lógico
  PRIMARY KEY (id),
  UNIQUE KEY uq_doctors_slug (slug),
  KEY ix_doctors_specialty (specialty),
  KEY ix_doctors_status (status),
  KEY ix_doctors_featured (is_featured),
  CONSTRAINT fk_doctors_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_doctors_updater FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 10. RECURSOS DE LECTURA  (artículos / guías de apoyo)
--     Contenido HTML escrito por el personal; portada en disco. Sin purga.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS articles (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug                VARCHAR(200) NULL,                 -- URL amigable para SEO
  title               VARCHAR(255) NOT NULL,
  category            VARCHAR(100) NULL,                 -- p.ej. 'Duelo', 'Trámites', 'Previsión'
  excerpt             TEXT NULL,                         -- resumen / entradilla
  content             LONGTEXT NOT NULL,                 -- cuerpo en HTML
  cover_path          VARCHAR(255) NULL,                 -- p.ej. 'uploads/recursos/rec_123.webp'
  cover_original_name VARCHAR(255) NULL,
  -- Visibilidad / destacados
  is_featured         TINYINT(1) NOT NULL DEFAULT 0,
  feature_order       INT NULL,
  status              ENUM('draft','active','inactive') NOT NULL DEFAULT 'draft',
  -- SEO / GEO
  meta_description    VARCHAR(320) NULL,
  view_count          INT NOT NULL DEFAULT 0,
  published_at        DATETIME NULL,                     -- se fija al publicarse (status=active)
  -- Auditoría básica
  created_by          BIGINT UNSIGNED NULL,
  updated_by          BIGINT UNSIGNED NULL,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at          DATETIME NULL,                     -- borrado lógico
  PRIMARY KEY (id),
  UNIQUE KEY uq_articles_slug (slug),
  KEY ix_articles_category (category),
  KEY ix_articles_status (status),
  KEY ix_articles_featured (is_featured),
  KEY ix_articles_published (published_at),
  CONSTRAINT fk_articles_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_articles_updater FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;

-- ============================================================================
-- DATOS SEMILLA (ejemplos en borrador para validar las páginas; edítalos/bórralos)
-- ============================================================================

INSERT IGNORE INTO doctors (slug, full_name, specialty, bio, phone, email, location_name, location_address, status, meta_description) VALUES
  ('dr-ejemplo-medicina-general',
   'Dr. Ejemplo Pérez', 'Medicina General',
   'Médico de referencia para certificaciones y acompañamiento a las familias. (Ficha de ejemplo: edítela o elimínela desde el panel.)',
   '+58 424 000-0000', 'contacto@funerariadelzulia.com',
   'Consultorio Valle Frío', 'Sector Valle Frío, Maracaibo, Estado Zulia',
   'draft', 'Médico de medicina general en Maracaibo — Directorio de la Funeraria del Zulia.');

INSERT IGNORE INTO articles (slug, title, category, excerpt, content, status, meta_description) VALUES
  ('como-acompanar-en-el-duelo',
   'Cómo acompañar a un ser querido en el duelo', 'Duelo',
   'Una guía breve y compasiva para acompañar a quienes atraviesan una pérdida.',
   '<p>El duelo es un proceso natural. Escuchar sin juzgar, ofrecer presencia y respetar los tiempos de cada persona ayuda a sanar. (Artículo de ejemplo: edítelo o elimínelo desde el panel.)</p><h2>Gestos que ayudan</h2><ul><li>Acompañar en silencio.</li><li>Ofrecer ayuda concreta.</li><li>Recordar a la persona fallecida con cariño.</li></ul>',
   'draft', 'Guía para acompañar en el duelo — Recursos de la Funeraria del Zulia, Maracaibo.');

-- ============================================================================
-- FIN
-- ============================================================================
