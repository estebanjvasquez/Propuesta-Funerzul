# Estado de integración Mercantil

- Estado global: PROJECT_DISCOVERY
- Fecha: 2026-08-06
- Responsable: por asignar
- Producto: Botón de Pagos Web + Búsquedas de Pagos Móviles (planeado); Pago Móvil C2P (planeado)
- Ambiente: sandbox — **desplegado actualmente en dominio de prueba** `https://legadoholding.com/funerzul` (NO es el dominio final `https://www.funerariadelzulia.com`). Ver `MIGRATION_PLAN.md` para el paso a producción.
- Próximo paso: crear cuenta en el Portal API de Mercantil (https://apiportal.mercantilbanco.com) y registrar la aplicación con los datos de la Funeraria del Zulia.
- Bloqueos: ver abajo.
- Evidencias: ninguna todavía.
- Última comunicación con el banco: ninguna todavía.

## Trabajo ya realizado (sin depender del banco)

- Descubrimiento del proyecto existente: ver `PROJECT_DISCOVERY.md`.
- Capa de pagos desacoplada (`api/lib/payments/PaymentProviderInterface.php`, `SimuladoProvider.php`, `MercantilProvider.php`, `PaymentService.php`) con proveedor **simulado**, lista para enchufar el adaptador real (`MercantilProvider`, hoy un stub que lanza excepción) en cuanto existan credenciales.
- Tablas `prev_pagos_electronicos`, `prev_pago_eventos`, `prev_solicitudes_publicas` (`database/10_prevision_pagos_electronicos.sql` — **pendiente de importar en la base de datos**, igual que las migraciones anteriores).
- Endpoints `api/prevision_pagos.php` (staff: crear intento, estado, conciliar, cancelar) y `api/prevision_solicitudes.php` (público: crear lead; staff: listar, actualizar estado, vincular a contrato).
- Panel admin (`admin.html` + `admin-prevision.js`): pestaña "Solicitudes" y botón "Cobro electrónico" (con badge de modo simulado) en el detalle de cada cuota pendiente de un contrato.
- CTA público "Solicitar con pago electrónico" (`partials/cta_pago_electronico.php`) agregado en las 4 páginas de plan, `planes/index.php`, `servicios/index.php` y las 4 páginas de servicio — crea una `prev_solicitudes_publicas` (lead), nunca ejecuta un cobro real.
- `api/prevision_mercantil_callback.php` (URL de retorno/OAuth) y `api/prevision_mercantil_webhook.php` (webhook de confirmación) creados para poder completar el registro de la aplicación en el Portal API — ver bloqueo MRC-001 arriba.
- Verificado con `php -l` en todos los archivos PHP nuevos/editados y `node --check` en `admin-prevision.js`. No se probó contra una base de datos real (no hay `api/config.php` local con el esquema importado) ni contra el banco (sin credenciales) — pendiente de que el usuario lo pruebe en un entorno con MySQL antes de desplegar.

## Bloqueo MRC-001

- Dato faltante: cuenta en el Portal API de Mercantil.
- Producto afectado: todos (Botón de Pagos Web, C2P, Búsquedas de Pagos Móviles).
- Motivo: sin cuenta no se puede registrar la aplicación ni solicitar productos.
- Fuente esperada: https://apiportal.mercantilbanco.com/mercantil-banco/produccion/
- Acción del usuario: crear la cuenta con correo corporativo y confirmar el registro.
- Trabajo que puede continuar: construcción de la capa de pagos con proveedor simulado, UI de cobranza y captura de leads públicos.
- Fecha: 2026-08-06
- Estado: **en progreso** — el usuario está registrando la aplicación en el Portal API, usando el dominio de prueba actual (`https://legadoholding.com/funerzul`), no el de producción. Se le entregaron las URLs propias del proyecto para el formulario de registro:
  - URL de redirección OAuth / retorno: `https://legadoholding.com/funerzul/api/prevision_mercantil_callback.php` (`api/prevision_mercantil_callback.php`, ya creado)
  - URL de notificación (webhook servidor-a-servidor): `https://legadoholding.com/funerzul/api/prevision_mercantil_webhook.php` (`api/prevision_mercantil_webhook.php`, ya creado)
  - Ambas quedaron precargadas en `api/config.example.php` → `payments.mercantil.return_url/cancel_url/notification_url`.
  - Nota: estas URLs no funcionan de verdad todavía hasta que este trabajo se despliegue al servidor de prueba (están en el repo, no en el servidor). Antes de completar el registro en el Portal, desplegar estos archivos nuevos al hosting de prueba.
  - **Importante**: cuando se migre a `www.funerariadelzulia.com` (producción), estas URLs registradas en Mercantil habrá que actualizarlas ahí también — no es automático. Ver `MIGRATION_PLAN.md`.

## Bloqueo MRC-002

- Dato faltante: razón social, RIF, representante legal y confirmación de afiliación a Mercantil en Línea Empresas.
- Producto afectado: todos.
- Motivo: son datos obligatorios para el registro de la aplicación y la afiliación comercial (§8.1 y §8.3 de `docs/mercantil.md`).
- Fuente esperada: administración/legal de Funeraria del Zulia.
- Acción del usuario: completar la sección "8. Datos pendientes del proyecto" de `docs/mercantil.md` (company.legal_name, company.rif, etc.).
- Trabajo que puede continuar: todo lo que no requiera enviar esos datos al banco.
- Fecha: 2026-08-06
- Estado: abierto

## Bloqueo MRC-003

- Dato faltante: especificación OpenAPI/OAS completa (con esquema de cuerpo, cabeceras y autenticación), colección Postman, credenciales de sandbox.
- Producto afectado: Botón de Pagos Web (llamada de inicio de pago).
- Motivo: sin la especificación oficial no se puede implementar `MercantilProvider::createPayment()` real (regla: no inventar endpoints/campos).
- Fuente esperada: paquete técnico entregado por Mercantil tras la suscripción a productos (§8.5 de `docs/mercantil.md`).
- Acción del usuario: solicitarlo formalmente una vez creada la cuenta del Portal API.
- Trabajo que puede continuar: `MercantilProvider` queda como stub que falla explícitamente hasta recibir la especificación.
- Fecha: 2026-08-06
- Estado: **parcial** — el usuario ya subió `docs/payments/mercantil/API_de_Botón_de_Pagos_Web-1.0.0.json` (swagger oficial exportado del Portal). Solo documenta `POST /api` con respuestas 200/400 genéricas, sin esquema de cuerpo ni seguridad — insuficiente todavía para implementar `createPayment()` sin inventar campos. Sigue abierto para esa parte.

## Bloqueo MRC-004

- Dato faltante: la MasterKey de cifrado y el detalle exacto del algoritmo (modo, padding, codificación) para el Servicio de Confirmación de Operación (webhook).
- Producto afectado: Servicio de Confirmación de Operación (webhook de pagos: Débito Inmediato, Tarjetas, C2P, P2C).
- Motivo: el documento oficial (`docs/payments/mercantil/api_servicio_confirmacion_descripcion_de_atributos_y_campos_0.md`, ya recibido) dice que el payload viaja cifrado "sha256 con RSA usando una MasterKey", pero esa MasterKey **se entrega al afiliarse al servicio** y el documento no detalla modo/padding/codificación — sin eso no se puede descifrar ni verificar con un vector de prueba real (regla docs/mercantil.md §21: solo es válida si coincide exactamente).
- Fuente esperada: Mercantil, al completar la afiliación al Servicio de Confirmación de Operación.
- Acción del usuario: solicitar la MasterKey y el detalle criptográfico completo al banco.
- Trabajo que puede continuar: `api/prevision_mercantil_webhook.php` ya existe y responde el envelope de éxito documentado (`{"codigo":"0000",...}`); por ahora solo registra el payload cifrado sin procesar (hash + `audit_log`), en vez de descifrarlo.
- Fecha: 2026-08-06
- Estado: abierto
