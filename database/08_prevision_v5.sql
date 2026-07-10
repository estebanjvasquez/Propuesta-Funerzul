-- ============================================================================
--  MÓDULO DE PREVISIÓN — Ronda 2.2 (v5)
--  Funeraria del Zulia · Cuotas en bolívares ancladas a la tasa de cambio.
--
--  Objetivo:
--   - Los contratos en Bs guardan una REFERENCIA en USD por cuota
--     (monto_ref_usd) y la TASA con la que se calculó el monto en Bs
--     (tasa_cambio). Así, cuando la tasa cambie, las cuotas pendientes en Bs
--     pueden recalcularse SIN perder el valor real del plan.
--   - El histórico de tasas ya existe en `prev_tasas` (fecha + tasa); esta
--     migración no lo toca.
--   - La actualización de las cuotas NO es automática: se aplica desde el panel
--     por decisión del usuario (botón "Actualizar cuotas en Bs").
--
--  Requiere: 04_prevision.sql importado previamente.
--  Idempotente en la práctica: si ya corrió, las columnas ya existirán
--  (MySQL avisará "Duplicate column"; puede ignorarse esa línea).
-- ============================================================================

SET NAMES utf8mb4;

-- Referencia en USD y tasa aplicada por cuota (para contratos en Bs).
ALTER TABLE prev_contratos
    ADD COLUMN monto_ref_usd DECIMAL(19,2) NULL AFTER monto_cuota,
    ADD COLUMN tasa_cambio   DECIMAL(19,4) NULL AFTER monto_ref_usd;

-- Contratos en USD: su referencia en USD es el propio monto de la cuota.
UPDATE prev_contratos
   SET monto_ref_usd = monto_cuota
 WHERE moneda = 'USD' AND monto_ref_usd IS NULL;

-- Los contratos en Bs ya existentes quedan con monto_ref_usd NULL: no se tocan
-- hasta que el usuario los actualice manualmente (no conocemos su tasa original).
