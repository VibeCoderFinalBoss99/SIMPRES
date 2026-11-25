@extends('admin.layout')

@section('title', 'Roster')
@section('subtitle', 'Jadwal shift dan libur per pegawai. Pegawai non-shift dan petugas lapangan otomatis memakai shift tetap; roster hanya untuk perubahan.')

@php
    $initials = fn ($name) => collect(explode(' ', $name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
    $chip = fn ($name) => match ($name) { 'Pagi' => 'green', 'Siang' => 'amber', 'Malam' => '', default => 'blue' };
    $dayNames = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];
    $shiftLabel = fn ($shift) => $shift->name.' ('.substr($shift->start_time, 0, 5).'-'.substr($shift->end_time, 0, 5).')';
@endphp

@section('content')
    <div class="toolbar">
        <a class="btn secondary" href="{{ route('admin.jadwal.index', ['minggu' => $weekStart->copy()->subWeek()->toDateString()]) }}" title="Minggu sebelumnya">@include('admin.icon', ['name' => 'chevron-left'])</a>
        <span class="btn secondary" style="cursor:default">@include('admin.icon', ['name' => 'calendar']) {{ $weekStart->translatedFormat('d M') }} – {{ $weekStart->copy()->addDays(6)->translatedFormat('d M Y') }}</span>
        <a class="btn secondary" href="{{ route('admin.jadwal.index', ['minggu' => $weekStart->copy()->addWeek()->toDateString()]) }}" title="Minggu berikutnya">@include('admin.icon', ['name' => 'chevron-right'])</a>
        <span class="grow"></span>
        <a class="btn secondary" href="{{ route('admin.shift.index') }}">Kelola shift</a>
        <a class="btn secondary" href="#isi-sekaligus">Isi jadwal sekaligus</a>
        <a class="btn" href="#bulanan">@include('admin.icon', ['name' => 'plus']) Atur per bulan</a>
    </div>

    <div class="card flush">
        <div class="table-wrap">
            <table class="roster">
                <thead>
                <tr>
                    <th>Pegawai</th>
                    @foreach ($weekDays as $day)
                        <th class="{{ $day->isToday() ? 'today' : '' }}">{{ $day->translatedFormat('D d') }}</th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @foreach ($employees as $employee)
                    <tr>
                        <td>
                            {{-- Klik nama untuk langsung mengatur jadwal bulan pegawai ini. --}}
                            <a class="person" href="{{ route('admin.jadwal.index', ['user_id' => $employee->id, 'month' => $weekStart->format('Y-m')]) }}#bulanan" title="Atur jadwal {{ $employee->name }}">
                                <span class="avatar">{{ $initials($employee->name) }}</span>
                                <span><strong>{{ $employee->name }}</strong><small>{{ \App\Models\User::WORK_TYPES[$employee->work_type] ?? '' }} · {{ $employee->unit }}</small></span>
                            </a>
                        </td>
                        @foreach ($weekDays as $day)
                            @php $s = $week[$employee->id][$day->toDateString()] ?? null; @endphp
                            <td>
                                @if (! $s)
                                    <span class="muted" title="Belum ada jadwal">-</span>
                                @elseif ($s->is_day_off)
                                    <span class="badge {{ $s->exists ? '' : 'auto' }}">Libur</span>
                                @else
                                    <span class="badge {{ $chip($s->shift?->name) }} {{ $s->exists ? '' : 'auto' }}">{{ $s->shift?->name }}</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="legend">Keterangan:
            <span class="badge green">Pagi</span><span class="badge amber">Siang</span><span class="badge">Malam / Libur</span><span class="badge blue">Shift lain</span>
            <span class="badge green auto">Garis putus</span> = otomatis dari shift tetap pegawai. Klik nama pegawai untuk mengubah jadwalnya.
        </div>
    </div>

    {{-- ===================== Atur per bulan ===================== --}}
    <h2 id="bulanan" style="margin-top:26px">Atur jadwal per bulan</h2>

    <form method="GET" action="{{ route('admin.jadwal.index') }}#bulanan" class="toolbar">
        <div class="field">
            <label for="user_id">Pegawai</label>
            <select id="user_id" name="user_id">
                <option value="">- pilih pegawai -</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}" @selected($selected?->id === $employee->id)>{{ $employee->name }} ({{ $employee->nip }})</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="month">Bulan</label>
            <input type="month" id="month" name="month" value="{{ $month->format('Y-m') }}">
        </div>
        <button type="submit" class="btn secondary">Tampilkan</button>
    </form>

    @if ($selected)
        @php
            $default = $selected->defaultShift;
            $emptyLabel = $default ? '- ikut shift tetap ('.$default->name.', Minggu libur) -' : '- tidak ada jadwal -';
        @endphp
        <form method="POST" action="{{ route('admin.jadwal.month') }}" class="card flush">
            @csrf
            @method('PUT')
            <input type="hidden" name="user_id" value="{{ $selected->id }}">
            <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">

            <div style="padding: 18px 20px 4px">
                <h2 style="margin:0">Jadwal {{ $selected->name }}, {{ $month->translatedFormat('F Y') }}</h2>
                <p class="muted" style="margin:6px 0 0">
                    {{ \App\Models\User::WORK_TYPES[$selected->work_type] ?? '' }}.
                    @if ($default)
                        Shift tetap: {{ $shiftLabel($default) }} setiap Senin-Sabtu. Tanggal yang dibiarkan "ikut shift tetap" otomatis memakai shift ini.
                    @else
                        Tidak punya shift tetap, jadi setiap tanggal perlu diisi di sini.
                    @endif
                </p>
            </div>

            {{-- Isi cepat: mengisi pilihan di tabel di bawah, lalu tekan Simpan. --}}
            <div class="toolbar" style="padding: 12px 20px; margin:0; border-bottom: 1px solid var(--border)">
                <span class="muted">Isi cepat:</span>
                <select id="quick-value" aria-label="Isi dengan">
                    @foreach ($shifts as $shift)
                        <option value="{{ $shift->id }}">{{ $shiftLabel($shift) }}</option>
                    @endforeach
                    <option value="libur">Libur</option>
                    <option value="">{{ $emptyLabel }}</option>
                </select>
                @foreach ($dayNames as $iso => $label)
                    <label class="checkbox"><input type="checkbox" class="quick-day" value="{{ $iso }}" @checked($iso <= 6)> {{ $label }}</label>
                @endforeach
                <label class="checkbox"><input type="checkbox" id="quick-off" checked> hari lain Libur</label>
                <button type="button" class="btn secondary small" id="quick-apply">Terapkan ke tabel</button>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr><th>Tanggal</th><th>Hari</th><th>Shift / libur</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($days as $day)
                        @php
                            $date = $day->toDateString();
                            $schedule = $schedules->get($date);
                            $current = old("days.$date", $schedule ? ($schedule->is_day_off ? 'libur' : (string) $schedule->shift_id) : '');
                        @endphp
                        <tr>
                            <td>{{ $day->format('d/m/Y') }}</td>
                            <td>{{ $day->translatedFormat('l') }}</td>
                            <td>
                                <select name="days[{{ $date }}]" data-iso="{{ $day->dayOfWeekIso }}" style="max-width: 320px">
                                    <option value="">{{ $emptyLabel }}</option>
                                    @foreach ($shifts as $shift)
                                        <option value="{{ $shift->id }}" @selected($current === (string) $shift->id)>{{ $shiftLabel($shift) }}</option>
                                    @endforeach
                                    <option value="libur" @selected($current === 'libur')>Libur</option>
                                </select>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div style="padding: 16px 20px">
                <button type="submit" class="btn">Simpan jadwal bulan ini</button>
            </div>
        </form>

        <script>
            document.getElementById('quick-apply').addEventListener('click', function () {
                const value = document.getElementById('quick-value').value;
                const off = document.getElementById('quick-off').checked;
                const days = [...document.querySelectorAll('.quick-day:checked')].map((box) => box.value);
                document.querySelectorAll('select[data-iso]').forEach((select) => {
                    if (days.includes(select.dataset.iso)) select.value = value;
                    else if (off) select.value = 'libur';
                });
            });
        </script>
    @else
        <div class="card empty">Pilih pegawai dan bulan, atau klik nama pegawai di tabel atas, untuk mengatur jadwal bulanannya.</div>
    @endif

    {{-- ===================== Isi sekaligus ===================== --}}
    <h2 id="isi-sekaligus" style="margin-top:26px">Isi jadwal sekaligus (sampai 12 bulan)</h2>
    <form method="POST" action="{{ route('admin.jadwal.fill') }}" class="card">
        @csrf
        <p class="muted" style="margin-top:0">
            Cocok untuk pegawai shift yang polanya sama dalam waktu lama, misalnya Pagi setiap Senin-Sabtu selama setahun.
            Pegawai non-shift dan petugas lapangan tidak perlu diisi karena sudah otomatis memakai shift tetap.
        </p>

        <div class="field">
            <label>Pegawai *</label>
            <div class="toolbar" style="margin:0">
                @foreach ($employees as $employee)
                    <label class="checkbox">
                        <input type="checkbox" name="user_ids[]" value="{{ $employee->id }}" @checked(in_array((string) $employee->id, old('user_ids', []), true))>
                        {{ $employee->name }} <small class="muted">({{ \App\Models\User::WORK_TYPES[$employee->work_type] ?? '' }})</small>
                    </label>
                @endforeach
            </div>
            @error('user_ids')<div class="error">{{ $message }}</div>@enderror
        </div>

        <div class="toolbar">
            <div class="field">
                <label for="fill_from">Dari bulan *</label>
                <input type="month" id="fill_from" name="from" value="{{ old('from', now()->format('Y-m')) }}" required>
            </div>
            <div class="field">
                <label for="fill_to">Sampai bulan *</label>
                <input type="month" id="fill_to" name="to" value="{{ old('to', now()->addMonths(11)->format('Y-m')) }}" required>
                @error('to')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="fill_shift">Shift *</label>
                <select id="fill_shift" name="shift_id" required>
                    @foreach ($shifts as $shift)
                        <option value="{{ $shift->id }}" @selected((string) old('shift_id') === (string) $shift->id)>{{ $shiftLabel($shift) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="field">
            <label>Hari kerja *</label>
            <div class="toolbar" style="margin:0">
                @foreach ($dayNames as $iso => $label)
                    <label class="checkbox"><input type="checkbox" name="weekdays[]" value="{{ $iso }}" @checked(in_array((string) $iso, old('weekdays', ['1', '2', '3', '4', '5', '6']), true))> {{ $label }}</label>
                @endforeach
            </div>
            @error('weekdays')<div class="error">{{ $message }}</div>@enderror
        </div>

        <div class="toolbar">
            <label class="checkbox"><input type="checkbox" name="other_days_off" value="1" @checked(old('other_days_off', '1'))> Hari yang tidak dicentang diisi Libur</label>
            <label class="checkbox"><input type="checkbox" name="overwrite" value="1" @checked(old('overwrite'))> Timpa jadwal yang sudah ada</label>
        </div>

        <button type="submit" class="btn">Isi jadwal</button>
    </form>

    {{-- ===================== Tambah satu jadwal ===================== --}}
    <div class="card" style="margin-top:26px">
        <h2>Tambah satu jadwal</h2>
        <form method="POST" action="{{ route('admin.jadwal.store') }}" class="toolbar">
            @csrf
            <div class="field">
                <label for="add_user">Pegawai</label>
                <select id="add_user" name="user_id" required>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) old('user_id', $selected?->id) === (string) $employee->id)>{{ $employee->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="add_date">Tanggal</label>
                <input type="date" id="add_date" name="date" value="{{ old('date') }}" required>
            </div>
            <div class="field">
                <label for="add_shift">Shift</label>
                <select id="add_shift" name="shift_id">
                    <option value="">-</option>
                    @foreach ($shifts as $shift)
                        <option value="{{ $shift->id }}" @selected((string) old('shift_id') === (string) $shift->id)>{{ $shiftLabel($shift) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="checkbox"><input type="checkbox" name="is_day_off" value="1" @checked(old('is_day_off'))> Libur</label>
            </div>
            <button type="submit" class="btn">Tambah</button>
        </form>
    </div>
@endsection
