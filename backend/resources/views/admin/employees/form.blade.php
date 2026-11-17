@extends('admin.layout')

@php($editing = $user->exists)

@section('title', $editing ? 'Ubah pegawai' : 'Tambah pegawai')

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $editing ? 'Ubah pegawai' : 'Tambah pegawai' }}</h1>
            <p>{{ $editing ? $user->name : 'Isi data pegawai baru beserta password awalnya.' }}</p>
        </div>
        <a href="{{ route('admin.pegawai.index') }}" class="btn secondary">Kembali</a>
    </div>

    <form method="POST" action="{{ $editing ? route('admin.pegawai.update', $user) : route('admin.pegawai.store') }}" class="card">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="form-grid">
            @foreach ([
                ['nip', 'NIP', 'text', true],
                ['name', 'Nama lengkap', 'text', true],
                ['email', 'Email (untuk login)', 'email', true],
                ['phone', 'Nomor HP', 'text', false],
                ['position', 'Jabatan', 'text', true],
                ['unit', 'Unit', 'text', false],
            ] as [$field, $label, $type, $required])
                <div class="field">
                    <label for="{{ $field }}">{{ $label }}{{ $required ? ' *' : '' }}</label>
                    <input type="{{ $type }}" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $user->$field) }}"
                           class="{{ $errors->has($field) ? 'is-invalid' : '' }}" {{ $required ? 'required' : '' }}>
                    @error($field)<div class="error">{{ $message }}</div>@enderror
                </div>
            @endforeach

            <div class="field">
                <label for="role">Peran *</label>
                <select id="role" name="role">
                    @foreach (['pegawai' => 'Pegawai', 'admin' => 'Admin'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('role', $user->role) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('role')<div class="error">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label for="work_location_id">Lokasi kerja</label>
                <select id="work_location_id" name="work_location_id">
                    <option value="">- belum diatur -</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected((string) old('work_location_id', $user->work_location_id) === (string) $location->id)>
                            {{ $location->name }}
                        </option>
                    @endforeach
                </select>
                @error('work_location_id')<div class="error">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label for="password">{{ $editing ? 'Password baru' : 'Password awal *' }}</label>
                <input type="password" id="password" name="password" autocomplete="new-password"
                       class="{{ $errors->has('password') ? 'is-invalid' : '' }}" {{ $editing ? '' : 'required' }}>
                <div class="hint">{{ $editing ? 'Kosongkan jika tidak diganti. ' : '' }}Minimal 8 karakter, disimpan sebagai hash.</div>
                @error('password')<div class="error">{{ $message }}</div>@enderror
            </div>
        </div>

        <button type="submit" class="btn">Simpan</button>
    </form>
@endsection
