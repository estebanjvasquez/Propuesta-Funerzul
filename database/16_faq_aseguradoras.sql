-- ============================================================================
-- 16. PREGUNTA FRECUENTE: ATENCIÓN A PÓLIZAS, CONTRATOS COLECTIVOS Y OTRAS
--     PREVISIONES
-- ============================================================================
-- Pedido del cliente del 2026-10-27. Se agrega al final de la lista (orden =
-- mayor actual + 10). Idempotente: no la duplica si ya existe una pregunta con
-- el mismo texto (por ejemplo, si ya se cargó a mano desde el panel). Se puede
-- reordenar, editar o pausar luego desde la pestaña "Preguntas" del panel.

-- El MAX() va en una tabla derivada de una sola fila: así el NOT EXISTS
-- filtra esa fila. (Con el MAX() directo en el SELECT, un agregado sin filas
-- igual devuelve una fila y la pregunta se duplicaría.)
INSERT INTO faqs (question, answer, sort_order, is_active)
SELECT
  '¿Atienden a familias con una póliza de seguro de vida, un contrato colectivo u otro plan de previsión funeraria?',
  'Sí. Gracias a nuestra reconocida solvencia, somos la aliada por excelencia en el Zulia para prestar servicios funerarios integrales a las mejores empresas de seguros nacionales, a los call centers de contratos corporativos y a empresas de previsión nacionales e internacionales.',
  t.siguiente,
  1
FROM (SELECT COALESCE(MAX(sort_order), 0) + 10 AS siguiente FROM faqs) AS t
WHERE NOT EXISTS (
  SELECT 1 FROM (SELECT question FROM faqs) AS f
  WHERE f.question = '¿Atienden a familias con una póliza de seguro de vida, un contrato colectivo u otro plan de previsión funeraria?'
);
