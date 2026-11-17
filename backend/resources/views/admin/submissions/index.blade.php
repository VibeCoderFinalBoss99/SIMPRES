@extends('admin.layout')

@section('title', 'Review & Persetujuan')
@section('subtitle', 'Periksa izin, koreksi, dan tugas lapangan. Setiap keputusan tercatat di audit log.')

@php
    $labels = ['izin' => 'Izin', 'koreksi' => 'Koreksi', 'tugas' => 'Tugas Lapangan'];
    $typeNames = ['izin_keluar' => 'Izin keluar jam kerja', 'sakit' => 'Izin Sakit', 'cuti' => 'Cuti', 'lainnya' => 'Izin keperluan lain'];
    $statusText = fn ($s) => $s === 'menunggu' ? 'Menunggu Konfirmasi' : ucfirst($s);
    $initials = fn ($name) => collect(explode(' ', $name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
    $selected = $selected ?? $items->first();
@endphp

@section('content')
    <div class="toolbar">
        <div class="tabs" style="margin:0">
            @foreach ($labels as $key => $label)
                <a href="{{ route('admin.pengajuan.index', ['jenis' => $key, 'status' => $status]) }}" class="{{ $type === $key ? 'active' : '' }}">
                    {{ $label }} ({{ $waitingCounts[$key] }})
                </a>
            @endforeach
        </div>
        <span class="grow"></span>
        <form method="GET">
            <input type="hidden" name="jenis" value="{{ $type }}">
            <select name="status" onchange="this.form.submit()" aria-label="Status">
                @foreach (['menunggu' => 'Menunggu Konfirmasi', 'diterima' => 'Diterima', 'ditolak' => 'Ditolak', 'semua' => 'Semua status'] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <noscript><button type="submit" class="btn secondary small">Terapkan</button></noscript>
        </form>
    </div>

    <div class="grid-split">
        <div class="card flush review-list">
            @forelse ($items as $item)
                <a href="{{ route('admin.pengajuan.show', [$type, $item->id]) }}?status={{ $status }}"
                   class="review-item {{ $selected && $selected->id === $item->id ? 'active' : '' }}">
                    <span class="avatar">{{ $initials($item->user->name) }}</span>
                    <span class="grow">
                        <strong>{{ $item->user->name }}</strong>
                        <span class="meta" style="display:block">
                            @if ($type === 'izin')
                                {{ $typeNames[$item->type] }} · {{ $item->start_date->format('d/m/Y') }}@unless ($item->end_date->isSameDay($item->start_date)) - {{ $item->end_date->format('d/m/Y') }}@endunless
                            @elseif ($type === 'koreksi')
                                Koreksi {{ $item->date->format('d/m/Y') }} · Masuk {{ $item->corrected_check_in ? substr($item->corrected_check_in, 0, 5) : '-' }}, pulang {{ $item->corrected_check_out ? substr($item->corrected_check_out, 0, 5) : '-' }}
                            @else
                                {{ $item->location_name }} · {{ $item->date->format('d/m/Y') }}
                            @endif
                        </span>
                        <span style="display:flex;justify-content:space-between;align-items:center;margin-top:6px">
                            <span class="badge {{ $item->status }}">{{ $statusText($item->status) }}</span>
                            <span class="meta">{{ $item->created_at->format('d/m H:i') }}</span>
                        </span>
                    </span>
                </a>
            @empty
                <div class="empty">Tidak ada pengajuan.</div>
            @endforelse
            {{ $items->links() }}
        </div>

        <div>
            @if ($selected)
                @include('admin.submissions.detail', ['item' => $selected])
            @else
                <div class="card empty">Pilih pengajuan di sebelah kiri untuk melihat detailnya.</div>
            @endif
        </div>
    </div>
@endsection
