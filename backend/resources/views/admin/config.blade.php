@extends('admin.layout')

@section('title', 'Konfigurasi')
@section('subtitle', 'Aturan tidak ditanam di aplikasi. Perubahan dipakai server saat validasi.')

@section('content')
    <form method="POST" action="{{ route('admin.konfigurasi.update') }}">
        @csrf
        @method('PUT')
        <div class="grid-cfg">
            @foreach (\App\Support\Settings::FIELDS as $group => $fields)
                <div class="card" style="margin:0">
                    <h2>{{ $group }}</h2>
                    @foreach ($fields as $key => [$label, $default, $type])
                        <div class="cfg-row">
                            <label for="{{ $key }}">
                                {{ $label }}
                                @if (in_array($key, \App\Support\Settings::ENFORCED, true))
                                    <span class="badge green" style="margin-left:6px">dipakai server</span>
                                @endif
                            </label>
                            @if ($type === 'bool')
                                <label class="switch">
                                    <input type="checkbox" id="{{ $key }}" name="{{ $key }}" value="1" @checked(old($key, $values[$key]))>
                                    <span></span>
                                </label>
                            @else
                                <input type="{{ $type === 'number' ? 'number' : 'text' }}" id="{{ $key }}" name="{{ $key }}"
                                       value="{{ old($key, $values[$key]) }}" class="@error($key) is-invalid @enderror">
                            @endif
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
