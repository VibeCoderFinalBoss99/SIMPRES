@extends('admin.layout')

@section('title', 'Rekap')

@section('content')
    <div class="page-head">
        <div>
            <h1>Rekap absensi</h1>
            <p>Bulan {{ $month->translatedFormat('F Y') }}.</p>
        </div>
        <a href="{{ route('admin.rekap.csv', ['month' => $month->format('Y-m')]) }}" class="btn">Unduh CSV</a>
    </div>

    <form method="GET" class="toolbar">
        <div class="field">
            <label for="month">Bulan</label>
            <input type="month" id="month" name="month" value="{{ $month->format('Y-m') }}">
        </div>
        <button type="submit" class="btn secondary">Tampilkan</button>
    </form>

    <div class="card flush">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>No</th>
                    <th>Pegawai</th>
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
                        <td>{{ $row->name }}<span class="sub">{{ $row->nip }} · {{ $row->position }}</span></td>
                        <td class="num">{{ $row->hadir }}</td>
                        <td class="num">{{ $row->terlambat }}</td>
                        <td class="num">{{ $row->izin }}</td>
                        <td class="num">{{ $row->alpa }}</td>
                        <td class="num">{{ $row->late_minutes }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">Belum ada pegawai.</td></tr>
                @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                    <tr>
                        <th colspan="2">Total</th>
                        <th class="num">{{ $rows->sum('hadir') }}</th>
                        <th class="num">{{ $rows->sum('terlambat') }}</th>
                        <th class="num">{{ $rows->sum('izin') }}</th>
                        <th class="num">{{ $rows->sum('alpa') }}</th>
                        <th class="num">{{ $rows->sum('late_minutes') }}</th>
                    </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <p class="sub">Alpa = absensi berstatus alpa ditambah hari kerja terjadwal (bukan libur) yang sudah lewat tanpa absensi.</p>
@endsection
