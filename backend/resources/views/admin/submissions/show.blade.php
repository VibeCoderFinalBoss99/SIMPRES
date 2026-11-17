@extends('admin.layout')

@section('title', 'Detail pengajuan')

@php
    $typeNames = ['izin_keluar' => 'Izin keluar', 'sakit' => 'Sakit', 'cuti' => 'Cuti', 'lainnya' => 'Lainnya'];
    $fileUrl = $filePath ? route('admin.pengajuan.file', [$type, $item->id]) : null;
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $title }}</h1>
            <p>Diajukan {{ $item->created_at->format('d/m/Y H:i') }} oleh {{ $item->user->name }}.</p>
        </div>
        <a href="{{ route('admin.pengajuan.index', ['jenis' => $type]) }}" class="btn secondary">Kembali</a>
    </div>

    <div class="card">
        <dl class="detail">
            <dt>Pegawai</dt>
            <dd>{{ $item->user->name }} ({{ $item->user->nip }}), {{ $item->user->position }}</dd>

            @if ($type === 'izin')
                <dt>Jenis</dt><dd>{{ $typeNames[$item->type] }}</dd>
                <dt>Tanggal</dt>
                <dd>{{ $item->start_date->format('d/m/Y') }} s.d. {{ $item->end_date->format('d/m/Y') }}</dd>
                @if ($item->start_time)
                    <dt>Jam</dt><dd>{{ substr($item->start_time, 0, 5) }} - {{ substr($item->end_time, 0, 5) }}</dd>
                @endif
                <dt>Alasan</dt><dd>{{ $item->reason }}</dd>
            @elseif ($type === 'koreksi')
                <dt>Tanggal</dt><dd>{{ $item->date->format('d/m/Y') }}</dd>
                <dt>Jam masuk dikoreksi</dt><dd>{{ $item->corrected_check_in ? substr($item->corrected_check_in, 0, 5) : '-' }}</dd>
                <dt>Jam pulang dikoreksi</dt><dd>{{ $item->corrected_check_out ? substr($item->corrected_check_out, 0, 5) : '-' }}</dd>
                <dt>Absensi tercatat</dt>
                <dd>
                    @if ($item->attendance)
                        Masuk {{ $item->attendance->check_in_at?->format('H:i') ?? '-' }},
                        pulang {{ $item->attendance->check_out_at?->format('H:i') ?? '-' }}
                        ({{ $item->attendance->status }})
                    @else
                        Belum ada absensi di tanggal ini
                    @endif
                </dd>
                <dt>Alasan</dt><dd>{{ $item->reason }}</dd>
            @else
                <dt>Tanggal</dt><dd>{{ $item->date->format('d/m/Y') }}</dd>
                <dt>Lokasi</dt><dd>{{ $item->location_name }}</dd>
                <dt>Keterangan</dt><dd>{{ $item->description }}</dd>
                @if ($item->latitude)
                    <dt>Koordinat</dt>
                    <dd>
                        <a href="https://www.google.com/maps?q={{ $item->latitude }},{{ $item->longitude }}" target="_blank" rel="noopener">
                            {{ $item->latitude }}, {{ $item->longitude }}
                        </a>
                    </dd>
                @endif
            @endif

            <dt>Status</dt>
            <dd><span class="badge {{ $item->status }}">{{ $item->status === 'menunggu' ? 'Menunggu Konfirmasi' : ucfirst($item->status) }}</span></dd>

            @if ($item->status !== 'menunggu')
                <dt>Diputuskan</dt>
                <dd>{{ $item->reviewed_at?->format('d/m/Y H:i') }} oleh {{ $item->reviewer?->name ?? '-' }}</dd>
                <dt>Catatan admin</dt><dd>{{ $item->admin_note ?? '-' }}</dd>
            @endif
        </dl>
    </div>

    <div class="card">
        <h2>{{ $type === 'tugas' ? 'Foto kegiatan' : 'Lampiran' }}</h2>
        @if ($fileUrl)
            <a href="{{ $fileUrl }}" target="_blank" rel="noopener"><img src="{{ $fileUrl }}" alt="Lampiran" class="photo"></a>
            <p><a href="{{ $fileUrl }}" target="_blank" rel="noopener">Buka di tab baru</a></p>
        @else
            <p class="sub">Tidak ada lampiran.</p>
        @endif
    </div>

    @if ($item->status === 'menunggu')
        <form method="POST" action="{{ route('admin.pengajuan.review', [$type, $item->id]) }}" class="card">
            @csrf
            <h2>Keputusan</h2>
            <div class="field">
                <label for="admin_note">Catatan untuk pegawai (opsional)</label>
                <textarea id="admin_note" name="admin_note" maxlength="255">{{ old('admin_note') }}</textarea>
                @error('admin_note')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="actions">
                <button type="submit" name="status" value="diterima" class="btn success">Terima</button>
                <button type="submit" name="status" value="ditolak" class="btn danger">Tolak</button>
            </div>
        </form>
    @endif
@endsection
