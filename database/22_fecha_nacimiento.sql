-- 22_fecha_nacimiento.sql — Fecha exacta de nacimiento en obituarios (2026-11-03).
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
