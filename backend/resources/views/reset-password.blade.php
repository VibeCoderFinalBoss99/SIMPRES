<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Atur Ulang Kata Sandi - SIMPRES</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
<div class="login-page">
    <div class="login-box">
        <div class="logo">
            <span class="brand-logo">@include('admin.icon', ['name' => 'shield'])</span>
            <span><strong>SIMPRES</strong><small>Atur ulang kata sandi</small></span>
        </div>

        @if ($errors->any())
            <div class="alert error">{{ $errors->first() }}</div>
        @endif

        @if (session('success'))
            <div class="alert success">{{ session('success') }}</div>
        @else
            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $email) }}" required readonly>
                </div>
                <div class="field">
                    <label for="password">Kata sandi baru</label>
                    <input type="password" id="password" name="password" minlength="8" required autofocus>
                </div>
                <div class="field">
                    <label for="password_confirmation">Ulangi kata sandi baru</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" minlength="8" required>
                </div>
                <button type="submit" class="btn block">Simpan Kata Sandi</button>
            </form>
        @endif
    </div>
</div>
</body>
</html>
