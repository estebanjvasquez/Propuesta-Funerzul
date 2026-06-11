-- ============================================================================
--  FUNERARIA DEL ZULIA — Sistema de Obituarios en Línea
--  Fase 1: Esquema de Base de Datos (MySQL / MariaDB en cPanel)
--  Importar en: cPanel -> phpMyAdmin -> [tu base de datos] -> Importar
--  Motor: InnoDB, charset utf8mb4. Control de acceso y auditoría: en PHP.
-- ============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

-- ----------------------------------------------------------------------------
-- 1. USUARIOS DEL PANEL  (autenticación gestionada por PHP)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email         VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,            -- bcrypt (password_hash/password_verify de PHP)
  full_name     VARCHAR(150) NOT NULL DEFAULT '',
  role          ENUM('admin','editor') NOT NULL DEFAULT 'editor',
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. PLANTILLAS DE OBITUARIO
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS obituary_templates (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  body_html   MEDIUMTEXT NOT NULL,                -- marcadores: {{full_name}}, {{biography}}, {{death_date}}, {{photo}}...
  styles      MEDIUMTEXT NULL,                    -- CSS opcional de la plantilla
  config      LONGTEXT NULL,                      -- opciones JSON (campos visibles, layout...)
  is_default  TINYINT(1) NOT NULL DEFAULT 0,      -- una sola por defecto (se garantiza desde PHP)
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_by  BIGINT UNSIGNED NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_tpl_default (is_default),
  CONSTRAINT fk_tpl_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. OBITUARIOS
--    La foto vive en el DISCO del servidor; aquí solo la ruta y su estado.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS obituaries (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug                VARCHAR(200) NULL,                 -- URL amigable para SEO
  full_name           VARCHAR(200) NOT NULL,
  birth_year          SMALLINT NULL,
  birth_date          DATE NULL,
  death_date          DATE NOT NULL,
  service_type        ENUM('Velación','Cremación','Homenaje Póstumo','Traslado','Otro') NOT NULL DEFAULT 'Velación',
  location_name       VARCHAR(200) NULL,
  location_address    VARCHAR(255) NULL,
  event_schedule      TEXT NULL,
  biography           TEXT NULL,
  -- Foto en disco + control de purga
  photo_path          VARCHAR(255) NULL,                 -- p.ej. 'uploads/obituarios/123.webp'
  photo_original_name VARCHAR(255) NULL,
  photo_uploaded_at   DATETIME NULL,
  photo_purge_at      DATETIME NULL,                     -- cuándo el cron debe purgar la foto
  photo_purged        TINYINT(1) NOT NULL DEFAULT 0,
  -- Visibilidad / destacados
  is_pinned           TINYINT(1) NOT NULL DEFAULT 0,     -- "fijar" en la portada
  pin_order           INT NULL,
  status              ENUM('draft','active','inactive') NOT NULL DEFAULT 'active',
  template_id         BIGINT UNSIGNED NULL,
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
  UNIQUE KEY uq_obit_slug (slug),
  KEY ix_obit_death (death_date),
  KEY ix_obit_status (status),
  KEY ix_obit_pinned (is_pinned),
  KEY ix_obit_purge (photo_purged, photo_purge_at),
  CONSTRAINT fk_obit_template FOREIGN KEY (template_id) REFERENCES obituary_templates(id) ON DELETE SET NULL,
  CONSTRAINT fk_obit_creator  FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_obit_updater  FOREIGN KEY (updated_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. CONDOLENCIAS  (moderación: pending -> approved / hidden)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS condolences (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  obituary_id  BIGINT UNSIGNED NOT NULL,
  author_name  VARCHAR(150) NOT NULL,
  message      TEXT NOT NULL,
  status       ENUM('pending','approved','hidden') NOT NULL DEFAULT 'pending',
  author_ip    VARCHAR(45) NULL,
  moderated_by BIGINT UNSIGNED NULL,
  moderated_at DATETIME NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_cond_obit (obituary_id),
  KEY ix_cond_status (status),
  CONSTRAINT fk_cond_obit FOREIGN KEY (obituary_id) REFERENCES obituaries(id) ON DELETE CASCADE,
  CONSTRAINT fk_cond_mod  FOREIGN KEY (moderated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. OFRENDAS FLORALES  (paridad con el sistema actual; simuladas)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS flower_offerings (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  obituary_id BIGINT UNSIGNED NOT NULL,
  sender      VARCHAR(150) NOT NULL,
  flower_type VARCHAR(100) NULL,
  price       VARCHAR(30) NULL,
  message     VARCHAR(255) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_flowers_obit (obituary_id),
  CONSTRAINT fk_flowers_obit FOREIGN KEY (obituary_id) REFERENCES obituaries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. CONFIGURACIÓN  (incluye la purga de fotos)
--    setting_value se guarda como texto; PHP lo interpreta (int/bool/string).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS app_settings (
  setting_key   VARCHAR(64) NOT NULL,
  setting_value TEXT NOT NULL,
  description   VARCHAR(255) NULL,
  updated_by    BIGINT UNSIGNED NULL,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key),
  CONSTRAINT fk_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 7. BITÁCORA DE AUDITORÍA  (escrita desde PHP en cada acción relevante)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_id    BIGINT UNSIGNED NULL,                -- usuario que ejecutó la acción (NULL = sistema/cron)
  actor_email VARCHAR(190) NULL,
  action      VARCHAR(100) NOT NULL,               -- p.ej. 'obituary.create', 'photo.purge', 'auth.login'
  entity_type VARCHAR(50) NULL,
  entity_id   VARCHAR(64) NULL,
  details     LONGTEXT NULL,                       -- JSON serializado (json_encode de PHP)
  ip_address  VARCHAR(45) NULL,
  user_agent  VARCHAR(500) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_audit_created (created_at),
  KEY ix_audit_entity (entity_type, entity_id),
  KEY ix_audit_actor (actor_id),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;

-- ============================================================================
-- 8. DATOS SEMILLA
-- ============================================================================

-- 8.1 Administrador inicial
--     Email:    admin@funerariadelzulia.com   (cámbialo por el real)
--     Password: FZulia.Admin2026              (CÁMBIALO al primer ingreso)
--     El hash es bcrypt y es compatible con password_verify() de PHP.
INSERT IGNORE INTO users (email, password_hash, full_name, role, is_active) VALUES
  ('admin@funerariadelzulia.com',
   '$2b$10$OSFLAjbktIaCF60.IXmM3ecd8jdOINV0f0WrjrmJFlcJTSou.gX3e',
   'Administrador', 'admin', 1);

-- 8.2 Configuración (purga + portada + moderación)
INSERT IGNORE INTO app_settings (setting_key, setting_value, description) VALUES
  ('photo_retention_days',   '30',
     'Días que se conserva la foto en el disco antes de purgarse.'),
  ('photo_purge_enabled',    '1',
     'Activa (1) / desactiva (0) la rutina automática de purga de fotos.'),
  ('photo_placeholder_path', 'uploads/obituarios/_placeholder.webp',
     'Imagen (basada en el logo) que reemplaza a la foto purgada.'),
  ('homepage_recent_count',  '3',
     'Cantidad de obituarios mostrados en la página principal.'),
  ('condolence_moderation',  '1',
     'Si es 1, las condolencias requieren aprobación antes de publicarse.');

-- 8.3 Plantillas iniciales (editables luego desde el panel; solo una por defecto)
INSERT IGNORE INTO obituary_templates (id, name, description, is_default, body_html) VALUES
  (1, 'Clásico Sobrio',
   'Diseño tradicional centrado, ideal para la mayoría de los homenajes.', 1,
   '<article class="obit-tpl obit-clasico"><div class="obit-cross">✝</div><h1>{{full_name}}</h1><p class="obit-dates">{{birth_year}} — {{death_date}}</p><p class="obit-qepd">Q.E.P.D.</p><div class="obit-photo">{{photo}}</div><p class="obit-bio">{{biography}}</p><div class="obit-service"><strong>{{service_type}}</strong><br>{{location_name}}<br>{{event_schedule}}</div></article>'),
  (2, 'Estilo Periódico',
   'Columna estrecha tipo esquela de periódico, blanco y negro.', 0,
   '<article class="obit-tpl obit-periodico"><h1>{{full_name}}</h1><hr><p class="obit-dates">{{birth_year}} - {{death_date}}</p><div class="obit-photo">{{photo}}</div><p class="obit-bio">{{biography}}</p><p class="obit-service">{{service_type}} · {{location_name}}</p><p>{{event_schedule}}</p></article>'),
  (3, 'Homenaje Celestial',
   'Diseño con el ala/ángel de la marca y acentos en azul.', 0,
   '<article class="obit-tpl obit-celestial"><div class="obit-wing"></div><h1>{{full_name}}</h1><p class="obit-dates">{{birth_year}} — {{death_date}}</p><div class="obit-photo">{{photo}}</div><blockquote class="obit-bio">{{biography}}</blockquote><div class="obit-service">{{service_type}} — {{location_name}}<br>{{event_schedule}}</div></article>');

-- ============================================================================
-- FIN DEL ESQUEMA
-- Tras importar: inicia sesión con el admin sembrado y cambia su contraseña.
-- ============================================================================
