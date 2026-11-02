Estado: **Implementada la variante católica (2026-11-02)**; las demás, propuestas.

# Plantillas de obituario por credo

## Problema

El cliente considera que la **Cinta Conmemorativa** es la plantilla que mejor
se ve, pero la cinta es un símbolo genérico (sin credo). Para un obituario
católico no funciona: la familia espera ver la **cruz**, y en la Cinta la cruz
es apenas una marca de agua casi invisible detrás de la cinta. Las otras
plantillas con cruz (Clásico Sobrio, Esquela Familiar) son mucho más sobrias.
Pide plantillas por credo que sean igual de vistosas.

## Qué se usa en cada credo (investigación)

| Credo | Símbolo central | Frases habituales | Evitar |
|---|---|---|---|
| **Católico** | Cruz latina (a veces crucifijo), rayos de luz, rosario, Virgen | "Descanse en paz", "Q.E.P.D.", "Dale, Señor, el descanso eterno", Juan 11,25 | Nada en particular |
| **Cristiano evangélico** | Cruz vacía (sin crucifijo), paloma, Biblia abierta | Versículo (Juan 11,25; Juan 14,2; Salmo 23), "Partió con el Señor", "Hasta el reencuentro" | Imágenes de santos y de la Virgen; "Q.E.P.D." es aceptable pero menos usado |
| **Judío** | Estrella de David, menorá, vela | "Que su memoria sea una bendición" (Z"L) | Cruces y **flores** (no son costumbre) |
| **Sin credo / genérico** | Cinta, flores, paloma, vela | "En memoria de", "Paz a su alma", "Siempre en nuestros corazones" | Símbolos religiosos |

Otros credos con presencia menor en el Zulia (musulmán: media luna, "A Dios
pertenecemos y a Él volvemos") se pueden agregar igual si el cliente los pide.

## Diseño

Todas las variantes **reutilizan la Cinta**: mismo fondo azul marino `#041D31`,
misma tipografía (Playfair para el nombre, cursiva para las frases), mismo
sello de agua del ángel y la misma tarjeta PNG 1080x1920 para redes. Solo
cambia la cabecera (símbolo) y el texto de cierre. Así todas se ven de la
misma familia y con el mismo nivel de acabado.

### Implementado: "Cruz de Luz (Católica)"

- Cabecera: cruz latina **dorada** (degradado metálico) con halo y rayos de
  luz, y el logo arriba a la izquierda, en lugar de la cinta.
- Cierre: "Descanse en paz", fechas, una línea dorada y la cita «Yo soy la
  resurrección y la vida; el que cree en mí, aunque muera, vivirá.» (Juan 11,25).
  Se eligió esta cita porque no depende del género del difunto.
- Archivos:
  - `database/20_plantilla_catolica.sql`: crea la plantilla (por nombre, sin id fijo).
  - `styles.css` (`.obit-catolica`): la cabecera se dibuja con CSS y un SVG, sin imágenes nuevas.
  - `api/lib/obituary_card.php` (`obit_card_catolica`): tarjeta PNG con GD.
  - `admin.js`: botón **Tarjeta ✝** en la lista de obituarios. El botón de la
    Cinta pasó a ser **Tarjeta 🎗️**.
- De paso se corrigió un error que también tenía la Cinta: "En memoria de" se
  pintaba oscuro sobre el azul (casi invisible), porque `.obit-detail p` le
  ganaba en especificidad.

### Propuestas (sin implementar, falta el visto bueno del cliente)

1. **Cristiana evangélica**: cruz vacía blanca/plateada con paloma, versículo a elegir.
2. **Judía**: Estrella de David dorada, "Que su memoria sea una bendición", sin flores.
3. **Sin credo**: la Cinta actual; opcional una variante con vela o con flores.

Cada una cuesta lo mismo que la católica: una migración, un bloque CSS y una
función de tarjeta PNG.

## Pendiente

- Importar `20_plantilla_catolica.sql` en el servidor.
- Probar la tarjeta PNG en el servidor: en el equipo de desarrollo PHP está
  bloqueado por Windows. El diseño se verificó con una simulación en canvas.
- La imagen de vista previa para redes (Open Graph, 1200x630) sigue siendo la
  de la Cinta para todas las plantillas. Si el cliente lo pide, puede tomar el
  símbolo de la plantilla asignada.
- Opcional: que el botón de tarjeta use automáticamente la plantilla asignada
  al obituario, en vez de un botón por estilo.
