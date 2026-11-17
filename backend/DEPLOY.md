# Panduan Deploy SIMPRES ke Shared Hosting (SSH)

Panduan ini untuk shared hosting dengan cPanel (atau panel sejenis) yang punya akses **SSH**, **PHP 8.3**, dan **MySQL/MariaDB**.
Contoh nama yang dipakai (ganti dengan milikmu):

| Contoh | Arti |
|---|---|
| `namadomain.com` | domain utama |
| `api.namadomain.com` | subdomain untuk API dan Admin Web SIMPRES |
| `userhost` | username hosting / SSH |
| `/home/userhost/simpres` | folder kode Laravel (di **luar** `public_html`) |
| `userhost_simpres` | nama database dari panel (biasanya diberi awalan username) |

API dan Admin Web berjalan dari satu aplikasi yang sama:
- API: `https://api.namadomain.com/api/...`
- Admin Web: `https://api.namadomain.com/admin`
  (jika ingin `https://namadomain.com/admin`, arahkan juga document root domain utama ke folder `public` yang sama, lihat langkah 8).

---

## 0. Persiapan di panel hosting

1. **Versi PHP 8.3**: menu *Select PHP Version* / *MultiPHP Manager*, pilih 8.3 untuk domain dan subdomain.
2. **Extension PHP aktif**: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `tokenizer`, `xml`, `ctype`, `bcmath`, `curl`, `zip`.
3. **Batas upload** (menu *PHP Options*): `upload_max_filesize = 8M`, `post_max_size = 10M`
   (foto dibatasi 2 MB oleh aplikasi, sisanya cadangan).
4. **Database**: menu *MySQL Databases*:
   - buat database, misalnya `userhost_simpres`,
   - buat user, misalnya `userhost_simpres` dengan password kuat,
   - tambahkan user ke database dengan **ALL PRIVILEGES**.
   Catat nama database, username, dan password untuk `.env`.
5. **SSH**: aktifkan di menu *SSH Access*, lalu coba dari komputer:
   ```
   ssh userhost@namadomain.com
   ```
6. Pastikan Composer tersedia di server: `composer -V`. Jika belum ada:
   ```
   cd ~
   curl -sS https://getcomposer.org/installer | php
   alias composer="php ~/composer.phar"
   ```

---

## 1. Upload kode

Kode diletakkan di **luar** `public_html`, supaya `.env`, `storage`, dan kode PHP tidak bisa dibuka dari browser.

**Cara A: git (disarankan)**, jika repo sudah di GitHub/GitLab (private):
```
cd ~
git clone https://github.com/USERNAME/SIMPRES.git simpres-repo
ln -s ~/simpres-repo/backend ~/simpres
```

**Cara B: upload zip.** Di komputer, buat zip isi folder `backend` **tanpa** folder berikut:
`vendor/`, `.env`, `storage/logs/*`, `storage/app/private/*`, `storage/framework/sessions/*`,
`storage/framework/views/*`, `tests/`. Upload lewat *File Manager* ke `/home/userhost/`, lalu:
```
cd ~
mkdir simpres
unzip backend.zip -d simpres
```

Cek hasilnya: `ls ~/simpres` menampilkan `app`, `bootstrap`, `config`, `public`, `routes`, `storage`, `artisan`, `composer.json`.

---

## 2. Install dependency

```
cd ~/simpres
composer install --no-dev --optimize-autoloader
```
`--no-dev` tidak memasang paket untuk test, `--optimize-autoloader` membuat aplikasi lebih cepat.

---

## 3. File `.env` produksi

```
cp .env.example .env
nano .env
```
Isi minimal:
```
APP_NAME=SIMPRES
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://api.namadomain.com
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id
APP_FALLBACK_LOCALE=en

LOG_CHANNEL=daily
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=userhost_simpres
DB_USERNAME=userhost_simpres
DB_PASSWORD=password_database_dari_panel

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
```
Catatan:
- `APP_DEBUG=false` wajib di produksi, supaya error tidak menampilkan isi kode dan password.
- `APP_TIMEZONE`: Puskesmas di Sulawesi Selatan memakai **WITA**. Jika benar, isi `Asia/Makassar`.
  Lihat `docs/PROGRESS.md` bagian *Perlu keputusan*.
- `DB_HOST` di shared hosting biasanya `localhost`. Jika panel menulis host lain, pakai yang dari panel.

Batasi izin file `.env` supaya hanya bisa dibaca pemiliknya:
```
chmod 600 .env
```

---

## 4. Application key

