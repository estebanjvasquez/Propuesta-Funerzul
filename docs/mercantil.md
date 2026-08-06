# Integración de Mercantil Banco en un proyecto web

> **Tipo de documento:** especificación operativa y guía para agente de desarrollo  
> **Banco:** Mercantil C.A., Banco Universal — Venezuela  
> **Objetivo:** guiar al usuario y al agente de desarrollo durante el alta bancaria, acceso al Portal API, pruebas de sandbox, construcción de la interfaz de pagos, conciliación, certificación y paso a producción.  
> **Fecha de revisión:** 2026-08-05  
> **Estado:** guía base; debe actualizarse con la especificación, credenciales, contratos y matriz de pruebas que Mercantil asigne al comercio.

---

## 1. Propósito

Este documento debe incorporarse en la raíz documental del proyecto para que cualquier agente de desarrollo pueda:

1. Identificar el estado real de la integración con Mercantil Banco.
2. Determinar cuál es el siguiente paso técnico, bancario o administrativo.
3. Guiar al responsable del proyecto durante el registro y afiliación.
4. Evitar inventar endpoints, credenciales, campos, códigos o algoritmos.
5. Construir una capa de integración desacoplada del frontend.
6. Crear una interfaz segura para iniciar, consultar y conciliar pagos.
7. Implementar pruebas automáticas y pruebas contra el sandbox.
8. Preparar las evidencias requeridas para la certificación bancaria.
9. Separar claramente sandbox y producción.
10. Mantener trazabilidad de decisiones, comunicaciones y entregables.

Este archivo no sustituye:

- La especificación OpenAPI u OAS entregada por Mercantil.
- La colección Postman oficial.
- El contrato comercial.
- Los anexos de afiliación.
- La matriz de códigos de respuesta.
- Las instrucciones particulares suministradas por el banco.
- La evaluación legal, fiscal, contable o regulatoria de la empresa.

Cuando exista una discrepancia, prevalece la documentación vigente suministrada directamente por Mercantil.

---

## 2. Instrucciones obligatorias para el agente

### 2.1 Regla de verificación

El agente no debe asumir que un dato utilizado en un ejemplo es válido para producción.

Antes de implementar o modificar una llamada a Mercantil debe verificar:

- Producto API contratado.
- Versión exacta.
- Ambiente.
- URL base.
- Método HTTP.
- Cabeceras.
- Esquema del cuerpo.
- Algoritmo de cifrado o firma.
- Campos obligatorios.
- Formato de fecha.
- Formato monetario.
- Código de moneda.
- Identificadores del comercio.
- Códigos de respuesta.
- Reglas de reintento.
- Procedimiento de conciliación.

### 2.2 Prohibiciones

El agente no puede:

- Inventar credenciales.
- Usar credenciales de producción en desarrollo.
- Copiar un endpoint de un foro y declararlo definitivo.
- exponer `client_secret`, claves criptográficas o tokens en el navegador.
- Marcar una orden como pagada únicamente porque la API devolvió HTTP 200.
- Considerar una captura de pantalla como confirmación bancaria.
- Guardar claves temporales C2P.
- Guardar CVV, PIN, credenciales bancarias o PAN completo.
- Registrar secretos en logs.
- hacer commit de archivos `.env`.
- Desactivar validaciones TLS.
- Implementar cifrado sin confirmar algoritmo, padding y codificación.
- Reintentar automáticamente una operación monetaria sin idempotencia.
- cambiar datos de comercio sin registrar la modificación.
- Utilizar datos personales reales en pruebas si no es indispensable y autorizado.
- pasar a producción sin conciliación y observabilidad.

### 2.3 Conducta ante información faltante

Cuando falte un dato, el agente debe:

1. Identificar el dato exacto faltante.
2. Explicar por qué es necesario.
3. Indicar dónde suele obtenerse.
4. Proporcionar instrucciones paso a paso para solicitarlo.
5. Registrar el bloqueo en `docs/payments/mercantil/STATUS.md`.
6. Continuar con tareas que no dependan de ese dato.
7. No sustituirlo por una suposición silenciosa.

Formato obligatorio:

```markdown
## Bloqueo MRC-XXX

- Dato faltante:
- Producto afectado:
- Motivo:
- Fuente esperada:
- Acción del usuario:
- Trabajo que puede continuar:
- Fecha:
- Estado:
```

---

## 3. Fuentes oficiales de referencia

El agente debe consultar primero las fuentes oficiales:

- Portal API de Mercantil:  
  `https://apiportal.mercantilbanco.com/mercantil-banco/produccion/`

- Productos API:  
  `https://apiportal.mercantilbanco.com/mercantil-banco/produccion/product`

- Botón de Pagos Web:  
  `https://apiportal.mercantilbanco.com/mercantil-banco/produccion/product/30094`

- API del Botón de Pagos Web:  
  `https://apiportal.mercantilbanco.com/mercantil-banco/produccion/product/30094/api/29256`

- Búsquedas de Pagos Móviles:  
  `https://apiportal.mercantilbanco.com/mercantil-banco/produccion/product/21013`

