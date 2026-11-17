@extends('admin.layout')

@section('title', 'Audit Log')
@section('subtitle', 'Jejak aktor, waktu, keputusan, dan perubahan. Disusun dari data yang tercatat di server.')

@section('content')
    <form method="GET" class="toolbar">
        <input type="date" name="dari" value="{{ $from->toDateString() }}" aria-label="Dari tanggal">
        <input type="date" name="sampai" value="{{ $to->toDateString() }}" aria-label="Sampai tanggal">
        <select name="aksi" aria-label="Jenis aksi">
            <option value="">Semua aksi</option>
            @foreach (\App\Http\Controllers\Admin\AuditController::ACTIONS as $key => $label)
                <option value="{{ $key }}" @selected($action === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn secondary">@include('admin.icon', ['name' => 'filter']) Terapkan</button>
        <span class="grow"></span>
        <a class="btn secondary" href="{{ route('admin.audit.csv', request()->query()) }}">@include('admin.icon', ['name' => 'download']) Ekspor CSV</a>
    </form>

    <div class="card flush">
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Waktu</th><th>Aktor</th><th>Aksi</th><th>Target</th><th>Alasan / catatan</th></tr>
                </thead>
                <tbody>
                @forelse ($entries as $e)
                    <tr>
                        <td>{{ $e['at']->translatedFormat('d M · H.i') }}</td>
                        <td>{{ $e['actor'] }}</td>
                        <td><span class="badge {{ $e['tone'] }}">{{ $e['label'] }}</span></td>
                        <td>{{ $e['target'] }}</td>
                        <td class="muted">{{ $e['note'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Belum ada catatan di rentang tanggal ini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-foot">{{ $entries->count() }} catatan · {{ $from->translatedFormat('d M Y') }} – {{ $to->translatedFormat('d M Y') }}</div>
    </div>
@endsection
