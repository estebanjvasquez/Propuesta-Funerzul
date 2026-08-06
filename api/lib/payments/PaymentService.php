<?php
if (!defined('OBIT_APP')) { http_response_code(403); exit('Forbidden'); }

require_once __DIR__ . '/PaymentProviderInterface.php';
require_once __DIR__ . '/SimuladoProvider.php';
require_once __DIR__ . '/MercantilProvider.php';

/**
 * Orquestador de cobros electrónicos. Requiere que api/lib/prevision.php ya
 * esté cargado (usa prev_tasa_del_dia(), PREV_FORMAS_PAGO_PAGO) — igual que
 * cualquier otro endpoint de api/prevision_*.php.
 *
 * No expone ninguna forma de marcar un intento como APPROVED salvo a través
 * de conciliar(), que a su vez solo confía en lo que devuelve el proveedor
 * (nunca un HTTP 200 genérico ni una redirección del navegador — regla de
 * docs/mercantil.md §2.2/§18.3).
 */
class PaymentService
{
    /** Transiciones permitidas (docs/mercantil.md §17). Cualquier otra se rechaza. */
    private const TRANSICIONES = [
        'CREATED'                  => ['PENDING', 'FAILED'],
        'PENDING'                  => ['REQUIRES_CUSTOMER_ACTION', 'PROCESSING', 'APPROVED', 'DECLINED', 'FAILED', 'EXPIRED', 'CANCELLED'],
        'REQUIRES_CUSTOMER_ACTION' => ['PROCESSING', 'APPROVED', 'DECLINED', 'EXPIRED', 'CANCELLED'],
        'PROCESSING'                => ['APPROVED', 'DECLINED', 'FAILED', 'REVERSED'],
        'APPROVED'                  => ['REVERSED', 'REFUND_PENDING'],
        'REFUND_PENDING'            => ['REFUNDED', 'APPROVED'],
        'DECLINED' => [], 'FAILED' => [], 'EXPIRED' => [], 'CANCELLED' => [], 'REVERSED' => [], 'REFUNDED' => [],
    ];

    private static function puedeTransicionar(string $de, string $a): bool
    {
        return in_array($a, self::TRANSICIONES[$de] ?? [], true);
    }

    private static function nombreProvider(): string
    {
        $p = $GLOBALS['CONFIG']['payments']['provider'] ?? 'simulado';
        return in_array($p, ['simulado', 'mercantil'], true) ? $p : 'simulado';
    }

    public static function provider(): PaymentProviderInterface
    {
        if (self::nombreProvider() === 'mercantil') {
            return new MercantilProvider($GLOBALS['CONFIG']['payments']['mercantil'] ?? []);
        }
        return new SimuladoProvider();
    }

    public static function out(array $r): array
    {
        return [
            'id'                  => (int)$r['id'],
            'contrato_id'         => $r['contrato_id'] !== null ? (int)$r['contrato_id'] : null,
            'cuota_id'            => $r['cuota_id'] !== null ? (int)$r['cuota_id'] : null,
            'provider'            => $r['provider'],
            'simulado'            => $r['provider'] === 'simulado',
            'metodo'              => $r['metodo'],
            'moneda'              => $r['moneda'],
            'monto'               => (float)$r['monto'],
            'estado'              => $r['estado'],
            'external_payment_id' => $r['external_payment_id'],
            'bank_reference'      => $r['bank_reference'],
            'pago_id'             => $r['pago_id'] !== null ? (int)$r['pago_id'] : null,
            'motivo'              => $r['motivo'],
            'created_at'          => $r['created_at'] ?? null,
            'updated_at'          => $r['updated_at'] ?? null,
            'approved_at'         => $r['approved_at'] ?? null,
        ];
    }

