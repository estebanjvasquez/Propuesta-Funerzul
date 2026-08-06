## Servicio de Confirmación de Operación

## Servicio de Confirmación de Operación

El Servicio de Confirmación de Pagos informa a tu empresa sobre transacciones financieras, como Débito Inmediato, Tarjetas, C2P, y P2C, permitiendo conocer el estado de cada operación en tiempo real.

## Especificaciones funcionales por parte de la empresa que recibe la confirmación

- 1. Es requerida la afiliación al servicio por parte de la empresa.

- 2. Se entrega una llave de cifrado a la empresa destinataria de la confirmación.

## Especificaciones técnicas para el uso del servicio

- 1. La empresa debe contar con un desarrollo para procesar la confirmación que se está entregando en línea.

- 2. El canal que se manejará es donde se originó la operación.

- 3. La mensajería llegará cifrada, mediante un algoritmo de cifrado y descifrado sha256 con RSA usando una MasterKey.

- 4. Se manejan HTTP Status Code. Por lo tanto el valor esperado para entrega exitosa es 200. Cualquier status code diferente al previo se considera no exitoso.


## Servicio de Confirmación de Operación

## Mensajería estándar de entrada y salida

## Cifrada:

## Request

{"data":"k0C4ikmKzDEU9tInn9wXsltGAPLT+wlWfFxgHPbMxXEvJHQWIXEcSDfDWv+jVkVhFnzFB5exwzcHiZUvNur ys8NLpCH15MnA5/+rwLxMiYEWpX3jnNpKQvBMa6PK6lqAsklJMNRkIWBLLKJFtqb9oegiIwDioy2OZB/k5Hn4z7WE1d3 FPYI1gbFe106LlKgtMgs5DPfeCU5G84GW+F80qxabKjXBFJU0jDR/yZUqCAXC9qjJ/Bcuhmb8FRC8CcmcmWCibRfDsRo /G+k6NdQFhTN0q6O6C08MPUrRF5cx5iBopDZY6Fsopel3//0GAPFUSjW7skgl/7SKlTbGd59SLueSvbsNRSarvdB3PHH ono8Pd/OE7uGilaMz9gW1kGDRovenNUq/alLebItnfzeO3FWLMs7f1niNMxB+Cb+pv0jYZ2AgKpPzzDIB9ZHpHW3LbR+ SSKjTPnXP8agat5oe3W9AM3CQzinJFy183EGIy7n871NZMi+wNgZuAdEeorX1scPZ8YRnY2F/wjabg5Yc8kHcqKiSR9N 2VKIJHv+l4j+UOMbBP/Lj+XzcWP4ZQqIzPQywXuaef+zFcH6h+cJMdARUd3cCVuyTMl6m9bbweIhakKqC0xjroQI1eOq oMPEOYwZUq/PKyolaGQQ3KnjtJzyevJX7rv0yne8hZO2krtNAlvpuPc22xQeb0+x6ByhnMmXj9Y1zZqcVvsT9U50s3ja 0yELvCE1nB3Mw/BGQ7ftXjNnKfUFMwKKrz3kTKiwtLsZuowe1sTH+rVwERm8HxrFj1yj6GGWABsEujslEdw3QYI5JIw1 8LiWufp8ZQyF3HxypgtZh1jljHoP6umcLidmyGgjDzLNRkSVbGo5DvL22jIVxa7rd0qIpDvW4bdGB+8REKPcQE6CNjUj 9XXgBhA=="}

## Response

```
{
"codigo": "0000",
"mensajeCliente": "Notificacion recibida con exito!",
"mensajeSistema": "Notificacion recibida con exito!!",
"idRegistro": "00000"
}
```


## En claro:

## Request

```
{
"infoMsg": {
```

## Servicio de Confirmación de Operación

```
"guId": "ad2a1719-f8af-10d1-60e7-d4e5d5b93464",(Provista por el Backend de Mercantil)
"channel": "0006",(Provista por el Backend de Mercantil)
"subchannel": "07",(Provista por el Backend de Mercantil)
"applId": "OLB",(Provista por el Backend de Mercantil)
"personId": "V11312786@J306993762",(Provista por el Backend de Mercantil)
"userId": "",(No aplica)
"token": "",(No aplica)
"action": "",(No aplica)
},
"webhookNotificationIn": {
"codigo": "12345",
"mensajeCliente": "Mensaje para el cliente",
"mensajeSistema": "Mensaje del sistema",
"referenciaBancoOrdenante": "REF123452",
"referenciaBancoBeneficiario": "REF654322",
"tipo": "TipoDeTransaccion",
"bancoOrdenante": "Banco Ordenante",
"bancoBeneficiario": "Banco Beneficiario",
"idCliente": "10824244",
"tipoDatoCliente": "",
"numeroProductoCliente": "NumeroCliente123",
"idComercio": "J000000406848786",
"tipoDatoComercio": "",
"numeroProductoComercio": "NumeroComercio123",
"fecha": "2024-02-09",
"hora": "14:00",
"codigoMoneda": "USD",
"monto": "100.00",
"numeroFactura": "0",
"numeroContrato": "0",
"concepto": "Pago de servicios"
}
}
```

