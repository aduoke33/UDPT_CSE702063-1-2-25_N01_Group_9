<?php

namespace App\Services\Api;

use App\Services\ApiService;
use Illuminate\Http\Client\Response;

class BookingApiService extends ApiService
{
    /**
     * Lấy sơ đồ ghế theo suất chiếu
     */
    public function getSeatsByShowtime($showtimeId)
    {
        return $this->request()->get($this->baseUrl . "/api/bookings/showtimes/{$showtimeId}/seats");
    }

    /**
     * Gửi yêu cầu đặt vé (Backend sẽ xử lý Redis Lock tại đây)
     */
    public function createBooking(array $data)
    {
        return $this->request()->post($this->baseUrl . "/api/bookings/reserve", $data);
    }
}
