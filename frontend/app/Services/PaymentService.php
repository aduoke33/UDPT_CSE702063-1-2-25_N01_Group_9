<?php

namespace App\Services;

class PaymentService extends ApiService
{
    /**
     * Create a payment
     */
    public function createPayment(array $data): array
    {
        return $this->post(config('api.endpoints.payments.create'), $data);
    }

    /**
     * Get payment detail
     */
    public function getPayment(string $id): array
    {
        $endpoint = str_replace('{id}', $id, config('api.endpoints.payments.detail'));
        return $this->get($endpoint);
    }

    /**
     * Process payment
     */
    public function processPayment(string $paymentId, array $paymentData): array
    {
        $endpoint = str_replace('{id}', $paymentId, config('api.endpoints.payments.process'));
        return $this->post($endpoint, $paymentData);
    }

    /**
     * Verify payment
     */
    public function verifyPayment(string $paymentId): array
    {
        $endpoint = str_replace('{id}', $paymentId, config('api.endpoints.payments.verify'));
        return $this->get($endpoint);
    }

    /**
     * Get payment methods
     */
    public function getPaymentMethods(): array
    {
        return $this->get(config('api.endpoints.payments.methods'), [], false);
    }

    /**
     * Request refund
     */
    public function requestRefund(string $paymentId, string $reason): array
    {
        $endpoint = str_replace('{id}', $paymentId, config('api.endpoints.payments.refund'));
        return $this->post($endpoint, ['reason' => $reason]);
    }

    /**
     * Get payment history
     */
    public function getPaymentHistory(): array
    {
        return $this->get(config('api.endpoints.payments.history'));
    }

    /**
     * Calculate total price
     */
    public function calculatePrice(string $showtimeId, array $seatIds): array
    {
        return $this->post('/api/payments/calculate', [
            'showtime_id' => $showtimeId,
            'seat_ids' => $seatIds,
        ]);
    }
}
