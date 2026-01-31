<?php

namespace App\Http\Controllers;

use App\Services\BookingService;
use App\Services\MovieService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class BookingController extends Controller
{
    protected BookingService $bookingService;
    protected MovieService $movieService;
    protected PaymentService $paymentService;

    public function __construct(
        BookingService $bookingService,
        MovieService $movieService,
        PaymentService $paymentService
    ) {
        $this->bookingService = $bookingService;
        $this->movieService = $movieService;
        $this->paymentService = $paymentService;
    }

    /**
     * Show seat selection page
     */
    public function selectSeats(string $showtimeId)
    {
        // Lấy thông tin showtime từ API
        $showtimeResponse = $this->movieService->getShowtimeDetail($showtimeId);
        $showtime = $showtimeResponse['data'] ?? $showtimeResponse ?? null;
        
        // Nếu API trả về showtime, lấy thêm thông tin movie
        if ($showtime) {
            // Có thể movie đã nằm trong response
            if (isset($showtime['movie']) && is_array($showtime['movie'])) {
                $movie = $showtime['movie'];
                $showtime['movie_id'] = $movie['id'] ?? $showtime['movie_id'] ?? null;
                $showtime['movie_title'] = $movie['title'] ?? 'Phim';
                $showtime['movie_poster'] = $movie['poster_url'] ?? null;
                $showtime['movie_backdrop'] = $movie['backdrop_url'] ?? null;
            } elseif (isset($showtime['movie_id'])) {
                // Fetch movie separately
                $movieResponse = $this->movieService->getMovie((string)$showtime['movie_id']);
                $movie = $movieResponse['data'] ?? $movieResponse ?? null;
                
                if ($movie && isset($movie['title'])) {
                    $showtime['movie_title'] = $movie['title'];
                    $showtime['movie_poster'] = $movie['poster_url'] ?? null;
                    $showtime['movie_backdrop'] = $movie['backdrop_url'] ?? null;
                }
            }
            
            // Theater info
            if (isset($showtime['theater']) && is_array($showtime['theater'])) {
                $showtime['cinema_name'] = $showtime['theater']['name'] ?? 'CineBook Cinema';
                $showtime['cinema_address'] = $showtime['theater']['location'] ?? '';
            }
        }
        
        // Nếu không có data, tạo mock data
        if (!$showtime || !isset($showtime['id'])) {
            $showtime = [
                'id' => $showtimeId,
                'movie_id' => $showtimeId,
                'movie_title' => 'Phim Đã Chọn',
                'movie_poster' => 'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?w=300&h=450&fit=crop',
                'cinema_name' => 'CineBook Cinema',
                'room' => 'Cinema 1',
                'show_date' => date('Y-m-d'),
                'show_time' => '19:00:00',
                'format' => '2D Phụ Đề',
                'age_rating' => 'P',
                'price' => 75000,
            ];
        }
        
        // Lưu showtime vào session để dùng sau
        Session::put('current_showtime', $showtime);
        
        $seatsResponse = $this->movieService->getSeats($showtimeId);
        $seats = [];
        if (isset($seatsResponse['data']) && is_array($seatsResponse['data'])) {
            $seats = $seatsResponse['data'];
        }
        
        return view('booking.seats', [
            'showtime' => $showtime,
            'seats' => $seats,
        ]);
    }

    /**
     * Hold selected seats
     */
    public function holdSeats(Request $request)
    {
        $request->validate([
            'showtime_id' => 'required|string',
            'seat_ids' => 'required|array|min:1',
        ]);

        $showtimeId = $request->input('showtime_id');
        $seatIds = $request->input('seat_ids');
        
        // Lấy showtime từ session hoặc API
        $showtime = Session::get('current_showtime');
        
        if (!$showtime || $showtime['id'] !== $showtimeId) {
            // Lấy lại từ API
            $showtimeResponse = $this->movieService->getShowtimeDetail($showtimeId);
            $showtime = $showtimeResponse['data'] ?? null;
            
            if ($showtime && isset($showtime['movie_id'])) {
                $movieResponse = $this->movieService->getMovie($showtime['movie_id']);
                $movie = $movieResponse['data'] ?? null;
                
                if ($movie) {
                    $showtime['movie_title'] = $movie['title'] ?? 'Phim';
                    $showtime['movie_poster'] = $movie['poster_url'] ?? null;
                }
            }
        }
        
        // Tạo thông tin ghế đã chọn
        $selectedSeats = $this->generateSeatInfo($seatIds);
        $totalPrice = collect($selectedSeats)->sum('price');
        
        // Lưu đầy đủ thông tin vào session
        Session::put('booking_data', [
            'showtime_id' => $showtimeId,
            'seat_ids' => $seatIds,
            'selected_seats' => $selectedSeats,
            'total_price' => $totalPrice,
            'showtime' => $showtime,
            'hold_id' => uniqid('hold_'),
            'created_at' => now()->toISOString(),
        ]);
        
        return response()->json([
            'success' => true,
            'redirect' => route('booking.confirm'),
        ]);
    }

    /**
     * Generate seat info from seat IDs
     */
    private function generateSeatInfo(array $seatIds): array
    {
        $selectedSeats = [];
        $rows = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
        $cols = 12;
        
        foreach ($seatIds as $seatId) {
            $seatNum = intval($seatId);
            $rowIndex = intval(($seatNum - 1) / $cols);
            $colNum = (($seatNum - 1) % $cols) + 1;
            $row = $rows[$rowIndex] ?? 'A';
            $isVip = $row >= 'D' && $row <= 'F' && $colNum >= 4 && $colNum <= 9;
            
            $selectedSeats[] = [
                'id' => $seatId,
                'code' => $row . $colNum,
                'seat_number' => $row . $colNum,
                'type' => $isVip ? 'VIP' : 'Thường',
                'price' => $isVip ? 100000 : 75000,
            ];
        }
        
        return $selectedSeats;
    }

    /**
     * Show booking confirmation page
     */
    public function confirm()
    {
        $bookingData = Session::get('booking_data');
        
        if (!$bookingData) {
            return redirect()->route('movies.index')
                ->withErrors(['error' => 'Vui lòng chọn ghế trước']);
        }
        
        $showtime = $bookingData['showtime'] ?? Session::get('current_showtime') ?? [
            'id' => $bookingData['showtime_id'],
            'movie_title' => 'Phim Đã Chọn',
            'movie_poster' => 'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?w=300&h=450&fit=crop',
            'cinema_name' => 'CineBook Cinema',
            'room' => 'Cinema 1',
            'show_date' => date('Y-m-d'),
            'show_time' => '19:00:00',
            'price' => 75000,
        ];
        
        $selectedSeats = $bookingData['selected_seats'] ?? $this->generateSeatInfo($bookingData['seat_ids']);
        $totalPrice = $bookingData['total_price'] ?? collect($selectedSeats)->sum('price');
        
        return view('booking.confirm', [
            'showtime' => $showtime,
            'selectedSeats' => $selectedSeats,
            'totalPrice' => $totalPrice,
        ]);
    }

    /**
     * Create booking and go to payment
     */
    public function store(Request $request)
    {
        $bookingData = Session::get('booking_data');
        
        if (!$bookingData) {
            return redirect()->route('movies.index')
                ->withErrors(['error' => 'Phiên đặt vé đã hết hạn']);
        }
        
        $showtime = $bookingData['showtime'] ?? Session::get('current_showtime');
        $selectedSeats = $bookingData['selected_seats'] ?? $this->generateSeatInfo($bookingData['seat_ids']);
        $totalPrice = $bookingData['total_price'] ?? collect($selectedSeats)->sum('price');
        
        // Tạo booking ID
        $bookingId = uniqid('BK');
        
        // Tạo booking object đầy đủ
        $booking = [
            'id' => $bookingId,
            'showtime_id' => $bookingData['showtime_id'],
            'showtime' => $showtime,
            'seats' => $selectedSeats,
            'seat_codes' => collect($selectedSeats)->pluck('code')->implode(', '),
            'total_price' => $totalPrice,
            'status' => 'pending',
            'created_at' => now()->toISOString(),
            'movie_title' => $showtime['movie_title'] ?? $showtime['title'] ?? 'Phim',
            'movie_poster' => $showtime['movie_poster'] ?? $showtime['poster_url'] ?? null,
            'cinema_name' => $showtime['cinema_name'] ?? 'CineBook Cinema',
            'room' => $showtime['room'] ?? 'Cinema 1',
            'show_date' => $showtime['show_date'] ?? date('Y-m-d'),
            'show_time' => $showtime['show_time'] ?? '19:00:00',
        ];
        
        // Lưu booking vào session
        Session::put('current_booking', $booking);
        Session::put('booking_id', $bookingId);
        
        // Lưu vào lịch sử booking (trong session)
        $bookingHistory = Session::get('booking_history', []);
        $bookingHistory[$bookingId] = $booking;
        Session::put('booking_history', $bookingHistory);
        
        return redirect()->route('payment.show', $bookingId);
    }

    /**
     * Show user's bookings
     */
    public function myBookings(Request $request)
    {
        // Lấy từ session
        $bookingHistory = Session::get('booking_history', []);
        
        // Thử lấy từ API
        $apiBookings = $this->bookingService->getMyBookings();
        
        $allBookings = array_values($bookingHistory);
        
        if (isset($apiBookings['data']) && is_array($apiBookings['data'])) {
            $allBookings = array_merge($allBookings, $apiBookings['data']);
        }
        
        // Sắp xếp theo thời gian tạo
        usort($allBookings, function($a, $b) {
            return ($b['created_at'] ?? '') <=> ($a['created_at'] ?? '');
        });
        
        // Tính counts cho các trạng thái
        $counts = [
            'all' => count($allBookings),
            'upcoming' => 0,
            'watched' => 0,
            'cancelled' => 0,
            'pending' => 0,
        ];
        
        foreach ($allBookings as $booking) {
            $status = $booking['status'] ?? 'pending';
            $showDate = $booking['show_date'] ?? date('Y-m-d');
            $isUpcoming = strtotime($showDate) > time();
            
            if ($status === 'cancelled') {
                $counts['cancelled']++;
            } elseif ($status === 'pending') {
                $counts['pending']++;
            } elseif ($status === 'confirmed') {
                if ($isUpcoming) {
                    $counts['upcoming']++;
                } else {
                    $counts['watched']++;
                }
            }
        }
        
        // Filter theo request
        $filter = $request->input('filter', 'all');
        $bookings = $allBookings;
        
        if ($filter !== 'all') {
            $bookings = array_filter($allBookings, function($booking) use ($filter) {
                $status = $booking['status'] ?? 'pending';
                $showDate = $booking['show_date'] ?? date('Y-m-d');
                $isUpcoming = strtotime($showDate) > time();
                
                return match($filter) {
                    'upcoming' => $status === 'confirmed' && $isUpcoming,
                    'watched' => $status === 'confirmed' && !$isUpcoming,
                    'cancelled' => $status === 'cancelled',
                    'pending' => $status === 'pending',
                    default => true,
                };
            });
        }
        
        return view('booking.history', [
            'bookings' => array_values($bookings),
            'filter' => $filter,
            'counts' => $counts,
        ]);
    }

    /**
     * Show booking detail
     */
    public function show(string $id)
    {
        // Tìm trong session trước
        $bookingHistory = Session::get('booking_history', []);
        
        if (isset($bookingHistory[$id])) {
            return view('booking.detail', [
                'booking' => $bookingHistory[$id],
            ]);
        }
        
        // Thử lấy từ API
        $booking = $this->bookingService->getBooking($id);
        
        if (!$booking['success']) {
            abort(404, 'Không tìm thấy đơn đặt vé');
        }
        
        return view('booking.detail', [
            'booking' => $booking['data'],
        ]);
    }

    /**
     * Cancel booking
     */
    public function cancel(string $id)
    {
        // Cập nhật trong session
        $bookingHistory = Session::get('booking_history', []);
        
        if (isset($bookingHistory[$id])) {
            $bookingHistory[$id]['status'] = 'cancelled';
            Session::put('booking_history', $bookingHistory);
            
            return redirect()->route('bookings.my')
                ->with('success', 'Hủy vé thành công');
        }
        
        // Thử API
        $response = $this->bookingService->cancelBooking($id);
        
        if ($response['success']) {
            return redirect()->route('bookings.my')
                ->with('success', 'Hủy vé thành công');
        }
        
        return back()->withErrors(['error' => $response['message'] ?? 'Không thể hủy vé']);
    }

    /**
     * Show ticket
     */
    public function ticket(string $id)
    {
        // Tìm trong session trước
        $bookingHistory = Session::get('booking_history', []);
        
        if (isset($bookingHistory[$id])) {
            $booking = $bookingHistory[$id];
            
            if ($booking['status'] !== 'confirmed') {
                return redirect()->route('bookings.show', $id)
                    ->withErrors(['error' => 'Vé chưa được thanh toán']);
            }
            
            return view('booking.ticket', [
                'booking' => $booking,
            ]);
        }
        
        // Thử API
        $booking = $this->bookingService->getBooking($id);
        
        if (!$booking['success']) {
            abort(404, 'Không tìm thấy vé');
        }
        
        if ($booking['data']['status'] !== 'confirmed') {
            return redirect()->route('bookings.show', $id)
                ->withErrors(['error' => 'Vé chưa được thanh toán']);
        }
        
        return view('booking.ticket', [
            'booking' => $booking['data'],
        ]);
    }
}
