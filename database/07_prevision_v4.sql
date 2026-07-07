-- ============================================================================
--  MÓDULO DE PREVISIÓN — Ronda 2.1 (v4)
--  Funeraria del Zulia · Flujo de comisiones por estados:
--  calculada → aprobada → pagada (con anulada). Permite generar las
--  comisiones, verificarlas y aprobarlas antes de enviarlas a pagar.
--
--  Requiere: 04_prevision.sql importado previamente.
--  Idempotente en la práctica: si ya corrió, las columnas ya existirán
--  (MySQL avisará "Duplicate column"; puede ignorarse esa línea).
-- ============================================================================

SET NAMES utf8mb4;

-- Nuevas columnas del flujo de aprobación de comisiones.
ALTER TABLE prev_comisiones
    ADD COLUMN estado ENUM('calculada','aprobada','pagada','anulada')
        NOT NULL DEFAULT 'pagada' AFTER etapa,
    ADD COLUMN base_monto      DECIMAL(19,2) NOT NULL DEFAULT 0 AFTER estado,
    ADD COLUMN porcentaje      DECIMAL(8,4)  NOT NULL DEFAULT 0 AFTER base_monto,
    ADD COLUMN monto_calculado DECIMAL(19,2) NOT NULL DEFAULT 0 AFTER porcentaje,
    ADD COLUMN fecha_calculo   DATE NULL AFTER monto_calculado,
    ADD COLUMN aprobado_por    BIGINT UNSIGNED NULL AFTER comentario,
    ADD COLUMN fecha_aprobacion DATETIME NULL AFTER aprobado_por;

-- Las comisiones que ya existían fueron pagos directos: quedan como 'pagada'
-- (el DEFAULT ya las dejó así) y su monto pagado sirve de monto calculado.
UPDATE prev_comisiones
   SET monto_calculado = monto_usd,
       fecha_calculo   = fecha_pago
 WHERE monto_calculado = 0;

-- Una comisión sin pagar aún no tiene fecha de pago.
ALTER TABLE prev_comisiones
    MODIFY COLUMN fecha_pago DATE NULL;

-- FK del aprobador (hacia users).
ALTER TABLE prev_comisiones
    ADD CONSTRAINT fk_prevcom_aprobador FOREIGN KEY (aprobado_por)
        REFERENCES users(id) ON DELETE SET NULL;

-- Índice para listar rápido por estado.
ALTER TABLE prev_comisiones
    ADD INDEX ix_prevcom_estado (estado, vendedor_id);