- Solicitud de Clave de Pago:  
  `https://apiportal.mercantilbanco.com/mercantil-banco/produccion/product/21040`

- API de Solicitud de Clave Temporal C2P:  
  `https://apiportal.mercantilbanco.com/mercantil-banco/produccion/product/21040/api/21037`

- Información de Tpago Empresas:  
  `https://www.mercantilbanco.com/empresas/servicios-digitales/soluciones-de-pago/tpago-empresas`

- Términos y condiciones del Portal API:  
  `https://apiportal.mercantilbanco.com/mercantil-banco/produccion/terminos-y-condiciones`

Las publicaciones del foro pueden ayudar a diagnosticar problemas, pero no constituyen una especificación contractual.

---

## 4. Productos relevantes

### 4.1 Botón de Pagos Web

Producto preferente para un checkout web multimedio.

La información pública del banco indica que permite integrar un aplicativo web de pagos y ofrecer múltiples métodos. Los requisitos publicados para operar incluyen:

- Ser cliente de Mercantil C.A., Banco Universal.
- Estar afiliado a Mercantil en Línea Empresas.
- Contar con una aplicación móvil o sitio web del negocio.

Debe confirmarse con el banco:

- Métodos habilitados para el comercio.
- Modalidad de integración.
- Redirección o interfaz alojada.
- URLs de retorno.
- Notificación asíncrona.
- API de consulta.
- Anulaciones y reembolsos.
- Comisiones.
- Liquidación.
- Certificación.

### 4.2 Pagos con Móvil C2P

Permite que el comercio reciba pagos interbancarios en bolívares mediante el flujo C2P.

El agente debe distinguir:

- Solicitud de clave temporal.
- Ejecución del pago.
- Consulta del resultado.
- Conciliación posterior.

No debe presumir que solicitar la clave temporal ejecuta el pago.

### 4.3 Solicitud de Clave Temporal de Pago C2P

Permite solicitar desde la web o aplicación del comercio la clave temporal para un cliente Mercantil afiliado al servicio correspondiente.

La clave:

- Es un factor de autorización.
- Tiene vigencia limitada.
- No debe persistirse.
- No debe aparecer en logs.
- Debe enviarse al banco exclusivamente desde el backend cuando el flujo lo requiera.

### 4.4 Búsquedas de Pagos Móviles

Permite consultar transacciones de Pago Móvil procesadas por Tpago, incluyendo modalidades publicadas como C2P, P2C o Vuelto.

Su uso principal es:

- Verificación.
- Conciliación.
- Investigación de operaciones pendientes.
- Prevención de falsas referencias.
- Cierre contable.

### 4.5 Pagos con tarjeta

Debe preferirse una interfaz alojada, tokenización o mecanismo controlado por el banco.

El proyecto no debe capturar ni almacenar CVV, PIN o número completo de tarjeta.

### 4.6 Débito inmediato

Puede formar parte del Botón de Pagos Web o utilizar una API específica, según la contratación.

Debe confirmarse:

- Bancos participantes.
- Datos del pagador.
- Flujo de autorización.
- Estados intermedios.
- Reversos.
- Límites.

---

## 5. Estrategia recomendada

### Fase inicial

1. Crear la capa interna de pagos.
2. Implementar un proveedor simulado.
3. Registrar la aplicación en el Portal API.
4. Solicitar acceso a:
   - Botón de Pagos Web.
   - Búsquedas de Pagos Móviles.
5. Importar las especificaciones oficiales.
6. Probar en Postman.
7. Implementar el adaptador de sandbox.
8. Construir la interfaz de checkout.
9. Ejecutar pruebas.
10. Preparar afiliación de producción.

### Configuración objetivo

- **Checkout principal:** Botón de Pagos Web.
- **Método local prioritario:** Pago Móvil C2P.
- **Conciliación:** Búsquedas de Pagos Móviles.
- **Contingencia:** Pago Móvil manual pendiente de verificación.
- **Internacional:** proveedor separado, cuando corresponda.

---

## 6. Estados de la integración

El agente debe mantener uno de los siguientes estados globales:

```text
NOT_STARTED
PROJECT_DISCOVERY
BANK_REGISTRATION_PENDING
PORTAL_ACCOUNT_CREATED
APPLICATION_REGISTERED
API_SUBSCRIPTION_PENDING
SANDBOX_CREDENTIALS_RECEIVED
SANDBOX_SPEC_RECEIVED
POSTMAN_VALIDATED
BACKEND_IMPLEMENTATION
FRONTEND_IMPLEMENTATION
AUTOMATED_TESTING
BANK_CERTIFICATION_PENDING
BANK_CERTIFICATION_IN_PROGRESS
PRODUCTION_CONTRACT_PENDING
PRODUCTION_CREDENTIALS_RECEIVED
PRODUCTION_PILOT
PRODUCTION_ACTIVE
SUSPENDED
```

Registrar el estado en:

```text
docs/payments/mercantil/STATUS.md
```

Plantilla:

