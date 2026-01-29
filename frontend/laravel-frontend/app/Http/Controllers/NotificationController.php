<?php

namespace App\Http\Controllers;

use App\Services\Api\NotificationApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class NotificationController extends Controller
{

    protected $notificationApi;

    public function __construct(NotificationApiService $notificationApi)
    {
        $this->notificationApi = $notificationApi;
    }

    public function index()
    {
        // Kiểm tra auth
        if (!Session::get('jwt_token')) {
            return redirect()->route('login');
        }
        /** @var \Illuminate\Http\Client\Response $response */
        $response = $this->notificationApi->getUserNotifications();
        $notifications = [];
        if ($response->successful()) {
            $notifications = $response->json();
        }

        return view('notifications.index', compact('notifications'));
    }

    public function read($id)
    {
        // Kiểm tra auth
        if (!Session::get('jwt_token')) {
            return redirect()->route('login');
        }

        $this->notificationApi->markAsRead($id);
        return back();
    }
}
