# SIMPRES Backend dan Admin Web

REST API untuk aplikasi Android SIMPRES dan Admin Web untuk admin UPTD Puskesmas Simbang.
Dibangun dengan Laravel, Laravel Sanctum, dan MySQL/MariaDB.

| Bagian | Alamat | Login |
|---|---|---|
| REST API | `/api/...` | Token Sanctum (`Authorization: Bearer <token>`) |
| Admin Web | `/admin` | Session, hanya akun berperan admin |

## Menjalankan di komputer

1. Buat database `simpres` dari `../docs/database/simpres.sql`.
2. `composer install`, salin `.env.example` menjadi `.env`, isi `DB_DATABASE=simpres`, lalu:
   ```
   php artisan key:generate
   php artisan migrate
   php artisan serve
   ```
3. Admin Web: `http://127.0.0.1:8000/admin`. API: `http://127.0.0.1:8000/api`.

## Dokumentasi

- `API.md` – daftar 32 endpoint, contoh request dan respons.
- `DEPLOY.md` – langkah memasang di shared hosting.
- `tests/api.http` – contoh request siap pakai.

## Test

```
powershell -File tests\buat-database-test.ps1
php artisan test
```
