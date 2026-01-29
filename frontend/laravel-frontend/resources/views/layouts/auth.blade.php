<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác thực - Hệ thống Đặt vé Phim</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .auth-card {
            width: 100%;
            max-width: 450px;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            background: #ffffff;
        }
        .auth-logo {
            font-size: 2rem;
            font-weight: bold;
            color: #0d6efd;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .btn-primary {
            border-radius: 8px;
            padding: 10px;
        }
        .form-control {
            border-radius: 8px;
            padding: 12px;
        }
    </style>
</head>
<body>

    <div class="auth-card">
        <div class="auth-logo">
            <a href="{{ route('movies.index') }}" style="text-decoration: none; color: inherit;">
            <i class="fas fa-film me-2"></i>PHENIKAA CINEMA
        </a>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if(session('info'))
            <div class="alert alert-info border-0 shadow-sm">
                <i class="fas fa-info-circle me-2"></i>{{ session('info') }}
            </div>
        @endif

        @yield('content')

        <div class="mt-4 text-center text-muted">
            <small>&copy; 2026 Phenikaa University - Group 9</small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>