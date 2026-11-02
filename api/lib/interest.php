<?php
/**
 * Medición del interés del público en servicios que la empresa todavía no
 * ofrece (migración 21, docs/specs/2026-11-02-interes-servicios-nuevos.md).
 *
 * Para evaluar un servicio nuevo: agregarlo a INTEREST_SERVICES (con sus
 * preguntas), crear su página con partials/interes_servicio.php y, si se
 * quiere, una pregunta frecuente con faqs.interest_key = su clave.
 */
if (!defined('OBIT_APP')) { http_response_code(403); exit('Forbidden'); }

const INTEREST_SERVICES = [
    'cremacion-mascotas' => [
        'label' => 'Cremación de mascotas',
        'page'  => 'servicios/cremacion-mascotas.php',
        'questions' => [
            'mascota' => [
                'label' => '¿Qué mascota tiene?', 'multi' => false,
                'options' => ['perro' => 'Perro', 'gato' => 'Gato', 'otra' => 'Otra'],
            ],
            'cuando' => [
                'label' => '¿Cuándo lo necesitaría?', 'multi' => false,
                'options' => [
                    'ahora'  => 'Ahora o en los próximos días',
                    'futuro' => 'Más adelante: quiero tenerlo previsto',
                    'info'   => 'Solo quiero información',
                ],
            ],
            'intereses' => [
                'label' => '¿Qué le interesaría? (puede marcar varias)', 'multi' => true,
                'options' => [
                    'individual' => 'Cremación individual con entrega de las cenizas',
                    'colectiva'  => 'Cremación colectiva, más económica (sin entrega de cenizas)',
                    'domicilio'  => 'Retiro de la mascota en casa o en la clínica veterinaria',
                    'urna'       => 'Urna o recuerdo (huella, relicario, joya con cenizas)',
                    'despedida'  => 'Un espacio para despedirse de la mascota',
                    'plan'       => 'Un plan de previsión que incluya a la mascota',
                ],
            ],
            'zona' => [
                'label' => '¿Dónde vive?', 'multi' => false,
                'options' => [
                    'maracaibo'     => 'Maracaibo',
                    'san-francisco' => 'San Francisco',
                    'costa-oriental'=> 'Costa Oriental del Lago',
                    'otro'          => 'Otro municipio',
                ],
            ],
        ],
    ],
];

/** Eventos que la página pública puede enviar por JavaScript (los demás los cuenta el servidor). */
const INTEREST_JS_EVENTS = ['faq_open', 'whatsapp', 'call', 'open'];

/** true para robots, generadores de vista previa y clientes sin navegador. */
function interest_is_bot(string $ua): bool
{
    return $ua === '' || (bool)preg_match(
        '/bot|crawl|spider|slurp|facebookexternalhit|facebookcatalog|whatsapp|telegram|preview|embedly|curl|wget|python|httpclient|headless|lighthouse|pagespeed/i',
        $ua
    );
}

/** Suma 1 al contador del día. Nunca rompe la página si la tabla no existe. */
function interest_hit(string $subject, string $event): void
{
    try {
        db()->prepare(
            "INSERT INTO interest_daily (subject, event, event_date, hits) VALUES (?, ?, CURDATE(), 1)
             ON DUPLICATE KEY UPDATE hits = hits + 1"
        )->execute([substr($subject, 0, 60), substr($event, 0, 20)]);
    } catch (\Throwable $e) {
        error_log('[obit][interest] ' . $e->getMessage());
    }
}

/**
 * Cuenta la visita a la página de un servicio en evaluación (llamar antes de
 * imprimir nada). 'view' = cada carga; 'visitor' = una vez por navegador y
 * día (cookie sin datos personales).
 */
function interest_count_page_view(string $key): void
{
    if (!isset(INTEREST_SERVICES[$key])) return;
    if (interest_is_bot((string)($_SERVER['HTTP_USER_AGENT'] ?? ''))) return;
    interest_hit($key, 'view');
    $cookie = 'fz_iv_' . str_replace('-', '_', $key);
    if (($_COOKIE[$cookie] ?? '') !== date('Y-m-d')) {
        interest_hit($key, 'visitor');
        if (!headers_sent()) {
            setcookie($cookie, date('Y-m-d'), [
                'expires' => strtotime('tomorrow'), 'path' => '/', 'samesite' => 'Lax',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true,
            ]);
        }
    }
}

/** Valida las respuestas del formulario contra las preguntas del servicio. */
function interest_clean_answers(string $key, $raw): array
{
    $out = [];
    if (!is_array($raw)) return $out;
    foreach (INTEREST_SERVICES[$key]['questions'] as $q => $def) {
        $v = $raw[$q] ?? null;
        if ($def['multi']) {
            $vals = array_values(array_unique(array_filter((array)$v, fn($x) => is_string($x) && isset($def['options'][$x]))));
            if ($vals) $out[$q] = $vals;
        } elseif (is_string($v) && isset($def['options'][$v])) {
            $out[$q] = $v;
        }
    }
    return $out;
}
