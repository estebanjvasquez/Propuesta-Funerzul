<?php
if (!defined('OBIT_APP')) { http_response_code(403); exit('Forbidden'); }

require_once __DIR__ . '/PaymentProviderInterface.php';

/**
 * Adaptador real de Mercantil Banco — STUB.
 *
 * No implementar ningún método aquí sin haber importado la especificación
 * OpenAPI/Postman oficial entregada por Mercantil (docs/mercantil.md §2.2:
 * "no inventar endpoints, credenciales, campos, códigos o algoritmos").
 * Estado del trámite bancario: docs/payments/mercantil/STATUS.md.
 */
class MercantilProvider implements PaymentProviderInterface
{
    public function __construct(private array $config = [])
    {
    }

    private function pendiente(string $operacion): never
    {
        throw new \RuntimeException(
            "Mercantil Banco: '$operacion' pendiente de implementar. Falta la especificación oficial " .
            '(OpenAPI/Postman) y las credenciales de sandbox — ver docs/payments/mercantil/STATUS.md.'
        );
    }

    public function createPayment(array $input): array { $this->pendiente('createPayment'); }
    public function getPaymentStatus(string $externalPaymentId): array { $this->pendiente('getPaymentStatus'); }
    public function cancelPayment(string $externalPaymentId): void { $this->pendiente('cancelPayment'); }
    public function refundPayment(array $input): array { $this->pendiente('refundPayment'); }
    public function requestC2PKey(array $input): array { $this->pendiente('requestC2PKey'); }
    public function processC2PPayment(array $input): array { $this->pendiente('processC2PPayment'); }
    public function searchMobilePayment(array $input): array { $this->pendiente('searchMobilePayment'); }
}
