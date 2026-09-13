<?php
declare(strict_types=1);

/**
 * Aviso interno por correo al staff de Funeraria del Zulia (nunca al público).
 * PHP mail() nativo -- sin dependencias nuevas, funciona en cPanel compartido
 * vía sendmail local (ver docs/agent-standards.md: no introducir dependencias
 * sin justificar impacto en cPanel). Si algún día la entrega por mail() nativo
 * no es confiable (va a spam, no hay SPF/DKIM), la alternativa es SMTP con
 * PHPMailer -- decisión de infraestructura, no de este archivo.
 *
 * Filosofía igual que api/lib/prevision_funeraria.php: esto NUNCA debe romper
 * el flujo que lo llama. Si el correo no sale, se registra en el log y el
 * caller sigue -- el dato ya está a salvo en su sistema real (hoy,
 * Prevision-Funeraria); el correo es solo un aviso adicional, no la fuente
 * de verdad.
 */
function notify_email(string $subject, string $body, ?string $replyTo = null): bool
{
    $to = trim((string)($GLOBALS['CONFIG']['app']['notify_email'] ?? ''));
    if ($to === '') return false; // sin destinatario configurado: no hay a quién avisar

    $siteUrl = (string)($GLOBALS['CONFIG']['app']['site_url'] ?? 'https://www.funerariadelzulia.com');
    $host    = (string)(parse_url($siteUrl, PHP_URL_HOST) ?: 'funerariadelzulia.com');
    $host    = preg_replace('/^www\./', '', $host) ?: $host;
    $from    = 'no-reply@' . $host;

    $headers = "From: Funeraria del Zulia <{$from}>\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n";
    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers .= "Reply-To: {$replyTo}\r\n";
    }

    // Asunto UTF-8 codificado (mail() no acepta acentos crudos en el header).
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    try {
        $sent = @mail($to, $encodedSubject, $body, $headers);
        if (!$sent) error_log('[notify_email] mail() devolvió false para: ' . $subject);
        return $sent;
    } catch (\Throwable $e) {
        error_log('[notify_email] excepción: ' . $e->getMessage());
        return false;
    }
}