```markdown
# Estado de integración Mercantil

- Estado global:
- Fecha:
- Responsable:
- Producto:
- Ambiente:
- Próximo paso:
- Bloqueos:
- Evidencias:
- Última comunicación con el banco:
```

---

## 7. Descubrimiento del proyecto existente

Antes de escribir código, el agente debe inspeccionar:

### 7.1 Arquitectura

- Framework frontend.
- Framework backend.
- Lenguaje.
- Base de datos.
- ORM.
- Sistema de autenticación.
- Gestión de secretos.
- Infraestructura.
- Dominio.
- HTTPS.
- CI/CD.
- Observabilidad.
- Sistema de pedidos.
- Monedas.
- Gestión de usuarios.
- Roles administrativos.
- Política de logs.

### 7.2 Funcionalidad

Determinar:

- Qué se vende.
- Cuándo se considera creada una orden.
- Cómo se calcula el monto.
- Si existen descuentos.
- Si existe IVA.
- Si el monto se fija en USD o VES.
- Cómo se determina la tasa de cambio.
- Cuándo se entrega el producto o servicio.
- Qué ocurre con pagos pendientes.
- Quién puede reembolsar.
- Cómo se genera la factura.
- Cómo se concilia contablemente.

### 7.3 Informe de descubrimiento

Crear:

```text
docs/payments/mercantil/PROJECT_DISCOVERY.md
```

Debe incluir:

```markdown
# Descubrimiento

## Stack
## Flujo de pedidos
## Modelo de datos
## Autenticación
## Infraestructura
## Gestión de secretos
## Observabilidad
## Riesgos
## Cambios necesarios
## Decisiones pendientes
```

---

## 8. Proceso con Mercantil Banco

### 8.1 Preparación

Reunir:

- Razón social.
- RIF.
- Documento constitutivo.
- Representante legal.
- Cuenta empresarial, si existe.
- Datos de contacto.
- Dominio.
- Descripción del negocio.
- Volumen esperado.
- Ticket promedio.
- Moneda.
- Métodos requeridos.
- Política de privacidad.
- Términos del servicio.
- Política de devoluciones.
- URLs del proyecto.

No todos los documentos serán necesariamente solicitados; la lista sirve para evitar retrasos.

### 8.2 Crear cuenta en el Portal API

1. Abrir el Portal API.
2. Seleccionar registro.
3. Usar correo corporativo.
4. Completar datos.
5. Confirmar correo.
6. Iniciar sesión.
7. Activar segundo factor si está disponible.
8. Guardar evidencia del alta.

No almacenar la contraseña en el repositorio.

### 8.3 Registrar la aplicación

Preparar:

```text
Nombre:
Descripción:
Empresa:
RIF:
Tipo: aplicación web
Dominio:
URL frontend:
URL backend:
URL retorno exitoso:
URL retorno cancelado:
URL de notificación:
Ambiente:
Productos:
Contacto técnico:
Contacto comercial:
```

Las URLs definitivas deben usar HTTPS.

### 8.4 Suscribirse a productos

Solicitar inicialmente:

- Botón de Pagos Web.
- Búsquedas de Pagos Móviles.

Solicitar C2P directo cuando el modelo requiera un flujo propio y exista claridad sobre el proceso.

### 8.5 Solicitar el paquete técnico

Pedir expresamente:

- Especificación OpenAPI/OAS.
- Colección Postman.
- URL exacta de sandbox.
- Credenciales.
- Identificadores del comercio.
- Claves de cifrado sandbox.
- Dataset de pruebas.
- Matriz de errores.
- Casos de prueba.
- Reglas de idempotencia.
- Firmas o autenticación.
- Reintentos.
- Timeouts.
- Webhooks.
- Consulta de estado.
- Reversos.
- Certificación.
- IP permitidas.
- Requisitos TLS.

### 8.6 Solicitar información comercial

Pedir:

- Requisitos de cuenta.
- Afiliación a Mercantil en Línea Empresas.
- Contratos.
- Comisión por método.
- Coste fijo.
- Liquidación.
- Retenciones.
- Límites.
- Reembolsos.
- Contracargos.
- Soporte.
- SLA.
- Horarios de mantenimiento.
- Procedimiento de incidentes.

---

## 9. Plantilla de comunicación con el banco

```text
Asunto: Solicitud de integración API para comercio web

Estimados señores:

Estamos desarrollando una aplicación web para [DESCRIPCIÓN] y deseamos
integrar los servicios de pago de Mercantil Banco.

Datos del proyecto:
- Razón social:
- RIF:
- Dominio:
- Aplicación:
- Volumen estimado:
- Ticket promedio:
- Moneda:
- Métodos solicitados:
  - Botón de Pagos Web
  - Pago Móvil C2P
  - Búsquedas de Pagos Móviles
  - [otros]

Solicitamos información sobre:
1. Proceso de registro y afiliación.
2. Acceso al sandbox.
3. Credenciales y datos de prueba.
4. Especificación OpenAPI y colección Postman.
5. URLs de retorno y notificación.
6. Proceso de certificación.
7. Requisitos de producción.
8. Tarifas y liquidación.
9. Gestión de anulaciones y reembolsos.
10. Contacto técnico y comercial.

Atentamente,
[NOMBRE]
[CARGO]
[EMPRESA]
[TELÉFONO]
[CORREO]
```

