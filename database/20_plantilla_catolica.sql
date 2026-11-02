-- ============================================================================
-- 20. PLANTILLA DE OBITUARIO "CRUZ DE LUZ (CATÓLICA)"
-- ============================================================================
-- Variante de la "Cinta Conmemorativa" (mismo azul marino, misma tipografía,
-- mismo sello de agua) para familias católicas: la cinta genérica se
-- reemplaza por una cruz latina dorada con rayos de luz, y el cierre lleva
-- "Descanse en paz" y una cita de Juan 11,25. Pedido del cliente 2026-11-02:
-- la Cinta es la que mejor se ve, pero no sirve para un obituario católico
-- porque le falta la cruz. Ver docs/specs/2026-11-02-plantillas-obituario-por-credo.md.
--
-- No usa id fijo (el admin pudo haber creado plantillas propias): inserta por
-- nombre si no existe. Queda sin predeterminar (is_default = 0). Idempotente.
-- El estilo vive en styles.css (.obit-catolica); la tarjeta PNG para redes,
-- en api/lib/obituary_card.php (style=catolica).

INSERT INTO obituary_templates (name, description, is_default, body_html)
SELECT 'Cruz de Luz (Católica)',
       'Azul marino con cruz dorada y rayos de luz, "Descanse en paz" y Juan 11,25 -- variante católica de la Cinta Conmemorativa.',
       0,
       '<article class="obit-tpl obit-cinta obit-catolica"><div class="obit-catolica-header" aria-hidden="true"><div class="obit-catolica-rays"></div><div class="obit-catolica-logo"></div><svg class="obit-catolica-cross" viewBox="0 0 120 180"><defs><linearGradient id="obitGold" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#9C7214"/><stop offset="0.45" stop-color="#F7DFA0"/><stop offset="0.55" stop-color="#E8C46C"/><stop offset="1" stop-color="#9C7214"/></linearGradient></defs><rect x="48" y="0" width="24" height="180" rx="2" fill="url(#obitGold)"/><rect x="10" y="44" width="100" height="24" rx="2" fill="url(#obitGold)"/></svg></div><div class="obit-cinta-body"><div class="obit-cinta-wing" aria-hidden="true"></div><p class="obit-cinta-kicker">En memoria de</p><h1>{{full_name}}</h1>{{photo_optional}}<p class="obit-qepd">Descanse en paz</p><p class="obit-dates">{{birth_year}} — {{death_date}}</p><p class="obit-catolica-verse">«Yo soy la resurrección y la vida; el que cree en mí, aunque muera, vivirá.»<span>Juan 11, 25</span></p></div></article>'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM obituary_templates WHERE name = 'Cruz de Luz (Católica)');
