<?php

namespace App\Http\Controllers;

use App\Services\Api\BookingApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Routing\Controller;

class BookingController extends \App\Http\Controllers\Controller
{
    protected $bookingApi;

    public function __construct(BookingApiService $bookingApi)
    {
        $this->bookingApi = $bookingApi;
    }

    public function selectSeats($showtimeId)
    {
        // Kiểm tra auth ở đây thay vì middleware
        if (!Session::get('jwt_token')) {
            return redirect()->route('login');
        }

        /** @var \Illuminate\Http\Client\Response $response */
        $response = $this->bookingApi->getSeatsByShowtime($showtimeId);

        if (!$response->successful()) {
            return redirect()->route('movies.index')->with('info', 'Không thể lấy thông tin phòng chiếu.');
        }

        $data = $response->json();
        return view('booking.seats', [
            'showtime' => $data['showtime'],
            'seats' => $data['seats']
        ]);
    }

    public function processBooking(Request $request)
    {
        // Kiểm tra auth
        if (!Session::get('jwt_token')) {
            return redirect()->route('login');
        }

        $payload = [
            'showtime_id' => $request->showtime_id,
            'seat_ids' => $request->seat_ids, // Mảng các ID ghế
        ];

        /** @var \Illuminate\Http\Client\Response $response */
        $response = $this->bookingApi->createBooking($payload);

        if ($response->successful()) {
            $bookingData = $response->json();
            // Lưu vào session để chuyển sang trang thanh toán
            Session::put('current_booking', $bookingData);
            return redirect()->route('payment.qr');
        }

        return back()->withErrors(['booking_error' => $response->json('detail') ?? 'Ghế đã có người chọn!']);
    }
}
