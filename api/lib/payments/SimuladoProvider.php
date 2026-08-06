<?php
if (!defined('OBIT_APP')) { http_response_code(403); exit('Forbidden'); }

require_once __DIR__ . '/PaymentProviderInterface.php';

/**
 * Proveedor de pagos SIMULADO: no llama a ningún banco. Sirve para construir y
 * probar el flujo de cobro electrónico mientras no exista cuenta/credenciales
 * de Mercantil (ver docs/payments/mercantil/STATUS.md). Nunca debe activarse
 * en producción como si fuera un cobro real: la aprobación siempre requiere
 * una acción explícita del staff (aprobarManualmente()), nunca es automática.
 */
class SimuladoProvider implements PaymentProviderInterface
{
    public function createPayment(array $input): array
    {
        return [
            'status'              => 'PENDING',
            'external_payment_id' => 'SIM-' . strtoupper(bin2hex(random_bytes(6))),
            'bank_reference'      => null,
            'redirect_url'        => null,
            'raw'                 => ['modo' => 'simulado', 'nota' => 'Sin conexión a ningún banco real.'],
        ];
    }

    public function getPaymentStatus(string $externalPaymentId): array
    {
        // Sin estado propio: el proveedor simulado no "sabe" nada por sí solo.
        // La transición a APPROVED solo ocurre vía aprobarManualmente().
        return ['status' => 'PENDING', 'external_payment_id' => $externalPaymentId,
                'bank_reference' => null, 'raw' => ['modo' => 'simulado']];
    }

    /** Único camino a APPROVED en modo simulado: acción explícita del staff. */
    public function aprobarManualmente(string $externalPaymentId): array
    {
        return [
            'status'              => 'APPROVED',
            'external_payment_id' => $externalPaymentId,
            'bank_reference'      => 'SIM-REF-' . strtoupper(bin2hex(random_bytes(4))),
            'raw'                 => ['modo' => 'simulado', 'nota' => 'Aprobación simulada por el staff, no es un pago real.'],
        ];
    }

    public function cancelPayment(string $externalPaymentId): void
    {
        // No-op: no hay nada que cancelar en un banco real.
    }

    public function refundPayment(array $input): array
    {
        return ['status' => 'REFUND_PENDING', 'raw' => ['modo' => 'simulado']];
    }

    public function requestC2PKey(array $input): array
    {
        return ['status' => 'REQUIRES_CUSTOMER_ACTION', 'raw' => ['modo' => 'simulado', 'nota' => 'Sin clave real.']];
    }

    public function processC2PPayment(array $input): array
    {
        return ['status' => 'PENDING', 'raw' => ['modo' => 'simulado']];
    }

    public function searchMobilePayment(array $input): array
    {
        return ['status' => 'NOT_FOUND', 'raw' => ['modo' => 'simulado']];
    }
}
