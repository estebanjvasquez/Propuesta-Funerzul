# task.md — Bitácora operativa (Propuesta Funerzul)

> Entradas más recientes **primero**. Cada cierre de sesión agrega una entrada
> arriba (no se edita el historial). Para contexto estable del proyecto (qué
> es, ramas, límites con los repos hermanos) lee primero
> [`ONBOARDING-AGENTES.md`](ONBOARDING-AGENTES.md) — es corto a propósito. Para
> el detalle funcional/técnico vigente, [`docs/SPEC.md`](docs/SPEC.md).
>
> Este archivo puede crecer mucho — no hace falta leerlo completo para
> retomar el trabajo. Basta la entrada de más arriba.

---

## Cierre: 2026-11-03 (2) — Prevision-Funeraria: recuperar contraseña y domiciliación Mercantil

**Rama:** sin cambios de código en este repo; solo docs (`SPEC.md` al día con
el corte, el SSO y Mercantil). El trabajo fue en
`estebanjvasquez/Prevision-Funeraria` (`main`, pusheado). Su bitácora propia
tiene el detalle: `Prevision-Funeraria/task.md`.

**Hecho (2026-11-02):**
- PF `dab51f9` (desplegado): `/recuperar` mostraba juntos el formulario del
  correo y el de la clave nueva; ahora son dos pantallas, con confirmación y
  botón de "ojo" en todos los campos de contraseña.
- PF `1b569bc`: formatos de Mercantil (afiliación, cobranza en cuenta y sus
  respuestas) verificados contra los archivos reales de FDZ. El cobro
  generado es idéntico byte por byte al lote real; las respuestas reales
  cruzan completas. Tope de fecha valor 30 días; lote de afiliación con ceros.
- **Los 4 formatos quedaron cargados y ACTIVOS en producción para `fdz`**
  (versiones 1-4 del banco Mercantil, id 3), vía la API del diseñador con la
  sesión de admin del usuario. Verificado: 30/45/5/8 campos, 0 problemas.

**Validaciones:** PF 530/530 pruebas y `tsc` limpio. Comparación con los
archivos reales hecha fuera del repo (tienen datos de clientes; carpeta
`docs/domiciliacion/Mercantil/` en `.gitignore` de PF).

**Bloqueos:** para el piloto faltan datos que pone el usuario en PF:
RIF de la empresa (vacío), cuenta recaudadora Mercantil y convenio.
Tabla completa de códigos de respuesta: pedirla a Mercantil.

**Siguiente acción (Mercantil, sin volver a verificar nada de lo anterior):**
1. Cuando el usuario cargue RIF, cuenta y convenio: lote de afiliación de
   prueba con pocos contratos → enviar al banco → importar respuesta.
2. Formato DESAFILIACION (= afiliación con `D` en la posición 2).

---

## Cierre: 2026-11-03 (corte a www.funerariadelzulia.com en curso)

**Rama:** `feature/prevision-funeraria`, último commit de código `c622b3d`,
pusheada. Sin mergear a `main`. Las sesiones del 2026-10-27 al 2026-11-02 no
dejaron entrada aquí: su trabajo está en los commits `9ce5234` a `f01d3d9` y
en `docs/BACKLOG.md`.

**Estado del sitio (retomar desde aquí, sin volver a comprobar):**
- El sitio nuevo **ya está en la raíz de `www.funerariadelzulia.com`**. El
  despliegue de cPanel del 2026-11-02 fue a `public_html/` porque existía
  `_wp-anterior/` (lógica de `.cpanel.yml`). El usuario copió
  `api/config.php` y `uploads/` desde `public_html/nuevo.funerariadelzulia.com/`.
- Verificado en vivo el 2026-11-03 con curl:
  - `robots.txt` correcto, con `Sitemap: https://www.funerariadelzulia.com/sitemap.php`.
  - Redirecciones 301: sin www → www; http → https; `sitemap.xml` y
    `wp-sitemap.xml` → `sitemap.php`; `servicios/sepelio-tradicional.php` →
    `funeral-tradicional.php`.
  - `wp-login.php` → 410.
  - `nuevo.funerariadelzulia.com` y `www…/nuevo.funerariadelzulia.com/` con
    `X-Robots-Tag: noindex`.
  - La portada tiene canonical www.
