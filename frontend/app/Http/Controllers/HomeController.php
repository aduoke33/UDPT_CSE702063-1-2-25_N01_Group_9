<?php

namespace App\Http\Controllers;

use App\Services\MovieService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    protected MovieService $movieService;

    public function __construct(MovieService $movieService)
    {
        $this->movieService = $movieService;
    }

    /**
     * Show home page
     */
    public function index()
    {
        $nowShowing = $this->movieService->getNowShowing();
        $comingSoon = $this->movieService->getComingSoon();
        
        return view('home', [
            'nowShowing' => $nowShowing['data'] ?? [],
            'comingSoon' => $comingSoon['data'] ?? [],
        ]);
    }

    /**
     * Search movies
     */
    public function search(Request $request)
    {
        $query = $request->input('q', '');
        $genre = $request->input('genre');
        
        $filters = [];
        if ($genre) {
            $filters['genre'] = $genre;
        }
        
        $results = $this->movieService->searchMovies($query, $filters);
        
        return view('search', [
            'query' => $query,
            'movies' => $results['data'] ?? [],
            'genre' => $genre,
        ]);
    }
}