    private static function fila(int $id): array
    {
        $st = db()->prepare('SELECT * FROM prev_pagos_electronicos WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch();
        if (!$r) json_out(['ok' => false, 'error' => 'Intento de pago electrónico no encontrado.'], 404);
        return $r;
    }

    /** Quita cualquier dato sensible antes de guardarlo en prev_pago_eventos. */
    private static function sanitizar(array $raw): array
    {
        $prohibido = ['client_secret', 'password', 'pan', 'cvv', 'pin', 'clave', 'clave_temporal', 'token'];
        $out = [];
        foreach ($raw as $k => $v) {
            $out[$k] = in_array(strtolower((string)$k), $prohibido, true) || !is_scalar($v)
                ? (is_scalar($v) ? '[omitido]' : $v) : $v;
        }
        return $out;
    }

    private static function logEvento(int $pagoElectronicoId, string $tipo, array $payload): void
    {
        $san = self::sanitizar($payload);
        db()->prepare(
            'INSERT INTO prev_pago_eventos (pago_electronico_id, tipo, payload_hash, sanitized_payload, processed_at)
             VALUES (?,?,?,?,NOW())'
        )->execute([$pagoElectronicoId, $tipo, hash('sha256', json_encode($san)), json_encode($san, JSON_UNESCAPED_UNICODE)]);
    }

    /**
     * Crea un intento de cobro electrónico para una cuota (o un contrato en
     * general si no se indica cuota). Solo lo usa el staff desde el panel de
     * cobranza — el sitio público nunca invoca este método directamente.
     */
    public static function crearIntento(array $d): array
    {
        $contratoId = (int)($d['contrato_id'] ?? 0);
        if (!$contratoId) json_out(['ok' => false, 'error' => 'Falta el contrato.'], 422);
        $cuotaId = (int)($d['cuota_id'] ?? 0) ?: null;
        $moneda  = prev_moneda($d['moneda'] ?? 'USD');
        $monto   = prev_money($d['monto'] ?? 0);
        if ($monto <= 0) json_out(['ok' => false, 'error' => 'El monto debe ser mayor a cero.'], 422);
        $metodo  = in_array($d['metodo'] ?? '', ['boton_web', 'c2p', 'tarjeta', 'otro'], true) ? $d['metodo'] : 'boton_web';

        $st = db()->prepare('SELECT COUNT(*) FROM prev_pagos_electronicos WHERE contrato_id = ?');
        $st->execute([$contratoId]);
        $intentoNum = (int)$st->fetchColumn() + 1;
        $idKey = "mercantil:$contratoId:$intentoNum";

        $provider = self::provider();
        $resp = $provider->createPayment([
            'orderId'  => (string)$contratoId,
            'amount'   => number_format($monto, 2, '.', ''),
            'currency' => $moneda,
            'idempotencyKey' => $idKey,
        ]);
        $estado = in_array($resp['status'] ?? '', array_keys(self::TRANSICIONES), true) ? $resp['status'] : 'PENDING';

        db()->prepare(
            'INSERT INTO prev_pagos_electronicos
             (contrato_id, cuota_id, provider, metodo, moneda, monto, estado,
              external_payment_id, bank_reference, idempotency_key, registrado_por)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $contratoId, $cuotaId, self::nombreProvider(), $metodo, $moneda, $monto, $estado,
            $resp['external_payment_id'] ?? null, $resp['bank_reference'] ?? null, $idKey,
            $d['registrado_por'] ?? null,
        ]);
        $id = (int)db()->lastInsertId();
        self::logEvento($id, 'creado', $resp['raw'] ?? []);
        audit('prev_pago_electronico.crear', 'prev_pagos_electronicos', $id,
              ['contrato_id' => $contratoId, 'monto' => $monto, 'moneda' => $moneda, 'provider' => self::nombreProvider()]);

        return self::out(self::fila($id));
    }

    public static function estado(int $id): array
    {
        return self::out(self::fila($id));
    }

