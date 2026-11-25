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
                <label for="work_type">Jenis kerja *</label>
                <select id="work_type" name="work_type">
                    @foreach (\App\Models\User::WORK_TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(old('work_type', $user->work_type ?? 'non_shift') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="hint">Non-shift dan petugas lapangan memakai shift tetap. Pegawai shift diatur bergantian di Roster.</div>
                @error('work_type')<div class="error">{{ $message }}</div>@enderror
            </div>

            <div class="field" id="default-shift-field">
                <label for="default_shift_id">Shift tetap</label>
                <select id="default_shift_id" name="default_shift_id" class="{{ $errors->has('default_shift_id') ? 'is-invalid' : '' }}">
                    <option value="">- tidak ada (atur di Roster) -</option>
                    @foreach ($shifts as $shift)
                        <option value="{{ $shift->id }}" @selected((string) old('default_shift_id', $user->default_shift_id) === (string) $shift->id)>
                            {{ $shift->name }} ({{ substr($shift->start_time, 0, 5) }}-{{ substr($shift->end_time, 0, 5) }})
                        </option>
                    @endforeach
                </select>
                <div class="hint">Berlaku otomatis setiap Senin-Sabtu, Minggu libur, sampai diubah. Tanggal yang diatur di Roster tetap diutamakan.</div>
                @error('default_shift_id')<div class="error">{{ $message }}</div>@enderror
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

    <script>
        // Pegawai shift tidak memakai shift tetap; jadwalnya diatur di Roster.
        (function () {
            const type = document.getElementById('work_type');
            const field = document.getElementById('default-shift-field');
            const select = document.getElementById('default_shift_id');
            const sync = () => {
                const isShift = type.value === 'shift';
                field.style.display = isShift ? 'none' : '';
                if (isShift) select.value = '';
            };
            type.addEventListener('change', sync);
            sync();
        })();
    </script>
@endsection
