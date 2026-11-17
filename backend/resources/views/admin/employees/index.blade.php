@extends('admin.layout')

@section('title', 'Pegawai')

@section('content')
    <div class="page-head">
        <div>
            <h1>Pegawai</h1>
            <p>Kelola akun pegawai dan admin.</p>
        </div>
        <a href="{{ route('admin.pegawai.create') }}" class="btn">+ Tambah pegawai</a>
    </div>

    <form method="GET" class="toolbar">
        <div class="field">
            <label for="q">Cari nama atau NIP</label>
            <input type="search" id="q" name="q" value="{{ $search }}" placeholder="contoh: Siti atau 1985...">
        </div>
        <button type="submit" class="btn secondary">Cari</button>
        @if ($search !== '')
            <a href="{{ route('admin.pegawai.index') }}" class="btn secondary">Reset</a>
        @endif
    </form>

    <div class="card flush">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Nama</th>
                    <th>NIP</th>
                    <th>Jabatan / Unit</th>
                    <th>Peran</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>{{ $user->name }}<span class="sub">{{ $user->email }}</span></td>
                        <td>{{ $user->nip }}</td>
                        <td>{{ $user->position }}<span class="sub">{{ $user->unit ?? '-' }}</span></td>
                        <td><span class="badge {{ $user->role }}">{{ ucfirst($user->role) }}</span></td>
                        <td>
                            <span class="badge {{ $user->is_active ? 'aktif' : 'nonaktif' }}">
                                {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                <a href="{{ route('admin.pegawai.edit', $user) }}" class="btn secondary small">Ubah</a>
                                @unless ($user->is(auth()->user()))
                                    <form method="POST" action="{{ route('admin.pegawai.toggle', $user) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn small {{ $user->is_active ? 'danger' : 'success' }}">
                                            {{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Tidak ada pegawai yang cocok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $users->links() }}
    </div>
@endsection
