-- 22_obituarios_nacimiento_y_servicios.sql — Obituarios (2026-11-03):
--   1) fecha exacta de nacimiento;
--   2) tipos de servicio nuevos: Inhumación y Servicio Directo.
--
-- El panel solo pedía el año (birth_year). Desde ahora pide la fecha completa,
-- que se guarda en obituaries.birth_date. Esa columna ya venía en
-- 01_schema.sql; esta migración solo la crea si una base vieja no la tuviera.
-- birth_year se conserva: se sigue llenando con el año de la fecha y es lo que
-- se muestra en los obituarios viejos que solo tienen el año. Idempotente.

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'obituaries' AND COLUMN_NAME = 'birth_date');
SET @sql := IF(@col = 0,
  'ALTER TABLE obituaries ADD COLUMN birth_date DATE NULL AFTER birth_year',
  'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 2) Tipos de servicio. El panel ofrece ahora: Acto Velatorio, Cremación,
-- Inhumación, Homenaje Póstumo y Servicio Directo. 'Traslado', 'Otro' y
-- 'Velación' se quedan en la lista de valores permitidos para no romper los
-- obituarios viejos que los tengan (quitarlos los dejaría en blanco).
-- **Importar ANTES de usar el panel nuevo**: sin esto, guardar un obituario con
-- Inhumación o Servicio Directo da error. Idempotente.
ALTER TABLE obituaries
  MODIFY service_type ENUM('Acto Velatorio','Cremación','Inhumación','Homenaje Póstumo','Servicio Directo','Traslado','Otro','Velación')
  NOT NULL DEFAULT 'Acto Velatorio';