---

## 10. Estructura documental

Crear:

```text
docs/
└── payments/
    └── mercantil/
        ├── README.md
        ├── STATUS.md
        ├── PROJECT_DISCOVERY.md
        ├── BANK_CHECKLIST.md
        ├── TECHNICAL_CHECKLIST.md
        ├── DECISIONS.md
        ├── QUESTIONS_FOR_BANK.md
        ├── TEST_MATRIX.md
        ├── ERROR_CATALOG.md
        ├── CERTIFICATION_EVIDENCE.md
        ├── RUNBOOK.md
        ├── INCIDENT_RESPONSE.md
        └── CHANGELOG.md
```

No guardar documentación confidencial sin controles adecuados.

---

## 11. Arquitectura

```text
Frontend
   |
   | HTTPS
   v
Backend del proyecto
   |
   +-- Order Service
   +-- Payment Service
   +-- Mercantil Adapter
   +-- Webhook/Callback Handler
   +-- Reconciliation Worker
   +-- Audit Service
   |
   | HTTPS + credenciales servidor
   v
Mercantil API
```

### Principios

- El navegador nunca llama directamente a Mercantil con credenciales privadas.
- El backend calcula el monto.
- La integración bancaria vive detrás de una interfaz.
- Los eventos se procesan de forma idempotente.
- La conciliación es independiente del callback del navegador.
- La orden no se entrega por una redirección del cliente.
- Los secretos se almacenan fuera del repositorio.
- Sandbox y producción están aislados.

---

## 12. Interfaz interna del proveedor

Ejemplo TypeScript:

```typescript
export type PaymentStatus =
  | "CREATED"
  | "PENDING"
  | "REQUIRES_CUSTOMER_ACTION"
  | "PROCESSING"
  | "APPROVED"
  | "DECLINED"
  | "FAILED"
  | "EXPIRED"
  | "CANCELLED"
  | "REVERSED"
  | "REFUND_PENDING"
  | "REFUNDED"
  | "UNKNOWN";

export interface CreatePaymentInput {
  orderId: string;
  amount: string;
  currency: "VES";
  customer: {
    id: string;
    documentType?: string;
    documentNumber?: string;
    phone?: string;
    email?: string;
  };
  returnUrl: string;
  cancelUrl: string;
  idempotencyKey: string;
}

export interface PaymentIntent {
  internalPaymentId: string;
  provider: "MERCANTIL";
  externalPaymentId?: string;
  status: PaymentStatus;
  redirectUrl?: string;
  expiresAt?: string;
  rawCode?: string;
}

export interface MercantilProvider {
  createPayment(input: CreatePaymentInput): Promise<PaymentIntent>;
  getPaymentStatus(externalPaymentId: string): Promise<PaymentIntent>;
  searchMobilePayment?(input: unknown): Promise<unknown>;
  requestC2PKey?(input: unknown): Promise<unknown>;
  processC2PPayment?(input: unknown): Promise<unknown>;
  cancelPayment?(externalPaymentId: string): Promise<void>;
  refundPayment?(input: unknown): Promise<unknown>;
}
```

Las funciones deben implementarse únicamente para productos habilitados.

---

## 13. Cliente HTTP

El cliente debe centralizar:

- URL base.
- Autenticación.
- Cabeceras.
- Timeout.
- Correlation ID.
- Sanitización de logs.
- Errores.
- Métricas.
- Reintentos permitidos.

Ejemplo conceptual:

```typescript
export class MercantilHttpClient {
  constructor(
    private readonly baseUrl: string,
    private readonly clientId: string,
    private readonly clientSecret?: string,
  ) {}

  async request<T>(
    path: string,
    options: {
      method: "GET" | "POST" | "PUT" | "DELETE";
      body?: unknown;
      idempotencyKey?: string;
      timeoutMs?: number;
    },
  ): Promise<T> {
    // Construir cabeceras conforme a la especificación oficial.
    // No registrar secretos ni payloads sensibles.
    // Aplicar timeout.
    // Clasificar errores técnicos y bancarios.
    // No reintentar operaciones monetarias sin garantía de idempotencia.
    throw new Error("Implementar después de importar la especificación oficial");
  }
}
```

---

## 14. Variables de entorno

Plantilla:

```env
MERCANTIL_ENABLED=false
MERCANTIL_ENVIRONMENT=sandbox
MERCANTIL_BASE_URL=
MERCANTIL_CLIENT_ID=
MERCANTIL_CLIENT_SECRET=
MERCANTIL_INTEGRATOR_ID=
MERCANTIL_MERCHANT_ID=
MERCANTIL_TERMINAL_ID=
MERCANTIL_ENCRYPTION_KEY=
MERCANTIL_RETURN_URL=
MERCANTIL_CANCEL_URL=
MERCANTIL_NOTIFICATION_URL=
MERCANTIL_CONNECT_TIMEOUT_MS=5000
MERCANTIL_REQUEST_TIMEOUT_MS=30000
MERCANTIL_LOG_LEVEL=info
```

