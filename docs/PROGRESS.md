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
| 7. Koreksi dan tugas lapangan | selesai | `Api/CorrectionController`, `Api/FieldDutyController`, `Api/Admin/CorrectionReviewController`, `Api/Admin/FieldDutyReviewController`, `StoreCorrectionRequest`, `StoreFieldDutyRequest`, 2 Policy, 2 Resource, `tests/Feature/CorrectionApiTest.php`, `FieldDutyApiTest.php` | 80 | Koreksi diterima mengubah jam di `attendances` (jam masuk dikoreksi = status terlambat dihitung ulang; belum ada absensi = dibuat baru). Lampiran koreksi di `lampiran-koreksi`. Foto tugas lapangan field `foto`, hanya jpg |
| 8. Jadwal, notifikasi, lokasi | selesai | `Api/ScheduleController`, `Api/NotificationController`, `Api/WorkLocationController`, `Api/DeviceTokenController`, `ScheduleResource`, `UserNotificationResource`, notifikasi di `SubmissionReviewer`, `tests/Feature/ScheduleNotificationApiTest.php` | 90 | Notifikasi hanya disimpan di `user_notifications`; kirim push FCM belum dibuat (tidak diminta, butuh kredensial Firebase). Notifikasi orang lain dijawab 404 |
| 9a. Login dan layout Admin Web | selesai | `Admin/AuthController`, `views/admin/layout.blade.php`, `login.blade.php`, `public/css/admin.css`, `routes/web.php`, `tests/Feature/Admin/AdminAuthTest.php` | 98 | Login session di `/admin/login`; pegawai dan admin nonaktif ditolak; header `no-store` supaya Back setelah logout tidak menampilkan halaman admin. Desain Figma A1-A8 tidak bisa dibuka dari sini (tidak ada tautan), jadi tampilan dibuat rapi sederhana |
| 9b. Dashboard | selesai | `Admin/DashboardController`, `views/admin/dashboard.blade.php`, `tests/Feature/Admin/DashboardTest.php` | 100 | Kartu hadir/terlambat/izin/belum absen (pegawai aktif tanpa absensi hari ini dan tidak libur), tabel absensi hari ini, jumlah pengajuan menunggu per jenis |
| 9c. Kelola pegawai | selesai | `Admin/EmployeeController`, `Requests/Admin/EmployeeRequest`, `views/admin/employees/*`, `views/admin/pagination.blade.php`, `tests/Feature/Admin/EmployeeTest.php` | 109 | Cari nama/NIP, 15 per halaman. Password kosong saat ubah = tidak diganti. Menonaktifkan akun ikut menghapus semua token API-nya. Admin tidak bisa menonaktifkan/menurunkan peran dirinya sendiri |
| 9d. Lokasi, shift, jadwal | selesai | `Admin/LocationController`, `Admin/ShiftController`, `Admin/ScheduleController`, `views/admin/location.blade.php`, `views/admin/shifts/*`, `views/admin/schedules/*`, `tests/Feature/Admin/LocationShiftScheduleTest.php` | 116 | Jadwal: isian per bulan untuk satu pegawai (tiap tanggal: shift/libur/kosong) dan form tambah satu jadwal yang menolak jadwal ganda. Menu Shift ada sebagai tab di halaman Jadwal |
| 9e. Persetujuan pengajuan | selesai | `Admin/SubmissionController`, `views/admin/submissions/index.blade.php`, `show.blade.php`, `tests/Feature/Admin/SubmissionTest.php` | 126 | Tab Izin/Koreksi/Tugas lapangan, filter status (bawaan: menunggu). Lampiran/foto dibuka lewat route admin berlogin. Keputusan memakai `SubmissionReviewer` yang sama dengan API (reviewed_by, reviewed_at, koreksi jam, notifikasi) |
| 9f. Rekap | selesai | `Admin/RecapController`, `views/admin/recap.blade.php`, `tests/Feature/Admin/RecapTest.php` | 130 | Hadir/terlambat/izin/menit terlambat dari `attendances` (test membandingkan dengan query nomor 6 PANDUAN). Alpa = status alpa + hari kerja terjadwal yang sudah lewat tanpa absensi. CSV pemisah `;` dengan BOM UTF-8, NIP ditulis sebagai teks. Tampilan semua halaman admin dicek dengan screenshot Chrome headless |
| 10. Dokumentasi API | selesai | `backend/API.md`, `backend/tests/api.http` | 130 | 32 endpoint di API.md = 32 di `route:list --path=api`. Setiap endpoint punya contoh request, respons sukses/gagal, kode status |
| 11. Panduan deploy | selesai | `backend/DEPLOY.md` | 130 | Hanya dokumen, tidak menghubungi server. Checklist hosting di PANDUAN belum bisa dicek (butuh hosting) |

## Perlu keputusan

1. **Zona waktu.** `.env` memakai `APP_TIMEZONE=Asia/Jakarta` (WIB, sesuai instruksi Langkah 1), tetapi koordinat Puskesmas
   (-5.02, 119.66) berada di Sulawesi Selatan yang memakai WITA (`Asia/Makassar`, UTC+8). Jam Windows komputer ini juga UTC+8.
   Dengan WIB, jam absen tercatat 1 jam lebih awal dan status terlambat ikut meleset 1 jam.
   Jika memang WITA: ubah `APP_TIMEZONE=Asia/Makassar` di `backend/.env` (dan `.env.example`), lalu `php artisan config:clear`.
2. **Push notification FCM** belum dibuat (token sudah disimpan). Butuh kredensial Firebase dan kemungkinan package baru.
3. **Tugas lapangan diterima** belum dihitung sebagai hadir di rekap. Perlu aturan: apakah tugas lapangan = hadir?
4. **Desain Figma A1-A8** tidak bisa dibuka dari sesi ini; tampilan Admin Web dibuat sederhana sendiri.
5. **Akun contoh** (password `simpres123`) harus diganti sebelum online (lihat DEPLOY.md langkah 5).
6. Branch `backend-setup` belum di-merge ke `main` dan belum di-push.
