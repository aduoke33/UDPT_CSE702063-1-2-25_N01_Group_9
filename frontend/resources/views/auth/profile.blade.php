@extends('layouts.app')

@section('title', 'Thông Tin Cá Nhân - CineBook')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-white">Thông Tin Cá Nhân</h1>
                <p class="text-gray-400 mt-2">Quản lý thông tin tài khoản của bạn</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Sidebar -->
                <div class="md:col-span-1">
                    <div class="bg-dark-200 rounded-xl p-6 border border-gray-800">
                        <!-- Avatar -->
                        <div class="text-center mb-6">
                            <div
                                class="w-24 h-24 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center mx-auto mb-4">
                                <span class="text-3xl font-bold text-white">
                                    {{ strtoupper(substr($user['full_name'] ?? $user['username'] ?? 'U', 0, 1)) }}
                                </span>
                            </div>
                            <h3 class="text-lg font-semibold text-white">
                                {{ $user['full_name'] ?? $user['username'] ?? 'User' }}</h3>
                            <p class="text-gray-400 text-sm">{{ $user['email'] ?? '' }}</p>
                        </div>

                        <!-- Menu -->
                        <nav class="space-y-2">
                            <a href="{{ route('profile') }}"
                                class="flex items-center px-4 py-3 text-white bg-dark-300 rounded-lg">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Thông Tin Cá Nhân
                            </a>
                            <a href="{{ route('bookings.my') }}"
                                class="flex items-center px-4 py-3 text-gray-400 hover:text-white hover:bg-dark-300 rounded-lg transition">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                                </svg>
                                Lịch Sử Đặt Vé
                            </a>
                            <a href="{{ route('notifications.index') }}"
                                class="flex items-center px-4 py-3 text-gray-400 hover:text-white hover:bg-dark-300 rounded-lg transition">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                                Thông Báo
                            </a>
                        </nav>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="md:col-span-2">
                    <div class="bg-dark-200 rounded-xl p-6 border border-gray-800">
                        <h2 class="text-xl font-semibold text-white mb-6">Chỉnh Sửa Thông Tin</h2>

                        <form action="{{ route('profile.update') }}" method="POST" class="space-y-6">
                            @csrf
                            @method('PUT')

                            <!-- Name -->
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-300 mb-2">
                                    Họ và Tên
                                </label>
                                <input type="text" id="name" name="name" value="{{ $user['full_name'] ?? '' }}"
                                    class="w-full bg-dark-100 border border-gray-700 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-red-500 transition">
                            </div>

                            <!-- Email (Read-only) -->
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-300 mb-2">
                                    Email
                                </label>
                                <input type="email" id="email" value="{{ $user['email'] ?? '' }}" disabled
                                    class="w-full bg-dark-300 border border-gray-700 rounded-lg px-4 py-3 text-gray-400 cursor-not-allowed">
                                <p class="mt-1 text-xs text-gray-500">Email không thể thay đổi</p>
                            </div>

                            <!-- Phone -->
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-300 mb-2">
                                    Số Điện Thoại
                                </label>
                                <input type="tel" id="phone" name="phone" value="{{ $user['phone'] ?? '' }}"
                                    class="w-full bg-dark-100 border border-gray-700 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-red-500 transition">
                            </div>

                            <!-- Submit -->
                            <div class="flex justify-end">
                                <button type="submit" class="btn-primary px-6 py-3 rounded-lg text-white font-semibold">
                                    Lưu Thay Đổi
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Change Password -->
                    <div class="bg-dark-200 rounded-xl p-6 border border-gray-800 mt-6">
                        <h2 class="text-xl font-semibold text-white mb-6">Đổi Mật Khẩu</h2>

                        @if(session('success'))
                            <div class="mb-4 p-4 bg-green-600/20 border border-green-600 rounded-lg text-green-400">
                                {{ session('success') }}
                            </div>
                        @endif

                        @if($errors->any())
                            <div class="mb-4 p-4 bg-red-600/20 border border-red-600 rounded-lg text-red-400">
                                <ul class="list-disc list-inside">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form action="{{ route('profile.change-password') }}" method="POST" class="space-y-6">
                            @csrf
                            <div>
                                <label for="current_password" class="block text-sm font-medium text-gray-300 mb-2">
                                    Mật Khẩu Hiện Tại
                                </label>
                                <input type="password" id="current_password" name="current_password" required
                                    class="w-full bg-dark-100 border border-gray-700 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-red-500 transition">
                            </div>

                            <div>
                                <label for="new_password" class="block text-sm font-medium text-gray-300 mb-2">
                                    Mật Khẩu Mới
                                </label>
                                <input type="password" id="new_password" name="new_password" required minlength="6"
                                    class="w-full bg-dark-100 border border-gray-700 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-red-500 transition">
                                <p class="mt-1 text-xs text-gray-500">Tối thiểu 6 ký tự</p>
                            </div>

                            <div>
                                <label for="new_password_confirmation" class="block text-sm font-medium text-gray-300 mb-2">
                                    Xác Nhận Mật Khẩu Mới
                                </label>
                                <input type="password" id="new_password_confirmation" name="new_password_confirmation"
                                    required
                                    class="w-full bg-dark-100 border border-gray-700 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-red-500 transition">
                            </div>

                            <div class="flex justify-end">
                                <button type="submit"
                                    class="px-6 py-3 rounded-lg border border-gray-600 text-white font-semibold hover:bg-dark-300 transition">
                                    Cập Nhật Mật Khẩu
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection