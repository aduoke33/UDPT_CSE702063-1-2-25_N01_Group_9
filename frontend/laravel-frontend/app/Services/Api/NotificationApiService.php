<?php

namespace App\Services\Api;

use App\Services\ApiService;
use Illuminate\Http\Client\Response;

class NotificationApiService extends ApiService
{
    /**
     * Lấy danh sách tất cả thông báo của người dùng
     */
    public function getUserNotifications()
    {
        // Backend sẽ dựa vào JWT Token để biết user nào đang gọi
        return $this->request()->get($this->baseUrl . '/api/notifications/');
    }

    /**
     * Đánh dấu một thông báo là đã đọc
     */
    public function markAsRead($id)
    {
        return $this->request()->put($this->baseUrl . "/api/notifications/{$id}/read");
    }
}
