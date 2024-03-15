<?php

namespace App\Services;

use App\Abstractions\Interfaces\PaymentReferenceInterface;
use Illuminate\Support\Facades\Log;

class WebhookService
{

    /**
     * @var string
     */
    private string $SERVICE_PATH = "\App\Abstractions\Implementations\PaymentReferences\\";

    /**
     *
     * @param string $paymentReferenceIdentifier
     * @return PaymentReferenceInterface|null
     */
    public function getPaymentReferenceServiceHandler(string $paymentReferenceIdentifier): ?PaymentReferenceInterface
    {
        try {
            # set the location of the retrieved class
            $service = $this->SERVICE_PATH . strtoupper($paymentReferenceIdentifier) . 'Service';
            return class_exists($service)
                ? new $service()
                : null;
        } catch (\Exception $exception) { Log::error($exception); }
    }
}
