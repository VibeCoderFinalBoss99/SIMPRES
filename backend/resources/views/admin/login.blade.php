<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="{{ asset('img/logo.svg') }}">
    <title>Masuk - SIMPRES Admin</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
<div class="login-page">
    <div class="login-box">
        <div class="logo">
            <img class="brand-logo img" src="{{ asset('img/logo.svg') }}" alt="Logo SIMPRES" width="40" height="40">
            <span><strong>SIMPRES</strong><small>Admin Web UPTD Puskesmas Simbang</small></span>
        </div>

        @if ($errors->any())
            <div class="alert error">{{ $errors->first() }}</div>
        @endif

        @if (session('success'))
            <div class="alert success">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                       class="{{ $errors->has('email') ? 'is-invalid' : '' }}">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="field">
                <label class="checkbox"><input type="checkbox" name="remember" value="1"> Ingat saya</label>
            </div>
            <button type="submit" class="btn block">Masuk</button>
        </form>
    </div>
</div>
@include('admin.password-toggle')
</body>
</html>
