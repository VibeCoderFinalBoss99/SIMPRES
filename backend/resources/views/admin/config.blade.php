@extends('admin.layout')

@section('title', 'Konfigurasi')
@section('subtitle', 'Aturan absensi yang dipakai server. Perubahan langsung berlaku untuk check-in dan check-out berikutnya.')

@section('content')
    <form method="POST" action="{{ route('admin.konfigurasi.update') }}">
        @csrf
        @method('PUT')
        <div class="grid-cfg">
            @foreach (\App\Support\Settings::FIELDS as $group => $fields)
                <div class="card" style="margin:0">
                    <h2>{{ $group }}</h2>

                    @if ($loop->first)
                        {{-- Radius disimpan di lokasi kerja (sama dengan halaman Lokasi Kerja). --}}
                        <div class="cfg-row stacked">
                            <label for="radius_m">Radius absensi (meter) <span class="badge green" style="margin-left:6px">dipakai server</span></label>
                            @if ($location)
                                <input type="number" id="radius_m" name="radius_m" min="10" max="1000"
                                       value="{{ old('radius_m', $location->radius_m) }}" class="@error('radius_m') is-invalid @enderror">
                                <div class="hint">Jarak maksimal dari titik {{ $location->name }}. Disarankan 50-100 m. Sama dengan radius di menu
                                    <a href="{{ route('admin.lokasi.edit') }}">Lokasi Kerja</a>.</div>
                            @else
                                <div class="hint">Lokasi kerja belum dibuat. Atur dulu di menu <a href="{{ route('admin.lokasi.edit') }}">Lokasi Kerja</a>.</div>
                            @endif
                        </div>
                    @endif

                    @foreach ($fields as $key => [$label, $default, $type, $min, $max, $help])
                        <div class="cfg-row {{ $type === 'bool' ? '' : 'stacked' }}">
                            <label for="{{ $key }}">
                                {{ $label }}
                                <span class="badge green" style="margin-left:6px">dipakai server</span>
                            </label>
                            @if ($type === 'bool')
                                <label class="switch">
                                    <input type="checkbox" id="{{ $key }}" name="{{ $key }}" value="1" @checked(old($key, $values[$key]))>
                                    <span></span>
                                </label>
                            @elseif ($type === 'list')
                                <textarea id="{{ $key }}" name="{{ $key }}" rows="6" class="@error($key) is-invalid @enderror"
                                          placeholder="2026-12-25 Hari Raya Natal">{{ old($key, $values[$key]) }}</textarea>
                            @else
                                <input type="number" id="{{ $key }}" name="{{ $key }}" min="{{ $min }}" max="{{ $max }}"
                                       value="{{ old($key, $values[$key]) }}" class="@error($key) is-invalid @enderror">
                            @endif
                            <div class="hint" style="flex-basis:100%">{{ $help }}</div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
        <div class="actions" style="justify-content:flex-end;margin-top:18px">
            <button type="submit" class="btn">Simpan Perubahan</button>
        </div>
    </form>
@endsection