- **Problema abierto:** `app.site_url` del `config.php` de producción sigue
  en `https://nuevo.funerariadelzulia.com`. Por eso las páginas internas
  (servicios, planes, Nosotros, Crematorio) y **todo el sitemap** declaran
  canonical y og:url con `nuevo.`. Se le explicó al usuario cómo corregirlo.
  No se sabe si ya lo hizo.
- `obituarios.php`, `directorio-medico.php` y `recursos.php` → 404: esas
  secciones están apagadas en el panel.
- Sin confirmar: si WordPress quedó **movido** (no borrado) a `_wp-anterior/`
  con `Require all denied`, si se quitó `auto_prepend_file` de Wordfence,
  si se importaron las migraciones 20-21 y si se desplegaron `ea624ec` y
  `c622b3d`.

**Hecho en esta sesión (2026-11-02 → 03):**
- Medición del interés en cremación de mascotas, manual al día y plan de
  onboarding (artifact), en `336d1c6`.
- Plan de corte reescrito para la cuenta única de FDZ y cómo mover
  WordPress (`f01d3d9`).
- Textos del cliente (`93b6e67`): hero, Sepelio → **Funeral Tradicional**
  (nueva URL + 301), Crematorios del Zulia "en el Estado Zulia", nuevos
  pasos y ventajas, Vanguardia sin "con ataúd", Grupo Selecto "Único en
  Venezuela".
- Crematorios del Zulia desde 2021, no 1942 (`aa9adb0`). Sello corporativo
  en la imagen de la página (`ea624ec`).
- `SITEMAP_STATIC_LASTMOD` = 2026-11-03 (`c622b3d`).
- Se le dio al usuario la lista de URLs para Search Console. Está copiada en
  `docs/BACKLOG.md` → "Retomar aquí".

**Validaciones:**
- Portada y la imagen del crematorio revisadas en Chromium (copia estática:
  no hay PHP local).
- Sitio en vivo revisado con curl (ver arriba).
- PHP de las páginas nuevas no probado localmente. En producción cargan
  (200).

**Bloqueos:**
- `site_url` de producción (lo corrige el usuario en cPanel).
- Heredados: merge a `main` sin decidir; catálogo `fdz` en PF; Mercantil.

**Siguiente acción:**
1. Preguntar al usuario si ya corrigió `site_url`. Luego comprobar con
   `curl -s https://www.funerariadelzulia.com/sitemap.php`: solo debe listar
   `www`.
2. Preguntar el estado de WordPress, Wordfence y las migraciones 20-21, y si
   desplegó `c622b3d`.
3. Search Console: enviar el sitemap y pedir indexación según la lista del
   backlog.
4. Encender la sección Obituarios si el cliente la quiere publicada.
5. Después: tareas posteriores al corte de
   `docs/specs/2026-10-28-migracion-dominio-final.md`, sección 7.

Detalle: [`docs/BACKLOG.md`](docs/BACKLOG.md), sección "▶ Retomar aquí".

---

## Cierre: 2026-10-08 (recuperación tras pérdida del equipo)

**Rama:** `feature/prevision-funeraria` (`e7e24ee` + el commit de esta
entrada; sin mergear a `main`, que sigue en `605235d`).

**Contexto:** el equipo de trabajo anterior se dañó. El repo se volvió a
clonar en un equipo nuevo, quedó en `main` (desactualizada) y sin
`api/config.php`. Además, el usuario **ya no tiene acceso a Notion**.

**Hecho hoy:**
- Contexto reconstruido desde GitHub (ramas, mensajes de commit, specs) y
  desde Notion (última lectura posible: hub y Backlog sin cambios desde el
  2026-08-27). Checkout local cambiado a `feature/prevision-funeraria`.
- Esta bitácora y Notion no registraban nada posterior al 2026-08-27; se
  agregó la entrada retroactiva de abajo (28-ago → 13-sep) a partir de los
  commits.
