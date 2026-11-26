@extends('admin.layout')

@section('title', 'Rekap & Ekspor')
@section('subtitle', 'Rekap absensi per pegawai bulan '.$month->translatedFormat('F Y').'. Unduh CSV, Excel, atau PDF untuk laporan.')

@php
    $initials = fn ($name) => collect(explode(' ', $name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
    // 658 menit -> "10 jam 58 menit" supaya mudah dibaca.
    $duration = function (int $minutes) {
        if ($minutes === 0) return '-';
        $h = intdiv($minutes, 60); $m = $minutes % 60;
        return trim(($h ? $h.' jam ' : '').($m ? $m.' menit' : ''));
    };
    $lateTotal = $rows->sum('late_minutes');
@endphp

@section('content')
    <div class="toolbar">
        <form method="GET" class="toolbar" style="margin:0">
            <input type="month" id="month" name="month" value="{{ $month->format('Y-m') }}" aria-label="Bulan">
            <button type="submit" class="btn secondary">@include('admin.icon', ['name' => 'filter']) Tampilkan</button>
        </form>
        <span class="grow"></span>
        <span class="muted">Unduh:</span>
        <a href="{{ route('admin.rekap.csv', ['month' => $month->format('Y-m')]) }}" class="btn secondary">@include('admin.icon', ['name' => 'download']) CSV</a>
        <a href="{{ route('admin.rekap.xlsx', ['month' => $month->format('Y-m')]) }}" class="btn secondary">@include('admin.icon', ['name' => 'download']) Excel (XLSX)</a>
        <a href="{{ route('admin.rekap.pdf', ['month' => $month->format('Y-m')]) }}" class="btn">@include('admin.icon', ['name' => 'download']) PDF</a>
    </div>

    <div class="stats">
        <div class="stat green"><div class="label">Hari masuk</div><div class="value colored">{{ $rows->sum('masuk') }} <small>hari</small></div>
            <div class="hint">{{ $rows->sum('hadir') }} tepat waktu, {{ $rows->sum('terlambat') }} terlambat (termasuk {{ $rows->sum('lapangan') }} hari tugas lapangan)</div></div>
        <div class="stat amber"><div class="label">Total waktu terlambat</div><div class="value colored">{{ $duration($lateTotal) }}</div>
            <div class="hint">{{ $lateTotal }} menit dari {{ $rows->sum('terlambat') }} hari terlambat</div></div>
        <div class="stat red"><div class="label">Hari alpa</div><div class="value colored">{{ $rows->sum('alpa') }} <small>hari</small></div><div class="hint">Hari kerja tanpa absensi</div></div>
        <div class="stat blue"><div class="label">Hari izin / sakit</div><div class="value colored">{{ $rows->sum('izin') }} <small>hari</small></div><div class="hint">Izin yang diterima admin</div></div>
    </div>

    <div class="card flush">
        <div class="card-head"><h2>Rekap per pegawai</h2><span class="muted">Angka = jumlah hari di {{ $month->translatedFormat('F Y') }}</span></div>
        <div class="table-wrap">
            <table class="stack recap">
                <thead>
                <tr>
                    <th>No</th>
                    <th>Pegawai</th>
                    <th>NIP</th>
                    <th class="num">Masuk</th>
                    <th class="num">Tepat waktu</th>
                    <th class="num">Terlambat</th>
                    <th class="num">Izin / sakit</th>
                    <th class="num">Alpa</th>
                    <th class="num">Total terlambat</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td data-label="No">{{ $loop->iteration }}</td>
                        <td data-label="Pegawai">
                            <div class="person">
                                <span class="avatar">{{ $initials($row->name) }}</span>
                                <span><strong>{{ $row->name }}</strong><small>{{ $row->position }}</small></span>
                            </div>
                        </td>
                        <td data-label="NIP">{{ $row->nip }}</td>
                        <td class="num" data-label="Masuk"><span><strong>{{ $row->masuk }}</strong> <span class="unit">hari</span></span></td>
                        <td class="num" data-label="Tepat waktu"><span class="badge hadir">{{ $row->hadir }} hari</span></td>
                        <td class="num" data-label="Terlambat"><span class="badge terlambat">{{ $row->terlambat }} hari</span></td>
                        <td class="num" data-label="Izin / sakit"><span class="badge izin">{{ $row->izin }} hari</span></td>
                        <td class="num" data-label="Alpa"><span class="badge alpa">{{ $row->alpa }} hari</span></td>
                        <td class="num" data-label="Total terlambat">
                            <span>{{ $duration($row->late_minutes) }}
                                @if ($row->terlambat > 0)<small class="unit block">rata-rata {{ round($row->late_minutes / $row->terlambat) }} menit per hari</small>@endif
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty">Belum ada pegawai.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-foot">
            <strong>Cara membaca:</strong> Masuk = tepat waktu + terlambat. Tugas lapangan yang diterima dihitung tepat waktu.
            Alpa = absensi berstatus alpa ditambah hari kerja terjadwal (bukan libur) yang sudah lewat tanpa absensi.
            Total terlambat = jumlah menit terlambat dari semua hari terlambat di bulan ini.
        </div>
    </div>
@endsection
