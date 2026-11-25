<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Pengaturan Admin Web (halaman Konfigurasi). Disimpan di storage/app/private/settings.json,
 * jadi tidak perlu tabel baru. Riwayat perubahan ikut disimpan untuk Audit Log.
 */
class Settings
{
    private const FILE = 'settings.json';

    /**
     * key => [label, nilai awal, jenis, minimum, maksimum, keterangan] ; jenis: number | bool | list.
     * Semua pengaturan di sini dipakai server. Radius absensi tidak disimpan di sini, tetapi langsung di
     * tabel work_locations (sama dengan halaman Lokasi Kerja), lihat ConfigController.
     */
    public const FIELDS = [
        'Geofence & lokasi' => [
            'max_accuracy_m' => ['Batas akurasi GPS (meter)', 100, 'number', 10, 500,
                'Absen ditolak jika akurasi GPS HP lebih buruk dari angka ini. Disarankan 30-100 m.'],
        ],
        'Jadwal & toleransi' => [
            'checkin_open_before_min' => ['Check-in dibuka sebelum jam masuk (menit)', 60, 'number', 0, 240,
                'Contoh 60: shift Pagi 07.30 baru bisa check-in mulai 06.30. Lebih awal ditolak "belum masuk jam kerja".'],
            'holidays' => ['Hari libur nasional', "2026-12-25 Hari Raya Natal\n2027-01-01 Tahun Baru Masehi", 'list', 0, 0,
                'Satu baris satu tanggal: YYYY-MM-DD Nama libur. Pegawai dengan shift tetap otomatis libur, tidak dihitung alpa.'],
        ],
        'Deteksi risiko' => [
            'reject_mock' => ['Tolak lokasi palsu (fake GPS / mock location)', true, 'bool', 0, 0,
                'Android menandai lokasi dari aplikasi fake GPS. Absen dengan tanda ini ditolak.'],
            'reject_emulator' => ['Tolak absen dari emulator', true, 'bool', 0, 0,
                'Absen dari emulator Android di laptop ditolak. Matikan hanya untuk pengujian.'],
        ],
    ];

    /** Pengaturan yang langsung dipakai server saat validasi absensi (sekarang semuanya). */
    public const ENFORCED = ['max_accuracy_m', 'checkin_open_before_min', 'holidays', 'reject_mock', 'reject_emulator'];

    /**
     * Hari libur nasional dari Konfigurasi, dengan kunci tanggal: ['2026-12-25' => 'Hari Raya Natal'].
     * Baris yang tanggalnya tidak sah dilewati.
     *
     * @return array<string, string>
     */
    public static function holidays(): array
    {
        // Dibaca sekali per permintaan, karena dipanggil untuk setiap tanggal di rekap dan roster.
        return once(fn () => self::parseHolidays());
    }

    private static function parseHolidays(): array
    {
        $list = [];
        foreach (preg_split('/\R/', (string) self::get('holidays')) as $line) {
            if (preg_match('/^\s*(\d{4}-\d{2}-\d{2})\s*(.*)$/', $line, $m) && checkdate((int) substr($m[1], 5, 2), (int) substr($m[1], 8, 2), (int) substr($m[1], 0, 4))) {
                $list[$m[1]] = trim($m[2]) ?: 'Hari libur';
            }
        }

        return $list;
    }

    public static function all(): array
    {
        $saved = self::read()['values'] ?? [];
        $values = [];
        foreach (self::FIELDS as $fields) {
            foreach ($fields as $key => [$label, $default]) {
                $values[$key] = $saved[$key] ?? $default;
            }
        }

        return $values;
    }

    public static function get(string $key): mixed
    {
        return self::all()[$key] ?? null;
    }

    /** Simpan nilai baru dan catat perubahan (siapa, kapan, apa) untuk Audit Log. */
    public static function save(array $values, string $actor): void
    {
        $data = self::read();
        $old = self::all();
        $changes = [];
        foreach ($values as $key => $value) {
            if (array_key_exists($key, $old) && $old[$key] != $value) {
                $changes[] = self::label($key).': '.self::show($old[$key]).' -> '.self::show($value);
            }
        }
        $data['values'] = array_merge($data['values'] ?? [], $values);
        if ($changes) {
            $data['history'] = array_slice(array_merge([[
                'at' => now()->toDateTimeString(), 'actor' => $actor, 'changes' => $changes,
            ]], $data['history'] ?? []), 0, 100);
        }
        Storage::put(self::FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public static function history(): array
    {
        return self::read()['history'] ?? [];
    }

    public static function label(string $key): string
    {
        foreach (self::FIELDS as $fields) {
            if (isset($fields[$key])) {
                return $fields[$key][0];
            }
        }

        return $key;
    }

    private static function show(mixed $value): string
    {
        return is_bool($value) ? ($value ? 'aktif' : 'nonaktif') : (string) $value;
    }

    private static function read(): array
    {
        // Test otomatis selalu memakai nilai awal, tidak terpengaruh pengaturan di komputer.
        if (app()->runningUnitTests()) {
            return [];
        }

        return Storage::exists(self::FILE) ? (json_decode(Storage::get(self::FILE), true) ?: []) : [];
    }
}
