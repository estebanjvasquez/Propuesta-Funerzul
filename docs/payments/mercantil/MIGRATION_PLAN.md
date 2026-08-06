# Plan de migración: ambiente de prueba → servidor de producción

## Contexto

Todo el trabajo actual (pagos electrónicos, y el sitio en general) se está probando en:

```
https://legadoholding.com/funerzul
```

Esto **no** es el servidor de producción final. El usuario confirmó que el servidor de
producción es una cuenta de hosting/infraestructura **distinta y separada** de
`legadoholding.com` — no es simplemente "apuntar un dominio" a la misma carpeta.
Por lo tanto la migración es un despliegue completo a un servidor nuevo, no solo
un cambio de DNS.

Dominio final esperado (según `api/config.example.php` / `.htaccess` originales):
`https://www.funerariadelzulia.com`. Confirmar si sigue siendo así antes de ejecutar.

## 0. Decisiones que hay que confirmar antes de empezar

- [ ] ¿Cuál es el hosting/proveedor del servidor de producción? (cPanel, VPS, otro)
- [ ] ¿Ya existe una cuenta de hosting creada para producción, o hay que contratarla?
- [ ] ¿El despliegue a producción será por Git (como `legadoholding.com` vía `.cpanel.yml`)
      o manual/otro mecanismo? Si es Git, ¿es el mismo repositorio con un segundo
      remoto/destino, o un repositorio distinto?
- [ ] ¿Quién tiene las credenciales de MySQL, cPanel y DNS del servidor de producción?
- [ ] ¿El registro de la aplicación en el Portal API de Mercantil para producción es
      **la misma aplicación** que la de prueba (cambiando solo las URLs) o Mercantil
      exige una aplicación/afiliación separada para el ambiente de producción?
      (Suele ser afiliación distinta: sandbox vs. producción tienen credenciales
      y a veces hasta host base distintos — confirmar con Mercantil, no asumir.)

## 1. Infraestructura del servidor de producción

- [ ] Crear/confirmar la cuenta de hosting (cPanel u otro) para producción.
- [ ] Configurar el dominio `www.funerariadelzulia.com` (y su variante sin `www`)
      apuntando (DNS) al servidor de producción — **no** al de prueba.
- [ ] Emitir/activar certificado SSL (Let's Encrypt u otro) para ese dominio.
- [ ] Crear la base de datos MySQL de producción (usuario, contraseña, nombre) —
      **nunca reutilizar las credenciales del ambiente de prueba**.
- [ ] Si el despliegue es por Git + cPanel, configurar el repositorio Git en la
      cuenta de producción y adaptar `.cpanel.yml` (el `DEPLOYPATH` actual apunta a
      `/home/legadoholding/public_html/funerzul`, que es específico del servidor de
      prueba — en producción será otra ruta según esa cuenta de cPanel).

## 2. Base de datos

- [ ] Exportar el esquema y datos del ambiente de prueba (`database/01_schema.sql` a
      `10_prevision_pagos_electronicos.sql`, en orden, más los datos reales que se
      quieran migrar). **No** incluir `database/SIEMPRE.sql` salvo que realmente se
      necesite (es un respaldo histórico de +1GB, ver `.gitignore`).
  - [ ] Confirmar si se migran también los datos de prueba (probablemente NO — producción
        debería arrancar limpia o solo con datos reales ya cargados).
- [ ] Importar el esquema en la base de datos de producción, en el mismo orden de
      migraciones (idempotentes según el propio comentario de cada archivo).
- [ ] Confirmar collation/engine consistentes (`utf8mb4_unicode_ci`, InnoDB) — ya
      están fijados en cada `CREATE TABLE`, no debería requerir ajuste manual.

## 3. Código y configuración

- [ ] Desplegar el código (misma rama que se probó, ya mergeada) al servidor de
      producción.
- [ ] Crear `api/config.php` en producción a partir de `api/config.example.php`,
      con:
  - Credenciales de MySQL de producción (no las de prueba).
  - `app.site_url` = `https://www.funerariadelzulia.com`.
  - `security.allowed_origins` con el dominio de producción (quitar el de prueba
    si estaba puesto ahí).
  - `payments.provider` = `'simulado'` hasta certificar Mercantil en producción
    (no cambiar a `'mercantil'` sin credenciales reales de producción — ver
    `docs/payments/mercantil/STATUS.md`).
  - `payments.mercantil.return_url` / `cancel_url` / `notification_url` apuntando
    a `https://www.funerariadelzulia.com/api/prevision_mercantil_callback.php` y
    `.../prevision_mercantil_webhook.php` (hoy apuntan al dominio de prueba —
    **cambiarlas** al desplegar a producción).
- [ ] Verificar `uploads/` (fotos de obituarios, adjuntos de previsión): en
      producción arrancan vacíos salvo que se migren archivos reales del servidor
      de prueba (los `.gitignore` ya excluyen su contenido del repo).

## 4. Mercantil Banco (específico de pagos electrónicos)

- [ ] Confirmar con Mercantil si el ambiente de producción requiere una aplicación/
      afiliación nueva o si se reconfiguran las URLs de la misma aplicación de
      prueba (ver punto 0).
- [ ] Registrar/actualizar en el Portal API las URLs de producción:
  - Redirección OAuth / retorno: `https://www.funerariadelzulia.com/api/prevision_mercantil_callback.php`
  - Notificación (webhook): `https://www.funerariadelzulia.com/api/prevision_mercantil_webhook.php`
- [ ] Solicitar credenciales de **producción** (no reusar las de sandbox/prueba).
- [ ] Actualizar `docs/payments/mercantil/STATUS.md` con el nuevo estado y ambiente.
- [ ] No activar `payments.provider = 'mercantil'` en producción hasta completar
      la certificación bancaria (§32 de `docs/mercantil.md`).

## 5. Pruebas antes del corte (smoke test)

- [ ] El sitio público carga (home, servicios, planes, obituarios).
- [ ] Login del panel admin funciona con las credenciales de producción.
- [ ] Se puede crear un contrato de previsión de prueba y registrar un pago manual.
- [ ] El botón "Cobro electrónico" (modo simulado) crea y concilia un intento
      correctamente contra la base de datos de producción.
- [ ] El formulario público "Solicitar con pago electrónico" crea una
      `prev_solicitudes_publicas` visible en el panel admin.
- [ ] `api/prevision_mercantil_callback.php` y `.../prevision_mercantil_webhook.php`
      responden (aunque sea con el flujo simulado/pendiente) en el dominio de
      producción.

## 6. Corte (cutover)

- [ ] Congelar cambios en el ambiente de prueba mientras se hace el corte.
- [ ] Apuntar DNS de `www.funerariadelzulia.com` al servidor de producción (si no
      se hizo ya en el punto 1) y esperar propagación.
- [ ] Confirmar SSL activo antes de anunciar el corte.
- [ ] Avisar a Mercantil (si ya hay integración activa) del cambio de ambiente.

## 7. Rollback

- [ ] Mientras el DNS no haya propagado del todo, el ambiente de prueba sigue
      disponible en `legadoholding.com/funerzul` como referencia/comparación.
- [ ] Mantener el respaldo de la base de datos de producción tomado justo antes
      del corte, por si hay que revertir datos.

## 8. Después de migrar

- [ ] Actualizar `docs/payments/mercantil/STATUS.md` (ambiente, URLs, próximo paso).
- [ ] Actualizar este documento si el proceso real difirió del plan.
- [ ] Ejecutar `graphify update .` para que el grafo de conocimiento refleje el
      estado final.
