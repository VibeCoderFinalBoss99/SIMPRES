@extends('admin.layout')

@section('title', 'Lokasi Kerja')
@section('subtitle', 'Atur titik lokasi dan radius geofence. Validasi jarak dilakukan oleh server.')

@php
    $locations = \App\Models\WorkLocation::orderBy('id')->get();
    $units = \App\Models\User::where('work_location_id', $location->id)->whereNotNull('unit')->distinct()->pluck('unit');
    $lat = (float) old('latitude', $location->latitude);
    $lng = (float) old('longitude', $location->longitude);
    // Kotak peta kira-kira 600 m di sekitar titik.
    $d = 0.004;
    $bbox = ($lng - $d).','.($lat - $d).','.($lng + $d).','.($lat + $d);
@endphp

@section('content')
    <div class="grid-loc">
        <div class="card">
            <h2>Daftar lokasi</h2>
            @foreach ($locations as $loc)
                <div class="loc-item {{ $loc->id === $location->id ? 'active' : '' }}">
                    <span class="pinbox">@include('admin.icon', ['name' => 'pin'])</span>
                    <span style="flex:1">
                        <strong>{{ $loc->name }}</strong>
                        <span class="muted" style="display:block">Radius {{ $loc->radius_m }} m · {{ $loc->users()->count() }} akun</span>
                    </span>
                    <span class="badge {{ $loc->is_active ? 'aktif' : '' }}">{{ $loc->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                </div>
            @endforeach
        </div>

        <div>
            @if ($location->exists)
                <div class="card flush" style="position:relative">
                    {{-- Gambar cadangan (gaya desain Figma) jika peta online belum termuat. --}}
                    <svg viewBox="0 0 600 300" preserveAspectRatio="xMidYMid slice" style="position:absolute;inset:0;width:100%;height:100%" aria-hidden="true">
                        <rect width="600" height="300" fill="#e6ebf3"/>
                        <rect x="0" y="120" width="600" height="26" fill="#fff"/><rect x="230" y="0" width="26" height="300" fill="#fff"/><rect x="420" y="0" width="22" height="300" fill="#fff"/>
                        <rect x="40" y="25" width="130" height="70" rx="10" fill="#d6ecd9"/><rect x="470" y="190" width="100" height="70" rx="10" fill="#d6ecd9"/>
                        <circle cx="300" cy="150" r="95" fill="#1b3f8f" fill-opacity=".12" stroke="#1b3f8f" stroke-width="2" stroke-dasharray="7 6"/>
                        <path d="M300 160s16-15 16-28a16 16 0 10-32 0c0 13 16 28 16 28z" fill="#1b3f8f"/><circle cx="300" cy="132" r="6" fill="#fff"/>
                    </svg>
                    <iframe class="map" style="position:relative" title="Peta lokasi {{ $location->name }}" loading="lazy"
                            src="https://www.openstreetmap.org/export/embed.html?bbox={{ $bbox }}&layer=mapnik&marker={{ $lat }},{{ $lng }}"></iframe>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.lokasi.update') }}" class="card">
                @csrf
                @method('PUT')
                <h2>Detail lokasi</h2>
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
                </div>
                <div class="form-grid four" style="margin-top:14px">
                    <div class="field">
                        <label for="latitude">Latitude *</label>
                        <input type="text" id="latitude" name="latitude" value="{{ old('latitude', $location->latitude) }}" required>
                        @error('latitude')<div class="error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="longitude">Longitude *</label>
                        <input type="text" id="longitude" name="longitude" value="{{ old('longitude', $location->longitude) }}" required>
                        @error('longitude')<div class="error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="radius_m">Radius (meter) *</label>
                        <input type="number" id="radius_m" name="radius_m" min="10" max="5000" value="{{ old('radius_m', $location->radius_m) }}" required>
                        @error('radius_m')<div class="error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label>Batas akurasi (meter)</label>
                        <input type="text" value="{{ \App\Support\Settings::get('max_accuracy_m') }}" disabled>
                        <div class="hint">Diubah di Konfigurasi</div>
                    </div>
                </div>
                <div class="field" style="margin-top:14px">
                    <label>Unit yang menggunakan</label>
                    <input type="text" value="{{ $units->join(', ') ?: '-' }}" disabled>
                </div>
                <div class="note" style="margin:16px 0">@include('admin.icon', ['name' => 'info']) Radius awal 100–200 m, lalu diuji dari dalam dan sekitar gedung sebelum dipakai. Aturan SIMPRES: 150 meter.</div>
                <div class="actions">
                    <button type="submit" class="btn">Simpan Lokasi</button>
                    @if ($location->exists)
                        <a href="https://www.google.com/maps?q={{ $location->latitude }},{{ $location->longitude }}" target="_blank" rel="noopener" class="btn secondary">Buka di Google Maps</a>
                    @endif
                </div>
            </form>
        </div>
    </div>
@endsection
