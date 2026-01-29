<?php

namespace App\Services\Api;

use App\Services\ApiService;
use Illuminate\Http\Client\Response;

class PaymentApiService extends ApiService
{
    /**
     * Lấy link hoặc mã QR thanh toán từ Payment Service
     */
    public function getPaymentDetails($bookingId)
    {
        return $this->request()->get($this->baseUrl . "/api/payments/booking/{$bookingId}");
    }

    /**
     * Kiểm tra xem đơn hàng đã được thanh toán chưa
     */
    public function checkStatus($bookingId)
    {
        return $this->request()->get($this->baseUrl . "/api/payments/check-status/{$bookingId}");
    }
}
