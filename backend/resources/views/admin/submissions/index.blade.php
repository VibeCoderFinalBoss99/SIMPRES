@extends('admin.layout')

@section('title', 'Pengajuan')

@php
    $labels = ['izin' => 'Izin', 'koreksi' => 'Koreksi absensi', 'tugas' => 'Tugas lapangan'];
    $typeNames = ['izin_keluar' => 'Izin keluar', 'sakit' => 'Sakit', 'cuti' => 'Cuti', 'lainnya' => 'Lainnya'];
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>Pengajuan</h1>
            <p>Izin, koreksi absensi, dan tugas lapangan dari pegawai.</p>
        </div>
    </div>

    <div class="tabs">
        @foreach ($labels as $key => $label)
            <a href="{{ route('admin.pengajuan.index', ['jenis' => $key, 'status' => $status]) }}" class="{{ $type === $key ? 'active' : '' }}">
                {{ $label }}
                @if ($waitingCounts[$key] > 0)
                    <span class="badge menunggu">{{ $waitingCounts[$key] }}</span>
                @endif
            </a>
        @endforeach
    </div>

    <form method="GET" class="toolbar">
        <input type="hidden" name="jenis" value="{{ $type }}">
        <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status" onchange="this.form.submit()">
                @foreach (['menunggu' => 'Menunggu Konfirmasi', 'diterima' => 'Diterima', 'ditolak' => 'Ditolak', 'semua' => 'Semua'] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <noscript><button type="submit" class="btn secondary">Terapkan</button></noscript>
    </form>

    <div class="card flush">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Pegawai</th>
                    <th>Tanggal</th>
                    <th>Keterangan</th>
                    <th>Diajukan</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td>{{ $item->user->name }}<span class="sub">{{ $item->user->nip }}</span></td>
                        <td>
                            @if ($type === 'izin')
                                {{ $item->start_date->format('d/m/Y') }}
                                @unless ($item->end_date->isSameDay($item->start_date)) - {{ $item->end_date->format('d/m/Y') }} @endunless
                            @else
                                {{ $item->date->format('d/m/Y') }}
                            @endif
                        </td>
                        <td>
                            @if ($type === 'izin')
                                <strong>{{ $typeNames[$item->type] }}</strong><span class="sub">{{ \Illuminate\Support\Str::limit($item->reason, 60) }}</span>
                            @elseif ($type === 'koreksi')
                                Masuk {{ $item->corrected_check_in ? substr($item->corrected_check_in, 0, 5) : '-' }},
                                pulang {{ $item->corrected_check_out ? substr($item->corrected_check_out, 0, 5) : '-' }}
                                <span class="sub">{{ \Illuminate\Support\Str::limit($item->reason, 60) }}</span>
                            @else
                                <strong>{{ $item->location_name }}</strong><span class="sub">{{ \Illuminate\Support\Str::limit($item->description, 60) }}</span>
                            @endif
                        </td>
                        <td>{{ $item->created_at->format('d/m/Y H:i') }}</td>
                        <td><span class="badge {{ $item->status }}">{{ $item->status === 'menunggu' ? 'Menunggu Konfirmasi' : ucfirst($item->status) }}</span></td>
                        <td><a href="{{ route('admin.pengajuan.show', [$type, $item->id]) }}" class="btn secondary small">Detail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Tidak ada pengajuan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $items->links() }}
    </div>
@endsection
