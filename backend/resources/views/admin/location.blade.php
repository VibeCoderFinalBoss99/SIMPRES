@extends('admin.layout')

@section('title', 'Lokasi')

@section('content')
    <div class="page-head">
        <div>
            <h1>Lokasi Puskesmas</h1>
            <p>Titik pusat dan radius absensi. Absensi di luar radius ditolak otomatis.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.lokasi.update') }}" class="card">
        @csrf
        @method('PUT')

        <div class="form-grid">
            <div class="field">
                <label for="name">Nama lokasi *</label>
                <input type="text" id="name" name="name" value="{{ old('name', $location->name) }}" required>
                @error('name')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="address">Alamat</label>
                <input type="text" id="address" name="address" value="{{ old('address', $location->address) }}">
                @error('address')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="latitude">Latitude *</label>
                <input type="text" id="latitude" name="latitude" value="{{ old('latitude', $location->latitude) }}" required>
                <div class="hint">Contoh: -5.0222565</div>
                @error('latitude')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="longitude">Longitude *</label>
                <input type="text" id="longitude" name="longitude" value="{{ old('longitude', $location->longitude) }}" required>
                <div class="hint">Contoh: 119.6653682</div>
                @error('longitude')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="radius_m">Radius absensi (meter) *</label>
                <input type="number" id="radius_m" name="radius_m" min="10" max="5000" value="{{ old('radius_m', $location->radius_m) }}" required>
                <div class="hint">Aturan SIMPRES: 150 meter.</div>
                @error('radius_m')<div class="error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="actions">
            <button type="submit" class="btn">Simpan</button>
            @if ($location->exists)
                <a href="https://www.google.com/maps?q={{ $location->latitude }},{{ $location->longitude }}" target="_blank" rel="noopener" class="btn secondary">
                    Lihat di Google Maps
                </a>
            @endif
        </div>
    </form>
@endsection
