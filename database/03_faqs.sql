-- ============================================================================
--  FUNERARIA DEL ZULIA — Sistema de Obituarios en Línea
--  Fase 6: Preguntas Frecuentes (FAQ) administrables
--  Importar en: cPanel -> phpMyAdmin -> [tu base de datos] -> Importar
--  Ejecutar DESPUÉS de 01_schema.sql. Es idempotente (CREATE TABLE IF NOT EXISTS).
--  Motor: InnoDB, charset utf8mb4. Control de acceso y auditoría: en PHP.
-- ============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

-- ----------------------------------------------------------------------------
-- 11. PREGUNTAS FRECUENTES
--     La sección "Preguntas Frecuentes" de la portada lee estas filas activas.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS faqs (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  question    VARCHAR(300) NOT NULL,
  answer      TEXT NOT NULL,
  sort_order  INT NOT NULL DEFAULT 0,             -- menor = aparece primero
  is_active   TINYINT(1) NOT NULL DEFAULT 1,      -- 1 visible / 0 en pausa
  created_by  BIGINT UNSIGNED NULL,
  updated_by  BIGINT UNSIGNED NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_faqs_active (is_active),
  KEY ix_faqs_order (sort_order),
  CONSTRAINT fk_faqs_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_faqs_updater FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;

-- ============================================================================
-- DATOS SEMILLA (las 4 preguntas que ya estaban en la portada; edítalas/bórralas)
-- ============================================================================

INSERT IGNORE INTO faqs (id, question, answer, sort_order, is_active) VALUES
  (1, '¿Cómo tramitar un acta de defunción en Maracaibo?',
   'El acta de defunción debe tramitarse ante el Registro Civil correspondiente al municipio en el Estado Zulia donde ocurrió el deceso. Nuestro equipo de asesores se encarga de guiarle paso a paso, requiriendo inicialmente el Certificado Médico de Defunción (EV-14) y las cédulas de identidad.',
   10, 1),
  (2, '¿Cuáles son los requisitos de cremación en el Estado Zulia?',
   'Para realizar una cremación en el Estado Zulia se requieren cuatro documentos: el Certificado Médico de Defunción (Forma EV-14), el acta de defunción emitida por el Registro Civil, el permiso sanitario de cremación de la autoridad competente y la autorización escrita del familiar responsable. En Funeraria del Zulia coordinamos la totalidad de la documentación y el proceso en nuestras instalaciones crematorias de Maracaibo, con acompañamiento las 24 horas.',
   20, 1),
  (5, '¿Atienden las 24 horas en Maracaibo y todo el Zulia?',
   'Sí. Funeraria del Zulia ofrece atención inmediata las 24 horas, los 7 días de la semana, en Maracaibo, San Francisco, Cabimas, Ciudad Ojeda y todo el Estado Zulia. Puede llamarnos al +58 424 695-0136 en cualquier momento.',
   25, 1),
  (3, '¿Qué incluye la previsión funeraria familiar?',
   'Los planes de previsión funeraria le permiten organizar anticipadamente los servicios. Incluyen el cofre (ataúd), traslados locales en Zulia, servicio de capilla velatoria, trámites legales y, si lo desea, la opción de cremación. Esto garantiza tranquilidad financiera y emocional para toda la familia.',
   30, 1),
  (4, '¿Es posible realizar cremación si el deceso ocurrió fuera de Maracaibo?',
   'Sí, ofrecemos servicios de traslado desde cualquier municipio del Estado Zulia (San Francisco, Cabimas, Ciudad Ojeda, etc.) u otras regiones del país hacia nuestras instalaciones crematorias centrales en Maracaibo, cumpliendo estrictamente con todas las normativas sanitarias y de sanidad vigentes.',
   40, 1);

-- ============================================================================
-- FIN
-- ============================================================================