- **Notion retirado del flujo de trabajo.** El Backlog se migró a
  [`docs/BACKLOG.md`](docs/BACKLOG.md) (8 tarjetas de Notion con estado
  actualizado + tareas nuevas). Se actualizaron `CLAUDE.md`,
  `ONBOARDING-AGENTES.md`, `docs/SPEC.md` y la skill
  `.claude/skills/cerrar-proyecto/` para que el cierre de jornada ya no
  dependa de Notion.
- Inventario de lo perdido (nunca estuvo en ninguna rama de GitHub):
  `AGENTS.md`, `docs/agent-standards.md`, `docs/frontend-standards.md`,
  `docs/spec-template.md`, `docs/specs/README.md`, `docs/MercantilAPI.md`,
  `codex.md`, `docs/legado-holding-prevision/`, `graphify-out/`; y los
  archivos locales fuera de git `api/config.php` y `database/SIEMPRE.sql`.

**Validaciones (todas de solo lectura, en vivo):**
- GitHub: `ls-remote`, `fetch` y `push --dry-run` OK; credenciales en
  Windows Credential Manager. Git ya estaba en el PATH de usuario
  (`%LOCALAPPDATA%\Programs\Git\cmd`); VS Code necesita reiniciarse para
  verlo.
- Worker Prevision-Funeraria: `GET fdz/planes` 200 (9 planes, cada uno con
  campo `mostrar_precio`), `GET fdz/servicios` 200 con `items: []`,
  `GET lh/servicios` 200 (3 ítems), `POST fdz/solicitudes` vacío → 400.
- **MySQL del sitio de prueba caído**: `api/obituaries.php`,
  `api/templates.php` y `obituarios.php` → 500 con
  `SQLSTATE[HY000] [2002] Resource temporarily unavailable`, 6/6 intentos.
  Portada y páginas de planes cargan (degradan sin BD); `auth.php` 200.
- El servidor tiene `app.env = development` (la API expone el mensaje crudo
  de PDO) y `site_url` sigue mal (`https://www.https://legadoholding.com/...`
  en canonical/og:url).
- No hay PHP ni MySQL instalados en el equipo nuevo — no se corrió nada
  local.

**Bloqueos:**
- MySQL del sitio de prueba caído (nuevo — requiere acceso a cPanel).
- `api/config.php` del servidor: `site_url` mal y `env` en development
  (requiere acceso al servidor).
- Heredados: merge a `main` sin decidir; SSO bloqueado por
  Prevision-Funeraria; spec de Mercantil pendiente del banco; catálogo de
  servicios de `fdz` vacío y Tradición < Esencial (ambos en PF).

**Siguiente acción:**
1. Revisar MySQL en cPanel (phpMyAdmin) y restablecer la conexión.
2. En el mismo acceso: corregir `site_url`, poner `env` en `production` y
   bajar una copia de `api/config.php` (fuera del repo).
3. Con la BD arriba: aplicar/probar migraciones 11-13 y las tarjetas de
   obituario.
4. Recrear `AGENTS.md` y los estándares perdidos (o quitar sus referencias).

Detalle y estado de cada tarea: [`docs/BACKLOG.md`](docs/BACKLOG.md).

---

## Retroactiva: 2026-08-28 → 2026-09-13 (reconstruida el 2026-10-08)

> Estas sesiones no dejaron entrada en `task.md` ni en Notion. Se
> reconstruyó desde los mensajes de commit de `feature/prevision-funeraria`
> y las specs correspondientes — no desde la conversación original.

**2026-08-28 — corte del módulo PHP de previsión** (`4dc83cb`): retirados 26
archivos (`admin-prevision.js`, 16 `api/prevision_*.php`, `api/lib/prevision.php`,
cron de lapsado, migraciones 04-10). Respaldo íntegro en
`archive/modulo-prevision-php` (nunca se mergea a `main`). Nuevo
`api/pf_solicitud.php`: los leads van directo a Prevision-Funeraria
(tenant `fdz`). Se conserva `api/lib/payments/` como referencia de diseño.
Spec: `docs/specs/2026-08-28-fase-e-corte-admin-prevision.md`.