Reglas:

- No confirmar que todas las variables sean requeridas.
- Eliminar las que no aparezcan en la especificación.
- Añadir las exigidas por el producto.
- Secretos en vault o gestor de secretos.
- No usar prefijos que expongan variables al frontend.
- No usar valores de producción en pruebas.

---

## 15. Endpoints internos del proyecto

```text
POST /api/orders
POST /api/orders/:orderId/payments
GET  /api/payments/:paymentId
POST /api/payments/:paymentId/reconcile
POST /api/payments/mercantil/callback
POST /api/payments/mercantil/webhook
POST /api/payments/:paymentId/cancel
POST /api/payments/:paymentId/refund
```

El callback del navegador y el webhook no deben confundirse:

- **Callback:** navegación del usuario.
- **Webhook:** notificación servidor a servidor.
- **Conciliación:** consulta independiente al banco.

Si Mercantil no ofrece webhook para el producto, implementar polling controlado o consulta manual conforme a la documentación.

---

## 16. Modelo de datos

### payments

```sql
CREATE TABLE payments (
  id UUID PRIMARY KEY,
  order_id UUID NOT NULL,
  provider VARCHAR(30) NOT NULL,
  method VARCHAR(50) NOT NULL,
  amount NUMERIC(18, 2) NOT NULL,
  currency VARCHAR(3) NOT NULL,
  status VARCHAR(30) NOT NULL,
  external_payment_id VARCHAR(150),
  bank_reference VARCHAR(150),
  idempotency_key VARCHAR(150) NOT NULL UNIQUE,
  provider_code VARCHAR(50),
  failure_code VARCHAR(50),
  failure_message TEXT,
  expires_at TIMESTAMPTZ,
  approved_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
```

### payment_attempts

```sql
CREATE TABLE payment_attempts (
  id UUID PRIMARY KEY,
  payment_id UUID NOT NULL,
  attempt_number INTEGER NOT NULL,
  request_correlation_id VARCHAR(150),
  http_status INTEGER,
  provider_code VARCHAR(50),
  result_status VARCHAR(30),
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE(payment_id, attempt_number)
);
```

### payment_events

```sql
CREATE TABLE payment_events (
  id UUID PRIMARY KEY,
  payment_id UUID,
  provider VARCHAR(30) NOT NULL,
  provider_event_id VARCHAR(150),
  event_type VARCHAR(100) NOT NULL,
  payload_hash VARCHAR(128),
  sanitized_payload JSONB,
  processed_at TIMESTAMPTZ,
  received_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
```

### payment_reconciliation

```sql
CREATE TABLE payment_reconciliation (
  id UUID PRIMARY KEY,
  payment_id UUID NOT NULL,
  requested_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  completed_at TIMESTAMPTZ,
  local_status VARCHAR(30),
  provider_status VARCHAR(30),
  matched BOOLEAN,
  discrepancy_reason TEXT,
  reviewed_by UUID
);
```

Índices:

```sql
CREATE UNIQUE INDEX uq_payment_provider_external
ON payments(provider, external_payment_id)
WHERE external_payment_id IS NOT NULL;

CREATE UNIQUE INDEX uq_payment_event_provider_id
ON payment_events(provider, provider_event_id)
WHERE provider_event_id IS NOT NULL;
```

---

## 17. Máquina de estados

Transiciones permitidas:

```text
CREATED -> PENDING
CREATED -> FAILED

PENDING -> REQUIRES_CUSTOMER_ACTION
PENDING -> PROCESSING
PENDING -> APPROVED
PENDING -> DECLINED
PENDING -> FAILED
PENDING -> EXPIRED
PENDING -> CANCELLED

REQUIRES_CUSTOMER_ACTION -> PROCESSING
REQUIRES_CUSTOMER_ACTION -> APPROVED
REQUIRES_CUSTOMER_ACTION -> DECLINED
REQUIRES_CUSTOMER_ACTION -> EXPIRED
REQUIRES_CUSTOMER_ACTION -> CANCELLED

PROCESSING -> APPROVED
PROCESSING -> DECLINED
PROCESSING -> FAILED
PROCESSING -> REVERSED

APPROVED -> REVERSED
APPROVED -> REFUND_PENDING
REFUND_PENDING -> REFUNDED
REFUND_PENDING -> APPROVED
```

Nunca permitir:

```text
DECLINED -> APPROVED
FAILED -> APPROVED
EXPIRED -> APPROVED
```

sin una conciliación explícita que pruebe que el banco procesó la operación.

---

## 18. Flujo del Botón de Pagos Web

### 18.1 Inicio

1. Usuario confirma compra.
2. Backend obtiene productos desde la base de datos.
3. Backend recalcula subtotal, impuestos y total.
4. Backend crea orden.
5. Backend crea registro de pago.
6. Backend genera clave de idempotencia.
7. Backend llama al API de Mercantil.
8. Backend guarda el identificador externo.
9. Frontend recibe la acción requerida.
10. Usuario continúa en la interfaz autorizada.

