@extends('layouts.app')

@section('title', 'Thông Báo - CineBook')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-3xl mx-auto">
            <!-- Header -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-white">Thông Báo</h1>
                    <p class="text-gray-400 mt-2">Quản lý thông báo của bạn</p>
                </div>

                @if(count($notifications) > 0)
                    <form action="{{ route('notifications.mark-all-read') }}" method="POST">
                        @csrf
                        <button type="submit" class="text-red-500 hover:text-red-400 text-sm font-medium">
                            Đánh Dấu Tất Cả Đã Đọc
                        </button>
                    </form>
                @endif
            </div>

            <!-- Notifications List -->
            @if(count($notifications) > 0)
                <div class="space-y-4">
                    @foreach($notifications as $notification)
                        <div
                            class="bg-dark-200 rounded-xl p-4 border border-gray-800 {{ !($notification['is_read'] ?? false) ? 'border-l-4 border-l-red-500' : '' }}">
                            <div class="flex items-start">
                                <!-- Icon -->
                                <div class="flex-shrink-0 mr-4">
                                    @php
                                        $iconClass = match ($notification['type'] ?? 'info') {
                                            'booking' => 'bg-green-600/20 text-green-500',
                                            'payment' => 'bg-blue-600/20 text-blue-500',
                                            'promotion' => 'bg-yellow-600/20 text-yellow-500',
                                            default => 'bg-gray-600/20 text-gray-500',
                                        };
                                    @endphp
                                    <div class="w-10 h-10 rounded-full {{ $iconClass }} flex items-center justify-center">
                                        @if(($notification['type'] ?? '') === 'booking')
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                                            </svg>
                                        @elseif(($notification['type'] ?? '') === 'payment')
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                            </svg>
                                        @elseif(($notification['type'] ?? '') === 'promotion')
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
                                            </svg>
                                        @else
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        @endif
                                    </div>
                                </div>

                                <!-- Content -->
                                <div class="flex-1">
                                    <h3
                                        class="text-white font-medium {{ !($notification['is_read'] ?? false) ? 'font-semibold' : '' }}">
                                        {{ $notification['title'] ?? 'Thông Báo' }}
                                    </h3>
                                    <p class="text-gray-400 text-sm mt-1">
                                        {{ $notification['message'] ?? '' }}
                                    </p>
                                    <span class="text-gray-500 text-xs mt-2 block">
                                        {{ isset($notification['created_at']) ? \Carbon\Carbon::parse($notification['created_at'])->diffForHumans() : '' }}
                                    </span>
                                </div>

                                <!-- Actions -->
                                <div class="flex items-center space-x-2 ml-4">
                                    @if(!($notification['is_read'] ?? false))
                                        <form action="{{ route('notifications.mark-read', $notification['id']) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="text-gray-500 hover:text-white p-2" title="Đánh dấu đã đọc">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M5 13l4 4L19 7" />
                                                </svg>
                                            </button>
                                        </form>
                                    @endif

                                    <form action="{{ route('notifications.destroy', $notification['id']) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-gray-500 hover:text-red-500 p-2" title="Xóa">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-16">
                    <svg class="w-20 h-20 text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <h3 class="text-xl font-semibold text-white mb-2">Không có thông báo</h3>
                    <p class="text-gray-400">Bạn chưa có thông báo nào</p>
                </div>
            @endif
        </div>
    </div>
@endsection