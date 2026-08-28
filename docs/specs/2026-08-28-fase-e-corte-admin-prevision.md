---
Estado: **Ejecutado** (2026-08-28) — corte inmediato, ver "Qué se ejecutó" abajo.
---

# Fase E — Corte del módulo PHP de Previsión hacia Prevision-Funeraria

Desglosa y cierra la Fase E de
[`docs/specs/2026-08-28-migracion-a-prevision-funeraria.md`](2026-08-28-migracion-a-prevision-funeraria.md#4-plan-de-migración-por-fases-funeraria-del-zulia).
Primera versión de este documento (mismo día) proponía un corte gradual con
ventana de verificación de staff antes de tocar código; **el usuario decidió
un corte inmediato** y pidió remover el módulo PHP directamente, no solo
ocultarlo. Esta versión documenta lo que realmente se ejecutó.

## Decisiones del usuario (2026-08-28, verbatim resumido)

1. **Tasa de cambio no es problema de este repo.** Prevision-Funeraria, al
   configurar una empresa como multimoneda, se conecta a una API que consulta
   la tasa oficial Bs/USD todos los días — eso resuelve el manejo de tasa en
   el tenant. Cualquier fricción entre el precio de catálogo y lo que paga el
   cliente **se resuelve en Prevision-Funeraria, no en este repo**. Esto
   descarta el "blocker" de la Fase B2 (migración de moneda del historial
   importado) como algo que este plan deba bloquear o monitorear — es un
   asunto interno de PF.
2. **Corte inmediato.** El módulo PHP de Previsión no se va a usar más y debe
   **quitarse** del código activo (no solo ocultar el tab).
3. **Respaldo en una rama que nunca se mergea a `main`.**
4. **Login del panel admin de este sitio (aspectos de contenido — obituarios,
   directorio médico, recursos, FAQs) debe integrarse a futuro con el inicio
   de sesión de los usuarios del tenant `fdz` en Prevision-Funeraria** (SSO
   único). **Ese módulo de autenticación compartida no existe todavía del
   lado de Prevision-Funeraria** — queda como dependencia externa bloqueada,
   documentada abajo, sin fecha.

## Qué se ejecutó

### Estrategia de ramas

- **`archive/modulo-prevision-php`** — snapshot completo del repo con el
  módulo PHP de Previsión íntegro (código + las dos specs de migración
  anteriores), creada desde `feature/modulo-prevision` en el commit
  `1e9e0d5` y **pusheada a `origin`**. Esta rama es el respaldo pedido por el
  usuario. **Regla dura: nunca se mergea a `main`.** Si algún día hay que
  consultar cómo funcionaba `prevision_contratos.php` o cualquier otro
  archivo retirado, está completo ahí.
- **`feature/prevision-funeraria`** — rama nueva desde el mismo punto, donde
  se ejecutó el corte. Es la que continúa hacia `main` cuando corresponda.
  `feature/modulo-prevision` (la rama de trabajo original) queda intacta en
  el remoto, sin más commits — un respaldo adicional, aunque
  `archive/modulo-prevision-php` es la referencia oficial.

### Código retirado (26 archivos)

- `admin-prevision.js` (consola completa del panel).
- 16 endpoints `api/prevision_*.php`: `adjuntos`, `ajustes`, `catalogos`,
  `clientes`, `cobranza`, `contratos`, `import`, `mensajes`,
  `mercantil_callback`, `mercantil_webhook`, `pagos`, `planes`, `reportes`,
  `siniestros`, `solicitudes`, `vendedores`.
- `api/lib/prevision.php` (librería compartida del módulo).
- `api/cron/prevision_lapsar.php` (cron de auto-lapsado de contratos).
- `database/04_prevision.sql` a `database/10_prevision_pagos_electronicos.sql`
  (7 migraciones, tablas `prev_*`).

Confirmado antes de borrar (no se repite la investigación, ya estaba hecha):
estas tablas **nunca tuvieron datos reales de producción** — la migración de
datos real de Funerzul a Prevision-Funeraria ya se había ejecutado el
2026-08-19 desde `database/SIEMPRE.sql` (dump del legacy), no desde estas
tablas `prev_*`. No hubo nada que migrar al borrar.

`api/lib/payments/` (`PaymentProviderInterface`, `PaymentService`,
`MercantilProvider`, `SimuladoProvider`) **se conserva**, por la regla ya
vigente en `CLAUDE.md`: sigue siendo la referencia de diseño para el
adaptador de pagos de Prevision-Funeraria. Quedó con comentarios que ya no
apuntan a archivos existentes (`api/lib/prevision.php`) — es código de
referencia, no llamado por nada activo, así que no se corrigieron esas
referencias cruzadas; ver el propio archivo si hace falta entender el
acoplamiento original.

### Reemplazo del lead público

`api/prevision_solicitudes.php` tenía una acción pública (`crear`, usada por
el formulario del sitio) mezclada con acciones de staff. Se reemplazó por
**`api/pf_solicitud.php`** — solo la parte pública, sin escribir en MySQL
(la tabla `prev_solicitudes_publicas` ya no existe): reenvía el lead directo
a `POST /api/public/t/fdz/solicitudes` de Prevision-Funeraria vía
`api/lib/prevision_funeraria.php` (que se conserva sin cambios). Si
Prevision-Funeraria no responde, se le informa al visitante en vez de
fingir que quedó guardado — ya no hay respaldo local.
`partials/cta_pago_electronico.php` se actualizó para llamar a este nuevo
endpoint.

### Panel admin (`admin.html` / `admin.js`)

- Tab "Previsión" y su sección completa (~280 líneas) removidos de
  `admin.html`.
- `<script src="admin-prevision.js">` removido.
- `admin.js`: removido el hook `if (tab === 'prevision') Prevision.open()`.
- El resto del panel (Obituarios, Condolencias, Directorio Médico, Recursos,
  FAQs, Plantillas, Configuración, Usuarios) no se tocó.

### Despliegue

- `.cpanel.yml`: quitado `admin-prevision.js` de la lista de `cp -R` (si se
  hubiera dejado, el deploy habría fallado o copiado un archivo inexistente).
- Nadie tiene que tocar nada más en cPanel para este corte — no había cron
  job de `prevision_lapsar.php` documentado como configurado en producción
  (si el usuario sí lo configuró manualmente en cPanel → Cron Jobs, **hay
  que borrar esa entrada a mano**, este repo no puede hacerlo).

### Documentación actualizada

- `README.md` — árbol de archivos, manual de usuario (sección 14 reescrita
  como "retirado"), paso de instalación de BD.
- `database/README.md` — nota de archivado arriba del todo, tabla de
  esquema `prev_*` retirada, paso de instalación de BD corregido.
- `api/README.md` — tabla de endpoints de previsión reemplazada por una nota
  de archivado + el nuevo `pf_solicitud.php`; sección de integración con
  Prevision-Funeraria actualizada (ya no es "Fases A/B/C", es el estado
  activo real); nota nueva sobre el login del panel admin (ver abajo).
- `api/config.example.php` (y el `api/config.php` local de pruebas) — el
  bloque `payments.mercantil` se conserva como referencia, pero
  `return_url`/`cancel_url`/`notification_url` quedan vacíos (apuntaban a
  archivos retirados).
- Banner de "archivado" agregado a los documentos históricos del módulo:
  `docs/propuesta-mejoras-prevision.md`,
  `docs/specs/2026-08-11-mejoras-prevision-plan-tecnico.md`,
  `docs/specs/2026-08-11-prevision-ui-redesign.md`,
  `docs/prevision/plan-2026-08-07.md` — se conservan como referencia
  histórica, no se borraron (siguen completos en `main` tras el corte, a
  diferencia del código, que solo vive en la rama archive).

## Pendiente real: SSO del panel admin con Prevision-Funeraria

Decisión del usuario, no ejecutable hoy: el login de `admin.html` (gestión
de contenido del sitio — obituarios, directorio médico, recursos, FAQs)
debería integrarse con el inicio de sesión de los usuarios del tenant `fdz`
en Prevision-Funeraria, para que el staff tenga una sola cuenta en vez de
dos sistemas de login separados.

**Bloqueador real: ese módulo no existe en Prevision-Funeraria.** Hoy PF
solo tiene su propio login (`usuarios`/`usuario_tenant` en `DB_CONTROL`,
sesión propia vía cookie firmada — ver `src/auth/` del repo PF) sin ninguna
forma de que otro sitio (este) valide una sesión contra él (no hay OAuth,
no hay endpoint de verificación de token pensado para terceros, no hay
API pública de autenticación). Construirlo es trabajo nuevo **del lado de
Prevision-Funeraria**, no de este repo — no se puede planear en detalle
desde acá sin inventar un diseño que el equipo de PF no ha decidido.

**No se toca `api/auth.php` de este repo mientras tanto** — el panel admin
de contenido sigue con su propio login local (usuarios en MySQL,
`bcrypt`/sesión PHP nativa) hasta que exista algo del lado de PF con lo que
integrarse. Cuando ese módulo exista, retomar este punto con una spec nueva.

## Documentación a actualizar (hecho)

- [x] `README.md`
- [x] `database/README.md`
- [x] `api/README.md`
- [x] `docs/SPEC.md`
- [x] `ONBOARDING-AGENTES.md`
- [x] `.cpanel.yml`
- [x] `api/config.example.php`
