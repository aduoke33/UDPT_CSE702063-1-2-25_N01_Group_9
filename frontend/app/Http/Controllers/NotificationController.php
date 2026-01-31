<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Show notifications page
     */
    public function index()
    {
        $notifications = $this->notificationService->getNotifications();
        
        return view('notifications.index', [
            'notifications' => $notifications['data'] ?? [],
        ]);
    }

    /**
     * Get unread count (AJAX)
     */
    public function unreadCount()
    {
        $response = $this->notificationService->getUnreadCount();
        
        return response()->json([
            'count' => $response['data']['count'] ?? 0,
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $id)
    {
        $response = $this->notificationService->markAsRead($id);
        
        if (request()->ajax()) {
            return response()->json($response);
        }
        
        return back();
    }

    /**
     * Mark all as read
     */
    public function markAllAsRead()
    {
        $response = $this->notificationService->markAllAsRead();
        
        if (request()->ajax()) {
            return response()->json($response);
        }
        
        return back()->with('success', 'Da danh dau tat ca la da doc');
    }

    /**
     * Delete notification
     */
    public function destroy(int $id)
    {
        $response = $this->notificationService->deleteNotification($id);
        
        if (request()->ajax()) {
            return response()->json($response);
        }
        
        return back()->with('success', 'Da xoa thong bao');
    }
}
