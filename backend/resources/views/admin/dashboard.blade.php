@extends('admin.layout')

@section('title', 'Dashboard')

@section('content')
    <div class="page-head">
        <div>
            <h1>Dashboard</h1>
            <p>Ringkasan absensi hari ini, {{ $today->translatedFormat('l, d F Y') }}.</p>
        </div>
    </div>

    <div class="stats">
        <div class="stat green"><div class="label">Hadir</div><div class="value">{{ $counts['hadir'] }}</div></div>
        <div class="stat amber"><div class="label">Terlambat</div><div class="value">{{ $counts['terlambat'] }}</div></div>
        <div class="stat blue"><div class="label">Izin</div><div class="value">{{ $counts['izin'] }}</div></div>
        <div class="stat red"><div class="label">Belum absen</div><div class="value">{{ $counts['belum'] }}</div></div>
    </div>

    <div class="stats">
        <a class="stat" href="{{ url('admin/pengajuan?jenis=izin&status=menunggu') }}">
            <div class="label">Izin menunggu</div><div class="value">{{ $waiting['izin'] }}</div>
        </a>
        <a class="stat" href="{{ url('admin/pengajuan?jenis=koreksi&status=menunggu') }}">
            <div class="label">Koreksi menunggu</div><div class="value">{{ $waiting['koreksi'] }}</div>
        </a>
        <a class="stat" href="{{ url('admin/pengajuan?jenis=tugas&status=menunggu') }}">
            <div class="label">Tugas lapangan menunggu</div><div class="value">{{ $waiting['tugas'] }}</div>
        </a>
    </div>

    <div class="card flush">
        <h2>Absensi hari ini</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Nama</th>
                    <th>Jam masuk</th>
                    <th>Jam pulang</th>
                    <th>Status</th>
                    <th class="num">Jarak masuk</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($attendances as $attendance)
                    <tr>
                        <td>{{ $attendance->user->name }}<span class="sub">{{ $attendance->user->position }}</span></td>
                        <td>{{ $attendance->check_in_at?->format('H:i') ?? '-' }}</td>
                        <td>{{ $attendance->check_out_at?->format('H:i') ?? '-' }}</td>
                        <td>
                            <span class="badge {{ $attendance->status }}">{{ ucfirst($attendance->status) }}</span>
                            @if ($attendance->late_minutes > 0)
                                <span class="sub">{{ $attendance->late_minutes }} menit</span>
                            @endif
                        </td>
                        <td class="num">{{ $attendance->check_in_distance_m !== null ? round($attendance->check_in_distance_m).' m' : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Belum ada absensi hari ini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($notYet->isNotEmpty())
        <div class="card">
            <h2>Belum absen ({{ $notYet->count() }})</h2>
            {{ $notYet->pluck('name')->join(', ') }}
        </div>
    @endif
@endsection
