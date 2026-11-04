-- ============================================================================
-- 23. "CINTA CONMEMORATIVA" = LA TARJETA QUE HOY USA EL CLIENTE (2026-11-04)
-- ============================================================================
-- El cliente mandó la tarjeta que publica hoy (Canva, 900x1600):
-- - fondo navy con logo, cruz y cinta;
-- - "En memoria de";
-- - un recuadro abierto arriba con el nombre en mayúsculas, "PAZ A SU ALMA" y
--   las fechas "dd/mm/aaaa | dd/mm/aaaa";
-- - el ángel como marca de agua.
-- La plantilla anterior (12 + 13) no se le parecía. Esta la calca: fondo
-- img/obit-cinta-fondo.png (recortado de esa tarjeta, sin el texto) y estilos
-- .obit-cinta2-* de styles.css. Como la tarjeta descargable ahora se genera
-- desde la plantilla, la tarjeta queda igual a la del cliente.
--
-- Sin foto, igual que la tarjeta del cliente. Marcadores nuevos:
-- {{birth_date_short}} y {{death_date_short}}, que dan dd/mm/aaaa. Si de un
-- obituario viejo solo se cargó el año de nacimiento, sale el año.
--
-- UPDATE por nombre (el id puede variar entre bases). Se puede correr más de
-- una vez. Si la 12 no está importada, no encuentra la fila y no hace nada:
-- importar antes la 12 y después esta. Las clases viejas .obit-cinta-* siguen
-- en styles.css porque las usa la plantilla católica (20).

UPDATE obituary_templates
SET body_html = '<article class="obit-tpl obit-cinta2"><div class="obit-cinta2-card"><img class="obit-cinta2-angel" src="img/angel-marca-agua.svg" alt=""><p class="obit-cinta2-kicker">En memoria de</p><div class="obit-cinta2-box" aria-hidden="true"></div><div class="obit-cinta2-name"><h1>{{full_name}}</h1></div><p class="obit-cinta2-paz">Paz a su alma</p><p class="obit-cinta2-dates"><span class="d">{{birth_date_short}}</span><span class="sep">|</span><span class="d">{{death_date_short}}</span></p></div></article>',
    description = 'Tarjeta azul con la cinta conmemorativa, igual a la que usa la funeraria (formato historia 9:16, sin foto).'
WHERE name = 'Cinta Conmemorativa';
