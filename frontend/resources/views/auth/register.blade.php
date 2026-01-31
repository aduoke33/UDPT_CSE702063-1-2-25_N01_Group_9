@extends('layouts.app')

@section('title', 'Đăng Ký - CineBook')

@section('content')
    <div class="min-h-[80vh] flex items-center justify-center py-12 px-4">
        <div class="w-full max-w-md">
            <!-- Logo -->
            <div class="text-center mb-8">
                <a href="{{ route('home') }}" class="inline-flex items-center space-x-2">
                    <div
                        class="w-12 h-12 bg-gradient-to-br from-red-500 to-red-700 rounded-lg flex items-center justify-center">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
                        </svg>
                    </div>
                    <span class="text-2xl font-bold text-white">CineBook</span>
                </a>
                <h1 class="text-2xl font-bold text-white mt-6">Tạo Tài Khoản</h1>
                <p class="text-gray-400 mt-2">Đăng ký để trải nghiệm dịch vụ đặt vé tuyệt vời</p>
            </div>

            <!-- Register Form -->
            <div class="bg-dark-200 rounded-2xl p-8 border border-gray-800">
                <!-- Hiển thị lỗi tổng quát -->
                @if($errors->any())
                    <div class="mb-6 bg-red-900/50 border border-red-500 rounded-lg p-4">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-red-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <h3 class="text-red-400 font-medium">Đăng ký không thành công</h3>
                                <p class="text-red-300 text-sm mt-1">{{ $errors->first() }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                <form action="{{ route('register') }}" method="POST" class="space-y-5">
                    @csrf

                    <!-- Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-300 mb-2">
                            Họ và Tên
                        </label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus
                            class="w-full bg-dark-100 border border-gray-700 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-red-500 transition @error('name') border-red-500 @enderror"
                            placeholder="Nhập họ và tên">
                        @error('name')
                            <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-300 mb-2">
                            Email
                        </label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required
                            class="w-full bg-dark-100 border border-gray-700 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-red-500 transition @error('email') border-red-500 @enderror"
                            placeholder="Nhập email của bạn">
                        @error('email')
                            <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Phone -->
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-300 mb-2">
                            Số Điện Thoại
                        </label>
                        <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" required
                            class="w-full bg-dark-100 border border-gray-700 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-red-500 transition @error('phone') border-red-500 @enderror"
                            placeholder="Nhập số điện thoại">
                        @error('phone')
                            <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-300 mb-2">
                            Mật Khẩu
                        </label>
                        <div class="relative">
                            <input type="password" id="password" name="password" required
                                class="w-full bg-dark-100 border border-gray-700 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-red-500 transition @error('password') border-red-500 @enderror"
                                placeholder="Nhập mật khẩu (tối thiểu 6 ký tự)">
                            <button type="button" onclick="togglePassword('password')"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                        @error('password')
                            <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-300 mb-2">
                            Xác Nhận Mật Khẩu
                        </label>
                        <div class="relative">
                            <input type="password" id="password_confirmation" name="password_confirmation" required
                                class="w-full bg-dark-100 border border-gray-700 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-red-500 transition"
                                placeholder="Nhập lại mật khẩu">
                            <button type="button" onclick="togglePassword('password_confirmation')"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Terms -->
                    <div class="flex items-start">
                        <input type="checkbox" id="terms" name="terms" required
                            class="w-4 h-4 mt-1 rounded border-gray-700 bg-dark-100 text-red-500 focus:ring-red-500">
                        <label for="terms" class="ml-2 text-sm text-gray-400">
                            Tôi đồng ý với
                            <a href="#" class="text-red-500 hover:text-red-400">Điều khoản sử dụng</a>
                            và
                            <a href="#" class="text-red-500 hover:text-red-400">Chính sách bảo mật</a>
                        </label>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="w-full btn-primary py-3 rounded-lg text-white font-semibold">
                        Đăng Ký
                    </button>
                </form>

                <!-- Login Link -->
                <p class="text-center mt-6 text-gray-400">
                    Đã có tài khoản?
                    <a href="{{ route('login') }}" class="text-red-500 hover:text-red-400 font-medium">Đăng nhập</a>
                </p>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            input.type = input.type === 'password' ? 'text' : 'password';
        }
    </script>
@endpush