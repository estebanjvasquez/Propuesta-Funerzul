-- ============================================================================
--  FUNERARIA DEL ZULIA — Módulo de Previsión, Ronda 2 (v2)
--  Fase A: sucursales y servicios adicionales
--  Fase B: siniestros / reclamos (equivale a cm_siniestros + cmsiniestrosdetalles)
--  Fase C: cobranza avanzada (gestiones de mora, cobradores y rutas; el
--          auto-lapsado se configura en app_settings y corre por cron)
--
--  Importar DESPUÉS de database/04_prevision.sql.
--  Motor: InnoDB, charset utf8mb4. Control de acceso y auditoría: en PHP.
-- ============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

-- ----------------------------------------------------------------------------
-- 1. SUCURSALES / OFICINAS  (equivale a `cmsucursal` / `oficina` de SIEMPRE)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prev_sucursales (
  id         SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre     VARCHAR(100) NOT NULL,
  direccion  VARCHAR(255) NULL,
  telefono   VARCHAR(20) NULL,
  activo     TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO prev_sucursales (id, nombre) VALUES (1, 'Principal');

-- ----------------------------------------------------------------------------
-- 2. SERVICIOS / CARGOS ADICIONALES  (equivale a `articulos`/`adicionaloption`)
--    Add-ons contratables: bóveda, cremación, traslado, etc.
--    recurrente = 1 -> se suma a cada cuota; 0 -> cargo único.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prev_servicios (
  id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre      VARCHAR(100) NOT NULL,
  descripcion VARCHAR(255) NULL,
  moneda      ENUM('BS','USD') NOT NULL DEFAULT 'USD',
  precio      DECIMAL(19,2) NOT NULL DEFAULT 0,
  recurrente  TINYINT(1) NOT NULL DEFAULT 0,
  activo      TINYINT(1) NOT NULL DEFAULT 1,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prev_contrato_servicios (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  contrato_id BIGINT UNSIGNED NOT NULL,
  servicio_id SMALLINT UNSIGNED NOT NULL,
  moneda      ENUM('BS','USD') NOT NULL DEFAULT 'USD',
  precio      DECIMAL(19,2) NOT NULL DEFAULT 0,   -- precio pactado (puede diferir del catálogo)
  recurrente  TINYINT(1) NOT NULL DEFAULT 0,
  fecha       DATE NULL,                          -- fecha de contratación del servicio
  notas       VARCHAR(255) NULL,
  activo      TINYINT(1) NOT NULL DEFAULT 1,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_prevcs_contrato (contrato_id, activo),
  CONSTRAINT fk_prevcs_contrato FOREIGN KEY (contrato_id) REFERENCES prev_contratos(id) ON DELETE CASCADE,
  CONSTRAINT fk_prevcs_servicio FOREIGN KEY (servicio_id) REFERENCES prev_servicios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. COBRADORES Y RUTAS DE COBRANZA  (equivale a `rutassystem` de SIEMPRE)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prev_cobradores (
  id         SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cedula     VARCHAR(15) NULL,
  nombre     VARCHAR(100) NOT NULL,
  telefono   VARCHAR(20) NULL,
  activo     TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prev_rutas (
  id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre      VARCHAR(100) NOT NULL,
  zona        VARCHAR(150) NULL,                  -- sector/parroquia que cubre
  dia_cobro   VARCHAR(20) NULL,                   -- p.ej. "lunes", "1 y 15"
  cobrador_id SMALLINT UNSIGNED NULL,
  activo      TINYINT(1) NOT NULL DEFAULT 1,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_prevruta_cobrador (cobrador_id),
  CONSTRAINT fk_prevruta_cobrador FOREIGN KEY (cobrador_id) REFERENCES prev_cobradores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. SINIESTROS / RECLAMOS  (equivale a `cm_siniestros` + `cmsiniestrosdetalles`)
--    Expediente por fallecimiento de un beneficiario (incluido el titular).
--    `validacion` guarda el resultado de los chequeos de cobertura al momento
--    del registro (JSON), para dejar constancia aunque el contrato cambie.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prev_siniestros (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  contrato_id      BIGINT UNSIGNED NOT NULL,
  beneficiario_id  BIGINT UNSIGNED NOT NULL,
  cedula_fallecido VARCHAR(15) NULL,
  nombre_fallecido VARCHAR(150) NOT NULL,
  es_titular       TINYINT(1) NOT NULL DEFAULT 0,
  fecha_defuncion  DATE NOT NULL,
  fecha_reporte    DATETIME NOT NULL,
  reportado_por    VARCHAR(100) NULL,
  telefono_reporta VARCHAR(20) NULL,
  cobertura        ENUM('cubierto','con_observaciones','sin_cobertura') NOT NULL DEFAULT 'con_observaciones',
  validacion       TEXT NULL,                     -- JSON [{check, ok, detalle}, ...]
  estado           ENUM('abierto','liquidado','cerrado','rechazado') NOT NULL DEFAULT 'abierto',
  motivo_rechazo   VARCHAR(255) NULL,
  moneda           ENUM('BS','USD') NOT NULL DEFAULT 'USD',
  monto_total      DECIMAL(19,2) NOT NULL DEFAULT 0,   -- suma de los detalles
  observaciones    VARCHAR(500) NULL,
  created_by       BIGINT UNSIGNED NULL,
  updated_by       BIGINT UNSIGNED NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_prevsin_contrato (contrato_id),
  KEY ix_prevsin_estado (estado, fecha_defuncion),
  CONSTRAINT fk_prevsin_contrato     FOREIGN KEY (contrato_id)     REFERENCES prev_contratos(id),
  CONSTRAINT fk_prevsin_beneficiario FOREIGN KEY (beneficiario_id) REFERENCES prev_beneficiarios(id),
  CONSTRAINT fk_prevsin_creator      FOREIGN KEY (created_by)      REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_prevsin_updater      FOREIGN KEY (updated_by)      REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prev_siniestro_detalles (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  siniestro_id BIGINT UNSIGNED NOT NULL,
  tipo         ENUM('servicio','pago','reintegro','otro') NOT NULL DEFAULT 'servicio',
  descripcion  VARCHAR(200) NOT NULL,
  proveedor    VARCHAR(150) NULL,                 -- proveedor externo si aplica
  moneda       ENUM('BS','USD') NOT NULL DEFAULT 'USD',
  monto        DECIMAL(19,2) NOT NULL DEFAULT 0,
  pagado       TINYINT(1) NOT NULL DEFAULT 0,
  fecha_pago   DATE NULL,
  notas        VARCHAR(255) NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_prevsind_siniestro (siniestro_id),
  CONSTRAINT fk_prevsind_siniestro FOREIGN KEY (siniestro_id) REFERENCES prev_siniestros(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. GESTIONES DE COBRANZA (bitácora de contacto por mora / promesas de pago)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prev_gestiones (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  contrato_id   BIGINT UNSIGNED NOT NULL,
  fecha         DATETIME NOT NULL,
  tipo          ENUM('llamada','visita','whatsapp','sms','email','otro') NOT NULL DEFAULT 'llamada',
  resultado     ENUM('contactado','no_contactado','promesa_pago','reclamo','otro') NOT NULL DEFAULT 'contactado',
  promesa_fecha DATE NULL,
  promesa_monto DECIMAL(19,2) NULL,
  notas         VARCHAR(500) NULL,
  usuario_id    BIGINT UNSIGNED NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_prevges_contrato (contrato_id, fecha),
  KEY ix_prevges_promesa (resultado, promesa_fecha),
  CONSTRAINT fk_prevges_contrato FOREIGN KEY (contrato_id) REFERENCES prev_contratos(id) ON DELETE CASCADE,
  CONSTRAINT fk_prevges_usuario  FOREIGN KEY (usuario_id)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. AMPLIACIONES A TABLAS EXISTENTES
--    - prev_contratos: sucursal, ruta de cobro y estatus 'finalizado'
--      (contrato cumplido tras el fallecimiento del titular).
--    - prev_vendedores: sucursal.
-- ----------------------------------------------------------------------------
ALTER TABLE prev_contratos
  ADD COLUMN sucursal_id SMALLINT UNSIGNED NULL AFTER vendedor_id,
  ADD COLUMN ruta_id     SMALLINT UNSIGNED NULL AFTER sucursal_id,
  ADD KEY ix_prevcon_sucursal (sucursal_id),
  ADD KEY ix_prevcon_ruta (ruta_id),
  ADD CONSTRAINT fk_prevcon_sucursal FOREIGN KEY (sucursal_id) REFERENCES prev_sucursales(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_prevcon_ruta     FOREIGN KEY (ruta_id)     REFERENCES prev_rutas(id) ON DELETE SET NULL;

ALTER TABLE prev_contratos
  MODIFY COLUMN estatus ENUM('activo','suspendido','anulado','renuncia','finalizado') NOT NULL DEFAULT 'activo';

ALTER TABLE prev_vendedores
  ADD COLUMN sucursal_id SMALLINT UNSIGNED NULL AFTER direccion,
  ADD KEY ix_prevvend_sucursal (sucursal_id),
  ADD CONSTRAINT fk_prevvend_sucursal FOREIGN KEY (sucursal_id) REFERENCES prev_sucursales(id) ON DELETE SET NULL;

SET foreign_key_checks = 1;

-- ----------------------------------------------------------------------------
-- 7. CONFIGURACIÓN DEL AUTO-LAPSADO (en app_settings; editable desde el panel)
--    prev_lapse_enabled: 1/0 · prev_lapse_cuotas: nº de cuotas vencidas para
--    suspender automáticamente el contrato.
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO app_settings (setting_key, setting_value, description) VALUES
  ('prev_lapse_enabled', '0', 'Previsión: suspensión automática de contratos morosos (1=activada)'),
  ('prev_lapse_cuotas',  '3', 'Previsión: nº de cuotas vencidas para suspender automáticamente');

-- ============================================================================
-- FIN — Tras importar, aparecen las sub-pestañas Siniestros, Cobranza y
-- Catálogos en la pestaña Previsión del panel.
-- ============================================================================
