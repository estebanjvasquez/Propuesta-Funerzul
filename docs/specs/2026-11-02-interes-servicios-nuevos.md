Estado: **Implementado (2026-11-02)**, pendiente de desplegar con la migración 21.

# Medir el interés del público en servicios nuevos (primero: cremación de mascotas)

## Pedido

La empresa no ofrece cremación de mascotas y quiere saber si vale la pena
invertir. Hay que medir el interés del público en ese servicio y en otros
relacionados, por las preguntas frecuentes, los buscadores u otra vía.

## Cómo se mide: cuatro señales

| Señal | Qué cuenta | Dónde se ve |
|---|---|---|
| **Búsquedas en Google** | Impresiones, clics y posición de búsquedas como "cremación de mascotas maracaibo" que llegan a la página nueva. Antes del corte ya había búsquedas de "cremación de mascotas" en posición 2-10 sin página propia (ver `2026-11-02-seo-search-console.md`). | Search Console → Rendimiento → filtro Página = `servicios/cremacion-mascotas.php` |
| **Visitas a la página** | Personas por día (cookie sin datos personales) y visitas totales, sin robots. | Panel → Sistema → Interés del público |
| **Pregunta frecuente** | Aperturas de "¿Ofrecen cremación de mascotas?" en la portada (una por visita). De paso se miden todas las preguntas: ranking de dudas del público. | Mismo lugar |
| **Formulario y contacto** | Respuestas a "Cuéntenos qué necesitaría": tipo de mascota, urgencia, servicios relacionados que interesan, zona. Contacto opcional con consentimiento. Clics en WhatsApp y Llamar. | Mismo lugar, con detalle y descarga CSV |

Los servicios relacionados se miden dentro del mismo formulario: cremación
individual o colectiva, retiro a domicilio, urnas y recuerdos, espacio de
despedida y plan de previsión con mascota. Si GA4 está configurado, también
se envían los eventos `interes_servicio` y `faq_abierta`.

## Piezas

- `database/21_interes_servicios.sql`: tablas `interest_daily` (contadores
  agregados) e `interest_responses`, columna `faqs.interest_key` y la pregunta
  frecuente nueva.
- `api/lib/interest.php`: catálogo `INTEREST_SERVICES` (servicios y sus
  preguntas), filtro de robots y contadores.
- `api/interest.php`: `track` y `respond` (públicos), `stats`, `responses`
  (con CSV) y `delete_response` (solo `sitio.admin`).
- `servicios/cremacion-mascotas.php` + `partials/interes_servicio.php`: la
  página "Próximamente" con el formulario. Está en el sitemap y enlazada desde
  Servicios y desde la pregunta frecuente.
- `app.js` (`initFaqTracking`): cuenta las aperturas de las preguntas frecuentes.
- Panel: pestaña **Sistema → Interés del público** (`admin.js`, `loadInterest`)
  y capítulo nuevo del manual.

## Cuidados

- La página dice claramente que el servicio **todavía no se presta**: no
  publica precio, no promete fechas y no lleva datos estructurados de servicio.
- Datos personales: el contacto es opcional y exige casilla de autorización.
  Sin contacto no se guarda el nombre. La IP solo se usa para limitar a 5
  envíos por hora. Cada respuesta con contacto avisa a `notify_email`.
  Las respuestas se pueden borrar desde el panel.
- Los contadores públicos (`track`) se pueden inflar a mano. Por eso la cifra
  que más pesa para decidir son las **respuestas con contacto** y las búsquedas
  de Search Console, no las visitas.

## Cómo decidir (propuesta para la dirección)

Revisar a los 60 y 90 días del corte del dominio:
1. Búsquedas mensuales en Search Console para "cremación de mascotas" y variantes.
2. Respuestas con contacto, y cuántas dicen "ahora o en los próximos días".
3. Qué combinación piden más (individual con cenizas + retiro a domicilio, etc.)
   para dimensionar el equipo y el precio.

Para evaluar otro servicio: agregarlo a `INTEREST_SERVICES`, crear su página
con el mismo partial y, si se quiere, una pregunta frecuente con su `interest_key`.
Si se descarta la cremación de mascotas: quitar la página, su línea del sitemap
y la pregunta frecuente (o dejarla con la respuesta "no ofrecemos").
