-- ============================================================================
--  MÓDULO DE PREVISIÓN — Pagos electrónicos (v7)
--  Funeraria del Zulia · Capa de pagos electrónicos desacoplada del banco:
--   - intentos de cobro electrónico (hoy solo proveedor "simulado"; Mercantil
--     Banco queda como adaptador pendiente, ver docs/mercantil.md)
--   - bitácora de eventos/webhooks (sanitizada, sin secretos ni datos sensibles)
--   - solicitudes públicas (leads de planes.php/servicios.php con pago
--     electrónico): NO son un contrato, solo una intención a revisar por staff.
--
--  Requiere: 04_prevision.sql y 05_prevision_v2.sql importados previamente.
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- 1. SOLICITUDES PÚBLICAS (leads del sitio público, plan o servicio de interés)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prev_solicitudes_publicas (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo           ENUM('plan','servicio') NOT NULL DEFAULT 'plan',
  plan_id        BIGINT UNSIGNED NULL,                 -- si tipo='plan' y coincide con el catálogo
  interes        VARCHAR(150) NOT NULL,                -- nombre del plan/servicio mostrado en la página
  nombres        VARCHAR(100) NOT NULL,
  apellidos      VARCHAR(100) NOT NULL,
  cedula         VARCHAR(20) NULL,
  telefono       VARCHAR(30) NOT NULL,
  email          VARCHAR(150) NULL,
  metodo_pago    ENUM('boton_web','c2p','otro') NOT NULL DEFAULT 'boton_web',
  estado         ENUM('nueva','contactada','convertida','descartada') NOT NULL DEFAULT 'nueva',
  contrato_id    BIGINT UNSIGNED NULL,                 -- se completa al convertir la solicitud
  notas          VARCHAR(500) NULL,
  ip             VARCHAR(45) NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_prevsol_estado (estado, created_at),
  CONSTRAINT fk_prevsol_plan     FOREIGN KEY (plan_id)     REFERENCES prev_planes(id)     ON DELETE SET NULL,
  CONSTRAINT fk_prevsol_contrato FOREIGN KEY (contrato_id) REFERENCES prev_contratos(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. INTENTOS DE COBRO ELECTRÓNICO
--    Máquina de estados (ver docs/mercantil.md §17): un intento nace CREATED y
--    solo avanza mediante el proveedor (PaymentService); nunca se marca
--    APPROVED directamente desde un endpoint sin pasar por él.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prev_pagos_electronicos (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  contrato_id         BIGINT UNSIGNED NULL,
  cuota_id            BIGINT UNSIGNED NULL,
  solicitud_id        BIGINT UNSIGNED NULL,
  provider            VARCHAR(30) NOT NULL DEFAULT 'simulado',   -- 'simulado' | 'mercantil'
  metodo              ENUM('boton_web','c2p','tarjeta','otro') NOT NULL DEFAULT 'boton_web',
  moneda              ENUM('BS','USD') NOT NULL DEFAULT 'USD',
  monto               DECIMAL(19,2) NOT NULL,
  estado              ENUM('CREATED','PENDING','REQUIRES_CUSTOMER_ACTION','PROCESSING',
                            'APPROVED','DECLINED','FAILED','EXPIRED','CANCELLED',
                            'REVERSED','REFUND_PENDING','REFUNDED') NOT NULL DEFAULT 'CREATED',
  external_payment_id VARCHAR(150) NULL,
  bank_reference      VARCHAR(150) NULL,
  idempotency_key     VARCHAR(150) NOT NULL,
  pago_id             BIGINT UNSIGNED NULL,              -- prev_pagos generado al conciliar en APPROVED
  motivo              VARCHAR(255) NULL,                 -- razón de rechazo/expiración, si aplica
  registrado_por      BIGINT UNSIGNED NULL,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  expires_at          DATETIME NULL,
  approved_at         DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_prevpe_idempotency (idempotency_key),
  KEY ix_prevpe_contrato (contrato_id, estado),
  KEY ix_prevpe_solicitud (solicitud_id),
  CONSTRAINT fk_prevpe_contrato   FOREIGN KEY (contrato_id)  REFERENCES prev_contratos(id)            ON DELETE SET NULL,
  CONSTRAINT fk_prevpe_cuota      FOREIGN KEY (cuota_id)     REFERENCES prev_cuotas(id)                ON DELETE SET NULL,
  CONSTRAINT fk_prevpe_solicitud  FOREIGN KEY (solicitud_id) REFERENCES prev_solicitudes_publicas(id)  ON DELETE SET NULL,
  CONSTRAINT fk_prevpe_pago       FOREIGN KEY (pago_id)      REFERENCES prev_pagos(id)                 ON DELETE SET NULL,
  CONSTRAINT fk_prevpe_usuario    FOREIGN KEY (registrado_por) REFERENCES users(id)                    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La columna y su FK se agregan aquí (después de crear prev_pagos_electronicos)
-- porque la referencia es circular entre ambas tablas.
ALTER TABLE prev_solicitudes_publicas
    ADD COLUMN pago_electronico_id BIGINT UNSIGNED NULL AFTER metodo_pago;

ALTER TABLE prev_solicitudes_publicas
    ADD CONSTRAINT fk_prevsol_pago_electronico FOREIGN KEY (pago_electronico_id)
        REFERENCES prev_pagos_electronicos(id) ON DELETE SET NULL;

-- ----------------------------------------------------------------------------
-- 3. EVENTOS DE PAGO (webhooks / consultas de estado, sanitizados)
--    Nunca debe guardarse aquí PAN, CVV, PIN, clave temporal C2P ni secretos.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prev_pago_eventos (
  id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pago_electronico_id   BIGINT UNSIGNED NOT NULL,
  tipo                  VARCHAR(100) NOT NULL,       -- ej. 'estado_consultado', 'webhook', 'conciliacion'
  payload_hash          VARCHAR(128) NULL,
  sanitized_payload     JSON NULL,
  received_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at          DATETIME NULL,
  PRIMARY KEY (id),
  KEY ix_prevpgev_pago (pago_electronico_id, received_at),
  CONSTRAINT fk_prevpgev_pago FOREIGN KEY (pago_electronico_id)
      REFERENCES prev_pagos_electronicos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
