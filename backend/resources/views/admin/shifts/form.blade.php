@extends('admin.layout')

@php($editing = $shift->exists)

@section('title', $editing ? 'Ubah shift' : 'Tambah shift')

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $editing ? 'Ubah shift' : 'Tambah shift' }}</h1>
        </div>
        <a href="{{ route('admin.shift.index') }}" class="btn secondary">Kembali</a>
    </div>

    <form method="POST" action="{{ $editing ? route('admin.shift.update', $shift) : route('admin.shift.store') }}" class="card">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="form-grid">
            <div class="field">
                <label for="name">Nama shift *</label>
                <input type="text" id="name" name="name" value="{{ old('name', $shift->name) }}" placeholder="contoh: Pagi" required>
                @error('name')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="start_time">Jam masuk *</label>
                <input type="time" id="start_time" name="start_time" value="{{ old('start_time', substr((string) $shift->start_time, 0, 5)) }}" required>
                @error('start_time')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="end_time">Jam pulang *</label>
                <input type="time" id="end_time" name="end_time" value="{{ old('end_time', substr((string) $shift->end_time, 0, 5)) }}" required>
                @error('end_time')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="late_tolerance_min">Toleransi terlambat (menit) *</label>
                <input type="number" id="late_tolerance_min" name="late_tolerance_min" min="0" max="240"
                       value="{{ old('late_tolerance_min', $shift->late_tolerance_min) }}" required>
                <div class="hint">Check-in setelah jam masuk + toleransi berstatus terlambat.</div>
                @error('late_tolerance_min')<div class="error">{{ $message }}</div>@enderror
            </div>
        </div>

        <button type="submit" class="btn">Simpan</button>
    </form>
@endsection
