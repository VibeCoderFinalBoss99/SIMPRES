@extends('admin.layout')

@section('title', 'Roster')
@section('subtitle', 'Jadwal shift dan libur per pegawai. Atur per bulan di bagian bawah halaman.')

@php
    // Roster mingguan: Senin s.d. Minggu, bisa digeser dengan ?minggu=YYYY-MM-DD.
    $weekStart = \Illuminate\Support\Carbon::parse(request('minggu', now()->toDateString()))->startOfWeek();
    $weekDays = collect(range(0, 6))->map(fn ($i) => $weekStart->copy()->addDays($i));
    $weekRows = \App\Models\Schedule::with('shift')
        ->whereBetween('date', [$weekStart->toDateString(), $weekStart->copy()->addDays(6)->toDateString()])
        ->get()
        ->groupBy('user_id');
    $initials = fn ($name) => collect(explode(' ', $name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
    $chip = fn ($name) => match ($name) { 'Pagi' => 'green', 'Siang' => 'amber', 'Malam' => '', default => 'blue' };
@endphp

@section('content')
    <div class="toolbar">
        <a class="btn secondary" href="{{ route('admin.jadwal.index', ['minggu' => $weekStart->copy()->subWeek()->toDateString()]) }}" title="Minggu sebelumnya">@include('admin.icon', ['name' => 'chevron-left'])</a>
        <span class="btn secondary" style="cursor:default">@include('admin.icon', ['name' => 'calendar']) {{ $weekStart->translatedFormat('d M') }} – {{ $weekStart->copy()->addDays(6)->translatedFormat('d M Y') }}</span>
        <a class="btn secondary" href="{{ route('admin.jadwal.index', ['minggu' => $weekStart->copy()->addWeek()->toDateString()]) }}" title="Minggu berikutnya">@include('admin.icon', ['name' => 'chevron-right'])</a>
        <span class="grow"></span>
        <a class="btn secondary" href="{{ route('admin.shift.index') }}">Kelola shift</a>
        <a class="btn" href="#atur-roster">@include('admin.icon', ['name' => 'plus']) Atur Roster</a>
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
                    @php $rows = ($weekRows[$employee->id] ?? collect())->keyBy(fn ($s) => $s->date->toDateString()); @endphp
                    <tr>
                        <td>
                            <div class="person">
                                <span class="avatar">{{ $initials($employee->name) }}</span>
                                <span><strong>{{ $employee->name }}</strong><small>{{ $employee->unit }}</small></span>
                            </div>
                        </td>
                        @foreach ($weekDays as $day)
                            @php $s = $rows[$day->toDateString()] ?? null; @endphp
                            <td>
                                @if (! $s)
                                    <span class="muted">-</span>
                                @elseif ($s->is_day_off)
                                    <span class="badge">Libur</span>
                                @else
                                    <span class="badge {{ $chip($s->shift?->name) }}">{{ $s->shift?->name }}</span>
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
        </div>
    </div>

    <h2 id="atur-roster" style="margin-top:26px">Atur roster per bulan</h2>

    <form method="GET" class="toolbar">
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

    <div class="card">
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
                        <option value="{{ $shift->id }}" @selected((string) old('shift_id') === (string) $shift->id)>
                            {{ $shift->name }} ({{ substr($shift->start_time, 0, 5) }}-{{ substr($shift->end_time, 0, 5) }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="checkbox"><input type="checkbox" name="is_day_off" value="1" @checked(old('is_day_off'))> Libur</label>
            </div>
            <button type="submit" class="btn">Tambah</button>
        </form>
    </div>

    @if ($selected)
        <form method="POST" action="{{ route('admin.jadwal.month') }}" class="card flush">
            @csrf
            @method('PUT')
            <input type="hidden" name="user_id" value="{{ $selected->id }}">
            <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">

            <h2>Jadwal {{ $selected->name }}, {{ $month->translatedFormat('F Y') }}</h2>
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
                                <select name="days[{{ $date }}]" style="max-width: 280px">
                                    <option value="">- tidak ada jadwal -</option>
                                    @foreach ($shifts as $shift)
                                        <option value="{{ $shift->id }}" @selected($current === (string) $shift->id)>
                                            {{ $shift->name }} ({{ substr($shift->start_time, 0, 5) }}-{{ substr($shift->end_time, 0, 5) }})
                                        </option>
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
    @else
        <div class="card empty">Pilih pegawai dan bulan untuk melihat dan mengatur jadwal bulanannya.</div>
    @endif
@endsection
