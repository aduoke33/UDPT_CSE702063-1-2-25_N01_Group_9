@extends('layouts.auth')
@section('content')
<form action="{{ route('register.post') }}" method="POST">
    @csrf
    <h3 class="text-center">Đăng ký</h3>
    @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif
    <div class="mb-3">
        <label>Username</label>
        <input type="text" name="username" class="form-control" value="{{ old('username') }}" required>
    </div>
    <div class="mb-3">
        <label>Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
    </div>
    <div class="mb-3">
        <label>Họ và tên</label>
        <input type="text" name="full_name" class="form-control">
    </div>
    <div class="mb-3">
        <label>Mật khẩu</label>
        <input type="password" name="password" class="form-control" required>
    </div>
    <div class="mb-3">
        <label>Xác nhận mật khẩu</label>
        <input type="password" name="password_confirmation" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-success w-100">Đăng ký</button>
</form>
@endsection