### 18.2 Retorno

1. El usuario vuelve a la aplicación.
2. La página muestra “verificando pago”.
3. El backend consulta el estado.
4. Se muestra el resultado confirmado.
5. No entregar por parámetros del navegador.
6. Si el estado es incierto, mantener pendiente.

### 18.3 Confirmación

La orden se marca pagada solo cuando coinciden:

- Identificador externo.
- Comercio.
- Orden.
- Monto.
- Moneda.
- Estado aprobado.
- Ausencia de procesamiento previo.

---

## 19. Flujo C2P

1. Usuario selecciona Pago Móvil C2P.
2. Frontend solicita únicamente los datos autorizados.
3. Backend valida formato.
4. Si corresponde, solicita clave temporal.
5. Cliente recibe la clave por el canal bancario.
6. Cliente introduce la clave.
7. Frontend envía la clave al backend.
8. Backend transmite la operación.
9. Backend elimina el valor de memoria tan pronto como sea posible.
10. Backend interpreta código técnico y de negocio.
11. Se consulta o concilia el estado.
12. Se actualiza la orden.

La clave temporal:

- No debe persistirse.
- No debe incluirse en analítica.
- No debe aparecer en herramientas de sesión.
- No debe registrarse en trazas.
- Debe enviarse por HTTPS.
- Debe eliminarse del estado del frontend.

---

## 20. Búsqueda y conciliación

La búsqueda debe utilizarse para:

- Operaciones pendientes.
- Dudas por timeout.
- Verificación de referencia.
- Revisión de discrepancias.
- Cierre diario.

No ejecutar búsquedas ilimitadas.

Implementar:

- Ventana temporal.
- Backoff.
- Límite de intentos.
- Cola de revisión manual.
- Auditoría.
- Métricas.

Resultado:

```text
MATCHED_APPROVED
MATCHED_PENDING
MATCHED_REJECTED
NOT_FOUND
AMBIGUOUS
TECHNICAL_ERROR
```

Una búsqueda “no encontrada” no debe convertirse automáticamente en rechazo definitivo si la operación es reciente.

---

## 21. Cifrado

Algunas integraciones publicadas en el portal han utilizado campos cifrados. El agente debe obtener de la documentación asignada:

- Algoritmo.
- Modo.
- Tamaño de clave.
- Padding.
- Codificación de clave.
- Codificación del texto.
- Salida base64 o hexadecimal.
- Campos cifrados.
- Normalización.
- Rotación.

No copiar implementaciones de foros sin comparar vectores de prueba.

Crear pruebas de vector:

```text
entrada conocida
clave sandbox
salida esperada del banco
```

La implementación se considera válida solo si coincide exactamente.

---

## 22. Interfaz de usuario

### 22.1 Pantalla de selección

Mostrar:

- Resumen de compra.
- Total.
- Moneda.
- Métodos disponibles.
- Aviso de redirección, si aplica.
- Política de cancelación.
- Botón claro.

### 22.2 Estados visuales

#### Pendiente

```text
Estamos verificando el pago. No cierres esta ventana ni repitas la operación.
```

#### Aprobado

```text
Pago confirmado.
Referencia: [referencia parcial]
Orden: [orden]
```

#### Rechazado

```text
El banco no aprobó la operación. No se ha confirmado el pago.
```

#### Incierto

```text
La solicitud fue recibida, pero todavía no podemos confirmar el resultado.
Puedes consultar nuevamente desde esta orden.
```

### 22.3 Reglas de UX

- No mostrar detalles internos.
- No mostrar secretos.
- No solicitar contraseña bancaria.
- No prometer aprobación.
- No permitir doble clic.
- Desactivar el botón durante la solicitud.
- Mantener el identificador de orden visible.
- Ofrecer recuperación de una operación pendiente.
- Ser accesible.
- Adaptarse a móvil.
- Usar HTTPS.
- No cargar scripts bancarios fuera de la documentación oficial.

---

## 23. Panel administrativo

Debe permitir:

- Buscar por orden.
- Buscar por referencia.
- Ver estado local.
- Ver estado del banco.
- Conciliar.
- Ver historial.
- Ver intentos.
- Ver eventos sanitizados.
- Marcar revisión manual.
- Registrar resolución.
- Iniciar reembolso si está habilitado.
- Exportar conciliación.

Debe usar RBAC:

- Soporte: lectura limitada.
- Finanzas: conciliación.
- Administrador de pagos: reembolsos.
- Auditor: lectura.
- Desarrollador: diagnóstico técnico sin datos innecesarios.

Toda acción sensible requiere auditoría.

---

## 24. Idempotencia

Generar:

```text
mercantil:{orderId}:{paymentAttempt}
```

La clave debe:

- Ser única.
- Permanecer estable durante el mismo intento.
- Cambiar solo para un intento nuevo autorizado.
- Guardarse antes de llamar al banco.