Hanya dilakukan **sekali** saat pertama deploy (mengganti key akan membuat semua session lama tidak berlaku):
```
php artisan key:generate --force
```
Cek: `grep APP_KEY .env` berisi `base64:...`.

---

## 5. Import database lewat phpMyAdmin

Database dan 10 tabel SIMPRES dibuat dari `docs/database/simpres.sql`, **bukan** dari migration.

1. Di komputer, salin `docs/database/simpres.sql` menjadi `simpres-hosting.sql`, lalu **hapus** baris berikut di bagian atas
   (di shared hosting database sudah dibuat lewat panel dan namanya berawalan username):
   ```sql
   CREATE DATABASE IF NOT EXISTS simpres
     CHARACTER SET utf8mb4
     COLLATE utf8mb4_unicode_ci;

   USE simpres;
   ```
2. Buka **phpMyAdmin**, klik database `userhost_simpres` di kiri.
3. Tab **Import**, pilih `simpres-hosting.sql`, klik **Import** (Go).
4. Tab **Structure** harus menampilkan 10 tabel: `work_locations`, `shifts`, `users`, `schedules`, `attendances`,
   `leave_requests`, `attendance_corrections`, `field_duties`, `user_notifications`, `device_tokens`.

**Penting setelah import:** data awal berisi akun contoh dengan password `simpres123`.
- Login ke Admin Web dengan `admin@simpres.test`, lalu di menu **Pegawai** ubah email dan password admin dengan yang asli.
- Nonaktifkan atau ubah akun contoh `siti@simpres.test` dan `budi@simpres.test`.
- Periksa titik Puskesmas di menu **Lokasi**.

---

## 6. Tabel sistem Laravel (migrate)

