<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class BookingGuard
{
    /**
     * Ngăn chặn truy cập sai luồng (ví dụ vào trang thanh toán khi chưa chọn ghế)
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Kiểm tra xem trong session có dữ liệu booking tạm thời chưa
        // Dữ liệu này thường được lưu sau khi bấm "Tiếp tục thanh toán" ở trang chọn ghế
        if (!Session::has('current_booking')) {
            return redirect()
                ->route('movies.index')
                ->with('error', 'Phiên đặt vé không hợp lệ hoặc đã hết hạn. Vui lòng chọn lại phim.');
        }

        return $next($request);
    }
}