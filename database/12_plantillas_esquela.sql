-- ============================================================================
-- 12. DOS PLANTILLAS DE OBITUARIO NUEVAS: "CINTA CONMEMORATIVA" Y "ESQUELA
--     FAMILIAR" -- adaptación de los dos formatos que la funeraria ya usa
--     hoy fuera del sistema (Canva/Photoshop), para la página web pública.
-- ============================================================================
-- No agrega tablas: obituary_templates ya existe desde 01_schema.sql. Usa
-- ids 4 y 5 -- si ya creaste plantillas personalizadas desde el panel con
-- esos ids exactos, ajusta los números antes de importar. Ninguna queda
-- como predeterminada (is_default = 0): el admin la asigna manualmente
-- desde "+ Nuevo obituario" o "Editar" cuando corresponda.
-- Ver docs/specs/2026-09-08-tarjetas-obituario.md.

INSERT IGNORE INTO obituary_templates (id, name, description, is_default, body_html) VALUES
  (4, 'Cinta Conmemorativa',
   'Fondo azul marino con cinta y marca de agua del ángel -- estilo tarjeta de duelo para compartir.', 0,
   '<article class="obit-tpl obit-cinta"><div class="obit-cinta-wing" aria-hidden="true"></div><div class="obit-cinta-cross">✝</div><p class="obit-cinta-kicker">En memoria de</p><h1>{{full_name}}</h1>{{photo_optional}}<p class="obit-qepd">Paz a su alma</p><p class="obit-dates">{{birth_year}} — {{death_date}}</p></article>'),
  (5, 'Esquela Familiar',
   'Blanco y negro, con cruz e invitación al servicio velatorio -- estilo esquela tradicional.', 0,
   '<article class="obit-tpl obit-esquela"><div class="obit-esquela-cross">✝</div><p class="obit-esquela-lead">Participamos con profundo pesar el sensible fallecimiento de</p><h1>{{full_name}}</h1>{{photo_optional}}<p class="obit-bio">{{biography}}</p><div class="obit-esquela-invite"><strong>Invitamos al servicio velatorio en</strong><br>Funeraria del Zulia<br>{{location_name}}<br>{{event_schedule}}</div></article>');
