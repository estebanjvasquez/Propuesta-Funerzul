-- ============================================================================
--  MÓDULO DE PREVISIÓN — Ronda 2.3 (v6)
--  Funeraria del Zulia · Paridad con el flujo SIEMPRE/KM de creación de contratos:
--   - fecha de corte para el cálculo de comisiones (por contrato)
--   - jerarquía comercial del vendedor (cargo + supervisor) y Zelle para pagos
--   - estado civil del beneficiario
--   - adjuntos por contrato (documentos escaneados)
--   - descuentos de comisión por anulación de contratos ya comisionados
--
--  Requiere: 04_prevision.sql importado previamente.
--  Idempotente en la práctica: si ya corrió, las columnas ya existirán
--  (MySQL avisará "Duplicate column"; puede ignorarse esa línea).
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- 1. CONTRATOS: fecha de corte (base de las etapas de comisión; NULL = ingreso)
-- ----------------------------------------------------------------------------
ALTER TABLE prev_contratos
    ADD COLUMN fecha_corte DATE NULL AFTER fecha_ingreso;

-- ----------------------------------------------------------------------------
-- 2. VENDEDORES: jerarquía comercial y Zelle
--    (banco / numero_cuenta / titular_cuenta ya existen desde el 04)
-- ----------------------------------------------------------------------------
ALTER TABLE prev_vendedores
    ADD COLUMN cargo ENUM('vendedor','coordinador','gerente') NOT NULL DEFAULT 'vendedor' AFTER nombre,
    ADD COLUMN supervisor_id BIGINT UNSIGNED NULL AFTER cargo,
    ADD COLUMN zelle VARCHAR(120) NULL AFTER cedula_cuenta;

ALTER TABLE prev_vendedores
    ADD CONSTRAINT fk_prevvend_supervisor FOREIGN KEY (supervisor_id)
        REFERENCES prev_vendedores(id) ON DELETE SET NULL;

-- ----------------------------------------------------------------------------
-- 3. BENEFICIARIOS: estado civil (opcional, como en SIEMPRE)
-- ----------------------------------------------------------------------------
ALTER TABLE prev_beneficiarios
    ADD COLUMN estado_civil VARCHAR(30) NULL AFTER sexo;

-- ----------------------------------------------------------------------------
-- 4. ADJUNTOS POR CONTRATO (equivale a "Adjuntar archivos" de SIEMPRE)
--    El archivo vive en disco (uploads/prevision/{contrato_id}/); aquí solo
--    la referencia.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prev_adjuntos (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  contrato_id    BIGINT UNSIGNED NOT NULL,
  nombre_archivo VARCHAR(190) NOT NULL,                  -- nombre original
  ruta           VARCHAR(255) NOT NULL,                  -- ruta relativa en uploads/
  mime           VARCHAR(100) NOT NULL,
  tamano         INT UNSIGNED NOT NULL DEFAULT 0,        -- bytes
  subido_por     BIGINT UNSIGNED NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_prevadj_contrato (contrato_id),
  CONSTRAINT fk_prevadj_contrato FOREIGN KEY (contrato_id) REFERENCES prev_contratos(id) ON DELETE CASCADE,
  CONSTRAINT fk_prevadj_usuario  FOREIGN KEY (subido_por) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. DESCUENTOS DE COMISIÓN (equivale a "Descuentos Recientes" de la app KM)
--    Al anular un contrato con comisiones ya pagadas, se registra un descuento
--    pendiente que se compensa en el próximo pago de comisión del vendedor.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prev_com_descuentos (
  id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  vendedor_id             BIGINT UNSIGNED NOT NULL,
  contrato_id             BIGINT UNSIGNED NULL,           -- contrato anulado que lo originó
  comision_id             BIGINT UNSIGNED NULL,           -- comisión pagada que se revierte
  monto_usd               DECIMAL(19,2) NOT NULL,
  motivo                  VARCHAR(200) NOT NULL,
  estado                  ENUM('pendiente','aplicado','anulado') NOT NULL DEFAULT 'pendiente',
  aplicado_en_comision_id BIGINT UNSIGNED NULL,           -- comisión donde se descontó
  registrado_por          BIGINT UNSIGNED NULL,
  created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  aplicado_at             DATETIME NULL,
  PRIMARY KEY (id),
  KEY ix_prevcdes_vendedor (vendedor_id, estado),
  CONSTRAINT fk_prevcdes_vendedor FOREIGN KEY (vendedor_id) REFERENCES prev_vendedores(id),
  CONSTRAINT fk_prevcdes_contrato FOREIGN KEY (contrato_id) REFERENCES prev_contratos(id) ON DELETE SET NULL,
  CONSTRAINT fk_prevcdes_usuario  FOREIGN KEY (registrado_por) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. PLANTILLA DE BIENVENIDA enriquecida (con {{cedula}})
--    Descomente el UPDATE si quiere reemplazar el texto de la plantilla actual.
-- ----------------------------------------------------------------------------
-- UPDATE prev_msg_plantillas SET cuerpo =
-- '🌟 ¡Tu familia ya está protegida!\n\nSr(a). {{cliente}}, titular de la C.I. N° {{cedula}}, le damos la más cordial bienvenida a {{empresa}}.\n\nSu contrato de previsión {{contrato}} ({{plan}}) fue registrado con éxito y su cuota es de {{monto_cuota}}.\n\nEstamos disponibles 24/7 para atenderle. ¡Gracias por confiar en nosotros!'
-- WHERE clave = 'bienvenida';
