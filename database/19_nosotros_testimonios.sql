-- ============================================================================
-- 19. PÁGINA "NOSOTROS" + TESTIMONIOS
-- ============================================================================
-- Ver docs/specs/2026-10-28-nosotros-y-testimonios.md.
--
-- testimonials: testimonios de familias, administrados desde el panel (pestaña
--   Testimonios, permiso sitio.contenido.write). Se muestran en la portada y en
--   nosotros.php. Semilla: los 7 testimonios distintos del WordPress actual
--   (www.funerariadelzulia.com tenía 10 entradas, 3 repetidas).
-- app_settings: interruptores de la página Nosotros y de los testimonios
--   (Configuración → Secciones del sitio), encendidos por defecto.
--
-- Idempotente: se puede correr más de una vez.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS testimonials (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  quote       TEXT NOT NULL,                       -- el testimonio, sin comillas
  author      VARCHAR(150) NOT NULL,               -- "Familia Suárez", "J. Velázquez"
  detail      VARCHAR(150) NULL,                   -- opcional: servicio, ciudad, año...
  sort_order  INT NOT NULL DEFAULT 0,              -- menor = aparece primero
  is_active   TINYINT(1) NOT NULL DEFAULT 1,       -- 1 visible / 0 en pausa
  created_by  BIGINT UNSIGNED NULL,
  updated_by  BIGINT UNSIGNED NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_testimonials_active (is_active),
  KEY ix_testimonials_order (sort_order),
  CONSTRAINT fk_testimonials_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_testimonials_updater FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO testimonials (id, quote, author, sort_order, is_active) VALUES
  (1, 'Estaré eternamente agradecida por el servicio realizado con motivo del sepelio de mi madre. ¡Dios les bendiga grandemente! Tener personas que den apoyo en tan duro momento no tiene precio. Gracias.',
      'Egdavi', 10, 1),
  (2, 'Te agradezco altamente la atención que nos brindaste… fue excelente. La atención y el cariño que pusiste en que todo quedara bien…',
      'Familia Mazzei Faría', 20, 1),
  (3, 'Excelente servicio y atención, gracias por apoyarnos con tanto cariño en la despedida de mis padres.',
      'Miguel Rendueles', 30, 1),
  (4, 'Consideramos que todos los servicios ofrecidos por la Funeraria del Zulia han sido excelentes… no tenemos cómo agradecerles todo el apoyo que nos han prestado en los momentos difíciles. Gracias a la familia Funeraria del Zulia.',
      'M. Pérez J.', 40, 1),
  (5, 'Les doy las gracias en nombre de mi familia por el gran apoyo, todo salió bien en el traslado desde Bogotá hasta Venezuela. Gracias al Señor.',
      'J. Velázquez', 50, 1),
  (6, 'Gracias por toda su colaboración, no tengo palabras. Creo que es el mejor servicio de Maracaibo.',
      'Familia Suárez', 60, 1),
  (7, '…se convierten en familia y hacen de ese momento tan duro más llevadero. Altamente agradecido con todos ustedes.',
      'Y. Moreno', 70, 1);

INSERT IGNORE INTO app_settings (setting_key, setting_value, description) VALUES
  ('section_nosotros_enabled', '1',
     'Activa (1) / desactiva (0) la página Nosotros (nosotros.php, enlaces del menú y pie, sitemap).'),
  ('section_testimonios_enabled', '1',
     'Activa (1) / desactiva (0) los Testimonios en la portada y en la página Nosotros.');