## Response

```
{
"infoMsg": {
"guId": "ad2a1719-f8af-10d1-60e7-d4e5d5b93464",
"channel": "0006",
"subchannel": "07",
"applId": "OLB",
"personId": "V11312786@J306993762",
"userId": "",
"token": "",
"action": ""
},
"code": 0,
"codigo": "00",
"mensajeCliente": "aprobado",
```


## Servicio de Confirmación de Operación

```
"mensajeSistema": "aprobado",
"idRegistro":
"1a0a225e0adf95e2a6c643c13340253becfca67e3a959bfbdf953296cf93ba82a64efc7dd8770ea9e015d87029c
ced5"
}
```


## Servicio de Confirmación de Operación

## Descripción de Atributos o Campos

| Campo | Tipo | Descripción |
| --- | --- | --- |
| Código | String | Indica el estado de la transacción por medio de un código de dos caracteres (Ej.: 00 – Aprobada, 51 – Fondo insuficiente). |
| Mensaje Cliente | String | Mensaje a mostrar al cliente asociado al código de estado de la transacción. |
| Mensaje Sistema | String | Mensaje del sistema asociado al código de estado de la transacción. |
| Referencia Banco Ordenante | String | Número de referencia, código generado u entregado por el banco ordenante para identificar de forma única el registro en la plataforma. |
| Referencia Banco Beneficiario | String | Número de referencia, código generado u entregado por el banco beneficiario para identificar de forma única el registro en la plataforma, este no es requerido para los registros de tipo “E”. |
| Tipo | String | Indicador de tipo de transacción, R – pago recibido, E – Pago enviado. |
| Banco Ordenante | String | Código de 4 dígitos que identifica a la entidad financiera ordenante de la transacción. |
| Banco Beneficiario | String | Código de 4 dígitos que identifica a la entidad financiera beneficiaria de la transacción. |
| Id Cliente | String | Documento de identidad de persona natural o jurídica, incluyendo tipo de documento del ordenante si el tipo es R o del beneficiario si el tipo es E, bajo el formato de tipo de documento (longitud = 1) + número de documento (longitud = 15) (Ej. V12345678. J000029610). |
| tipoDatoCliente | String | CEL o EMAIL o CTA o TAR Siglas que representan el tipo de artefacto utilizado. |
| numeroProductoCli ente | String | Número de teléfono del ordenante de la transacción si el tipo es R o del beneficiario si el tipo es E, bajo el formato de número internacional (Ej: 00584141234567), también puede ser el correo o la cuenta o la tarjeta del cliente. |
| Id Comercio | String | Documento de identidad de persona natural o jurídica, incluyendo el tipo de persona, del comercio afiliado al servicio de P2C de la entidad financiera, bajo el formato de tipo de persona (longitud = 1) + número de |


## Servicio de Confirmación de Operación

|   |   | documento (longitud = 15) (Ej. V12345678. J000029610). |
| --- | --- | --- |
| tipoDatoComercio | String | CEL o EMAIL o CTA o TAR Siglas que representan el tipo de artefacto utilizado. |
| numeroProductoC omercio | String | Número de teléfono del comercio de la transacción si el tipo es R o del beneficiario si el tipo es E, bajo el formato de número internacional (Ej: 00584141234567), también puede ser el correo o la cuenta o la tarjeta del cliente. |
| Fecha | String | Fecha de la transacción en el formato YYYYMMDD. (Ej. 19810101). |
| Hora | String | Hora militar de la transacción en formato HHMM (2359). |
| Codigo Moneda | String | Contiene la representación de la moneda usada en la transacción (ej. 0928). |
| Monto | String | Monto de la transacción en formato 15+2 con separador decimal “.” (Ej. 999999999999999.99). |
| numeroFactura | String | Será la referencia a la operación que genera el cliente del banco que hace uso de estos servicios. |
| numeroContrato | String | Suministrado por el cliente define su relación con la operadora por ej. telefónica movistar. |
| Concepto | String | Descripción que el cliente da a la transacción. |


## Servicio de Confirmación de Operación

## Respuesta

| Campo | Tipo | Descripción |
| --- | --- | --- |
| Código | String | Indica el estado del registro por medio de un código de dos caracteres (00 – aprobada, 99 – error en plataforma). |
| Mensaje Cliente | String | Mensaje a mostrar al cliente asociado al código de estado del registro. |
| Mensaje Sistema | String | Mensaje del sistema asociado al código de estado del registro. |
| Id Registro | String | Identificador de registro único de transacción generado por el servicio para uso del solicitante. |
