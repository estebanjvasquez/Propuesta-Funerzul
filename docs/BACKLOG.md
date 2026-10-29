# Backlog — Propuesta Funerzul

Reemplaza a la base de datos **"Propuesta Funerzul — Backlog"** de Notion,
que dejó de estar disponible (el usuario perdió el acceso a Notion en
octubre de 2026). Las 8 tarjetas que había allí se migraron a este archivo
el 2026-10-08 con su estado actualizado; las demás son nuevas.

Estados: `Pendiente` · `En curso` · `Bloqueado` (depende de un tercero) ·
`Hecho` · `Obsoleto` (ya no aplica — se conserva con el motivo, no se borra).

Cómo se mantiene: en cada cierre de jornada (`.claude/skills/cerrar-proyecto/`)
se cambian estados aquí y se agregan tareas nuevas. Las tareas `Hecho` /
`Obsoleto` se mueven a la sección final en vez de borrarse.

---

## Abiertas

### Urgente — sitio de prueba (`legadoholding.com/funerzul/`)

| Tarea | Estado | Notas |
|---|---|---|
| **Cambiar la contraseña del admin inicial** (y su correo genérico) | Pendiente | La contraseña sembrada en `database/01_schema.sql` está a la vista y el repo es **público**. Si nunca se cambió, cualquiera entra al panel. Ver `docs/specs/2026-10-27-autenticacion-staff-y-portal-clientes.md`, paso 0. |
| Poner `app.env` en `'production'` en el `api/config.php` del servidor | Pendiente | Hoy está en `development`: la API publica el mensaje crudo de la excepción de PDO (ver `api/lib/bootstrap.php`). |
| Corregir `app.site_url` en el `api/config.php` del servidor | Pendiente | Verificado otra vez el 2026-10-08: canonical/og:url salen como `https://www.https://legadoholding.com/funerzul/...`. Rompe canonical/OG/JSON-LD/sitemap en ~30 páginas. Lo corrige quien tenga acceso al servidor (archivo fuera de git). Ver `docs/specs/2026-09-09-auditoria-seo-funerzul.md`. |
| Recuperar una copia de `api/config.php` del servidor | Pendiente | La copia local se perdió con el equipo anterior. Guardarla fuera del repo (nunca se commitea). |
| Aplicar y probar en el servidor real las migraciones 11, 12 y 13 + tarjetas de obituario | Pendiente | `database/11_secciones_toggle.sql`, `12_plantillas_esquela.sql`, `13_plantilla_cinta_imagen_real.sql`. Nunca se probaron contra la BD real. `api/diag.php` ya reporta si la 12 está aplicada y prueba GD/FreeType. Depende de la tarea de MySQL. |

### Del lado de Prevision-Funeraria (no se resuelve en este repo)

| Tarea | Estado | Notas |
|---|---|---|
| Cargar el catálogo de servicios del tenant `fdz` | Pendiente | `GET /api/public/t/fdz/servicios` sigue devolviendo `items: []` (2026-10-08). El tenant `lh` sí tiene 3 servicios. Ojo: los slugs no coinciden con los 4 de `servicios/*.php`. |
| Corregir precio de Tradición por debajo de Esencial | Pendiente | 2026-10-08: TRADICION US$12,00/mes vs. ESENCIAL US$13,70/mes. El catálogo de `fdz` tiene ahora 9 planes (incluye variantes "NEW" y planes de empleados al 30%/50%) — confirmar cuáles deben mostrarse en el sitio público. |
| Mercantil: spec completa (`POST /api` + MasterKey del webhook) | Bloqueado | Depende del banco. Relevante para Prevision-Funeraria; este repo ya no tiene endpoints de cobro. *(Migrada de Notion.)* |

### Comentarios del cliente (2026-10-27) — pendientes

