<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') - SIMPRES</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
@php
    $waiting = \App\Models\LeaveRequest::where('status', 'menunggu')->count()
        + \App\Models\AttendanceCorrection::where('status', 'menunggu')->count()
        + \App\Models\FieldDuty::where('status', 'menunggu')->count();
    $menu = [
        ['Dashboard', 'admin', 'admin', 'dashboard'],
        ['Review & Persetujuan', 'admin/pengajuan', 'admin/pengajuan*', 'review'],
        ['Data Pegawai', 'admin/pegawai', 'admin/pegawai*', 'users'],
        ['Lokasi Kerja', 'admin/lokasi', 'admin/lokasi*', 'pin'],
        ['Roster', 'admin/jadwal', ['admin/jadwal*', 'admin/shift*'], 'calendar'],
        ['Rekap & Ekspor', 'admin/rekap', 'admin/rekap*', 'download'],
        ['Audit Log', 'admin/audit', 'admin/audit*', 'history'],
        ['Konfigurasi', 'admin/konfigurasi', 'admin/konfigurasi*', 'sliders'],
    ];
    $admin = auth()->user();
    $initials = collect(explode(' ', $admin->name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
@endphp
<div class="layout">
    <aside class="sidebar">
        <div class="brand">
            <span class="brand-logo">@include('admin.icon', ['name' => 'shield'])</span>
            <span><strong>SIMPRES</strong><small>Admin Web</small></span>
        </div>

        <nav class="menu">
            @foreach ($menu as [$label, $path, $pattern, $icon])
                <a href="{{ url($path) }}" class="{{ request()->is(...(array) $pattern) ? 'active' : '' }}">
                    @include('admin.icon', ['name' => $icon])
                    <span>{{ $label }}</span>
                    @if ($icon === 'review' && $waiting > 0)
                        <span class="count">{{ $waiting }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="sidebar-user">
            <span class="avatar light">{{ $initials }}</span>
            <span class="who"><strong>{{ $admin->name }}</strong><small>{{ $admin->email }}</small></span>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" title="Keluar">Keluar</button>
            </form>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div>
                <h1>@yield('title')</h1>
                <p class="subtitle">@yield('subtitle')</p>
            </div>
            <div class="topbar-tools">
                <form class="search" method="GET" action="{{ route('admin.pegawai.index') }}">
                    @include('admin.icon', ['name' => 'search'])
                    <input type="search" name="q" placeholder="Cari pegawai atau data..." value="{{ request()->is('admin/pegawai') ? request('q') : '' }}">
                </form>
                <a class="bell" href="{{ route('admin.pengajuan.index') }}" title="Pengajuan menunggu">
                    @include('admin.icon', ['name' => 'bell'])
                    @if ($waiting > 0)<span class="dot"></span>@endif
                </a>
            </div>
        </header>

        @if (session('success'))
            <div class="alert success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert error">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</div>
</body>
</html>