Si la API no soporta cabecera de idempotencia, la aplicación debe evitar duplicados internamente y consultar antes de repetir una operación incierta.

---

## 25. Timeouts y reintentos

Clasificar:

### Reintentables

- Error DNS temporal.
- Conexión no establecida.
- HTTP 502, 503 o 504, cuando la operación sea segura para reintento.
- Consulta de estado.

### No reintentar automáticamente

- Operación monetaria con resultado desconocido.
- HTTP 400 por validación.
- HTTP 401 o 403.
- Rechazo bancario.
- Clave temporal inválida.
- Error de cifrado.
- Duplicidad.

Ante timeout después de enviar una operación:

1. Marcar `PENDING`.
2. No repetir inmediatamente.
3. Consultar estado.
4. Conciliar.
5. Escalar si sigue incierto.

---

## 26. Logs

Registrar:

- Correlation ID.
- Payment ID interno.
- Order ID.
- Proveedor.
- Endpoint lógico.
- Duración.
- HTTP status.
- Código bancario.
- Estado mapeado.
- Número de intento.
- Ambiente.

No registrar:

- Client secret.
- Claves.
- Clave temporal C2P.
- PAN.
- CVV.
- PIN.
- Documento completo si no es necesario.
- Teléfono completo.
- Payload sin sanitizar.

Enmascarar:

```text
V-12****78
0414***1234
**** **** **** 1234
```

---

## 27. Seguridad

Controles mínimos:

- TLS válido.
- Secret manager.
- Rotación.
- RBAC.
- MFA administrativo.
- Protección CSRF.
- Rate limiting.
- Validación de entrada.
- CSP.
- Sanitización.
- Auditoría.
- Alertas.
- Backups.
- Retención definida.
- Revisión de dependencias.
- SAST y DAST.
- Separación de ambientes.
- Acceso mínimo.
- Protección de endpoints de webhook.
- Verificación criptográfica cuando exista.

---

## 28. Pruebas

### 28.1 Unitarias

- Mapeo de estados.
- Validación de monto.
- Serialización.
- Cifrado.
- Sanitización.
- Idempotencia.
- Transiciones.
- Errores.
- Enmascaramiento.

### 28.2 Integración simulada

- Aprobado.
- Rechazado.
- Pendiente.
- Timeout.
- Respuesta malformada.
- 401.
- 403.
- 500.
- Evento duplicado.
- Consulta sin resultados.
- Monto diferente.
- Moneda diferente.
- Identificador desconocido.

### 28.3 Sandbox

Usar exclusivamente datos suministrados por Mercantil.

Registrar:

- Caso.
- Fecha.
- Entrada sanitizada.
- Resultado esperado.
- Resultado real.
- Evidencia.
- Incidencia.
- Estado.

### 28.4 Seguridad

- Manipulación de monto.
- Cambio de order ID.
- Replay.
- Doble clic.
- Webhook falso.
- Firma inválida.
- Privilegios insuficientes.
- Inyección.
- Enumeración.
- Exposición en logs.

---

## 29. Matriz de certificación

Crear `TEST_MATRIX.md`:

```markdown
| ID | Producto | Caso | Datos oficiales | Esperado | Obtenido | Evidencia | Estado |
|---|---|---|---|---|---|---|---|
| MRC-001 | Botón | Aprobado | Dataset A | APPROVED | | | Pendiente |
| MRC-002 | Botón | Rechazado | Dataset B | DECLINED | | | Pendiente |
| MRC-003 | C2P | Clave inválida | Dataset C | DECLINED | | | Pendiente |
| MRC-004 | Search | No encontrado | Dataset D | NOT_FOUND | | | Pendiente |
```

---

## 30. Importación de OpenAPI

Cuando se reciba:

1. Guardar copia controlada fuera de exposición pública.
2. Registrar versión y checksum.
3. Importar en Postman.
4. Generar cliente solo si el código resultante se revisará.
5. No editar la especificación original.
6. Crear un adaptador propio.
7. Detectar cambios entre versiones.
8. Actualizar pruebas.

Estructura:

```text
vendor/
└── mercantil/
    ├── README.md
    ├── openapi/
    └── postman/
```

No incluir archivos confidenciales si el repositorio no tiene controles.

---

## 31. Postman

Crear ambientes:

```text
Mercantil Sandbox
Mercantil Production
```

Las variables secretas deben ser locales o gestionadas por un vault.

Colecciones:

```text
00 Health/Auth
10 Botón de Pagos
20 C2P
30 Solicitud de Clave
40 Búsqueda
50 Reversos
60 Errores
```

Añadir scripts para:

- Correlation ID.
- Timestamp.
- Firma.
- Cifrado.
- Assertions.
- Enmascaramiento.

No exportar valores secretos.

---

## 32. Producción

No activar hasta completar:

- Contrato.
- Afiliación.
- Cuenta empresarial.
- Certificación.
- Credenciales.
- URLs finales.
- SSL.
- Secrets.
- Alertas.
- Runbook.
- Conciliación.
- Soporte.
- Piloto.
- Reversos.
- Prueba de recuperación.
- Formación de operadores.

