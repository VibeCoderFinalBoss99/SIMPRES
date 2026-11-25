@extends('admin.layout')

@section('title', 'Dashboard')
@section('subtitle', $today->translatedFormat('l, d F Y').' · UPTD Puskesmas Simbang')

@php
    $initials = fn ($name) => collect(explode(' ', $name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
    $waitingTotal = array_sum($waiting);
    $present = $counts['hadir'] + $counts['terlambat'];
    $maxBar = max(1, $week->max('count'), $totalEmployees);
    $typeLabels = ['izin' => 'Izin', 'koreksi' => 'Koreksi', 'tugas' => 'Lapangan'];
@endphp

@section('content')
    <div class="stats">
        <div class="stat green">
            <div class="label">Hadir hari ini</div>
            <div class="value">{{ $present }} / {{ $totalEmployees }}</div>
            <div class="hint">Check-in diterima</div>
        </div>
        <div class="stat amber">
            <div class="label">Terlambat</div>
            <div class="value">{{ $counts['terlambat'] }}</div>
            <div class="hint">Lewat jam masuk + toleransi</div>
        </div>
        <div class="stat blue">
            <div class="label">Izin / Sakit</div>
            <div class="value">{{ $counts['izin'] }}</div>
            <div class="hint">{{ $waiting['izin'] }} menunggu konfirmasi</div>
        </div>
        <a class="stat red" href="{{ route('admin.pengajuan.index') }}">
            <div class="label">Perlu ditinjau</div>
            <div class="value">{{ $waitingTotal }}</div>
            <div class="hint">Koreksi {{ $waiting['koreksi'] }}, lapangan {{ $waiting['tugas'] }}, izin {{ $waiting['izin'] }}</div>
        </a>
    </div>

    <div class="grid-2">
        <div class="card">
            <h2 style="margin-bottom:2px">Kehadiran 7 hari terakhir</h2>
            <div class="muted">Jumlah pegawai hadir per hari</div>
            <div class="bars">
                @foreach ($week as $day)
                    <div class="bar">
                        <span class="num">{{ $day['count'] }}</span>
                        <span class="track"><span class="fill" style="height: {{ round($day['count'] / $maxBar * 100) }}%"></span></span>
                        <span class="day">{{ $day['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h2>Perlu ditinjau</h2>
                <a href="{{ route('admin.pengajuan.index') }}">Lihat semua</a>
            </div>
            @forelse ($pending as $row)
                <a class="list-row" href="{{ route('admin.pengajuan.show', [$row['type'], $row['item']->id]) }}" style="color:inherit;text-decoration:none">
                    <span class="avatar">{{ $initials($row['item']->user->name) }}</span>
                    <span class="grow person">
                        <span><strong>{{ $row['item']->user->name }}</strong>
                        <small>{{ $typeLabels[$row['type']] }} · {{ $row['item']->created_at->translatedFormat('d M, H:i') }}</small></span>
                    </span>
                    <span class="badge menunggu">Menunggu</span>
                </a>
            @empty
                <div class="empty">Tidak ada pengajuan yang menunggu.</div>
            @endforelse
        </div>
    </div>

    @if ($changeRequests->isNotEmpty())
        {{-- Permintaan perubahan data dari menu Pengaturan aplikasi pegawai. --}}
        <div class="card" id="permintaan">
            <div class="card-head"><h2>Permintaan perubahan data ({{ $changeRequests->count() }})</h2></div>
            @foreach ($changeRequests as $req)
                <div class="list-row">
                    <span class="grow person">
                        <span><strong>{{ \Illuminate\Support\Str::after($req->title, ': ') }}</strong>
                        <small>{{ $req->created_at->translatedFormat('d M Y, H:i') }} · {{ $req->body }}</small></span>
                    </span>
                    @if ($req->data['employee_id'] ?? null)
                        <a class="btn secondary small" href="{{ route('admin.pegawai.edit', $req->data['employee_id']) }}">Ubah data</a>
                    @endif
                    <form method="POST" action="{{ route('admin.permintaan.done', $req) }}">
                        @csrf
                        <button type="submit" class="btn small">Tandai selesai</button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif

    <div class="card flush">
        <div class="card-head"><h2>Aktivitas terbaru</h2></div>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Pegawai</th>
                    <th>Masuk</th>
                    <th>Pulang</th>
                    <th>Lokasi</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($attendances as $attendance)
                    <tr>
                        <td>
                            <div class="person">
                                <span class="avatar">{{ $initials($attendance->user->name) }}</span>
                                <span><strong>{{ $attendance->user->name }}</strong><small>{{ $attendance->user->position }}</small></span>
                            </div>
                        </td>
                        <td>{{ $attendance->check_in_at?->format('H:i') ?? '-' }}</td>
                        <td>{{ $attendance->check_out_at?->format('H:i') ?? '-' }}</td>
                        <td>{{ $attendance->check_in_distance_m !== null ? 'Puskesmas · '.round($attendance->check_in_distance_m).' m' : '-' }}</td>
                        <td>
                            <span class="badge {{ $attendance->status }}">{{ ucfirst($attendance->status) }}</span>
                            @if ($attendance->late_minutes > 0)
                                <span class="muted">{{ $attendance->late_minutes }} menit</span>
                            @endif
                        </td>
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
            <div class="muted" style="font-size:14px">{{ $notYet->pluck('name')->join(', ') }}</div>
        </div>
    @endif
@endsection
