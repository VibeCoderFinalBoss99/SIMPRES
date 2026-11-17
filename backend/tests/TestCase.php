<?php

namespace Tests;

use App\Models\Schedule;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // Data uji dari docs/PANDUAN.md.
    protected const PUSKESMAS_LAT = -6.9000000;

    protected const PUSKESMAS_LNG = 109.6000000;

    protected const DEKAT_LAT = -6.9002000;   // sekitar 22 m

    protected const JAUH_LAT = -6.9020000;    // sekitar 222 m

    protected function makeLocation(array $data = []): WorkLocation
    {
        return WorkLocation::create(array_merge([
            'name' => 'UPTD Puskesmas Simbang',
            'latitude' => self::PUSKESMAS_LAT,
            'longitude' => self::PUSKESMAS_LNG,
            'radius_m' => 150,
        ], $data));
    }

    protected function makeShift(array $data = []): Shift
    {
        return Shift::create(array_merge([
            'name' => 'Pagi',
            'start_time' => '07:30:00',
            'end_time' => '14:00:00',
            'late_tolerance_min' => 10,
        ], $data));
    }

    /** Pegawai dengan lokasi kerja dan (opsional) jadwal shift Pagi pada tanggal tertentu. */
    protected function makePegawai(?string $scheduleDate = null, array $data = []): User
    {
        $user = User::factory()->create(array_merge([
            'work_location_id' => $this->makeLocation()->id,
        ], $data));

        if ($scheduleDate) {
            Schedule::create([
                'user_id' => $user->id,
                'shift_id' => $this->makeShift()->id,
                'date' => $scheduleDate,
            ]);
        }

        return $user;
    }

    protected function makeAdmin(array $data = []): User
    {
        return User::factory()->admin()->create($data);
    }
}
