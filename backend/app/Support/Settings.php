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

    /** key => [label, nilai awal, jenis] ; jenis: number | text | bool */
    public const FIELDS = [
        'Geofence & lokasi' => [
            'radius_default_m' => ['Radius awal lokasi baru (meter)', 150, 'number'],
            'max_accuracy_m' => ['Batas akurasi GPS (meter)', 100, 'number'],
            'near_edge_review' => ['Lokasi dekat batas ditandai perlu review', true, 'bool'],
        ],
        'Jadwal & toleransi' => [
            'nonshift_hours' => ['Jam kerja non-shift', '07.30-14.00', 'text'],
            'late_tolerance_min' => ['Toleransi terlambat bawaan (menit)', 10, 'number'],
            'workdays' => ['Hari kerja', 'Senin-Sabtu', 'text'],
        ],
        'Deteksi risiko' => [
            'reject_mock' => ['Tolak lokasi palsu (mock location)', true, 'bool'],
            'flag_repeat_location' => ['Tandai lokasi sama berulang', true, 'bool'],
            'device_binding' => ['Kunci perangkat terdaftar (device binding)', false, 'bool'],
        ],
        'Sinkronisasi' => [
            'auto_send' => ['Kirim otomatis saat online', true, 'bool'],
            'max_retries' => ['Percobaan ulang maksimal', 5, 'number'],
            'delete_local_photo' => ['Hapus foto lokal setelah terkirim', true, 'bool'],
        ],
        'Retensi data' => [
            'photo_days' => ['Foto tugas lapangan (hari)', 90, 'number'],
            'location_days' => ['Data lokasi transaksi (hari)', 180, 'number'],
        ],
        'Peran & akses' => [
            'admin_approve_leave' => ['Admin dapat menyetujui izin', true, 'bool'],
            'validator_review_photo' => ['Validator lapangan dapat meninjau foto', true, 'bool'],
        ],
    ];

    /** Pengaturan yang langsung dipakai server saat validasi absensi. */
    public const ENFORCED = ['max_accuracy_m', 'reject_mock'];

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
