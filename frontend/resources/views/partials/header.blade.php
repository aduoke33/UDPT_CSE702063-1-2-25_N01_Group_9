<header class="fixed top-0 left-0 right-0 z-50 bg-dark-100/95 backdrop-blur-md border-b border-gray-800">
    <nav class="container mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            <!-- Logo -->
            <a href="{{ route('home') }}" class="flex items-center space-x-2">
                <div
                    class="w-10 h-10 bg-gradient-to-br from-red-500 to-red-700 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
                    </svg>
                </div>
                <span class="text-xl font-bold text-white">CineBook</span>
            </a>

            <!-- Navigation Links -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="{{ route('home') }}"
                    class="text-gray-300 hover:text-white transition {{ request()->routeIs('home') ? 'text-white font-medium' : '' }}">
                    Trang Chủ
                </a>
                <a href="{{ route('movies.now-showing') }}"
                    class="text-gray-300 hover:text-white transition {{ request()->routeIs('movies.now-showing') ? 'text-white font-medium' : '' }}">
                    Phim Đang Chiếu
                </a>
                <a href="{{ route('movies.coming-soon') }}"
                    class="text-gray-300 hover:text-white transition {{ request()->routeIs('movies.coming-soon') ? 'text-white font-medium' : '' }}">
                    Phim Sắp Chiếu
                </a>
            </div>

            <!-- Search Bar -->
            <div class="hidden lg:block flex-1 max-w-md mx-8">
                <form action="{{ route('search') }}" method="GET" class="relative">
                    <input type="text" name="q" placeholder="Tìm kiếm phim..." value="{{ request('q') }}"
                        class="w-full bg-dark-200 border border-gray-700 rounded-full py-2 pl-10 pr-4 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-red-500 transition">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </form>
            </div>

            <!-- User Menu -->
            <div class="flex items-center space-x-4">
                @if($isAuthenticated ?? false)
                    <!-- Notifications -->
                    <a href="{{ route('notifications.index') }}" class="relative text-gray-400 hover:text-white transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <span id="notification-badge"
                            class="hidden absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center"></span>
                    </a>

                    <!-- User Dropdown -->
                    <div class="relative" x-data="{ open: false }">
                        <button onclick="this.nextElementSibling.classList.toggle('hidden')"
                            class="flex items-center space-x-2 text-gray-300 hover:text-white transition">
                            <div
                                class="w-8 h-8 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center">
                                <span class="text-sm font-medium text-white">
                                    {{ strtoupper(substr($currentUser['full_name'] ?? $currentUser['username'] ?? 'U', 0, 1)) }}
                                </span>
                            </div>
                            <span
                                class="hidden sm:block">{{ $currentUser['full_name'] ?? $currentUser['username'] ?? 'User' }}</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div
                            class="hidden absolute right-0 mt-2 w-48 bg-dark-200 rounded-lg shadow-xl border border-gray-700 py-1">
                            <a href="{{ route('profile') }}"
                                class="block px-4 py-2 text-gray-300 hover:bg-dark-300 hover:text-white transition">
                                Thông Tin Cá Nhân
                            </a>
                            <a href="{{ route('bookings.my') }}"
                                class="block px-4 py-2 text-gray-300 hover:bg-dark-300 hover:text-white transition">
                                Lịch Sử Đặt Vé
                            </a>
                            <hr class="my-1 border-gray-700">
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit"
                                    class="w-full text-left px-4 py-2 text-gray-300 hover:bg-dark-300 hover:text-white transition">
                                    Đăng Xuất
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="text-gray-300 hover:text-white transition">
                        Đăng Nhập
                    </a>
                    <a href="{{ route('register') }}"
                        class="btn-primary px-4 py-2 rounded-full text-white text-sm font-medium">
                        Đăng Ký
                    </a>
                @endif

                <!-- Mobile Menu Button -->
                <button id="mobile-menu-btn" class="md:hidden text-gray-400 hover:text-white"
                    onclick="document.getElementById('mobile-menu').classList.toggle('hidden')">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div id="mobile-menu" class="hidden md:hidden pb-4">
            <div class="flex flex-col space-y-2">
                <a href="{{ route('home') }}" class="text-gray-300 hover:text-white py-2 transition">Trang Chủ</a>
                <a href="{{ route('movies.now-showing') }}" class="text-gray-300 hover:text-white py-2 transition">Phim
                    Đang Chiếu</a>
                <a href="{{ route('movies.coming-soon') }}" class="text-gray-300 hover:text-white py-2 transition">Phim
                    Sắp Chiếu</a>

                <!-- Mobile Search -->
                <form action="{{ route('search') }}" method="GET" class="pt-2">
                    <input type="text" name="q" placeholder="Tìm kiếm phim..."
                        class="w-full bg-dark-200 border border-gray-700 rounded-lg py-2 px-4 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-red-500">
                </form>
            </div>
        </div>
    </nav>
</header>

<!-- Spacer for fixed header -->
<div class="h-16"></div>