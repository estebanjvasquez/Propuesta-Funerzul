# Modelo De Datos Y Contratos

Este documento resume el modelo de datos para orientar agentes. No reemplaza
`database/README.md` ni las migraciones SQL.

## Dominios Principales

- Usuarios: `users`, roles `admin` y `editor`.
- Obituarios: `obituaries`, `obituary_templates`, `condolences`,
  `flower_offerings`.
- Configuracion y auditoria: `app_settings`, `audit_log`.
- Directorio y contenidos: tablas agregadas por `02_directorio_recursos.sql` y
  `03_faqs.sql`.
- ~~Prevision heredada: tablas `prev_*`~~ — retirado el 2026-08-28, ver
  `docs/specs/2026-08-28-fase-e-corte-admin-prevision.md`. Ya no existen en
  este repo (`04_prevision.sql` a `10_prevision_pagos_electronicos.sql`
  solo viven en la rama `archive/modulo-prevision-php`).
- Pagos electronicos: `api/lib/payments/` se conserva como referencia de
  diseno (sin tablas propias activas en este repo); ver
  `docs/payments/mercantil/`.

## Reglas De Actualizacion

- Toda nueva tabla o columna debe aparecer en una migracion incremental.
- Toda migracion debe indicar requisitos previos si depende de otra.
- Si un endpoint empieza a depender de una columna nueva, documentarlo en
  `api/README.md`.
- Si cambia una regla de negocio, documentarla tambien en el README funcional o en la
  especificacion del cambio.

## Campos Sensibles

Tratar con cuidado:

- Credenciales de usuarios.
- Datos personales de clientes, beneficiarios, fallecidos y condolientes.
- Cedulas, telefonos, correos, direcciones y datos de pago.
- Tokens, secretos, claves Mercantil y configuracion de proveedores externos.

## Pendientes Que Conviene Completar

Cuando el proyecto evolucione, mantener aqui:

- Diagrama simple de entidades principales.
- Mapa de endpoints a tablas.
- Reglas de retencion de fotos y adjuntos.
- Estados validos de contratos, cuotas, comisiones, siniestros y pagos.
- Relacion exacta entre este repo y `Prevision-Funeraria`.

