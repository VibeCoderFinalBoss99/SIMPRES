@extends('admin.layout')

@section('title', 'Lokasi Kerja')
@section('subtitle', 'Atur titik lokasi dan radius geofence. Validasi jarak dilakukan oleh server.')

@php
    $locations = \App\Models\WorkLocation::orderBy('id')->get();
    $units = \App\Models\User::where('work_location_id', $location->id)->whereNotNull('unit')->distinct()->pluck('unit');
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
            {{-- Peta yang bisa diklik (Leaflet + OpenStreetMap, tanpa API key). Klik atau geser penanda untuk mengisi koordinat. --}}
            <div class="card flush" style="position:relative">
                {{-- Gambar cadangan (gaya desain Figma) jika peta online belum termuat. --}}
                <svg viewBox="0 0 600 300" preserveAspectRatio="xMidYMid slice" style="position:absolute;inset:0;width:100%;height:100%;border-radius:var(--radius)" aria-hidden="true">
                    <rect width="600" height="300" fill="#e6ebf3"/>
                    <rect x="0" y="120" width="600" height="26" fill="#fff"/><rect x="230" y="0" width="26" height="300" fill="#fff"/><rect x="420" y="0" width="22" height="300" fill="#fff"/>
                    <rect x="40" y="25" width="130" height="70" rx="10" fill="#d6ecd9"/><rect x="470" y="190" width="100" height="70" rx="10" fill="#d6ecd9"/>
                    <circle cx="300" cy="150" r="95" fill="#1b3f8f" fill-opacity=".12" stroke="#1b3f8f" stroke-width="2" stroke-dasharray="7 6"/>
                    <path d="M300 160s16-15 16-28a16 16 0 10-32 0c0 13 16 28 16 28z" fill="#1b3f8f"/><circle cx="300" cy="132" r="6" fill="#fff"/>
                </svg>
                <div id="loc-map" class="map" style="position:relative;height:360px" role="application" aria-label="Peta lokasi kerja"></div>
            </div>
            <div class="note" style="margin:-6px 0 18px">@include('admin.icon', ['name' => 'info'])
                Klik di peta atau geser penanda untuk memilih titik Puskesmas. Lingkaran menunjukkan radius absensi.
                Koordinat dari Google Maps (klik kanan di titik, lalu klik angkanya) bisa ditempel di kolom "Tempel koordinat".
            </div>

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
                <div class="toolbar" style="margin:14px 0 0">
                    <div class="field" style="flex:1;min-width:260px">
                        <label for="paste-coords">Tempel koordinat (dari Google Maps)</label>
                        <input type="text" id="paste-coords" placeholder="contoh: -5.0222565, 119.6653682" autocomplete="off">
                        <div class="hint" id="paste-hint">Format: latitude, longitude</div>
                    </div>
                    <button type="button" class="btn secondary" id="btn-my-location" style="align-self:flex-end">@include('admin.icon', ['name' => 'pin']) Pakai lokasi saya</button>
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

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        (function () {
            if (!window.L) return; // Leaflet gagal dimuat: gambar cadangan tetap tampil, kolom tetap bisa diisi manual.
            const latInput = document.getElementById('latitude');
            const lngInput = document.getElementById('longitude');
            const radiusInput = document.getElementById('radius_m');
            const paste = document.getElementById('paste-coords');
            const pasteHint = document.getElementById('paste-hint');
            const read = () => [parseFloat(latInput.value), parseFloat(lngInput.value)];
            const valid = ([lat, lng]) => isFinite(lat) && isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180;
            // Titik awal: lokasi tersimpan, atau sekitar Simbang jika belum ada.
            const start = valid(read()) ? read() : [-5.0222565, 119.6653682];

            const map = L.map('loc-map').setView(start, 17);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19, attribution: '&copy; OpenStreetMap',
            }).addTo(map);
            const marker = L.marker(start, { draggable: true }).addTo(map);
            const circle = L.circle(start, {
                radius: parseInt(radiusInput.value, 10) || 150, color: '#1b3f8f', fillOpacity: 0.12, weight: 2, dashArray: '6 6',
            }).addTo(map);

            // Pindahkan penanda + lingkaran, dan isi kolom latitude/longitude (7 angka di belakang koma).
            function place(lat, lng, fill = true, pan = false) {
                marker.setLatLng([lat, lng]);
                circle.setLatLng([lat, lng]);
                if (fill) {
                    latInput.value = lat.toFixed(7);
                    lngInput.value = lng.toFixed(7);
                }
                if (pan) map.setView([lat, lng], Math.max(map.getZoom(), 17));
            }

            map.on('click', (e) => place(e.latlng.lat, e.latlng.lng));
            marker.on('dragend', () => { const p = marker.getLatLng(); place(p.lat, p.lng); });
            [latInput, lngInput].forEach((input) => input.addEventListener('change', () => {
                const point = read();
                if (valid(point)) place(point[0], point[1], false, true);
            }));
            radiusInput.addEventListener('input', () => circle.setRadius(parseInt(radiusInput.value, 10) || 0));

            // "-5.0222565, 119.6653682" dari Google Maps.
            paste.addEventListener('input', () => {
                const parts = paste.value.split(/[,;\s]+/).map(parseFloat).filter(isFinite);
                if (parts.length >= 2 && valid(parts)) {
                    place(parts[0], parts[1], true, true);
                    pasteHint.textContent = 'Koordinat terpasang. Tekan Simpan Lokasi untuk menyimpan.';
                } else if (paste.value.trim() !== '') {
                    pasteHint.textContent = 'Format belum dikenali. Contoh: -5.0222565, 119.6653682';
                }
            });

            document.getElementById('btn-my-location').addEventListener('click', () => {
                if (!navigator.geolocation) return alert('Browser ini tidak mendukung lokasi.');
                navigator.geolocation.getCurrentPosition(
                    (pos) => place(pos.coords.latitude, pos.coords.longitude, true, true),
                    () => alert('Lokasi tidak bisa diambil. Izinkan akses lokasi di browser.'),
                    { enableHighAccuracy: true, timeout: 15000 },
                );
            });
        })();
    </script>
@endsection
