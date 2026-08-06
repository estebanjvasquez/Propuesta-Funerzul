<?php
if (!defined('OBIT_APP')) { http_response_code(403); exit('Forbidden'); }

/**
 * Contrato que debe cumplir cualquier proveedor de pagos electrónicos
 * (simulado, Mercantil, etc.). Basado en la interfaz de docs/mercantil.md §12.
 * Todos los métodos reciben/devuelven arrays asociativos (mismo estilo que el
 * resto del backend, ver prev_*_out() en api/lib/prevision.php).
 *
 * createPayment()/getPaymentStatus() devuelven al menos:
 *   ['status' => PaymentService::ESTADOS[...], 'external_payment_id' => ?string,
 *    'bank_reference' => ?string, 'redirect_url' => ?string, 'raw' => array]
 */
interface PaymentProviderInterface
{
    public function createPayment(array $input): array;
    public function getPaymentStatus(string $externalPaymentId): array;
    public function cancelPayment(string $externalPaymentId): void;
    public function refundPayment(array $input): array;
    public function requestC2PKey(array $input): array;
    public function processC2PPayment(array $input): array;
    public function searchMobilePayment(array $input): array;
}
