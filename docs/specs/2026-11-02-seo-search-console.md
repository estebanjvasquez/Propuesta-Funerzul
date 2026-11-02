Estado: **Diagnóstico (2026-11-02)**. Correcciones de código aplicadas; el resto, propuesto.

# Por qué el sitio no aparece mejor en Google (Search Console, 5-oct → 1-nov-2026)

Fuente: exportación "Queries: Organic Google Search query" de Google Analytics
(cuenta *Funeraria del Zulia*, **propiedad "Legado Holding"**), 76 búsquedas,
**18 clics** en 4 semanas. En esas fechas www.funerariadelzulia.com era todavía
el **WordPress**: el sitio nuevo no se ha publicado en el dominio (verificado en
vivo el 2026-11-02). Los datos miden el WordPress, no el sitio nuevo.

## 1. Lo que dicen los datos

| Búsqueda | Clics | Impr. | CTR | Posición | Lectura |
|---|---:|---:|---:|---:|---|
| funeraria del zulia | 6 | 59 | 10 % | **6,3** | El nombre exacto de la empresa debería salir 1º con CTR > 40 % |
| funeraria zulia | 4 | 67 | 6 % | 1,2 | Sale 1º pero casi nadie entra: el resultado no convence |
| funerarias en maracaibo | 2 | 58 | 3 % | 2,4 | Buena posición, poco atractivo |
| funeraria maracaibo | 1 | 40 | 2,5 % | 2,2 | Igual |
| crematorios del zulia | 4 | 41 | 10 % | 1,6 | Bien: es la única página temática que tiene el WordPress |
| funeraria | 0 | 36 | 0 | 7 | |
| funeraria zulia maracaibo | 0 | 15 | 0 | 3,5 | |
| sala velatoria / salas / sala de velación | 0 | 10 | 0 | 5-7 | No hay página de salas en el WordPress |
| tanatorio | 0 | 7 | 0 | 11 | |
| servicios funerarios (+ venezuela) | 0 | 11 | 0 | **15-38** | No hay página de servicios en el WordPress |
| obituarios | 0 | 3 | 0 | 12,7 | El WordPress no tiene obituarios |
| urna funeraria, cremación de mascotas | 0 | ~10 | 0 | 2-10 | Sin página; confirmar si se ofrecen |

## 2. Causas

1. **El WordPress casi no tiene páginas.** Solo portada, Nosotros, Preguntas,
   Familias Protegidas y Crematorios. Para "servicios funerarios", "sala
   velatoria", "tanatorio" u "obituarios" Google no tiene una página que
   mostrar, así que sale abajo o no sale. Donde sí hay página (Crematorios),
   el sitio queda 1º.
2. **Todas las páginas del WordPress tienen el mismo título principal (H1)**:
   "Desde 1942, haciendo de Cada Despedida un Homenaje a la vida". Ninguna
   dice "funeraria en Maracaibo", "cremación" ni "salas velatorias".
3. **El título que muestra Google para la portada no vende.** "Hacemos de la
   despedida un homenaje a la vida - Funeraria del Zulia" no dice Maracaibo,
   24 horas ni el teléfono: por eso el CTR es del 2-6 % aun estando 1º-2º.
4. **Datos estructurados débiles.** El WordPress solo declara `Organization`
   (sin dirección, horario ni teléfono como negocio local). El sitio nuevo
   declaraba `FuneralService`/`FuneralHome`, **tipos que no existen en
   schema.org**: Google los ignoraba. *Corregido hoy* (ver 3).
5. **La búsqueda de la marca en 6º lugar** suele indicar que delante salen
   la ficha de Google Maps, redes sociales, directorios o negocios de nombre
   parecido ("Funeraria Zulia"). Hay que verlo en Search Console:
   Rendimiento → filtrar la consulta → pestaña *Páginas*, y revisar que la
   **ficha de Google Business Profile** enlace a `https://www.funerariadelzulia.com/`.
6. **Copias de prueba indexables.** `nuevo.funerariadelzulia.com` y
   `legadoholding.com/funerzul/` servían el sitio nuevo con `index, follow`:
   contenido duplicado que compite con el dominio real. *Corregido hoy*.
7. **Propiedad de Analytics con otro nombre.** Los datos de Funeraria del
   Zulia están en una propiedad llamada "Legado Holding". Confirmar que es la
   de `funerariadelzulia.com` (la de Site Kit, `GT-PJ5S7WTQ`) y que el sitio
   nuevo envía sus datos a esa misma propiedad. Si no, se pierde el historial.

## 3. Corregido en el código (2026-11-02)

- JSON-LD: `FuneralService`/`FuneralHome` → `LocalBusiness` con
  `additionalType` de funeraria (portada, servicios, planes, Nosotros);
  el `publisher` de los obituarios pasa a `Organization`.
- `.htaccess`: cabecera `X-Robots-Tag: noindex, nofollow` solo para
  `nuevo.funerariadelzulia.com` y `legadoholding.com`.

## 4. Propuesta (por prioridad)

1. **Publicar el sitio nuevo en el dominio** (plan de
   `2026-10-28-migracion-dominio-final.md`). Por sí solo resuelve las causas
   1-3: ya tiene páginas propias para servicios, capillas velatorias,
   cremación, sepelio, traslados, crematorio, planes y obituarios, cada una
   con su título y su H1 ("Capillas Velatorias en Maracaibo", "Cremación en
   Maracaibo"…), y las redirecciones 301 del WordPress ya están en `.htaccess`.
2. **El día del corte, en Search Console**: enviar `sitemap.php`, pedir
   indexación de la portada, servicios y planes, y revisar a diario los 404
   durante 2 semanas.
3. **Google Business Profile** (lo que más pesa en "funeraria Maracaibo"):
   categoría principal *Funeraria*, secundaria *Crematorio*; sitio web, horario
   24 h, fotos de las capillas, y pedir reseñas a las familias atendidas. Si
   Crematorios del Zulia tiene sede propia, darle su propia ficha.
4. **Sinónimos que la gente usa**: ya se agregaron "sala velatoria", "sala de
   velación" y "tanatorio" a `servicios/capillas-velatorias.php` (2026-11-02).
   Falta una pregunta frecuente "¿Qué es un sepelio y qué incluye?" (desde el panel).
5. **Mantener obituarios activos y publicados** en el sitio: generan
   búsquedas con el nombre de cada difunto y enlaces desde redes.
6. **Solo si el cliente los ofrece**: páginas de *urnas y cofres* y de
   *cremación de mascotas* (hay búsquedas en posición 1-4 sin página).
7. Revisar resultados a las **4 y 8 semanas** del corte, con esta misma
   exportación como línea base.
