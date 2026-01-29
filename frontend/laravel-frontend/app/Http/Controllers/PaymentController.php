<?php

namespace App\Http\Controllers;

use App\Services\Api\PaymentApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class PaymentController extends Controller
{
    protected $paymentApi;

    public function __construct(PaymentApiService $paymentApi)
    {
        $this->paymentApi = $paymentApi;
    }

    /**
     * Hiển thị trang quét mã QR (Checkout)
     */
    public function showCheckout()
    {
        // Kiểm tra auth
        if (!Session::get('jwt_token')) {
            return redirect()->route('login');
        }

        $booking = Session::get('current_booking');
        if (!$booking) {
            return redirect()->route('movies.index')->with('info', 'Không có đơn hàng nào.');
        }

        // Giả lập tạo URL thanh toán qua VietQR hoặc Napas
        // Trong thực tế, URL này sẽ lấy từ PaymentApiService
        $paymentUrl = "STB|999999999|{$booking['total_price']}|{$booking['id']}";

        return view('booking.checkout', [
            'booking' => $booking,
            'payment_url' => $paymentUrl
        ]);
    }

    /**
     * Trang trung gian thông báo đang kiểm tra thanh toán
     */
    public function processing(Request $request)
    {
        if (!Session::get('jwt_token')) {
            return redirect()->route('login');
        }

        return view('booking.processing');
    }

    /**
     * Trang thông báo thành công cuối cùng
     */
    public function success()
    {
        if (!Session::get('jwt_token')) {
            return redirect()->route('login');
        }

        $booking = Session::get('current_booking');

        // Xóa session booking sau khi hoàn tất để tránh đặt lại
        Session::forget('current_booking');

        return view('booking.success', [
            'booking_id' => $booking['id'] ?? 'N/A'
        ]);
    }
}
