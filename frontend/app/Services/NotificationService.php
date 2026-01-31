<?php

namespace App\Services;

class NotificationService extends ApiService
{
    /**
     * Get user notifications
     */
    public function getNotifications(int $page = 1, int $limit = 20): array
    {
        $params = http_build_query(['page' => $page, 'limit' => $limit]);
        return $this->get(config('api.endpoints.notifications.list') . '?' . $params);
    }

    /**
     * Get unread notifications
     */
    public function getUnreadNotifications(): array
    {
        return $this->get(config('api.endpoints.notifications.list') . '?unread=true');
    }

    /**
     * Get unread count
     */
    public function getUnreadCount(): array
    {
        return $this->get(config('api.endpoints.notifications.unread_count'));
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $id): array
    {
        $endpoint = str_replace('{id}', $id, config('api.endpoints.notifications.mark_read'));
        return $this->post($endpoint);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): array
    {
        return $this->post(config('api.endpoints.notifications.mark_all_read'));
    }

    /**
     * Delete notification
     */
    public function deleteNotification(int $id): array
    {
        $endpoint = str_replace('{id}', $id, config('api.endpoints.notifications.delete'));
        return $this->delete($endpoint);
    }

    /**
     * Get notification settings
     */
    public function getSettings(): array
    {
        return $this->get('/api/notifications/settings');
    }

    /**
     * Update notification settings
     */
    public function updateSettings(array $settings): array
    {
        return $this->put('/api/notifications/settings', $settings);
    }
}
