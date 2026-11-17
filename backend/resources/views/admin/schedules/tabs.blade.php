<div class="tabs">
    <a href="{{ route('admin.jadwal.index') }}" class="{{ request()->is('admin/jadwal*') ? 'active' : '' }}">Jadwal pegawai</a>
    <a href="{{ route('admin.shift.index') }}" class="{{ request()->is('admin/shift*') ? 'active' : '' }}">Shift</a>
</div>