| Tarea | Estado | Notas |
|---|---|---|
| Desplegar el código del 2026-10-27 (`9ef7d8d`) y revisar en el servidor | Pendiente | Migraciones 14, 15 y 16 ya aplicadas (verificado en vivo 2026-10-27). Revisar tras el deploy: Inicio y Preguntas Frecuentes no lleven a Legado, planes, un obituario sin foto, interruptor del Portal de Clientes en Configuración. |
| Respuestas del cliente a las preguntas del 2026-10-27 | Esperando al cliente | Cobertura regional, edades, Grupo Selecto (una o dos tarjetas, quién lo contrata, destino del botón, precios, contenido), color de "Paz a su alma", qué logo sobraba, "En memoria de", texto de gestión documental, Tradición < Esencial y planes extra en PF, correo de avisos. **Nuevo:** revisar la tabla comparativa de `planes/#comparar` (en especial la columna Esencial: servicio a domicilio, oficios, flores y cafetería marcados como no incluidos según el catálogo de PF) y los datos de la sección Contacto de la portada. Grupo Selecto existe en Legado Holding (tenant `lh`: esencial-selecto, vanguardia-selecto, toda Venezuela). |
| Grupo Selecto como 4to plan | Hecho (2026-10-29), falta desplegar | Publicado como "Plan Grupo Selecto" con el contenido del "Plan Vanguardia Grupo Selecto" de Legado (tenant `lh`, `vanguardia-selecto`), sin precios: tarjeta en portada y `planes/`, página `planes/plan-grupo-selecto.php`, columna en `#comparar`, sitemap. El Plan Vanguardia se alineó con el "Plan Vanguardia Zulia" de Legado (ataúd, oficios, flores naturales, cremación/bóveda aérea/inhumación, 6 familiares, padres hasta 80) y ya no muestra precio. Pendiente: no existe en el catálogo de `fdz` en PF (el botón de pago electrónico solo deja el lead); confirmar con el cliente la tabla (Esencial sin ataúd; Selecto con servicio a domicilio/cafetería "a su elección"). |
| ¿Los planes son "a nivel regional"? | Pendiente (respuesta del cliente) | El cliente lo pregunta por cada plan. Falta definir la cobertura geográfica para publicarla. |
| Confirmar color de "Paz a su alma" | Pendiente (confirmar) | Se agrandó y pasó de dorado a blanco en la Cinta Conmemorativa (web y PNG). El cliente pidió "otro color" sin decir cuál. |
| Confirmar que "el logo que no va" era la imagen genérica sin foto | Pendiente (confirmar) | Corregido: ya no se muestra el logo cuando no hay foto. Si se refería al logo que viene dentro de la imagen de cabecera de la Cinta (`img/obit-cinta-header.png`), hay que recortar esa imagen. |
| Vanguardia Plus en Prevision-Funeraria | Pendiente (en PF) | Se retiró del sitio; sigue en el catálogo de `fdz` en PF (y las variantes "NEW" y de empleados). Decidir si se desactiva allí. |
| Textos de "velación" que vienen de Prevision-Funeraria | Pendiente (en PF) | La descripción detallada del plan Tradición en PF dice "sala de velación por 24 horas". Hoy el sitio no la muestra, pero el portal de clientes sí podría. |

### Manual de usuario del panel (2026-10-27)

| Tarea | Estado | Notas |
|---|---|---|
| **Eliminar el usuario temporal `manual@funerariadelzulia.com`** | Pendiente — **urgente** | Admin con contraseña débil, creado solo para las capturas. Borrarlo en Sistema → Usuarios (o cambiarle la clave si se reusará para regenerar capturas). |
| Desplegar el manual y regenerar las capturas | Pendiente | Las capturas se tomaron antes de desplegar el botón "Manual": la 02 no lo muestra. Tras desplegar, correr `docs/manual/capturar-manual.mjs` de nuevo (y otra vez al mudarse al dominio final, para que los enlaces de "Redes" muestren funerariadelzulia.com). |
| Botón "Restaurar" para obituarios dados de baja | Pendiente (decidir) | La API tiene `action=restore` pero el panel no ofrece cómo ver ni restaurar los dados de baja; el README decía que se podía. El manual indica pedir ayuda al soporte. |

### Enlaces para redes y analítica (2026-10-27)

