@extends('admin.layout')

@section('title', 'Data Pegawai')
@section('subtitle', $users->total().' akun terdaftar · kelola unit, jadwal, dan status akun.')

@php
    $initials = fn ($name) => collect(explode(' ', $name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
@endphp

@section('content')
    <div class="toolbar">
        <form method="GET" class="toolbar" style="margin:0">
            <input type="search" id="q" name="q" value="{{ $search }}" placeholder="Cari nama atau NIP" aria-label="Cari nama atau NIP">
            <button type="submit" class="btn secondary">@include('admin.icon', ['name' => 'filter']) Cari</button>
            @if ($search !== '')
                <a href="{{ route('admin.pegawai.index') }}" class="btn secondary">Reset</a>
            @endif
        </form>
        <span class="grow"></span>
        <a href="{{ route('admin.pegawai.create') }}" class="btn">@include('admin.icon', ['name' => 'plus']) Tambah Pegawai</a>
    </div>

    <div class="card flush">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Pegawai</th>
                    <th>NIP</th>
                    <th>Unit</th>
                    <th>Jadwal</th>
                    <th>Peran</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($users as $user)
                    @php $shift = $user->schedules()->with('shift')->latest('date')->first()?->shift; @endphp
                    <tr>
                        <td>
                            <div class="person">
                                <span class="avatar">{{ $initials($user->name) }}</span>
                                <span><strong>{{ $user->name }}</strong><small>{{ $user->position }} · {{ $user->email }}</small></span>
                            </div>
                        </td>
                        <td>{{ $user->nip }}</td>
                        <td>{{ $user->unit ?? '-' }}</td>
                        <td>
                            @if ($shift)
                                <span class="badge {{ $shift->name === 'Pagi' ? 'blue' : 'amber' }}">{{ $shift->name }}</span>
                            @else
                                <span class="badge">Belum diatur</span>
                            @endif
                        </td>
                        <td><span class="badge {{ $user->role === 'admin' ? 'blue' : '' }}">{{ ucfirst($user->role) }}</span></td>
                        <td>
                            <span class="badge {{ $user->is_active ? 'aktif' : 'nonaktif' }}">
                                {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                <a href="{{ route('admin.pegawai.edit', $user) }}" class="icon-btn" title="Ubah">@include('admin.icon', ['name' => 'edit'])</a>
                                @unless ($user->is(auth()->user()))
                                    <form method="POST" action="{{ route('admin.pegawai.toggle', $user) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="icon-btn {{ $user->is_active ? 'red' : '' }}"
                                                title="{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">@include('admin.icon', ['name' => 'power'])</button>
                                    </form>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">Tidak ada pegawai yang cocok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-foot">Menampilkan {{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }} dari {{ $users->total() }} pegawai</div>
        {{ $users->links() }}
    </div>
@endsection
