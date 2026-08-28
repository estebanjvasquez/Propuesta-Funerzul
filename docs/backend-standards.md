# Estandares Backend

Aplica a `api/*.php`, `api/lib/**`, `api/cron/**`, `database/*.sql` y cualquier
integracion servidor-servidor.

## PHP Y API

- PHP objetivo: 8.1+; el entorno actual esta probado en PHP 8.4.
- Mantener endpoints simples por archivo, como el patron existente
  `api/<dominio>.php?action=...`.
- Responder JSON con `{ ok: true|false, ... }`.
- Para errores de usuario, devolver mensajes claros y controlados.
- Para errores internos, registrar lo necesario sin filtrar credenciales ni trazas al
  publico.
- Usar `require_once` de librerias existentes en `api/lib/`.
- No acceder directamente a `api/lib/` desde web; respetar protecciones `.htaccess`.

## Seguridad

- Toda mutacion requiere sesion y `X-CSRF-Token`, salvo endpoints publicos
  explicitamente documentados.
- Verificar rol (`admin`, `editor`, publico) dentro de cada accion.
- Usar `password_hash` y `password_verify` para contrasenas.
- No guardar secretos en git. `api/config.php` es local/servidor.
- Validar tipos, rangos y enums antes de escribir en base de datos.
- Sanitizar nombres de archivo y no permitir ejecucion en uploads.

## Base De Datos

- Usar PDO y consultas preparadas.
- Para cambios de esquema, agregar migracion incremental numerada en `database/`.
- No editar migraciones antiguas que ya puedan estar aplicadas en produccion, salvo
  correcciones documentadas y seguras.
- Documentar nuevas tablas/columnas en `database/README.md` y `docs/data-model.md`.
- Mantener InnoDB y `utf8mb4`.

## Pagos Mercantil

- `api/lib/payments/` se conserva solo como referencia de diseno (los
  endpoints que la usaban, `prevision_mercantil_*.php`, se retiraron el
  2026-08-28 junto con el modulo de prevision). Sigue siendo superficie
  critica si se reutiliza.
- Antes de cambios, leer `docs/payments/mercantil/STATUS.md`,
  `PROJECT_DISCOVERY.md` y `MIGRATION_PLAN.md`.
- Mantener idempotencia en callbacks/webhooks.
- Guardar estados y auditoria suficientes para conciliacion.
- No asumir exito de proveedor externo sin confirmacion verificable.

## Pruebas Y Verificacion

Este repo no tiene todavia una suite automatizada completa. Para cambios backend,
documenta al menos:

- Endpoint probado.
- Metodo y payload usado.
- Usuario/rol usado.
- Resultado esperado en JSON.
- Efecto esperado en base de datos.

Cuando sea viable, agregar scripts o casos reproducibles antes de modificar flujos
criticos.

