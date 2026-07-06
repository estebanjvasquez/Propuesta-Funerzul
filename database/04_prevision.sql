-- ============================================================================
--  FUNERARIA DEL ZULIA — Módulo de Previsión (planes de previsión funeraria)
--  Modelado sobre la base de datos del sistema administrativo SIEMPRE
--  (database/SIEMPRE.sql): clientes, contratos, beneficiarios, planes,
--  vendedores y sus comisiones, cuotas por cobrar, pagos e importación.
--
--  Importar en: cPanel -> phpMyAdmin -> [tu base de datos] -> Importar
--  Motor: InnoDB, charset utf8mb4. Control de acceso y auditoría: en PHP.
--  Requiere: 01_schema.sql (tabla users) importado previamente.
-- ============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

-- ----------------------------------------------------------------------------
-- 1. PLANES DE PREVISIÓN  (equivale a `planes` / `planesunidos` de SIEMPRE)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prev_planes (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo              VARCHAR(20) NULL,                  -- código en el sistema de origen
  nombre              VARCHAR(100) NOT NULL,
  descripcion         VARCHAR(255) NULL,
  moneda              ENUM('BS','USD') NOT NULL DEFAULT 'USD',
  cuota_mensual       DECIMAL(19,2) NOT NULL DEFAULT 0,  -- cuota base del plan
  cuota_inicial       DECIMAL(19,2) NOT NULL DEFAULT 0,
  monto_servicio      DECIMAL(19,2) NOT NULL DEFAULT 0,  -- valor de cobertura del servicio
  max_beneficiarios   TINYINT UNSIGNED NOT NULL DEFAULT 0,  -- 0 = sin límite
  solo_nuevo_contrato TINYINT(1) NOT NULL DEFAULT 0,
  es_apoyo            TINYINT(1) NOT NULL DEFAULT 0,     -- plan de apoyo social
  bloqueado           TINYINT(1) NOT NULL DEFAULT 0,     -- no admite nuevos contratos
  activo              TINYINT(1) NOT NULL DEFAULT 1,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_prevplan_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. VENDEDORES  (equivale a `vendedores` + `vendedores_bancos` de SIEMPRE)
--    Los porcentajes de comisión se guardan por vendedor; los pagos de
--    comisión por contrato/etapa van en prev_comisiones.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prev_vendedores (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cedula            VARCHAR(15) NULL,
  nombre            VARCHAR(100) NOT NULL,
  telefono1         VARCHAR(20) NULL,
  telefono2         VARCHAR(20) NULL,
  email             VARCHAR(190) NULL,
  direccion         VARCHAR(255) NULL,
  fecha_ingreso     DATE NULL,
  fecha_retiro      DATE NULL,
  comision_semanal  DECIMAL(5,2) NOT NULL DEFAULT 0,     -- % sobre cobranza semanal
  comision_mensual  DECIMAL(5,2) NOT NULL DEFAULT 0,     -- % sobre cobranza mensual
  comision_anual    DECIMAL(5,2) NOT NULL DEFAULT 0,     -- % sobre cobranza anual
  banco             VARCHAR(100) NULL,                   -- datos de pago de comisiones
  numero_cuenta     VARCHAR(24) NULL,
  titular_cuenta    VARCHAR(100) NULL,
  cedula_cuenta     VARCHAR(15) NULL,
  notas             VARCHAR(500) NULL,
  activo            TINYINT(1) NOT NULL DEFAULT 1,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_prevvend_cedula (cedula),
  KEY ix_prevvend_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. CLIENTES (titulares de contratos)  (equivale a `clientes` de SIEMPRE)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prev_clientes (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo_persona        ENUM('natural','juridica') NOT NULL DEFAULT 'natural',
  nacionalidad        CHAR(1) NOT NULL DEFAULT 'V',      -- V, E, J, P
  cedula              VARCHAR(15) NOT NULL,              -- cédula o RIF (sin nacionalidad)
  nombres             VARCHAR(100) NOT NULL,
  apellidos           VARCHAR(100) NOT NULL DEFAULT '',
  fecha_nacimiento    DATE NULL,
  sexo                ENUM('M','F') NULL,
  estado_civil        VARCHAR(30) NULL,
  telefono_habitacion VARCHAR(20) NULL,
  telefono_celular    VARCHAR(20) NULL,
  telefono_oficina    VARCHAR(20) NULL,
  email               VARCHAR(190) NULL,
  direccion           VARCHAR(255) NULL,
  ciudad              VARCHAR(100) NULL,
  estado              VARCHAR(100) NULL,
  municipio           VARCHAR(100) NULL,
  parroquia           VARCHAR(100) NULL,
  empleador           VARCHAR(200) NULL,
  cargo               VARCHAR(100) NULL,
  profesion           VARCHAR(100) NULL,
  origen              VARCHAR(60) NULL,                  -- origen del cliente (Particulares, Colectivos...)
  info_adicional      VARCHAR(500) NULL,
  created_by          BIGINT UNSIGNED NULL,
  updated_by          BIGINT UNSIGNED NULL,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at          DATETIME NULL,                     -- baja lógica
  PRIMARY KEY (id),
  UNIQUE KEY uq_prevcli_cedula (cedula),
  KEY ix_prevcli_nombre (nombres, apellidos),
  KEY ix_prevcli_deleted (deleted_at),
  CONSTRAINT fk_prevcli_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_prevcli_updater FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. PARENTESCOS (catálogo)  (equivale a `parentesco` de SIEMPRE)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prev_parentescos (
  id                 SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre             VARCHAR(60) NOT NULL,
  familiar_directo   TINYINT(1) NOT NULL DEFAULT 0,
  edad_min           TINYINT UNSIGNED NOT NULL DEFAULT 0,
  edad_max           SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  permite_sin_cedula TINYINT(1) NOT NULL DEFAULT 1,      -- admite beneficiarios no cedulados
  activo             TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. CONTRATOS DE PREVISIÓN  (equivale a `contratos` de SIEMPRE)
--    Estatus según `cmestadoscontrato`: ACTIVO, ANULADO, SUSPENDIDO, RENUNCIA.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prev_contratos (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  numero             VARCHAR(20) NOT NULL,               -- número de contrato
  cliente_id         BIGINT UNSIGNED NOT NULL,
  plan_id            BIGINT UNSIGNED NULL,
  vendedor_id        BIGINT UNSIGNED NULL,
  origen             VARCHAR(60) NULL,
  fecha_solicitud    DATE NULL,
  fecha_ingreso      DATE NOT NULL,
  vigente_desde      DATE NULL,                          -- fin del plazo de espera
  plazo_espera_meses TINYINT UNSIGNED NOT NULL DEFAULT 4,
  frecuencia_pago    ENUM('semanal','quincenal','mensual','trimestral','semestral','anual') NOT NULL DEFAULT 'mensual',
  forma_pago         ENUM('caja','domiciliacion','transferencia','pago_movil','cobrador','otro') NOT NULL DEFAULT 'caja',
  moneda             ENUM('BS','USD') NOT NULL DEFAULT 'USD',
  cuota_inicial      DECIMAL(19,2) NOT NULL DEFAULT 0,
  monto_cuota        DECIMAL(19,2) NOT NULL DEFAULT 0,   -- monto de cada cuota según la frecuencia
  numero_cuotas      INT NOT NULL DEFAULT 0,             -- 0 = indefinido (vitalicio)
  comision_venta     DECIMAL(5,2) NOT NULL DEFAULT 0,    -- % de comisión de venta del contrato
  edad_ingreso       SMALLINT NULL,
  banco              VARCHAR(100) NULL,                  -- datos de domiciliación bancaria
  numero_cuenta      VARCHAR(24) NULL,
  titular_cuenta     VARCHAR(100) NULL,
  tipo_cuenta        ENUM('corriente','ahorro','otra') NULL,
  estatus            ENUM('activo','suspendido','anulado','renuncia') NOT NULL DEFAULT 'activo',
  fecha_estatus      DATE NULL,                          -- fecha del último cambio de estatus
  motivo_estatus     VARCHAR(255) NULL,                  -- motivo de anulación/suspensión/renuncia
  comentarios        VARCHAR(500) NULL,
  created_by         BIGINT UNSIGNED NULL,
  updated_by         BIGINT UNSIGNED NULL,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_prevcon_numero (numero),
  KEY ix_prevcon_cliente (cliente_id),
  KEY ix_prevcon_vendedor (vendedor_id),
  KEY ix_prevcon_plan (plan_id),
  KEY ix_prevcon_estatus (estatus),
  CONSTRAINT fk_prevcon_cliente  FOREIGN KEY (cliente_id)  REFERENCES prev_clientes(id),
  CONSTRAINT fk_prevcon_plan     FOREIGN KEY (plan_id)     REFERENCES prev_planes(id) ON DELETE SET NULL,
  CONSTRAINT fk_prevcon_vendedor FOREIGN KEY (vendedor_id) REFERENCES prev_vendedores(id) ON DELETE SET NULL,
  CONSTRAINT fk_prevcon_creator  FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_prevcon_updater  FOREIGN KEY (updated_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. BENEFICIARIOS DE CONTRATO  (equivale a `cmbeneficiarios` de SIEMPRE)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prev_beneficiarios (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  contrato_id        BIGINT UNSIGNED NOT NULL,
  parentesco_id      SMALLINT UNSIGNED NOT NULL,
  nacionalidad       CHAR(1) NOT NULL DEFAULT 'V',
  cedula             VARCHAR(15) NULL,                   -- NULL = no cedulado (menores)
  nombres            VARCHAR(100) NOT NULL,
  apellidos          VARCHAR(100) NOT NULL DEFAULT '',
  fecha_nacimiento   DATE NULL,
  sexo               ENUM('M','F') NULL,
  cuota_adicional    DECIMAL(19,2) NOT NULL DEFAULT 0,   -- cargo adicional por este beneficiario
  plazo_espera_meses TINYINT UNSIGNED NULL,              -- NULL = hereda el del contrato
  estatus            ENUM('activo','suspendido','excluido','fallecido') NOT NULL DEFAULT 'activo',
  fecha_inclusion    DATE NULL,
  fecha_exclusion    DATE NULL,
  fecha_defuncion    DATE NULL,
  fecha_suspension   DATE NULL,
  comentarios        VARCHAR(500) NULL,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_prevben_contrato (contrato_id, estatus),
  KEY ix_prevben_cedula (cedula),
  CONSTRAINT fk_prevben_contrato   FOREIGN KEY (contrato_id)   REFERENCES prev_contratos(id) ON DELETE CASCADE,
  CONSTRAINT fk_prevben_parentesco FOREIGN KEY (parentesco_id) REFERENCES prev_parentescos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 7. CUOTAS POR COBRAR  (equivale a `cuotascxc` de SIEMPRE)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prev_cuotas (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  contrato_id       BIGINT UNSIGNED NOT NULL,
  numero            INT NOT NULL,                        -- correlativo dentro del contrato
  tipo              ENUM('inicial','programada','especial','mora','final') NOT NULL DEFAULT 'programada',
  fecha_vencimiento DATE NOT NULL,
  moneda            ENUM('BS','USD') NOT NULL DEFAULT 'USD',
  monto             DECIMAL(19,2) NOT NULL,
  saldo             DECIMAL(19,2) NOT NULL,              -- lo que falta por cobrar de la cuota
  estado            ENUM('pendiente','parcial','cobrada','anulada') NOT NULL DEFAULT 'pendiente',
  fecha_cobro       DATE NULL,                           -- fecha en que quedó totalmente cobrada
  generada_el       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_prevcuo_contrato (contrato_id, estado),
  KEY ix_prevcuo_vencimiento (fecha_vencimiento, estado),
  KEY ix_prevcuo_correlativo (contrato_id, numero),
  CONSTRAINT fk_prevcuo_contrato FOREIGN KEY (contrato_id) REFERENCES prev_contratos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 8. PAGOS / ABONOS  (equivale a `abonoscxc` + `caja1` de SIEMPRE)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prev_pagos (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  contrato_id    BIGINT UNSIGNED NOT NULL,
  cuota_id       BIGINT UNSIGNED NULL,                   -- cuota a la que se aplicó (si aplica)
  fecha          DATE NOT NULL,
  recibo         VARCHAR(20) NULL,
  moneda         ENUM('BS','USD') NOT NULL DEFAULT 'USD',
  monto          DECIMAL(19,2) NOT NULL,
  tasa           DECIMAL(19,4) NOT NULL DEFAULT 0,       -- tasa Bs/USD del día del pago
  forma_pago     ENUM('efectivo','transferencia','pago_movil','punto','zelle','divisa','otro') NOT NULL DEFAULT 'efectivo',
  referencia     VARCHAR(60) NULL,
  banco          VARCHAR(100) NULL,
  observaciones  VARCHAR(255) NULL,
  registrado_por BIGINT UNSIGNED NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_prevpag_contrato (contrato_id),
  KEY ix_prevpag_cuota (cuota_id),
  KEY ix_prevpag_fecha (fecha),
  CONSTRAINT fk_prevpag_contrato FOREIGN KEY (contrato_id) REFERENCES prev_contratos(id) ON DELETE CASCADE,
  CONSTRAINT fk_prevpag_cuota    FOREIGN KEY (cuota_id)    REFERENCES prev_cuotas(id) ON DELETE SET NULL,
  CONSTRAINT fk_prevpag_usuario  FOREIGN KEY (registrado_por) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 9. COMISIONES PAGADAS A VENDEDORES  (equivale a `comisiones_pagadas`)
--    Etapas del esquema SIEMPRE: semana1, fin_mes1, mes2, mes13.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prev_comisiones (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  contrato_id    BIGINT UNSIGNED NOT NULL,
  vendedor_id    BIGINT UNSIGNED NOT NULL,
  etapa          ENUM('semana1','fin_mes1','mes2','mes13') NOT NULL,
  monto_bs       DECIMAL(19,2) NOT NULL DEFAULT 0,
  tasa           DECIMAL(19,4) NOT NULL DEFAULT 0,
  monto_usd      DECIMAL(19,2) NOT NULL DEFAULT 0,
  fecha_pago     DATE NOT NULL,
  comentario     VARCHAR(200) NULL,
  registrado_por BIGINT UNSIGNED NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_prevcom_contrato_etapa (contrato_id, etapa),
  KEY ix_prevcom_vendedor (vendedor_id, fecha_pago),
  CONSTRAINT fk_prevcom_contrato FOREIGN KEY (contrato_id) REFERENCES prev_contratos(id) ON DELETE CASCADE,
  CONSTRAINT fk_prevcom_vendedor FOREIGN KEY (vendedor_id) REFERENCES prev_vendedores(id),
  CONSTRAINT fk_prevcom_usuario  FOREIGN KEY (registrado_por) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 10. TASAS DE CAMBIO DIARIAS  (equivale a `tasas_diarias` de SIEMPRE)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prev_tasas (
  fecha          DATE NOT NULL,
  tasa           DECIMAL(19,4) NOT NULL,                 -- Bs por USD
  registrado_por BIGINT UNSIGNED NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (fecha),
  CONSTRAINT fk_prevtasa_usuario FOREIGN KEY (registrado_por) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 11. LOTES DE IMPORTACIÓN  (bitácora de importaciones desde otros sistemas)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prev_import_lotes (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo           VARCHAR(20) NOT NULL,                   -- clientes | vendedores | contratos | beneficiarios | pagos
  nombre_archivo VARCHAR(255) NULL,
  simulacion     TINYINT(1) NOT NULL DEFAULT 0,          -- 1 = solo validación (dry run)
  total_filas    INT NOT NULL DEFAULT 0,
  insertados     INT NOT NULL DEFAULT 0,
  actualizados   INT NOT NULL DEFAULT 0,
  rechazados     INT NOT NULL DEFAULT 0,
  errores        LONGTEXT NULL,                          -- JSON [{fila, error}, ...]
  usuario_id     BIGINT UNSIGNED NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_previmp_tipo (tipo, created_at),
  CONSTRAINT fk_previmp_usuario FOREIGN KEY (usuario_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;

-- ============================================================================
-- DATOS SEMILLA (catálogos tomados del sistema SIEMPRE)
-- ============================================================================

-- Parentescos (tabla `parentesco` de SIEMPRE; se conservan los visibles)
INSERT IGNORE INTO prev_parentescos (id, nombre, familiar_directo, edad_min, edad_max, permite_sin_cedula, activo) VALUES
  (1,  'TITULAR',                     1, 18, 79,  0, 1),
  (2,  'MADRE',                       1, 0,  100, 1, 1),
  (3,  'HIJO (A)',                    1, 0,  100, 1, 1),
  (4,  'HERMANO (A) EXENTO',          1, 0,  100, 1, 1),
  (5,  'HERMANO (A)',                 0, 15, 100, 1, 1),
  (6,  'ESPOSO (A)',                  1, 0,  150, 1, 1),
  (7,  'CONCUBINO (A)',               1, 0,  150, 1, 1),
  (8,  'NIETO (A) EXENTO',            1, 0,  100, 1, 1),
  (9,  'PADRE',                       1, 0,  150, 1, 1),
  (10, 'CUÑADO (A)',                  0, 0,  100, 1, 1),
  (11, 'PRIMO (A)',                   0, 0,  100, 1, 1),
  (12, 'SOBRINO (A) EXENTO',          1, 0,  100, 1, 1),
  (13, 'OTRO (A)',                    0, 0,  100, 1, 1),
  (14, 'TIO (A)',                     0, 0,  100, 1, 1),
  (15, 'SUEGRO (A)',                  0, 0,  100, 1, 1),
  (16, 'ABUELO (A)',                  0, 0,  100, 1, 1),
  (17, 'YERNO (A)',                   0, 0,  100, 1, 1),
  (18, 'HERMANO (A) (DISCAPACITADO)', 0, 0,  100, 1, 1);

-- Planes vigentes (tabla `planes` de SIEMPRE; cuota mensual en USD)
INSERT IGNORE INTO prev_planes (id, codigo, nombre, moneda, cuota_mensual, activo) VALUES
  (1, '5',  'TRADICION NEW',        'USD', 8.00,  1),
  (2, '8',  'TRADICION',            'USD', 12.00, 1),
  (3, '2',  'ESENCIAL',             'USD', 13.70, 1),
  (4, '6',  'ESENCIAL NEW',         'USD', 12.00, 1),
  (5, '3',  'VANGUARDIA',           'USD', 17.00, 1),
  (6, '4',  'VANGUARDIA PLUS',      'USD', 24.00, 1),
  (7, '7',  'VANGUARDIA TOTAL',     'USD', 14.00, 1),
  (8, '9',  'EMPLE ESENCIAL (50%)', 'USD', 7.85,  1),
  (9, '10', 'EMP ESENCIAL (30%)',   'USD', 9.59,  1);

-- ============================================================================
-- FIN — Tras importar, la pestaña "Previsión" del panel queda operativa.
-- ============================================================================
