<?php

namespace App\Services;

class BookingService extends ApiService
{
    /**
     * Create a new booking
     */
    public function createBooking(array $data): array
    {
        return $this->post(config('api.endpoints.bookings.create'), $data);
    }

    /**
     * Get user's bookings
     */
    public function getMyBookings(): array
    {
        return $this->get(config('api.endpoints.bookings.my_bookings'));
    }

    /**
     * Get booking detail
     */
    public function getBooking(string $id): array
    {
        $endpoint = str_replace('{id}', $id, config('api.endpoints.bookings.detail'));
        return $this->get($endpoint);
    }

    /**
     * Cancel a booking
     */
    public function cancelBooking(string $id): array
    {
        $endpoint = str_replace('{id}', $id, config('api.endpoints.bookings.cancel'));
        return $this->post($endpoint);
    }

    /**
     * Hold seats temporarily
     */
    public function holdSeats(string $showtimeId, array $seatIds): array
    {
        return $this->post(config('api.endpoints.bookings.hold_seats'), [
            'showtime_id' => $showtimeId,
            'seat_ids' => $seatIds,
        ]);
    }

    /**
     * Release held seats
     */
    public function releaseSeats(string $showtimeId, array $seatIds): array
    {
        return $this->post(config('api.endpoints.bookings.release_seats'), [
            'showtime_id' => $showtimeId,
            'seat_ids' => $seatIds,
        ]);
    }

    /**
     * Confirm booking after payment
     */
    public function confirmBooking(string $bookingId, string $paymentId): array
    {
        $endpoint = str_replace('{id}', $bookingId, config('api.endpoints.bookings.confirm'));
        return $this->post($endpoint, ['payment_id' => $paymentId]);
    }

    /**
     * Get booking history
     */
    public function getBookingHistory(int $page = 1, int $limit = 10): array
    {
        $params = http_build_query(['page' => $page, 'limit' => $limit]);
        return $this->get(config('api.endpoints.bookings.my_bookings') . '?' . $params);
    }

    /**
     * Get upcoming bookings
     */
    public function getUpcomingBookings(): array
    {
        return $this->get(config('api.endpoints.bookings.my_bookings') . '?status=upcoming');
    }

    /**
     * Get past bookings
     */
    public function getPastBookings(): array
    {
        return $this->get(config('api.endpoints.bookings.my_bookings') . '?status=past');
    }
}
