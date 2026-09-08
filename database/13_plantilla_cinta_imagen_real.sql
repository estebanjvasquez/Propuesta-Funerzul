-- ============================================================================
-- 13. AJUSTA EL body_html DE "CINTA CONMEMORATIVA" (id 4) PARA USAR LA
--     IMAGEN REAL DE LA FUNERARIA COMO FONDO EN VEZ DE FORMAS DIBUJADAS
--     A MANO EN CSS.
-- ============================================================================
-- La primera versión (12_plantillas_esquela.sql) recreaba la cinta y la cruz
-- con CSS puro. El usuario compartió el diseño real (Canva) y no se parecía
-- lo suficiente -- ahora la plantilla usa img/obit-cinta-header.png (recorte
-- de esa imagen real: logo, cinta y cruz) como fondo. UPDATE, no INSERT: no
-- se toca 12_plantillas_esquela.sql (regla del repo de no editar
-- migraciones ya aplicadas) porque puede que ya la hayas importado.
-- Ver docs/specs/2026-09-08-tarjetas-obituario.md.
-- Seguro correrlo aunque la 12 todavía NO esté importada (simplemente no
-- encuentra la fila id=4 y no hace nada) -- en ese caso, importa primero
-- 12_plantillas_esquela.sql y después esta.

UPDATE obituary_templates
SET body_html = '<article class="obit-tpl obit-cinta"><div class="obit-cinta-header" aria-hidden="true"></div><div class="obit-cinta-body"><div class="obit-cinta-wing" aria-hidden="true"></div><p class="obit-cinta-kicker">En memoria de</p><h1>{{full_name}}</h1>{{photo_optional}}<p class="obit-qepd">Paz a su alma</p><p class="obit-dates">{{birth_year}} — {{death_date}}</p></div></article>'
WHERE id = 4 AND name = 'Cinta Conmemorativa';
