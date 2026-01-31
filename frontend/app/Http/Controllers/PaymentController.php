<?php

namespace App\Http\Controllers;

use App\Services\BookingService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class PaymentController extends Controller
{
    protected PaymentService $paymentService;
    protected BookingService $bookingService;

    public function __construct(PaymentService $paymentService, BookingService $bookingService)
    {
        $this->paymentService = $paymentService;
        $this->bookingService = $bookingService;
    }

    /**
     * Show payment page
     */
    public function show(string $bookingId)
    {
        // Tìm booking trong session trước
        $booking = Session::get('current_booking');
        
        if (!$booking || $booking['id'] !== $bookingId) {
            // Tìm trong lịch sử
            $bookingHistory = Session::get('booking_history', []);
            $booking = $bookingHistory[$bookingId] ?? null;
        }
        
        // Nếu không có trong session, thử API
        if (!$booking) {
            $apiResponse = $this->bookingService->getBooking($bookingId);
            if ($apiResponse['success']) {
                $booking = $apiResponse['data'];
            }
        }
        
        if (!$booking) {
            return redirect()->route('movies.index')
                ->withErrors(['error' => 'Không tìm thấy đơn đặt vé. Vui lòng đặt vé lại.']);
        }
        
        // Danh sách phương thức thanh toán mặc định
        $paymentMethods = [
            ['id' => 'credit_card', 'name' => 'Thẻ tín dụng/ghi nợ', 'icon' => 'credit-card'],
            ['id' => 'momo', 'name' => 'Ví MoMo', 'icon' => 'wallet'],
            ['id' => 'vnpay', 'name' => 'VNPay', 'icon' => 'banknotes'],
            ['id' => 'bank_transfer', 'name' => 'Chuyển khoản ngân hàng', 'icon' => 'building-library'],
        ];
        
        return view('payment.show', [
            'booking' => $booking,
            'paymentMethods' => $paymentMethods,
        ]);
    }

    /**
     * Process payment
     */
    public function process(Request $request, string $bookingId)
    {
        $request->validate([
            'payment_method' => 'required|string',
        ]);

        // Tìm booking
        $booking = Session::get('current_booking');
        
        if (!$booking || $booking['id'] !== $bookingId) {
            $bookingHistory = Session::get('booking_history', []);
            $booking = $bookingHistory[$bookingId] ?? null;
        }
        
        if (!$booking) {
            return redirect()->route('movies.index')
                ->withErrors(['error' => 'Không tìm thấy đơn đặt vé']);
        }

        // Giả lập xử lý thanh toán (luôn thành công cho demo)
        $paymentId = uniqid('PAY');
        
        // Cập nhật trạng thái booking
        $booking['status'] = 'confirmed';
        $booking['payment_id'] = $paymentId;
        $booking['payment_method'] = $request->input('payment_method');
        $booking['paid_at'] = now()->toISOString();
        
        // Cập nhật trong session
        Session::put('current_booking', $booking);
        
        // Cập nhật trong lịch sử
        $bookingHistory = Session::get('booking_history', []);
        $bookingHistory[$bookingId] = $booking;
        Session::put('booking_history', $bookingHistory);
        
        // Xóa booking_data (đã hoàn tất)
        Session::forget('booking_data');
        
        return redirect()->route('payment.success', $bookingId);
    }

    /**
     * Payment success page
     */
    public function successPage(string $bookingId)
    {
        // Tìm booking
        $booking = Session::get('current_booking');
        
        if (!$booking || $booking['id'] !== $bookingId) {
            $bookingHistory = Session::get('booking_history', []);
            $booking = $bookingHistory[$bookingId] ?? null;
        }
        
        if (!$booking) {
            return redirect()->route('movies.index');
        }
        
        return view('payment.success', [
            'booking' => $booking,
        ]);
    }

    /**
     * Payment failed page
     */
    public function failedPage(string $bookingId)
    {
        $bookingHistory = Session::get('booking_history', []);
        $booking = $bookingHistory[$bookingId] ?? null;
        
        return view('payment.failed', [
            'booking' => $booking,
        ]);
    }

    /**
     * Payment history
     */
    public function history()
    {
        // Lấy bookings đã thanh toán từ session
        $bookingHistory = Session::get('booking_history', []);
        $payments = array_filter($bookingHistory, function($b) {
            return ($b['status'] ?? '') === 'confirmed';
        });
        
        return view('payment.history', [
            'payments' => array_values($payments),
        ]);
    }
}
