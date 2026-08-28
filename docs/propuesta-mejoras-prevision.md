# Propuesta de mejoras — Sistema de Previsión

> **Archivado (2026-08-28):** el módulo PHP de Previsión al que se refiere
> esta propuesta fue retirado (ver
> [`docs/specs/2026-08-28-fase-e-corte-admin-prevision.md`](specs/2026-08-28-fase-e-corte-admin-prevision.md)).
> Documento histórico — no implementar nada de esto aquí; cualquier mejora
> equivalente se evalúa del lado de Prevision-Funeraria.

**Para:** Funeraria del Zulia
**Fecha:** 11 de agosto de 2026
**Estado:** Propuesta para revisión (posterior al MVP actual)

---

## En resumen

Usted está revisando la primera versión (MVP) del sistema de previsión. Mientras
llegan sus comentarios, estudiamos cómo funcionan otras plataformas de pólizas
funerarias y de seguros para identificar mejoras que aporten valor real a su
operación y a sus clientes.

Este documento describe, en lenguaje sencillo, **seis mejoras propuestas**. No es
obligatorio hacerlas todas ni de una vez: las organizamos en **tres fases** para que
usted decida qué priorizar según su interés y presupuesto. El detalle técnico de cada
una queda en un documento aparte para el equipo de desarrollo.

---

## Las seis mejoras propuestas

| # | Mejora | Qué gana usted | Prioridad sugerida | Esfuerzo |
|---|--------|----------------|--------------------|----------|
| 1 | **Portal para el titular (autogestión)** | Menos llamadas y visitas: el cliente consulta y paga solo | Alta | Alto |
| 2 | **Gestión de reclamos más completa** | Control total del proceso cuando fallece un afiliado | Alta | Medio |
| 3 | **Renovación y reactivación de contratos** | Recuperar clientes que se atrasaron, sin perderlos | Alta | Bajo |
| 4 | **Captación y seguimiento de prospectos** | Que ningún interesado se pierda; más ventas cerradas | Media | Bajo |
| 5 | **Planes según edad y cobertura por beneficiario** | Precios más justos y planes más atractivos | Media | Medio |
| 6 | **Operación por sucursales / multiempresa** | Crecer a varias sedes o empresas con orden | Media | Alto |

---

### 1. Portal para el titular (autogestión)

**Qué es.** Una zona privada donde cada cliente titular entra con su cédula y una
clave que recibe por WhatsApp, y puede ver el estado de su contrato, sus pagos, la
próxima cuota, sus beneficiarios y **pagar en línea** sin llamar ni acudir a la
oficina.

**Qué gana usted.**
- Menos llamadas y visitas para preguntar "¿cuánto debo?" o "¿ya se registró mi pago?".
- Cobranza más ágil: el cliente paga desde su teléfono cuando quiera.
- Imagen moderna y confiable frente a la competencia.

**Qué gana el cliente.** Autonomía y tranquilidad: su información siempre a la mano,
24 horas.

---

### 2. Gestión de reclamos (siniestros) más completa

**Qué es.** Ampliar el manejo de un fallecimiento para llevarlo paso a paso: tipo de
reclamo, documentos de soporte, **monto aprobado frente a monto efectivamente pagado**,
fechas, y coordinación con los proveedores del servicio.

**Qué gana usted.**
- Claridad en cada caso: qué se aprobó, qué se pagó y qué falta.
- Respuestas más rápidas y menos errores en el momento más sensible para la familia.
- Registro ordenado para auditoría y control de costos.

---

### 3. Renovación y reactivación de contratos

**Qué es.** Hoy el sistema puede marcar un contrato como "caído" cuando el cliente
se atrasa. Esta mejora agrega el **camino de regreso**: un proceso claro para
reactivar contratos atrasados, con un período de gracia y las condiciones para volver
a ponerse al día.

**Qué gana usted.**
- Recuperar clientes en lugar de perderlos.
- Reglas claras y parejas para todos (menos decisiones caso por caso).

---

### 4. Captación y seguimiento de prospectos (leads)

**Qué es.** El sistema ya recibe solicitudes desde la página web. Esta mejora las
convierte en una **lista de seguimiento** con prioridad, para que su equipo comercial
contacte primero a los más interesados y no se pierda ninguna oportunidad.

**Qué gana usted.**
- Más solicitudes convertidas en contratos.
- Visibilidad de cuántos interesados llegan y en qué estado está cada uno.

---

### 5. Planes según edad y cobertura por beneficiario

**Qué es.** Permitir que el precio de un plan varíe según la **edad** del titular o
beneficiario, y definir un **monto de cobertura por cada beneficiario**, en vez de un
precio único para todos.

**Qué gana usted.**
- Precios más justos y competitivos.
- Planes más flexibles y atractivos para distintos perfiles de cliente.

**Nota.** Cualquier cambio de tarifas respeta los contratos ya firmados; aplicaría a
nuevas contrataciones.

---

### 6. Operación por sucursales / multiempresa

**Qué es.** Preparar el sistema para operar de forma ordenada en **varias sucursales**
o incluso varias empresas del grupo, con información separada por sede pero visible de
forma consolidada para la administración.

**Qué gana usted.**
- Crecer sin desorden: cada sede con su información, la gerencia con la vista total.
- Base para expandirse a nuevas ubicaciones o marcas.

**Nota.** Parte de esta capacidad ya se está construyendo en la nueva plataforma
multiempresa; aquí la mencionamos para que quede en el plan general.

---

## Plan por fases (sugerido)

Pensado para entregar valor pronto y sin arriesgar lo que ya funciona.

**Fase 1 — Impacto rápido**
- Renovación y reactivación de contratos (#3)
- Captación y seguimiento de prospectos (#4)

*Mejoras de bajo esfuerzo que se apoyan en lo que ya existe. Resultados visibles en
poco tiempo.*

**Fase 2 — Experiencia del cliente**
- Portal para el titular con pago en línea (#1)
- Gestión de reclamos más completa (#2)

*El salto más grande en servicio al cliente y en descarga de trabajo para su equipo.*

**Fase 3 — Crecimiento**
- Planes según edad y cobertura por beneficiario (#5)
- Operación por sucursales / multiempresa (#6)

*Mejoras estructurales para escalar el negocio.*

---

## Qué necesitamos de usted

1. **Sus comentarios del MVP actual** (lo que está revisando ahora).
2. **Cuáles de estas seis mejoras le interesan** y en qué orden.
3. Con eso preparamos un **cronograma y presupuesto** por fase.

No hace falta decidir todo hoy: podemos empezar por la Fase 1 y avanzar según los
resultados.

---

*El detalle técnico de cada mejora (cómo se implementa, qué se modifica y qué riesgos
hay) está documentado por separado para el equipo de desarrollo, y se consultará
cuando usted apruebe avanzar.*