    /**
     * Confirma el intento como APPROVED. En modo simulado es una acción
     * explícita del staff (nunca automática); en modo Mercantil consultaría
     * getPaymentStatus() del banco (hoy lanza excepción: adaptador pendiente).
     * Al aprobar, si el intento tiene contrato, se refleja en prev_pagos
     * (mismo libro mayor que usan cobranza y reportes) — así ningún reporte
     * existente necesita saber que el pago fue electrónico.
     */
    public static function conciliar(int $id): array
    {
        $r = self::fila($id);
        if (!self::puedeTransicionar($r['estado'], 'APPROVED')) {
            json_out(['ok' => false, 'error' => "No se puede aprobar un intento en estado {$r['estado']}."], 409);
        }

        $provider = self::provider();
        $resp = $r['provider'] === 'simulado'
            ? $provider->aprobarManualmente($r['external_payment_id'])
            : $provider->getPaymentStatus($r['external_payment_id']);

        if (($resp['status'] ?? '') !== 'APPROVED') {
            json_out(['ok' => false, 'error' => 'El proveedor no confirmó el pago como aprobado.'], 409);
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pagoId = null;
            if ($r['contrato_id']) {
                $tasa = $r['moneda'] === 'BS' ? prev_tasa_del_dia() : 0;
                $fecha = date('Y-m-d');
                $obs = 'Pago electrónico #' . $id . ($r['provider'] === 'simulado' ? ' (MODO SIMULADO, no es un cobro real)' : '');
                $pdo->prepare(
                    'INSERT INTO prev_pagos
                     (contrato_id, cuota_id, fecha, moneda, monto, tasa, forma_pago, referencia, banco, observaciones)
                     VALUES (?,?,?,?,?,?,?,?,?,?)'
                )->execute([
                    $r['contrato_id'], $r['cuota_id'], $fecha, $r['moneda'], $r['monto'], $tasa,
                    $r['metodo'] === 'c2p' ? 'pago_movil' : 'otro',
                    $resp['bank_reference'] ?? $r['external_payment_id'], 'Mercantil', $obs,
                ]);
                $pagoId = (int)$pdo->lastInsertId();

                if ($r['cuota_id']) {
                    $q = $pdo->prepare('SELECT saldo FROM prev_cuotas WHERE id = ?');
                    $q->execute([$r['cuota_id']]);
                    $saldo = (float)$q->fetchColumn();
                    $abono = min((float)$r['monto'], $saldo);
                    $nuevoSaldo = round($saldo - $abono, 2);
                    $cobrada = $nuevoSaldo <= 0.009;
                    $pdo->prepare('UPDATE prev_cuotas SET saldo = ?, estado = ?, fecha_cobro = ? WHERE id = ?')
                        ->execute([$cobrada ? 0 : $nuevoSaldo, $cobrada ? 'cobrada' : 'parcial', $cobrada ? $fecha : null, $r['cuota_id']]);
                }
            }

            $pdo->prepare(
                "UPDATE prev_pagos_electronicos
                 SET estado = 'APPROVED', bank_reference = ?, pago_id = ?, approved_at = NOW()
                 WHERE id = ?"
            )->execute([$resp['bank_reference'] ?? $r['bank_reference'], $pagoId, $id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        self::logEvento($id, 'conciliacion', $resp['raw'] ?? []);
        audit('prev_pago_electronico.conciliar', 'prev_pagos_electronicos', $id, ['pago_id' => $pagoId]);
        return self::out(self::fila($id));
    }

    public static function cancelar(int $id, ?string $motivo = null): array
    {
        $r = self::fila($id);
        if (!self::puedeTransicionar($r['estado'], 'CANCELLED')) {
            json_out(['ok' => false, 'error' => "No se puede cancelar un intento en estado {$r['estado']}."], 409);
        }
        if ($r['external_payment_id']) {
            self::provider()->cancelPayment($r['external_payment_id']);
        }
        db()->prepare("UPDATE prev_pagos_electronicos SET estado = 'CANCELLED', motivo = ? WHERE id = ?")
            ->execute([$motivo ? clean_str($motivo, 255) : null, $id]);
        self::logEvento($id, 'cancelacion', ['motivo' => $motivo]);
        audit('prev_pago_electronico.cancelar', 'prev_pagos_electronicos', $id, ['motivo' => $motivo]);
        return self::out(self::fila($id));
    }
}
