@extends('admin.layout')

@section('title', 'Rekap & Ekspor')
@section('subtitle', 'Rekap absensi per pegawai bulan '.$month->translatedFormat('F Y').'. Unduh CSV, Excel, atau PDF untuk laporan.')

@php
    $initials = fn ($name) => collect(explode(' ', $name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
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
        <div class="stat green"><div class="label">Hadir</div><div class="value colored">{{ $rows->sum('hadir') }}</div><div class="hint">Termasuk {{ $rows->sum('lapangan') }} hari tugas lapangan</div></div>
        <div class="stat amber"><div class="label">Terlambat</div><div class="value colored">{{ $rows->sum('terlambat') }}</div><div class="hint">{{ $rows->sum('late_minutes') }} menit total</div></div>
        <div class="stat red"><div class="label">Alpa</div><div class="value colored">{{ $rows->sum('alpa') }}</div><div class="hint">Hari kerja tanpa absensi</div></div>
        <div class="stat blue"><div class="label">Izin / Sakit</div><div class="value colored">{{ $rows->sum('izin') }}</div><div class="hint">Izin yang diterima admin</div></div>
    </div>

    <div class="card flush">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>No</th>
                    <th>Pegawai</th>
                    <th>NIP</th>
                    <th class="num">Hadir</th>
                    <th class="num">Terlambat</th>
                    <th class="num">Izin</th>
                    <th class="num">Alpa</th>
                    <th class="num">Total menit terlambat</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>
                            <div class="person">
                                <span class="avatar">{{ $initials($row->name) }}</span>
                                <span><strong>{{ $row->name }}</strong><small>{{ $row->position }}</small></span>
                            </div>
                        </td>
                        <td>{{ $row->nip }}</td>
                        <td class="num"><span class="badge hadir">{{ $row->hadir }}</span></td>
                        <td class="num"><span class="badge terlambat">{{ $row->terlambat }}</span></td>
                        <td class="num"><span class="badge izin">{{ $row->izin }}</span></td>
                        <td class="num"><span class="badge alpa">{{ $row->alpa }}</span></td>
                        <td class="num">{{ $row->late_minutes }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">Belum ada pegawai.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-foot">Alpa = absensi berstatus alpa ditambah hari kerja terjadwal (bukan libur) yang sudah lewat tanpa absensi. Tugas lapangan yang diterima dihitung hadir.</div>
    </div>
@endsection