**2026-09-08 — sitio y obituarios:**
- Secciones activables/desactivables (Obituarios, Directorio Médico,
  Recursos, FAQs) desde Configuración + marca de agua del ángel en el hero
  (`5c5d27b`, migración `11_secciones_toggle.sql`).
- Plantillas "Cinta Conmemorativa" y "Esquela Familiar" + tarjeta PNG
  1080×1920 descargable generada con GD (`5e4c075`, migración
  `12_plantillas_esquela.sql`, fuentes en `assets/fonts/`). Ajustada para
  usar la imagen real de la cinta (`19594ef`, migración `13_...`).
- `api/diag.php` verifica la migración 12 y GD/FreeType (`ee86856`).
- Dos bumps de cache-busting: Cloudflare servía `styles.css` viejo
  (`51415d5`, `3620529`).
- Sin prueba contra MySQL real (solo `php -l` y pruebas locales del GD).

**2026-09-09 — auditoría SEO** (`e08cee2`, `b5579a0`): dominio sin
hardcodear en `index.php`, 301 `/index.php` → `/`, `Options -Indexes`,
`noindex` en búsquedas/filtros, hero auto-hospedado en WebP, fuentes sin
`@import`, breadcrumbs JSON-LD, `og:site_name`/`twitter:card`, sitemap con
imágenes, títulos/descripciones recortados, contraste del footer, enlaces
públicos a `admin.html` quitados. Pendiente fuera del repo: `site_url` del
servidor. Spec: `docs/specs/2026-09-09-auditoria-seo-funerzul.md`.

**2026-09-13 — Prevision-Funeraria y marca:**
- Aviso por correo al staff al crear un lead (`3806e7c`, `api/lib/mail.php`,
  clave `app.notify_email`). Verificado en vivo que la integración con PF
  está activa en el servidor.
- Favicon real de la marca (`a489bc9`).
- Interruptor `prevision_funeraria.show_prices` (false por defecto) para no
  publicar precios de planes, igual que el sitio WordPress actual
  (`e7e24ee`).
- Servicios reales de funerariadelzulia.com documentados en la spec de
  migración (`549eade`).

---

## Cierre: 2026-08-27 (verificación del sistema de cierre)

**Rama:** `feature/modulo-prevision` (1 commit sin push desde el cierre
anterior de hoy; 26 commits vs. `main`, sin cambios respecto a la entrada
previa).

**Hecho hoy:** Nada nuevo — el usuario pidió "cerrar proyecto" para probar el
flujo recién construido (`.claude/skills/cerrar-proyecto/`). Se ejecutó el
procedimiento completo (esta entrada, verificación de Notion) para
confirmar que funciona de punta a punta. No hubo cambios de código ni de
documentación más allá de esta entrada.

**Validaciones:** `git status`/`git log` confirman que no hay commits ni
archivos nuevos desde el cierre anterior (commit `c39cee3`). No se corrió
build/test — no aplica a un cierre sin cambios de código.

**Bloqueos:** sin cambios — los mismos de la entrada anterior (merge a
`main` sin decidir, spec de Mercantil pendiente del banco, proveedor de
mensajería sin contratar, rol futuro del módulo PHP frente a
`Prevision-Funeraria`).

**Siguiente acción:** las mismas de la entrada anterior — ver Backlog en
Notion (sin cambios de estado en esta verificación).

