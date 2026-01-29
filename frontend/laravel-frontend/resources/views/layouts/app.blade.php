<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}"> {{-- THÊM DÒNG NÀY --}}
    <title>Phenikaa Cinema - Trải nghiệm điện ảnh đích thực</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root { --main-color: #e50914; }
        body { background-color: #f4f7f6; font-family: 'Segoe UI', sans-serif; }
        .navbar-brand { font-weight: 800; font-size: 1.5rem; color: var(--main-color) !important; }
        .footer { background: #212529; color: white; padding: 30px 0; margin-top: 50px; }
        .nav-link:hover { color: var(--main-color) !important; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="{{ route('movies.index') }}">
                <i class="fas fa-film me-2"></i>
                <span>PHENIKAA CINEMA</span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto align-items-center">
                    @if(Session::has('user'))
                        {{-- ICON THÔNG BÁO - Đã đưa vào trong Navbar --}}
                        <a href="{{ route('notifications.index') }}" class="nav-link text-white position-relative me-3">
                            <i class="fas fa-bell fs-5"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="noti-count" style="font-size: 0.6rem;">
                                {{-- Số thông báo thực tế --}}
                                {{ $unreadCount ?? 0 }} 
                            </span>
                        </a>

                        <div class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle text-white fw-bold" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i> {{ Session::get('user')['username'] }}
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                <li><a class="dropdown-item" href="{{ route('notifications.index') }}"><i class="fas fa-ticket-alt me-2"></i>Vé của tôi</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form action="{{ route('logout') }}" method="POST">
                                        @csrf
                                        <button type="submit" class="dropdown-item text-danger fw-bold">
                                            <i class="fas fa-sign-out-alt me-2"></i>Đăng xuất
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    @else
                        <a href="{{ route('login') }}" class="nav-link text-white me-3 fw-bold">Đăng nhập</a>
                        <a href="{{ route('register') }}" class="btn btn-danger btn-auth shadow-sm px-4 rounded-pill fw-bold">Đăng ký</a>
                    @endif
                </div>
            </div>
        </div>
    </nav>

    <main class="container my-5" style="min-height: 70vh;">
        {{-- Thông báo lỗi chung --}}
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- Thông báo thành công --}}
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
                <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="footer text-center">
        <div class="container">
            <p class="mb-1 fw-bold">PHENIKAA CINEMA - TRẢI NGHIỆM ĐIỆN ẢNH ĐÍNH THỰC</p>
            <p class="small text-muted mb-0">&copy; 2026 Nhóm 9 - Dự án Microservices. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>