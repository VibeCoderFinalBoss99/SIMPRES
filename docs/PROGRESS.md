# Progres Backend SIMPRES

Mengikuti urutan `docs/PANDUAN.md`. Diperbarui setiap kali satu langkah selesai.
Branch kerja: `backend-setup` (belum di-push).

## Catatan umum

- Database lokal `simpres` (MySQL Laragon 8.4.3) diimpor dari `docs/database/simpres.sql`.
- Titik Puskesmas di database sudah koordinat asli dari Google Maps: lat `-5.0222565`, lng `119.6653682`, radius 150 m.
  Untuk uji manual di database ini pakai koordinat dekat lat `-5.0224565` (sekitar 22 m)
  dan jauh lat `-5.0240565` (sekitar 200 m), lng `119.6653682`.
- Test otomatis memakai database terpisah `simpres_test` (dibuat dengan `backend/tests/buat-database-test.ps1`)
  dan membuat data sendiri, termasuk titik -6.9000000 / 109.6000000. Data test dibatalkan setelah tiap test.

## Status per langkah

| Langkah | Status | File utama | Test PASS | Catatan |
|---|---|---|---|---|
| 1. Proyek Laravel | selesai | `backend/`, `.env`, `config/app.php` | 2 | Extension zip PHP diaktifkan di Laragon |
| 2. Database dan Model | selesai | 10 model di `app/Models`, migration tanpa `users` | 7 | |
| 3. Login API (Sanctum) | selesai | `Api/AuthController`, `LoginRequest`, `UserResource`, `tests/Feature/AuthTest.php`, `tests/api.http` | 15 | Pesan 401/403/404/405 dan validasi bahasa Indonesia |
| 4. Aturan validasi absensi | selesai | `app/Services/AttendanceValidator.php`, `tests/Feature/AttendanceValidatorTest.php`, helper data uji di `tests/TestCase.php` | 32 | 17 test untuk 7 aturan. Menit terlambat dihitung dari jam masuk shift. Izin keluar tidak membebaskan check-in |
| 5. API absensi | selesai | `Api/AttendanceController`, `CheckInRequest`, `CheckOutRequest`, `AttendanceResource`, `lang/id/validation.php`, `tests/Feature/AttendanceApiTest.php` | 46 | Detail absensi orang lain dijawab 404. client_uuid hanya untuk check-in (kolomnya satu). Sudah diuji curl ke database asli |
| 6. CRUD izin dan cuti | selesai | `Api/LeaveController`, `Api/Admin/LeaveReviewController`, `StoreLeaveRequest`, `UpdateLeaveRequest`, `ReviewSubmissionRequest`, `LeaveRequestPolicy`, `LeaveRequestResource`, `Services/SubmissionReviewer`, middleware `admin`, `tests/Feature/LeaveApiTest.php` | 62 | Field file bernama `lampiran`. Izin diterima (selain izin keluar) dicatat sebagai baris `attendances` status `izin` per hari. Disk `local` tidak lagi melayani `/storage/...`. Sudah diuji curl dengan upload foto |

## Perlu keputusan

1. **Zona waktu.** `.env` memakai `APP_TIMEZONE=Asia/Jakarta` (WIB, sesuai instruksi Langkah 1), tetapi koordinat Puskesmas
   (-5.02, 119.66) berada di Sulawesi Selatan yang memakai WITA (`Asia/Makassar`, UTC+8). Jam Windows komputer ini juga UTC+8.
   Dengan WIB, jam absen tercatat 1 jam lebih awal dan status terlambat ikut meleset 1 jam.
   Jika memang WITA: ubah `APP_TIMEZONE=Asia/Makassar` di `backend/.env` (dan `.env.example`), lalu `php artisan config:clear`.
