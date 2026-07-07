-- ============================================================================
--  MÓDULO DE PREVISIÓN — Ronda 2 (v3)
--  Funeraria del Zulia · Ajuste masivo de tarifas, notificaciones
--  WhatsApp/SMS con proveedor configurable y reportería.
--
--  Requiere: 01_schema.sql (users, app_settings), 04_prevision.sql y
--  05_prevision_v2.sql importados previamente.
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- 1. AJUSTES MASIVOS DE TARIFAS
--    Cada corrida queda registrada con el detalle valor anterior → nuevo de
--    cada contrato/plan/cuota tocado, lo que permite revertirla por completo.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prev_ajustes (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    descripcion     VARCHAR(255) NOT NULL,
    tipo            ENUM('porcentaje','monto') NOT NULL DEFAULT 'porcentaje',
    valor           DECIMAL(12,4) NOT NULL,               -- % (p.ej. 10 = +10%) o monto delta; admite negativos
    redondeo        ENUM('centimos','entero') NOT NULL DEFAULT 'centimos',
    plan_id         INT UNSIGNED NULL,                    -- NULL = todos los planes
    moneda          ENUM('BS','USD') NULL,                -- NULL = ambas monedas
    aplicar_planes  TINYINT(1) NOT NULL DEFAULT 0,        -- actualizar cuota_mensual del plan
    aplicar_cuotas  TINYINT(1) NOT NULL DEFAULT 0,        -- actualizar cuotas pendientes futuras
    afectados       INT UNSIGNED NOT NULL DEFAULT 0,
    estado          ENUM('aplicado','revertido') NOT NULL DEFAULT 'aplicado',
    usuario_id      INT UNSIGNED NULL,
    revertido_por   INT UNSIGNED NULL,
    revertido_en    DATETIME NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ajuste_estado (estado),
    CONSTRAINT fk_ajuste_plan     FOREIGN KEY (plan_id)       REFERENCES prev_planes (id) ON DELETE SET NULL,
    CONSTRAINT fk_ajuste_usuario  FOREIGN KEY (usuario_id)    REFERENCES users (id)       ON DELETE SET NULL,
    CONSTRAINT fk_ajuste_revierte FOREIGN KEY (revertido_por) REFERENCES users (id)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prev_ajuste_detalles (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ajuste_id      INT UNSIGNED NOT NULL,
    objeto         ENUM('contrato','plan','cuota') NOT NULL,
    objeto_id      INT UNSIGNED NOT NULL,                 -- id del contrato/plan/cuota
    valor_anterior DECIMAL(12,2) NOT NULL,
    valor_nuevo    DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_ajd_ajuste (ajuste_id),
    KEY idx_ajd_objeto (objeto, objeto_id),
    CONSTRAINT fk_ajd_ajuste FOREIGN KEY (ajuste_id) REFERENCES prev_ajustes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. MENSAJERÍA (WhatsApp / SMS)
--    Plantillas con variables {{...}} y bitácora de envíos. El proveedor se
--    configura por canal en app_settings (manual, whatsapp_cloud, twilio, http).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prev_msg_plantillas (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    clave      VARCHAR(40)  NOT NULL,
    nombre     VARCHAR(120) NOT NULL,
    canal      ENUM('whatsapp','sms') NOT NULL DEFAULT 'whatsapp',
    cuerpo     TEXT NOT NULL,                             -- admite {{cliente}}, {{contrato}}, {{monto}}, ...
    activo     TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_msg_clave (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prev_msg_envios (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contrato_id  INT UNSIGNED NULL,
    cliente_id   INT UNSIGNED NULL,
    canal        ENUM('whatsapp','sms') NOT NULL,
    destinatario VARCHAR(30) NOT NULL,                    -- teléfono normalizado (58412...)
    plantilla_id INT UNSIGNED NULL,
    cuerpo       TEXT NOT NULL,                           -- mensaje ya renderizado
    estado       ENUM('enviado','fallido','manual') NOT NULL DEFAULT 'manual',
    proveedor    VARCHAR(30) NOT NULL DEFAULT 'manual',
    respuesta    TEXT NULL,                               -- respuesta cruda del proveedor (JSON)
    error        VARCHAR(255) NULL,
    usuario_id   INT UNSIGNED NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_msg_contrato (contrato_id),
    KEY idx_msg_estado (estado, created_at),
    CONSTRAINT fk_msg_contrato  FOREIGN KEY (contrato_id)  REFERENCES prev_contratos (id)      ON DELETE SET NULL,
    CONSTRAINT fk_msg_cliente   FOREIGN KEY (cliente_id)   REFERENCES prev_clientes (id)       ON DELETE SET NULL,
    CONSTRAINT fk_msg_plantilla FOREIGN KEY (plantilla_id) REFERENCES prev_msg_plantillas (id) ON DELETE SET NULL,
    CONSTRAINT fk_msg_usuario   FOREIGN KEY (usuario_id)   REFERENCES users (id)               ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Plantillas iniciales (editables desde el panel)
INSERT IGNORE INTO prev_msg_plantillas (clave, nombre, canal, cuerpo) VALUES
('recordatorio_cuota', 'Recordatorio de cuota', 'whatsapp',
 'Estimado(a) {{cliente}}, le recordamos que su cuota de {{monto_cuota}} del contrato {{contrato}} ({{plan}}) está próxima a vencer. Puede pagar por transferencia, pago móvil o en nuestras oficinas. ¡Gracias por confiar en {{empresa}}!'),
('cuota_vencida', 'Aviso de cuotas vencidas', 'whatsapp',
 'Estimado(a) {{cliente}}, su contrato {{contrato}} presenta {{cuotas_vencidas}} cuota(s) vencida(s) por {{saldo_vencido}}. Regularice su pago para mantener activa la cobertura de su plan {{plan}}. {{empresa}}.'),
('promesa_pago', 'Recordatorio de promesa de pago', 'whatsapp',
 'Estimado(a) {{cliente}}, le recordamos su compromiso de pago del contrato {{contrato}}. Cualquier duda estamos a su orden. {{empresa}}.'),
('bienvenida', 'Bienvenida a nuevo contrato', 'whatsapp',
 '¡Bienvenido(a) {{cliente}}! Su contrato de previsión {{contrato}} ({{plan}}) fue registrado con éxito. Su cuota es de {{monto_cuota}}. Gracias por confiar en {{empresa}}.'),
('pago_recibido', 'Confirmación de pago', 'whatsapp',
 'Estimado(a) {{cliente}}, hemos recibido su pago del contrato {{contrato}}. ¡Gracias por mantenerse al día! {{empresa}}.'),
('sms_cuota_vencida', 'SMS: cuotas vencidas', 'sms',
 '{{empresa}}: Sr(a) {{cliente}}, su contrato {{contrato}} tiene {{cuotas_vencidas}} cuota(s) vencida(s) por {{saldo_vencido}}. Regularice su pago.'),
('sms_recordatorio', 'SMS: recordatorio de cuota', 'sms',
 '{{empresa}}: Sr(a) {{cliente}}, recuerde el pago de su cuota de {{monto_cuota}} del contrato {{contrato}}.');

-- ----------------------------------------------------------------------------
-- 3. CONFIGURACIÓN DEL PROVEEDOR DE MENSAJERÍA (app_settings)
--    'manual'         = solo registra el envío (WhatsApp abre wa.me para enviar a mano)
--    'whatsapp_cloud' = API oficial de Meta (WhatsApp Cloud API)
--    'twilio'         = Twilio (WhatsApp y/o SMS)
--    'http'           = API HTTP genérica (proveedor local de SMS u otro gateway)
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES
('prev_msg_proveedor_whatsapp', 'manual'),
('prev_msg_proveedor_sms',      'manual'),
('prev_msg_empresa',            'Funeraria del Zulia'),
('prev_msg_pais',               '58'),
-- WhatsApp Cloud API (Meta)
('prev_msg_wa_token',    ''),
('prev_msg_wa_phone_id', ''),
-- Twilio
('prev_msg_twilio_sid',      ''),
('prev_msg_twilio_token',    ''),
('prev_msg_twilio_from_sms', ''),
('prev_msg_twilio_from_wa',  ''),
-- API HTTP genérica (gateway local): URL con {to} y {message}, o POST JSON
('prev_msg_http_url',    ''),
('prev_msg_http_metodo', 'POST'),
('prev_msg_http_token',  '');