Migration hanya membuat tabel sistem (`migrations`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`,
`job_batches`, `failed_jobs`, `personal_access_tokens`). Tabel SIMPRES tidak disentuh.
```
cd ~/simpres
php artisan migrate --force
```
`--force` dibutuhkan karena `APP_ENV=production`.

---

## 7. Cache konfigurasi dan izin folder

```
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
Setiap kali `.env` diubah, jalankan lagi `php artisan config:cache`.

Folder yang harus bisa ditulis PHP:
```
mkdir -p storage/app/private/lampiran-izin storage/app/private/lampiran-koreksi storage/app/private/tugas-lapangan
chmod -R 775 storage bootstrap/cache
```
Jika hosting memakai suEXEC/PHP-FPM dengan user yang sama (umum di cPanel), `755` untuk folder sudah cukup.
Jangan pernah memakai `777`.

Lampiran izin dan foto tugas lapangan disimpan di `storage/app/private` dan hanya bisa dibuka lewat endpoint berlogin.
Tidak perlu `php artisan storage:link`.

---

## 8. Arahkan subdomain ke folder `public`

Di cPanel menu **Domains** (atau *Subdomains*):
1. Buat/ubah subdomain `api.namadomain.com`.
2. **Document Root**: `/home/userhost/simpres/public`.

Jika panel tidak mengizinkan document root di luar `public_html`, buat symlink:
```
rm -rf ~/public_html/api        # hanya jika folder ini kosong / dibuat otomatis oleh panel
ln -s ~/simpres/public ~/public_html/api
```
lalu set document root subdomain ke `/home/userhost/public_html/api`.

Untuk Admin Web di domain utama (`https://namadomain.com/admin`), arahkan document root domain utama ke
`/home/userhost/simpres/public` juga (atau cukup pakai `https://api.namadomain.com/admin`).

Jangan arahkan document root ke folder `simpres` (tanpa `/public`), karena `.env` akan bisa diunduh.

---

## 9. SSL (HTTPS)

1. cPanel menu **SSL/TLS Status** (AutoSSL) atau **Let's Encrypt**: pasang sertifikat untuk `api.namadomain.com`
   (dan `namadomain.com` jika dipakai).
2. Paksa HTTPS: tambahkan di **awal** `~/simpres/public/.htaccess`, sebelum aturan Laravel:
   ```
   RewriteEngine On
   RewriteCond %{HTTPS} off
   RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```
3. Pastikan `APP_URL` di `.env` memakai `https://`, lalu `php artisan config:cache`.
4. Di aplikasi Android, ganti alamat API ke `https://api.namadomain.com/api/`.

---

## 10. Cron: scheduler dan backup database harian

Di cPanel menu **Cron Jobs**.

**a. Scheduler Laravel** (tiap menit; saat ini belum ada tugas terjadwal, tapi disiapkan untuk ke depan):
```
* * * * * cd /home/userhost/simpres && php artisan schedule:run >> /dev/null 2>&1
```
Jika `php` di cron bukan versi 8.3, pakai path lengkap, misalnya `/usr/local/bin/ea-php83` atau `/opt/alt/php83/usr/bin/php`
(lihat dengan `which php` lewat SSH, atau tanya penyedia hosting).

**b. Backup database harian** jam 01.00, disimpan 14 hari:

1. Simpan password database di file khusus supaya tidak tertulis di perintah cron:
   ```
   nano ~/.my.cnf
   ```
   isi:
   ```
   [mysqldump]
   user=userhost_simpres
   password=password_database_dari_panel
   ```
   lalu:
   ```
   chmod 600 ~/.my.cnf
   mkdir -p ~/backups
   ```
2. Cron job:
   ```
   0 1 * * * mysqldump --single-transaction userhost_simpres | gzip > /home/userhost/backups/simpres-$(date +\%F).sql.gz && find /home/userhost/backups -name "simpres-*.sql.gz" -mtime +14 -delete
   ```
   (tanda `%` wajib ditulis `\%` di cron.)
3. Backup foto dan lampiran (opsional, mingguan, Minggu jam 02.00):
   ```
   0 2 * * 0 tar -czf /home/userhost/backups/storage-$(date +\%F).tar.gz -C /home/userhost/simpres/storage/app private && find /home/userhost/backups -name "storage-*.tar.gz" -mtime +30 -delete
   ```
4. Sesekali unduh isi `~/backups` ke komputer atau Google Drive. Backup yang hanya ada di server ikut hilang jika server bermasalah.

Mengembalikan backup:
```
gunzip -c ~/backups/simpres-2026-11-17.sql.gz | mysql userhost_simpres
```

---

## 11. Pengecekan setelah deploy

| Cek | Cara | Hasil yang benar |
|---|---|---|
| API tanpa token | buka `https://api.namadomain.com/api/work-location` | **401** JSON `{"message":"Anda belum login atau token tidak valid. Silakan login kembali."}`, bukan halaman HTML |
| Login API | Postman `POST https://api.namadomain.com/api/login` dengan akun admin | **200**, ada `token` |
| Admin Web | buka `https://api.namadomain.com/admin` | halaman login admin, ada gembok HTTPS |
| Debug mati | buka `https://api.namadomain.com/api/tidak-ada` | `{"message":"Data atau alamat tidak ditemukan."}`, tanpa detail file/kode |
| `.env` aman | buka `https://api.namadomain.com/.env` dan `https://api.namadomain.com/../.env` | **404** atau **403**, isi tidak tampil |
| Upload foto | dari Postman/HP kirim `POST /api/field-duties` dengan foto | **201**, file ada di `~/simpres/storage/app/private/tugas-lapangan/` |
| Cron | menu Cron Jobs | dua (atau tiga) cron terdaftar; besok pagi ada file di `~/backups` |
| Log | `tail -n 50 ~/simpres/storage/logs/laravel-*.log` | tidak ada error baru |

---

## 12. Update aplikasi (deploy berikutnya)

```
cd ~/simpres
php artisan down                      # tampilkan mode perbaikan
git pull                              # atau upload file yang berubah
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```
Jika struktur tabel SIMPRES berubah (`simpres.sql`), jalankan perintah `ALTER TABLE` yang sama lewat phpMyAdmin
sebelum `php artisan up`. Jangan import ulang `simpres.sql` di produksi karena data akan tertimpa.

---

## 13. Jika ada masalah

| Gejala | Penyebab umum | Solusi |
|---|---|---|
| Error 500 | izin folder `storage`/`bootstrap/cache`, `.env` salah | lihat `storage/logs/laravel-*.log`, jalankan langkah 7 lagi |
| `SQLSTATE[HY000] [1045] Access denied` | user/password database salah | cek `.env`, lalu `php artisan config:cache` |
| Perubahan `.env` tidak berpengaruh | config masih di-cache | `php artisan config:cache` |
| `419 Page Expired` saat login admin | cookie session tidak tersimpan | pastikan HTTPS aktif dan `SESSION_SECURE_COOKIE=true`, `APP_URL` benar |
| Upload foto gagal / 422 "gagal diunggah" | batas upload PHP terlalu kecil | naikkan `upload_max_filesize` dan `post_max_size` (langkah 0) |
| Halaman `Index of /` atau kode PHP tampil | document root salah | arahkan ke `.../simpres/public` (langkah 8) |
| Jam absen selisih 1 jam | zona waktu | sesuaikan `APP_TIMEZONE`, lalu `php artisan config:cache` |