### Piloto

1. Habilitar acceso limitado.
2. Usar montos controlados.
3. Ejecutar pagos de prueba autorizados.
4. Conciliar.
5. Revisar logs.
6. Confirmar liquidación.
7. Validar factura.
8. Corregir.
9. Ampliar gradualmente.

---

## 33. Runbook

Debe incluir:

### Banco no disponible

- Desactivar temporalmente el método.
- Mantener órdenes.
- Mostrar mensaje.
- No perder intentos.
- Activar contingencia.
- Registrar incidente.

### Pago pendiente

- Consultar estado.
- Conciliar.
- No entregar.
- Escalar según antigüedad.

### Doble pago

- Confirmar ambas operaciones.
- Bloquear entrega duplicada.
- Notificar finanzas.
- Aplicar devolución según política.

### Discrepancia de monto

- No aprobar automáticamente.
- Revisar orden y tasa.
- Conciliar.
- Registrar resolución.

### Credencial comprometida

- Desactivar integración.
- Rotar.
- Informar seguridad.
- Revisar logs.
- Documentar alcance.
- Reactivar solo después de validar.

---

## 34. Observabilidad

Métricas:

- Pagos iniciados.
- Aprobados.
- Rechazados.
- Pendientes.
- Tasa de conversión.
- Latencia.
- Errores por endpoint.
- Timeouts.
- Conciliaciones.
- Discrepancias.
- Duplicados evitados.
- Reembolsos.

Alertas:

- Aumento de errores.
- Caída de aprobación.
- Credenciales inválidas.
- Latencia.
- Cola pendiente.
- Discrepancias.
- Webhooks detenidos.
- Falta de conciliación.

---

## 35. Criterios de aceptación

La integración se considera terminada cuando:

- El alta bancaria está documentada.
- El producto está confirmado.
- La especificación oficial está importada.
- Sandbox funciona.
- El backend está desacoplado.
- El frontend no contiene secretos.
- Los montos se calculan en servidor.
- La idempotencia funciona.
- Los estados están controlados.
- La conciliación funciona.
- Las pruebas pasan.
- Los logs están sanitizados.
- Existe panel de revisión.
- Existe runbook.
- Se completó certificación.
- Producción usa secretos separados.
- El piloto fue conciliado.
- La documentación está actualizada.

---

## 36. Secuencia de trabajo del agente

En cada sesión:

1. Leer este archivo.
2. Leer `STATUS.md`.
3. Revisar bloqueos.
4. Inspeccionar cambios.
5. Determinar la siguiente fase.
6. Presentar al usuario los pasos bancarios exactos.
7. Ejecutar el trabajo técnico posible.
8. Solicitar solo datos indispensables.
9. Actualizar estado.
10. Registrar decisiones.
11. Añadir pruebas.
12. No declarar completado sin evidencia.

Formato de respuesta:

```markdown
## Estado actual
## Paso bancario
## Paso técnico
## Datos necesarios
## Archivos modificados
## Pruebas
## Riesgos
## Siguiente acción
```

---

## 37. Checklist inmediato

- [ ] Crear carpeta documental.
- [ ] Ejecutar descubrimiento del proyecto.
- [ ] Confirmar razón social y RIF.
- [ ] Confirmar cuenta empresarial.
- [ ] Crear cuenta Portal API.
- [ ] Registrar aplicación.
- [ ] Solicitar Botón de Pagos Web.
- [ ] Solicitar Búsquedas de Pagos Móviles.
- [ ] Obtener OpenAPI.
- [ ] Obtener Postman.
- [ ] Obtener credenciales sandbox.
- [ ] Obtener dataset.
- [ ] Implementar proveedor simulado.
- [ ] Crear tablas.
- [ ] Crear adaptador.
- [ ] Crear checkout.
- [ ] Crear conciliación.
- [ ] Ejecutar sandbox.
- [ ] Preparar certificación.
- [ ] Solicitar producción.
- [ ] Ejecutar piloto.
- [ ] Activar monitoreo.

---

## 38. Datos pendientes del proyecto

Completar:

```yaml
project:
  name:
  repository:
  frontend:
  backend:
  database:
  hosting:
  production_domain:
  staging_domain:
  order_module:
  authentication:
  secret_manager:

company:
  legal_name:
  rif:
  mercantil_customer: unknown
  business_account: unknown
  mercantil_empresas: unknown
  legal_representative:
  technical_contact:
  commercial_contact:

integration:
  selected_product: pending
  portal_account: pending
  application_registered: pending
  sandbox_access: pending
  production_contract: pending
  certification: pending
```

---

## 39. Principio final

La integración bancaria debe diseñarse para soportar incertidumbre.

Una respuesta técnicamente exitosa no equivale siempre a un pago aprobado. Una redirección del navegador no prueba que el dinero fue liquidado. Un timeout no prueba que el cobro falló. Una referencia escrita por el usuario no prueba que el pago existe.

La fuente final de verdad debe ser el estado bancario confirmado y conciliado conforme al producto contratado y a la documentación vigente de Mercantil Banco.
