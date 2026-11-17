<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') - SIMPRES</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="brand">
            <strong>SIMPRES</strong>
            <span>UPTD Puskesmas Simbang</span>
        </div>

        @php
            $menu = [
                ['Dashboard', 'admin', 'admin'],
                ['Pegawai', 'admin/pegawai', 'admin/pegawai*'],
                ['Pengajuan', 'admin/pengajuan', 'admin/pengajuan*'],
                ['Jadwal', 'admin/jadwal', ['admin/jadwal*', 'admin/shift*']],
                ['Lokasi', 'admin/lokasi', 'admin/lokasi*'],
                ['Rekap', 'admin/rekap', 'admin/rekap*'],
            ];
            $waiting = \App\Models\LeaveRequest::where('status', 'menunggu')->count()
                + \App\Models\AttendanceCorrection::where('status', 'menunggu')->count()
                + \App\Models\FieldDuty::where('status', 'menunggu')->count();
        @endphp

        <ul class="menu">
            @foreach ($menu as [$label, $path, $pattern])
                <li>
                    <a href="{{ url($path) }}" class="{{ request()->is(...(array) $pattern) ? 'active' : '' }}">
                        {{ $label }}
                        @if ($label === 'Pengajuan' && $waiting > 0)
                            <span class="count">{{ $waiting }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="sidebar-footer">
            <div class="name">{{ auth()->user()->name }}</div>
            <div>{{ auth()->user()->position }}</div>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit">Keluar</button>
            </form>
        </div>
    </aside>

    <main class="main">
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
