<?php

namespace App\Services;

class MovieService extends ApiService
{
    /**
     * Get all movies with optional filters
     */
    public function getMovies(array $filters = []): array
    {
        $endpoint = config('api.endpoints.movies.list');
        return $this->get($endpoint, $filters, false);
    }

    /**
     * Get movie by ID
     */
    public function getMovie(string $id): array
    {
        $endpoint = str_replace('{id}', $id, config('api.endpoints.movies.detail'));
        return $this->get($endpoint, [], false);
    }

    /**
     * Search movies
     */
    public function searchMovies(string $query, array $filters = []): array
    {
        $params = array_merge(['q' => $query], $filters);
        return $this->get(config('api.endpoints.movies.search'), $params, false);
    }

    /**
     * Get now showing movies
     */
    public function getNowShowing(): array
    {
        return $this->get(config('api.endpoints.movies.now_showing'), [], false);
    }

    /**
     * Get coming soon movies
     */
    public function getComingSoon(): array
    {
        return $this->get(config('api.endpoints.movies.coming_soon'), [], false);
    }

    /**
     * Get showtimes for a movie
     */
    public function getShowtimes(string $movieId, ?string $date = null): array
    {
        $endpoint = config('api.endpoints.showtimes.by_movie');
        $params = ['movie_id' => $movieId];
        if ($date) {
            $params['show_date'] = $date;
        }
        return $this->get($endpoint, $params, false);
    }

    /**
     * Get showtime detail
     */
    public function getShowtimeDetail(string $showtimeId): array
    {
        $endpoint = str_replace('{id}', $showtimeId, config('api.endpoints.showtimes.detail'));
        return $this->get($endpoint, [], false);
    }

    /**
     * Get available seats for a showtime
     */
    public function getSeats(string $showtimeId): array
    {
        $endpoint = str_replace('{showtime_id}', $showtimeId, config('api.endpoints.seats.by_showtime'));
        return $this->get($endpoint, [], false);
    }

    /**
     * Get movies by genre
     */
    public function getMoviesByGenre(string $genre): array
    {
        return $this->getMovies(['genre' => $genre]);
    }

    /**
     * Get featured movies
     */
    public function getFeaturedMovies(int $limit = 5): array
    {
        return $this->getMovies(['featured' => true, 'limit' => $limit]);
    }
}
