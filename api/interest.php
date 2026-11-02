<?php
/**
 * Interés del público en servicios en evaluación:  api/interest.php?action=...
 *   POST track            (público)  {subject, event}  suma 1 a un contador agregado
 *   POST respond          (público)  formulario "Quiero que me avisen"
 *   GET  stats            (admin)    ?days=30|90|365|0  resumen por servicio y por pregunta frecuente
 *   GET  responses        (admin)    ?service=&format=csv  respuestas del formulario
 *   POST delete_response  (admin)    {id}
 * Ver docs/specs/2026-11-02-interes-servicios-nuevos.md (migración 21).
 */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/interest.php';

$action = $_GET['action'] ?? 'stats';

/** Fecha de inicio del periodo pedido (null = todo). */
function interest_since(): ?string
{
    $days = (int)($_GET['days'] ?? 30);
    return $days > 0 ? date('Y-m-d', strtotime('-' . ($days - 1) . ' days')) : null;
}

switch ($action) {

    // ---- CONTADOR (público) -------------------------------------------------
    case 'track': {
        require_method('POST');
        $b = body_json();
        $subject = (string)($b['subject'] ?? '');
        $event   = (string)($b['event'] ?? '');
        $ok = in_array($event, INTEREST_JS_EVENTS, true)
            && (isset(INTEREST_SERVICES[$subject]) || preg_match('/^faq:\d{1,9}$/', $subject));
        if ($ok && !interest_is_bot((string)($_SERVER['HTTP_USER_AGENT'] ?? ''))) {
            interest_hit($subject, $event);
        }
        json_out(['ok' => true]); // siempre ok: es una métrica, no debe molestar al visitante
    }

    // ---- FORMULARIO "QUIERO QUE ME AVISEN" (público) ------------------------
    case 'respond': {
        require_method('POST');
        $b = body_json();
        if (!empty($b['website'])) json_out(['ok' => true]); // trampa para robots: se descarta en silencio

        $key = (string)($b['service'] ?? '');
        if (!isset(INTEREST_SERVICES[$key])) json_out(['ok' => false, 'error' => 'Servicio no válido.'], 422);

        $answers = interest_clean_answers($key, $b['answers'] ?? []);
        $name    = clean_str($b['full_name'] ?? '', 150);
        $phone   = preg_replace('/[^0-9+()\s-]/', '', clean_str($b['phone'] ?? '', 40));
        $email   = clean_str($b['email'] ?? '', 190);
        $comment = clean_str($b['comment'] ?? '', 1000);
        $consent = !empty($b['consent']);
        $source  = clean_str($b['source'] ?? '', 120);

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_out(['ok' => false, 'error' => 'Revise el correo electrónico.'], 422);
        }
        $hasContact = $phone !== '' || $email !== '';
        if (!$answers && !$hasContact && $comment === '') {
            json_out(['ok' => false, 'error' => 'Responda al menos una pregunta o déjenos un dato de contacto.'], 422);
        }
        if ($hasContact && !$consent) {
            json_out(['ok' => false, 'error' => 'Para dejar sus datos de contacto, marque la casilla de autorización.'], 422);
        }
        if (!$hasContact) { $name = ''; $consent = false; } // sin contacto no se guarda el nombre

        // Máximo 5 respuestas por hora desde la misma dirección.
        $ip = client_ip();
        $st = db()->prepare("SELECT COUNT(*) FROM interest_responses WHERE ip_address = ? AND created_at > NOW() - INTERVAL 1 HOUR");
        $st->execute([$ip]);
        if ((int)$st->fetchColumn() >= 5) {
            json_out(['ok' => false, 'error' => 'Ya recibimos sus respuestas. Gracias.'], 429);
        }

        db()->prepare(
            "INSERT INTO interest_responses (service_key, answers, full_name, phone, email, comment, consent, source, ip_address)
             VALUES (?,?,?,?,?,?,?,?,?)"
        )->execute([
            $key, $answers ? json_encode($answers, JSON_UNESCAPED_UNICODE) : null,
            $name ?: null, $phone ?: null, $email ?: null, $comment ?: null, $consent ? 1 : 0, $source ?: null, $ip,
        ]);
        $id = (int)db()->lastInsertId();
        audit('interest.respond', 'interest_responses', $id, ['service' => $key, 'contact' => $hasContact]);

        if ($hasContact) {
            $label = INTEREST_SERVICES[$key]['label'];
            notify_email(
                "Interés en $label: " . ($name ?: 'sin nombre'),
                "Una persona dejó sus datos en la página de $label (servicio en evaluación).\n\n"
                . "Nombre: " . ($name ?: '-') . "\nTeléfono: " . ($phone ?: '-') . "\nCorreo: " . ($email ?: '-')
                . ($comment !== '' ? "\nComentario: $comment" : '')
                . "\n\nVea todas las respuestas en el panel: Sistema → Interés del público.",
                $email ?: null
            );
        }
        json_out(['ok' => true]);
    }

    // ---- RESUMEN (admin) ------------------------------------------------------
    case 'stats': {
        require_method('GET');
        require_perm('sitio.admin');
        $since = interest_since();

        $where = $since ? 'WHERE event_date >= ?' : '';
        $st = db()->prepare("SELECT subject, event, SUM(hits) AS hits FROM interest_daily $where GROUP BY subject, event");
        $st->execute($since ? [$since] : []);
        $counts = [];
        foreach ($st->fetchAll() as $r) $counts[$r['subject']][$r['event']] = (int)$r['hits'];

        // Preguntas frecuentes: aperturas por pregunta (y suma por servicio enlazado).
        $faqRows = db()->query("SELECT id, question, interest_key, is_active FROM faqs ORDER BY sort_order ASC, id ASC")->fetchAll();
        $faqs = [];
        $faqByService = [];
        foreach ($faqRows as $f) {
            $opens = $counts['faq:' . $f['id']]['open'] ?? 0;
            $faqs[] = ['id' => (int)$f['id'], 'question' => $f['question'], 'interest_key' => $f['interest_key'],
                       'is_active' => (bool)$f['is_active'], 'opens' => $opens];
            if ($f['interest_key']) $faqByService[$f['interest_key']] = ($faqByService[$f['interest_key']] ?? 0) + $opens;
        }
        usort($faqs, fn($a, $b) => $b['opens'] <=> $a['opens']);

        $services = [];
        foreach (INTEREST_SERVICES as $key => $def) {
            $st = db()->prepare(
                "SELECT answers, (phone IS NOT NULL OR email IS NOT NULL) AS has_contact FROM interest_responses
                 WHERE service_key = ?" . ($since ? " AND created_at >= ?" : "")
            );
            $st->execute($since ? [$key, $since] : [$key]);
            $total = 0; $withContact = 0; $tally = [];
            foreach ($st->fetchAll() as $r) {
                $total++;
                if ($r['has_contact']) $withContact++;
                foreach ((json_decode((string)$r['answers'], true) ?: []) as $q => $v) {
                    foreach ((array)$v as $opt) $tally[$q][$opt] = ($tally[$q][$opt] ?? 0) + 1;
                }
            }
            $breakdown = [];
            foreach ($def['questions'] as $q => $qd) {
                $opts = [];
                foreach ($qd['options'] as $ok => $ol) $opts[] = ['key' => $ok, 'label' => $ol, 'count' => $tally[$q][$ok] ?? 0];
                $breakdown[] = ['key' => $q, 'label' => $qd['label'], 'multi' => $qd['multi'], 'options' => $opts];
            }
            $c = $counts[$key] ?? [];
            $services[] = [
                'key' => $key, 'label' => $def['label'], 'page' => $def['page'],
                'visits' => $c['view'] ?? 0, 'visitors' => $c['visitor'] ?? 0,
                'faq_opens' => ($faqByService[$key] ?? 0) + ($c['faq_open'] ?? 0),
                'whatsapp' => $c['whatsapp'] ?? 0, 'calls' => $c['call'] ?? 0,
                'responses' => $total, 'with_contact' => $withContact,
                'breakdown' => $breakdown,
            ];
        }
        json_out(['ok' => true, 'since' => $since, 'services' => $services, 'faqs' => $faqs]);
    }

    // ---- RESPUESTAS (admin) -----------------------------------------------------
    case 'responses': {
        require_method('GET');
        require_perm('sitio.admin');
        $key = (string)($_GET['service'] ?? '');
        if (!isset(INTEREST_SERVICES[$key])) json_out(['ok' => false, 'error' => 'Servicio no válido.'], 422);
        $def = INTEREST_SERVICES[$key];

        $st = db()->prepare("SELECT * FROM interest_responses WHERE service_key = ? ORDER BY created_at DESC LIMIT 2000");
        $st->execute([$key]);
        $items = [];
        foreach ($st->fetchAll() as $r) {
            $ans = json_decode((string)$r['answers'], true) ?: [];
            $readable = [];
            foreach ($def['questions'] as $q => $qd) {
                $vals = array_map(fn($o) => $qd['options'][$o] ?? $o, (array)($ans[$q] ?? []));
                $readable[$q] = implode('; ', $vals);
            }
            $items[] = [
                'id' => (int)$r['id'], 'created_at' => $r['created_at'], 'answers' => $readable,
                'full_name' => $r['full_name'], 'phone' => $r['phone'], 'email' => $r['email'],
                'comment' => $r['comment'], 'source' => $r['source'],
            ];
        }

        if (($_GET['format'] ?? '') === 'csv') {
            audit('interest.export', 'interest_responses', null, ['service' => $key, 'rows' => count($items)]);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="interes-' . $key . '-' . date('Y-m-d') . '.csv"');
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM: Excel abre bien los acentos
            $qs = array_keys($def['questions']);
            fputcsv($out, array_merge(['Fecha'], array_map(fn($q) => $def['questions'][$q]['label'], $qs),
                ['Nombre', 'Teléfono', 'Correo', 'Comentario', 'Origen']), ';');
            foreach ($items as $i) {
                fputcsv($out, array_merge([$i['created_at']], array_map(fn($q) => $i['answers'][$q], $qs),
                    [$i['full_name'], $i['phone'], $i['email'], $i['comment'], $i['source']]), ';');
            }
            fclose($out);
            exit;
        }
        $questions = [];
        foreach ($def['questions'] as $q => $qd) $questions[] = ['key' => $q, 'label' => $qd['label']];
        json_out(['ok' => true, 'questions' => $questions, 'items' => $items]);
    }

    case 'delete_response': {
        require_method('POST');
        require_perm('sitio.admin');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        db()->prepare("DELETE FROM interest_responses WHERE id = ?")->execute([$id]);
        audit('interest.delete_response', 'interest_responses', $id);
        json_out(['ok' => true]);
    }

    default:
        json_out(['ok' => false, 'error' => 'Acción no encontrada.'], 404);
}