| Tarea | Estado | Notas |
|---|---|---|
| Aplicar `database/17_enlaces_redes_y_analitica.sql` y desplegar | Pendiente | Código listo, sin probar contra PHP/MySQL real. Probar: botón "Redes" en Obituarios, abrir `/o/{id}/fb` (debe llevar al obituario y sumar 1 clic), `obituario_og.php?id=` (imagen 1200×630). |
| **Corregir `site_url` en el `config.php` del servidor** | Pendiente — **bloquea la vista previa en redes** | Hoy og:image/og:url salen `https://www.https://…`. Sin esto Facebook/WhatsApp no muestran imagen. |
| Crear la propiedad de Google Analytics 4 y cargar el ID en Configuración | Pendiente | analytics.google.com (gratis). Idealmente con el dominio final. |
| Verificar el sitio en Google Search Console | Pendiente | Método "Etiqueta HTML" → pegar el valor de `content` en Configuración. Luego enviar `sitemap.php`. Hacerlo con el dominio final. |
| Empezar a publicar enlaces en redes solo con el dominio final | Pendiente | Los enlaces de `legadoholding.com/funerzul/o/…` dejarían de funcionar al mudarse. |

### Seguridad del login y portal de clientes

| Tarea | Estado | Notas |
|---|---|---|
| Fase 1 de seguridad del login local (ahora solo emergencia): MFA TOTP, vencimientos, revocación, cabeceras | Pendiente (aprobada) | Ya hechos el 2026-10-28 con el SSO: límite de intentos y los 4 perfiles. Lo demás en `docs/specs/2026-10-27-autenticacion-staff-y-portal-clientes.md`, sección 4; necesita una migración nueva después de la 18. |
| Probar el interruptor del Portal de Clientes tras el deploy | Pendiente | Migración 14 aplicada (2026-10-27); código sin probar contra PHP/MySQL real. Activar el enlace solo cuando el portal de PF esté listo para `fdz`. |
| PF: entregabilidad del correo del OTP y marca FDZ | Pendiente (en Prevision-Funeraria) | El OTP queda **solo por correo** (decisión 2026-10-27); clientes sin correo registrado no pueden entrar — limitación conocida. |
| **"Entrar con Previsión Funeraria" (SSO del panel con el staff de `fdz`)** — puesta en marcha | Pendiente — código listo (2026-10-28) | PF: rama `feature/sso-sitios` (465 pruebas OK) → merge, migración `control/0017` en remoto, deploy, `npm run oauth-client`. Este repo: importar `18_sso_prevision.sql`, bloque `pf_sso` en `api/config.php`, deploy, y dejar 1-2 cuentas locales de emergencia (correo propio). Pasos en `docs/specs/2026-10-28-sso-prevision-funeraria.md`, sección 7. PHP sin probar contra servidor real. |
| Asignar los perfiles del sitio (`sitio.*`) a los roles de `fdz` en PF | Pendiente | Solo `tenant_global_admin` los recibe por migración. Definir con el cliente qué rol modera condolencias, publica obituarios, etc. |
| Regenerar las capturas del manual (login nuevo + botón "Previsión (FDZ)") | Pendiente | Después del deploy del SSO. `docs/manual/capturar-manual.mjs` usa login local: correrlo con una cuenta local de emergencia y `admin.html?emergencia=1`. |

### Migración a `www.funerariadelzulia.com` (reemplaza el WordPress)

| Tarea | Estado | Notas |
|---|---|---|
| **Corte a `www.funerariadelzulia.com`** — objetivo: en línea antes del 2-nov | En preparación | Decisiones respondidas (cuentas separadas, dominio principal, tienda sin uso, Nosotros + Testimonios). Calendario y pasos: `docs/specs/2026-10-28-migracion-dominio-final.md`, sección 3.1. |
| Importar `19_nosotros_testimonios.sql` y revisar Nosotros, Testimonios y el menú nuevo en el sitio de prueba | Pendiente | Código del 2026-10-28, sin probar contra PHP/MySQL real. |
| Confirmar la IP real de los visitantes detrás de Cloudflare | Pendiente | Si `REMOTE_ADDR` es de Cloudflare, el límite de intentos del login de emergencia agrupa a todos. Plan, punto 0.6. |
| Redirecciones 301 de las URLs de WordPress | Hecho (2026-10-28) | En `.htaccess`; tabla en la sección 1.6 del plan. |
| Reutilizar la propiedad de Google de Site Kit (`GT-PJ5S7WTQ`) y pasar Search Console a verificación DNS | Pendiente | Reemplaza las tareas "crear GA4" y "verificar Search Console" de la sección de enlaces y analítica. |

