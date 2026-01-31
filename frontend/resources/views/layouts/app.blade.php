<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CineBook - Hệ Thống Đặt Vé Xem Phim Trực Tuyến')</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#fef2f2',
                            100: '#fee2e2',
                            200: '#fecaca',
                            300: '#fca5a5',
                            400: '#f87171',
                            500: '#ef4444',
                            600: '#dc2626',
                            700: '#b91c1c',
                            800: '#991b1b',
                            900: '#7f1d1d',
                        },
                        dark: {
                            100: '#1e1e1e',
                            200: '#2d2d2d',
                            300: '#3d3d3d',
                            400: '#4d4d4d',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <!-- Custom Styles -->
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0f0f0f;
            color: #ffffff;
        }

        .gradient-bg {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
        }

        .card-hover {
            transition: all 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        }

        .btn-primary {
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);
            transform: translateY(-2px);
        }

        .seat {
            width: 32px;
            height: 32px;
            border-radius: 6px 6px 10px 10px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .seat.available {
            background-color: #374151;
            border: 2px solid #4b5563;
        }

        .seat.available:hover {
            background-color: #4b5563;
        }

        .seat.selected {
            background-color: #dc2626;
            border: 2px solid #ef4444;
        }

        .seat.occupied {
            background-color: #1f2937;
            border: 2px solid #374151;
            cursor: not-allowed;
        }

        .seat.vip {
            background-color: #7c3aed;
            border: 2px solid #8b5cf6;
        }

        .movie-card-overlay {
            background: linear-gradient(to top, rgba(0, 0, 0, 0.9) 0%, rgba(0, 0, 0, 0.5) 50%, transparent 100%);
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #1f2937;
        }

        ::-webkit-scrollbar-thumb {
            background: #4b5563;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #6b7280;
        }
    </style>

    @stack('styles')
</head>

<body class="min-h-screen">
    <!-- Header -->
    @include('partials.header')

    <!-- Flash Messages -->
    @if(session('success'))
        <div id="flash-success" class="fixed top-20 right-4 z-50 bg-green-600 text-white px-6 py-3 rounded-lg shadow-lg">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div id="flash-error" class="fixed top-20 right-4 z-50 bg-red-600 text-white px-6 py-3 rounded-lg shadow-lg">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div id="flash-error" class="fixed top-20 right-4 z-50 bg-red-600 text-white px-6 py-3 rounded-lg shadow-lg">
            {{ $errors->first() }}
        </div>
    @endif

    <!-- Main Content -->
    <main class="min-h-screen">
        @yield('content')
    </main>

    <!-- Footer -->
    @include('partials.footer')

    <!-- Scripts -->
    <script>
        // CSRF Token for AJAX
        window.csrfToken = '{{ csrf_token() }}';

        // Auto hide flash messages
        setTimeout(() => {
            const flashMessages = document.querySelectorAll('[id^="flash-"]');
            flashMessages.forEach(msg => {
                msg.style.opacity = '0';
                msg.style.transition = 'opacity 0.5s ease';
                setTimeout(() => msg.remove(), 500);
            });
        }, 3000);

        // Format currency
        function formatCurrency(amount) {
            return new Intl.NumberFormat('vi-VN', {
                style: 'currency',
                currency: 'VND'
            }).format(amount);
        }

        // Format date
        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('vi-VN', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }
    </script>

    @stack('scripts')
</body>

</html>