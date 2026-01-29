@extends('layouts.auth')
@section('content')
<form action="{{ route('login.post') }}" method="POST">
    @csrf
    <h3 class="text-center">Đăng nhập</h3>
    @if($errors->has('login_fail')) <div class="alert alert-danger">{{ $errors->first('login_fail') }}</div> @endif
    <div class="mb-3">
        <label>Username</label>
        <input type="text" name="username" class="form-control" required>
    </div>
    <div class="mb-3">
        <label>Mật khẩu</label>
        <input type="password" name="password" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-primary w-100">Đăng nhập</button>
    <p class="mt-2 text-center">Chưa có tài khoản? <a href="{{ route('register') }}">Đăng ký</a></p>
</form>
@endsection