**Notion:** [Registro de Proyecto — 27 de agosto, 2026 (verificación)](https://app.notion.com/p/3c96989da22e8187bc65ed97075190cb)

---

## Cierre: 2026-08-27

**Rama:** `feature/modulo-prevision` (25 commits sin mergear a `main`; sin
decisión tomada sobre si mergear o seguir trabajando ahí — ver "Bloqueos").

**Hecho hoy:**
- Se armó un paquete de documentación (`docs/legado-holding-prevision/`, 7
  archivos, ~1000 líneas) para llevar el módulo de previsión al repo hermano
  `legado-holding`. **Se perdió sin commitear** — el entorno de la sesión se
  reinició antes de guardarlo en git. Queda como pendiente rehacer (ver
  Trabajo pendiente).
- Se investigó el estado real del repositorio (no existía `task.md` en ese
  momento) y se creó `ONBOARDING-AGENTES.md` en la raíz: punto de entrada
  corto para cualquier agente nuevo — estado de ramas, relación con
  `legado-holding` y `Prevision-Funeraria`, mapa de qué documento leer según
  la tarea, reglas de trabajo y superficies sensibles. Referenciado desde
  `README.md`.
- Ese cambio (`ONBOARDING-AGENTES.md` + referencia en `README.md`) se llevó
  también a `main` vía un `git worktree` aislado, sin tocar el árbol de
  trabajo de esta rama — commit [`605235d`](https://github.com/estebanjvasquez/Propuesta-Funerzul/commit/605235d)
  en `origin/main`.
- Se estableció el sistema de "spec + retomar con bajo consumo de tokens +
  cierre de jornada", reutilizando el patrón ya probado en los repos
  hermanos (`Prevision-Funeraria`, `EventPass VE` en Notion):
  - Este archivo (`task.md`) como bitácora cronológica local.
  - `docs/SPEC.md` como especificación funcional/técnica vigente del
    proyecto completo (nuevo — no confundir con `docs/PLAN.md` de
    `Prevision-Funeraria`, que es otro repo y otro documento; por eso este
    archivo se llama `SPEC.md` y no `PLAN.md`).
  - `ONBOARDING-AGENTES.md` actualizado con referencias a ambos.
  - `CLAUDE.md` actualizado con un puntero a `ONBOARDING-AGENTES.md` (carga
    automática en cada sesión) y la regla de cierre de jornada.
  - Skill `.claude/skills/cerrar-proyecto/` con el procedimiento exacto de
    cierre (actualizar docs locales + sincronizar Notion).
  - Notion: página hub **"Propuesta Funerzul — Proyecto y documentación"**
    + base de datos **Backlog** + esta primera entrada de "Registro de
    Proyecto", siguiendo el mismo formato que ya usan los repos hermanos.

**Validaciones:**
- `git status`/`git log` revisados antes y después de cada cambio para no
  perder ni mezclar trabajo pendiente de otras tareas.
- Confirmado que el árbol de trabajo de `feature/modulo-prevision` no se
  tocó al publicar en `main` (mismo listado de archivos sin commitear antes
  y después, más los dos archivos nuevos de onboarding).
- No aplica build/test automatizado — este repo no tiene suite (ver
  `docs/backend-standards.md`, sección Pruebas).

**Bloqueos:**
- Sin decidir: ¿merge de `feature/modulo-prevision` a `main`, o se sigue
  trabajando sin mergear? (heredado, ver `docs/resume.md`).
- Spec completa de Mercantil pendiente del banco (esquema `POST /api` +
  MasterKey del webhook) — heredado, ver commit `d8ffed0`.
- Proveedor de mensajería WhatsApp/SMS sin contratar — heredado.

**Siguiente acción:**
1. Si se retoma la integración con `legado-holding`: rehacer
   `docs/legado-holding-prevision/` **y commitearlo apenas esté listo**
   (no esperar a tener todo el paquete completo).
2. Definir si `feature/modulo-prevision` se mergea a `main` o sigue como
   rama de trabajo de largo plazo.
3. Empezar a usar `docs/SPEC.md` como documento vigente en vez de releer
   `docs/resume.md` completo (que queda como histórico de las rondas hasta
   2026-07-07).

**Notion:** [Registro de Proyecto — 27 de agosto, 2026](https://app.notion.com/p/3c96989da22e818a9606cde42e22ffdf)
· [Hub del proyecto](https://app.notion.com/p/3c96989da22e81bcbfd9e7b80e017054)
· [Backlog](https://app.notion.com/p/b16fb5ea38b4461c99156a5f6e777d97) (8
tarjetas cargadas, todas `Not started`).
