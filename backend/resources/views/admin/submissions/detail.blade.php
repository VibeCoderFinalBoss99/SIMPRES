{{-- Panel detail pengajuan (kanan). Dipakai di halaman daftar dan halaman detail. --}}
@php
    $typeNames = ['izin_keluar' => 'Izin keluar jam kerja', 'sakit' => 'Izin Sakit', 'cuti' => 'Cuti', 'lainnya' => 'Izin keperluan lain'];
    $fileColumn = \App\Http\Controllers\Admin\SubmissionController::TYPES[$type][2];
    $fileUrl = $item->{$fileColumn} ? route('admin.pengajuan.file', [$type, $item->id]) : null;
    $heading = $type === 'izin' ? $typeNames[$item->type] : ($type === 'koreksi' ? 'Koreksi absensi' : 'Tugas lapangan');
    $initials = collect(explode(' ', $item->user->name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
@endphp

<div class="card">
    <div class="card-head" style="border-bottom:1px solid var(--border);padding-bottom:14px">
        <h2>{{ $heading }}</h2>
        <span class="badge {{ $item->status }}">{{ $item->status === 'menunggu' ? 'Menunggu Konfirmasi' : ucfirst($item->status) }}</span>
    </div>

    <div class="person" style="margin:16px 0 18px">
        <span class="avatar">{{ $initials }}</span>
        <span><strong>{{ $item->user->name }}</strong><small>{{ $item->user->position }} · {{ $item->user->unit }} · NIP {{ $item->user->nip }}</small></span>
    </div>

    <dl class="detail">
        @if ($type === 'izin')
            <dt>Tanggal</dt>
            <dd>{{ $item->start_date->format('d/m/Y') }} s.d. {{ $item->end_date->format('d/m/Y') }}</dd>
            <dt>Durasi</dt><dd>{{ $item->start_date->diffInDays($item->end_date) + 1 }} hari</dd>
            @if ($item->start_time)
                <dt>Jam</dt><dd>{{ substr($item->start_time, 0, 5) }} - {{ substr($item->end_time, 0, 5) }}</dd>
            @endif
            <dt>Alasan</dt><dd>{{ $item->reason }}</dd>
        @elseif ($type === 'koreksi')
            <dt>Tanggal</dt><dd>{{ $item->date->format('d/m/Y') }}</dd>
            <dt>Jam dikoreksi</dt>
            <dd>Masuk {{ $item->corrected_check_in ? substr($item->corrected_check_in, 0, 5) : '-' }}, pulang {{ $item->corrected_check_out ? substr($item->corrected_check_out, 0, 5) : '-' }}</dd>
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
                <dd><a href="https://www.google.com/maps?q={{ $item->latitude }},{{ $item->longitude }}" target="_blank" rel="noopener">{{ $item->latitude }}, {{ $item->longitude }}</a></dd>
            @endif
        @endif

        <dt>Diajukan</dt><dd>{{ $item->created_at->format('d/m/Y H:i') }}</dd>
        <dt>{{ $type === 'tugas' ? 'Foto kegiatan' : 'Lampiran' }}</dt>
        <dd>
            @if ($fileUrl)
                <a href="{{ $fileUrl }}" target="_blank" rel="noopener"><img src="{{ $fileUrl }}" alt="Lampiran" class="photo"></a>
            @else
                <span class="muted">Tidak ada lampiran.</span>
            @endif
        </dd>

        @if ($item->status !== 'menunggu')
            <dt>Diputuskan</dt><dd>{{ $item->reviewed_at?->format('d/m/Y H:i') }} oleh {{ $item->reviewer?->name ?? '-' }}</dd>
            <dt>Catatan admin</dt><dd>{{ $item->admin_note ?? '-' }}</dd>
        @endif
    </dl>

    @if ($item->status === 'menunggu')
        <form method="POST" action="{{ route('admin.pengajuan.review', [$type, $item->id]) }}" style="margin-top:22px">
            @csrf
            <div class="field">
                <label for="admin_note">Catatan keputusan <span class="muted">(dianjurkan diisi saat menolak)</span></label>
                <textarea id="admin_note" name="admin_note" maxlength="255" placeholder="Tulis alasan atau catatan untuk pegawai...">{{ old('admin_note') }}</textarea>
                @error('admin_note')<div class="error">{{ $message }}</div>@enderror
            </div>
            @if ($type === 'izin')
                <div class="note" style="margin-top:12px">@include('admin.icon', ['name' => 'info']) Jika diterima, absensi pada tanggal izin dicatat otomatis sebagai Izin.</div>
            @endif
            <div class="form-grid" style="margin-top:14px">
                <button type="submit" name="status" value="ditolak" class="btn danger">Tolak</button>
                <button type="submit" name="status" value="diterima" class="btn">Terima</button>
            </div>
        </form>
    @endif
</div>