### Repositorio / documentación

| Tarea | Estado | Notas |
|---|---|---|
| Decidir si `feature/prevision-funeraria` se mergea a `main` (o PR) | Pendiente | *(Migrada de Notion, donde decía `feature/modulo-prevision` — esa rama quedó congelada tras el corte del 2026-08-28.)* `main` sigue en el estado del 2026-07-02 + onboarding. |
| Evaluar usar `mostrar_precio` por plan (API de PF) en vez del interruptor global `show_prices` | Pendiente | El commit `e7e24ee` asumía que PF no exponía visibilidad por ítem; el 2026-10-08 la API ya devuelve `mostrar_precio` en cada plan. Cambio chico en `api/lib/prevision_funeraria.php` + `partials/pf_precio_plan.php`. |
| Recrear los documentos de reglas perdidos | Pendiente | `AGENTS.md`, `docs/agent-standards.md`, `docs/frontend-standards.md`, `docs/spec-template.md`, `docs/specs/README.md` nunca se commitearon y se perdieron con el equipo anterior. `CLAUDE.md` y `ONBOARDING-AGENTES.md` todavía los mencionan. Sí sobreviven `docs/backend-standards.md` y `docs/data-model.md`. |
| Rehacer `docs/legado-holding-prevision/` | Pendiente (probablemente innecesaria) | *(Migrada de Notion.)* Solo si Prevision-Funeraria (tenant `lh`, en producción) no cubre ya lo que necesita Legado Holding. Confirmar con el usuario antes de invertir tiempo. |

---

## Cerradas

| Tarea | Estado | Motivo |
|---|---|---|
| Aplicar migraciones 14, 15 y 16 | Hecho | Aplicadas por el usuario el 2026-10-27. Verificado en vivo: FAQ nueva en la portada, obituarios con "Acto Velatorio", "En memoria de" en la plantilla predeterminada y ninguna plantilla con `{{photo}}`. |
| Decidir esquema de login del personal y perfiles | Hecho | 2026-10-27: Opción C (híbrida) + 4 perfiles; portal de clientes en PF con OTP solo por correo. Ver sección 8 de la spec. |
| Enlace "Portal de Clientes" con interruptor y URL en Configuración | Hecho | 2026-10-27, arranca apagado. Falta aplicarlo en el servidor (tarea abierta arriba). |
| Restablecer la conexión a MySQL del sitio de prueba | Hecho | Caída verificada el 2026-10-08 (`SQLSTATE[HY000] [2002]`); el 2026-10-27 `api/templates.php?action=list` ya devuelve datos. No se registró qué la resolvió. |
| Confirmar si sigue el módulo PHP de previsión o se migra a Prevision-Funeraria | Hecho | Decidido el 2026-08-28: corte inmediato, módulo retirado (respaldo en `archive/modulo-prevision-php`). *(Migrada de Notion.)* |
| Ronda 3 del módulo de previsión (domiciliación, planes colectivos, imprimibles, envejecimiento de dependientes) | Obsoleto | El módulo PHP se retiró el 2026-08-28; si hace falta, es trabajo de Prevision-Funeraria. *(Migrada de Notion.)* |
| Numeración de contratos configurable (`docs/prevision/plan-2026-08-07.md`) | Obsoleto | Mismo motivo: los contratos viven ahora en Prevision-Funeraria. *(Migrada de Notion.)* |
| Priorizar con el cliente las 6 mejoras de previsión (`docs/propuesta-mejoras-prevision.md`) | Obsoleto | Mismo motivo; el documento queda como histórico. *(Migrada de Notion.)* |
| Contratar proveedor de mensajería WhatsApp/SMS | Obsoleto (en este repo) | La mensajería configurable era parte del módulo PHP retirado. Si sigue haciendo falta, se resuelve en Prevision-Funeraria. *(Migrada de Notion.)* |
