<?php

namespace App\Http\Controllers;

use App\Services\MovieService;
use Illuminate\Http\Request;

class MovieController extends Controller
{
    protected MovieService $movieService;

    public function __construct(MovieService $movieService)
    {
        $this->movieService = $movieService;
    }

    /**
     * Show all movies
     */
    public function index(Request $request)
    {
        $type = $request->input('type', 'all');
        
        $movies = match($type) {
            'now-showing' => $this->movieService->getNowShowing(),
            'coming-soon' => $this->movieService->getComingSoon(),
            default => $this->movieService->getMovies(),
        };
        
        return view('movies.index', [
            'movies' => $movies['data'] ?? [],
            'type' => $type,
        ]);
    }

    /**
     * Show movie detail
     */
    public function show(string $id)
    {
        $movie = $this->movieService->getMovie($id);
        
        if (!$movie['success']) {
            abort(404, 'Không tìm thấy phim');
        }
        
        $showtimesResponse = $this->movieService->getShowtimes($id);
        $showtimes = $showtimesResponse['data'] ?? [];
        
        // Thêm thông tin cinema_name nếu chưa có
        foreach ($showtimes as &$showtime) {
            if (!isset($showtime['cinema_name'])) {
                $showtime['cinema_name'] = 'CineBook Cinema';
            }
        }
        
        return view('movies.show', [
            'movie' => $movie['data'],
            'showtimes' => $showtimes,
        ]);
    }

    /**
     * Show now showing movies
     */
    public function nowShowing()
    {
        $movies = $this->movieService->getNowShowing();
        
        return view('movies.index', [
            'movies' => $movies['data'] ?? [],
            'type' => 'now-showing',
            'title' => 'Phim Đang Chiếu',
        ]);
    }

    /**
     * Show coming soon movies
     */
    public function comingSoon()
    {
        $movies = $this->movieService->getComingSoon();
        
        return view('movies.index', [
            'movies' => $movies['data'] ?? [],
            'type' => 'coming-soon',
            'title' => 'Phim Sắp Chiếu',
        ]);
    }

    /**
     * Get showtimes for a movie (AJAX)
     */
    public function getShowtimes(string $id, Request $request)
    {
        $date = $request->input('date');
        $showtimes = $this->movieService->getShowtimes($id, $date);
        
        // Enrich showtimes with cinema_name and start_time
        if (isset($showtimes['data']) && is_array($showtimes['data'])) {
            foreach ($showtimes['data'] as &$showtime) {
                if (!isset($showtime['cinema_name'])) {
                    $showtime['cinema_name'] = 'CineBook Cinema';
                }
                // Map show_time to start_time for JavaScript compatibility
                if (isset($showtime['show_time']) && !isset($showtime['start_time'])) {
                    // Combine show_date and show_time for proper parsing
                    $dateStr = $showtime['show_date'] ?? date('Y-m-d');
                    $timeStr = $showtime['show_time'] ?? '00:00:00';
                    $showtime['start_time'] = $dateStr . 'T' . $timeStr;
                }
            }
        }
        
        return response()->json($showtimes);
    }

    /**
     * Get available seats for a showtime (AJAX)
     */
    public function getSeats(string $showtimeId)
    {
        $seats = $this->movieService->getSeats($showtimeId);
        
        return response()->json($seats);
    }
}
