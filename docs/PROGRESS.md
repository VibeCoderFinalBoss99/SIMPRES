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
