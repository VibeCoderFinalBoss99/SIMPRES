@extends('admin.layout')

@section('title', 'Shift')

@section('content')
    <div class="page-head">
        <div>
            <h1>Jadwal</h1>
            <p>Daftar shift kerja beserta jam masuk, jam pulang, dan toleransi terlambat.</p>
        </div>
        <a href="{{ route('admin.shift.create') }}" class="btn">+ Tambah shift</a>
    </div>

    @include('admin.schedules.tabs')

    <div class="card flush">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Nama shift</th>
                    <th>Jam masuk</th>
                    <th>Jam pulang</th>
                    <th>Toleransi terlambat</th>
                    <th class="num">Dipakai di jadwal</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($shifts as $shift)
                    <tr>
                        <td>{{ $shift->name }}</td>
                        <td>{{ substr($shift->start_time, 0, 5) }}</td>
                        <td>{{ substr($shift->end_time, 0, 5) }}</td>
                        <td>{{ $shift->late_tolerance_min }} menit</td>
                        <td class="num">{{ $shift->schedules_count }}</td>
                        <td>
                            <div class="actions">
                                <a href="{{ route('admin.shift.edit', $shift) }}" class="btn secondary small">Ubah</a>
                                <form method="POST" action="{{ route('admin.shift.destroy', $shift) }}"
                                      onsubmit="return confirm('Hapus shift {{ $shift->name }}? Jadwal yang memakai shift ini menjadi tanpa shift.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn danger small">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Belum ada shift.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
