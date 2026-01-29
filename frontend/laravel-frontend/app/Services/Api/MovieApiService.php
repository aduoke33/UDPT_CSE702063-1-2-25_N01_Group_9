<?php

namespace App\Services\Api;

use App\Services\ApiService;
use Illuminate\Http\Client\Response;

class MovieApiService extends ApiService
{
    /**
     * Lấy danh sách phim từ Backend
     */
    public function getAllMovies()
    {
        return $this->request()->get($this->baseUrl . '/api/movies/');
    }

    /**
     * Lấy chi tiết một bộ phim và các suất chiếu của nó
     */
    public function getMovieDetail($id)
    {
        return $this->request()->get($this->baseUrl . '/api/movies/' . $id);
    }
}
