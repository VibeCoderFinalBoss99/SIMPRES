<p align="center">
  <img src="docs/logo.png" width="96" alt="Logo SIMPRES">
</p>

# SIMPRES – Sistem Presensi Simbang

Aplikasi presensi pegawai UPTD Puskesmas Simbang. Terdiri dari tiga bagian:

| Bagian | Teknologi | Folder |
|---|---|---|
| Aplikasi Android (pegawai) | Kotlin + XML, Navigation Component, Retrofit, Lottie | `app/` |
| REST API (server) | Laravel, Laravel Sanctum, MySQL/MariaDB | `backend/` |
| Admin Web (admin Puskesmas) | Laravel Blade, satu file CSS, tanpa npm | `backend/` (alamat `/admin`) |

Aturan utama: absensi sah otomatis jika pegawai berada maksimal 150 m dari titik Puskesmas.
Keputusan sah atau tidaknya absensi dibuat di server, bukan di HP.

## Versi aplikasi

| Versi | Minggu | Tanggal | Status | Isi utama |
|---|---|---|---|---|
| [v0.1.0](#v010--prototipe-tampilan-android) | Minggu 12 | 11 November 2026 | Selesai | Prototipe tampilan Android (29 halaman) |
| [v0.2.0](#v020--backend-admin-web-dan-integrasi-aplikasi) | Minggu 13 | 18 November 2026 | Selesai | Basis data, REST API, CRUD, validasi absensi, Admin Web, aplikasi tersambung ke server |
| [v0.3.0](#v030--pengujian-fitur-dan-perbaikan-bug) | Minggu 14 | 25 November 2026 | **Sedang berjalan** | Pengujian fitur dan perbaikan bug |
| [v1.0.0](#v100--rilis-rencana) | Minggu 15 | 2 Desember 2026 | Rencana | Rilis di hosting, APK rilis, video, laporan PjBL |

Riwayat perubahan lengkap per versi ada di [docs/CHANGELOG.md](docs/CHANGELOG.md).

---

## v0.1.0 – Prototipe tampilan Android

Minggu 12 (11 November 2026). Checkpoint: implementasi halaman Android, Splash Screen, onboarding, login, navigasi, dashboard.

| Profil | Splash | Login | Riwayat |
|---|---|---|---|
| <img src="docs/screenshots/submitted/01_profile.png" width="180" alt="Halaman profil"> | <img src="docs/screenshots/submitted/02_splash.png" width="180" alt="Halaman splash"> | <img src="docs/screenshots/submitted/03_login.png" width="180" alt="Halaman login"> | <img src="docs/screenshots/submitted/04_history.png" width="180" alt="Halaman riwayat absensi"> |

| Tentang aplikasi | Dialog keluar | Onboarding | Notifikasi |
|---|---|---|---|
| <img src="docs/screenshots/submitted/05_about.png" width="180" alt="Halaman tentang aplikasi"> | <img src="docs/screenshots/submitted/06_logout_dialog.png" width="180" alt="Dialog keluar akun"> | <img src="docs/screenshots/submitted/07_onboarding.png" width="180" alt="Halaman onboarding"> | <img src="docs/screenshots/submitted/08_notifications.png" width="180" alt="Halaman notifikasi"> |

Pratinjau layout dari Android Studio (tema terang):

| Splash | Login | Beranda | Riwayat | Profil |
|---|---|---|---|---|
| <img src="docs/screenshots/01_splash.png" width="160" alt="Splash"> | <img src="docs/screenshots/02_login.png" width="160" alt="Login"> | <img src="docs/screenshots/03_beranda.png" width="160" alt="Beranda"> | <img src="docs/screenshots/04_riwayat.png" width="160" alt="Riwayat absensi"> | <img src="docs/screenshots/05_profil.png" width="160" alt="Profil"> |

Isi versi ini: 29 layout halaman, tema terang dan gelap, font Lexend, Navigation Graph, BaseFragment dan bottom navigation,
alur check-in dengan radius 150 m (data contoh), jarak ikon dan animasi ikon ringan.

---

## v0.2.0 – Backend, Admin Web, dan integrasi aplikasi

Minggu 13 (18 November 2026). Checkpoint: implementasi fitur utama, basis data, CRUD, REST API, dan aturan validasi absensi.

### Aplikasi Android

| Onboarding | Panduan awal | Beranda | Check-in berhasil |
|---|---|---|---|
| <img src="docs/screenshots/v0.2/app_onboarding.jpg" width="180" alt="Onboarding 3 halaman"> | <img src="docs/screenshots/v0.2/app_panduan.jpg" width="180" alt="Panduan awal di Beranda"> | <img src="docs/screenshots/v0.2/app_beranda.jpg" width="180" alt="Beranda dengan data server"> | <img src="docs/screenshots/v0.2/app_checkin_berhasil.jpg" width="180" alt="Check-in berhasil"> |

| Izin & Cuti | Riwayat | Notifikasi | Profil |
|---|---|---|---|
| <img src="docs/screenshots/v0.2/app_izin.jpg" width="180" alt="Daftar izin"> | <img src="docs/screenshots/v0.2/app_riwayat.jpg" width="180" alt="Riwayat absensi"> | <img src="docs/screenshots/v0.2/app_notifikasi.jpg" width="180" alt="Notifikasi izin diterima"> | <img src="docs/screenshots/v0.2/app_profil.jpg" width="180" alt="Profil pegawai"> |

### Logo, mode terang, dan mode gelap

Logo aplikasi: pin lokasi dengan tanda centang dan cincin radius emas, dipakai sebagai ikon aplikasi, di halaman Login, dan Tentang Aplikasi.
Tampilan bisa dipilih di **Profil > Pengaturan & tampilan > Mode terang / gelap** (Ikuti sistem, Terang, atau Gelap).

| Beranda – terang | Beranda – gelap | Pilihan tampilan | Logo di Login | Tentang aplikasi |
|---|---|---|---|---|
| <img src="docs/screenshots/v0.2/app_beranda_terang.jpg" width="150" alt="Beranda mode terang"> | <img src="docs/screenshots/v0.2/app_beranda_gelap.jpg" width="150" alt="Beranda mode gelap"> | <img src="docs/screenshots/v0.2/app_pengaturan_tema.jpg" width="150" alt="Pengaturan mode tampilan"> | <img src="docs/screenshots/v0.2/app_login_logo.jpg" width="150" alt="Logo di halaman login"> | <img src="docs/screenshots/v0.2/app_tentang_logo.jpg" width="150" alt="Logo di halaman tentang aplikasi"> |

### Admin Web

| Dashboard | Review & Persetujuan | Roster |
|---|---|---|
| <img src="docs/screenshots/v0.2/web_dashboard.jpg" width="300" alt="Dashboard admin"> | <img src="docs/screenshots/v0.2/web_review.jpg" width="300" alt="Review dan persetujuan"> | <img src="docs/screenshots/v0.2/web_roster.jpg" width="300" alt="Roster mingguan"> |

| Rekap & Ekspor | Audit Log | Konfigurasi |
|---|---|---|
| <img src="docs/screenshots/v0.2/web_rekap.jpg" width="300" alt="Rekap absensi"> | <img src="docs/screenshots/v0.2/web_audit.jpg" width="300" alt="Audit log"> | <img src="docs/screenshots/v0.2/web_konfigurasi.jpg" width="300" alt="Konfigurasi"> |

### ERD database

10 tabel MySQL. Garis memakai notasi crow's foot: `||` tepat satu, `o|` nol atau satu, `o<` nol atau banyak.

<img src="docs/diagram/erd_simpres.png" alt="ERD database SIMPRES">

### Flowchart

| Absensi (check-in dan check-out) | Pengajuan izin, koreksi, dan tugas lapangan |
|---|---|
| <img src="docs/diagram/flowchart_absensi.png" width="420" alt="Flowchart absensi"> | <img src="docs/diagram/flowchart_izin.png" width="420" alt="Flowchart pengajuan izin"> |

### Cuplikan kode

Validasi absensi di server (`backend/app/Services/AttendanceValidator.php`): jarak dihitung dengan rumus Haversine,
lalu dicek lokasi palsu, akurasi GPS, dan radius lokasi kerja.

<img src="docs/screenshots/v0.2/code_validator.png" alt="Kode validasi absensi">

Check-in di aplikasi (`ui/attendance/AttendanceScreens.kt`): ambil GPS, kirim ke server, lalu pindah halaman sesuai hasil.
Kalau tidak ada internet, absensi disimpan ke antrean offline.

<img src="docs/screenshots/v0.2/code_checkin.png" alt="Kode check-in Android">

Daftar endpoint REST API (`backend/routes/api.php`), semua kecuali login memakai token Sanctum.

<img src="docs/screenshots/v0.2/code_routes.png" width="640" alt="Kode route API">

### Fitur yang selesai

| Agenda | Hasil |
|---|---|
| Basis data | 10 tabel MySQL (ERD di atas, skrip `docs/database/simpres.sql`) |
| REST API | 32 endpoint, login token Sanctum, dokumentasi di `backend/API.md` |
| Aturan validasi absensi | Jarak Haversine maks. 150 m, akurasi GPS, tolak lokasi palsu, satu check-in per hari, status terlambat, izin diterima |
| CRUD | Izin dan cuti, koreksi absensi, tugas lapangan dengan foto, pegawai, shift, jadwal |
| Admin Web | Dashboard, Review & Persetujuan, Data Pegawai, Lokasi Kerja, Roster, Rekap & Ekspor CSV, Audit Log, Konfigurasi |
| Aplikasi Android | Login, check-in GPS, check-out geser, riwayat, jadwal, izin, koreksi, tugas lapangan, notifikasi, profil, antrean offline |
| Tampilan | Logo dan ikon aplikasi, pilihan mode terang/gelap, intro animasi Lottie, onboarding 3 halaman, panduan awal (coach mark) saat pertama masuk Beranda |

---

## v0.3.0 – Pengujian fitur dan perbaikan bug

Minggu 14 (25 November 2026). Minggu ini berisi **pengujian semua fitur** aplikasi Android dan Admin Web untuk setiap
peran, lalu **memperbaiki bug** yang ditemukan.

### Skema pengujian

Semua fitur per peran (admin, pegawai non-shift, pegawai shift, petugas lapangan), cara pakai, dan langkah pengujiannya
ada di [docs/SKEMA_PENGUJIAN.md](docs/SKEMA_PENGUJIAN.md): 130 langkah uji untuk Admin Web, aplikasi, mode offline, notifikasi,
alur aplikasi ke website, keamanan, dan test otomatis. Hasil pengujian per pengguna ada di
[docs/HASIL_PENGUJIAN.md](docs/HASIL_PENGUJIAN.md).

### Unduh aplikasi

<img src="docs/qr_download_simpres.png" width="200" alt="QR unduh APK SIMPRES">

Scan QR atau buka [halaman rilis](https://github.com/VibeCoderFinalBoss99/SIMPRES/releases/latest) untuk mengunduh `SIMPRES.apk`.

### Ditambah minggu ini

| Fitur | Keterangan |
|---|---|
| Lupa kata sandi | Tautan atur ulang dikirim ke email akun yang dicari lewat NIP atau email, halaman web untuk membuat sandi baru |
| Peta asli di check-in | Peta OpenStreetMap dengan radius 150 m, titik Puskesmas, dan posisi pegawai. Bisa digeser dan di-zoom |
| Cuti di aplikasi | Pilihan Cuti di formulir izin, dengan rentang tanggal dan lampiran opsional |
| Menu Koreksi Absensi | Halaman koreksi absensi bisa dibuka dari Profil |
| Notifikasi HP (Firebase) | Keputusan admin muncul di HP 1–2 detik kemudian, walaupun aplikasi ditutup |
| Sinkronisasi otomatis | Antrean absensi offline terkirim di latar belakang (WorkManager) |
| Video pengujian | Satu video per peran di `docs/videos/` dengan keterangan langkah dan hasil |
| APK rilis | APK bertanda tangan di GitHub Releases dan QR unduhan |

### Notifikasi HP (Firebase Cloud Messaging)

Saat admin menerima atau menolak izin, koreksi, atau tugas lapangan, pegawai langsung mendapat notifikasi di HP walaupun
aplikasi sedang ditutup. Dalam pengujian, notifikasi muncul **1–2 detik** setelah admin menekan Terima atau Tolak.
Notifikasi yang diketuk langsung membuka halaman Notifikasi di aplikasi.

| Notifikasi di HP | Percobaan Beberapa Notifikasi|
|---|---|
| <img src="docs/pengujian/notifikasi/N-4.jpg" width="200" alt="Notifikasi keputusan admin di HP"> | <img src="docs/pengujian/notifikasi/N-5.jpg" width="200" alt="Halaman notifikasi"> |

Cara kerjanya:
1. Saat pegawai login, aplikasi mengirim token Firebase HP-nya ke server (`POST /api/device-token`, tabel `device_tokens`).
2. Admin memutuskan pengajuan. Server menyimpan notifikasi (tabel `user_notifications`), lalu `PushNotifier`
   (`backend/app/Services/PushNotifier.php`) mengirimnya ke semua HP pegawai itu lewat FCM HTTP v1.
3. Di HP, `PushService` (`app/.../data/Push.kt`) menerima pesan dan menampilkannya sebagai notifikasi Android
   (kanal "Keputusan admin").
4. **Cadangan:** `SyncWorker` (WorkManager, `app/.../data/Background.kt`) tetap mengecek `GET /api/notifications` satu
   menit setelah aplikasi ditutup, lalu setiap 15 menit. Jika Firebase gagal, notifikasi tetap datang dalam beberapa menit.
   Worker yang sama mengirim antrean absensi offline di latar belakang.
5. Saat pegawai keluar akun, token HP dihapus sehingga HP itu tidak menerima notifikasi akun tadi lagi.

Syarat di HP: izin notifikasi (diminta saat pertama membuka Beranda di Android 13 ke atas) dan Google Play Services.

**File rahasia Firebase tidak ada di repo** (repo ini publik). Untuk menjalankan di komputer lain:
- `app/google-services.json`: unduh dari Firebase Console > Project settings > General > aplikasi Android `com.simpres.app`.
  Tanpa file ini aplikasi tetap bisa di-build, notifikasi memakai pengecekan berkala.
- `backend/storage/app/private/firebase.json`: kunci akun layanan dari Firebase Console > Project settings >
  Service accounts > Generate new private key. Lokasi lain bisa diatur lewat `FCM_CREDENTIALS` di `.env`.

### Pengujian per peran

Setiap peran diuji terpisah dengan server dan data asli, direkam video, dan setiap langkah difoto dari layar aplikasi
atau Admin Web. Semua langkah lulus. Rincian hasil per langkah ada di [docs/HASIL_PENGUJIAN.md](docs/HASIL_PENGUJIAN.md).

#### Admin (Admin Web)

Admin Puskesmas memakai Admin Web di browser untuk mengelola pegawai, lokasi, dan roster, serta memutuskan pengajuan yang dikirim pegawai dari aplikasi.

**Fitur yang diuji:**

- Login admin (sandi salah dan akun pegawai ditolak), keluar
- Dashboard absensi hari ini
- Tambah pegawai, email ganda ditolak, cari pegawai, nonaktifkan akun
- Lokasi kerja dan radius 150 m
- Roster jadwal shift
- Terima dan tolak izin, koreksi, tugas lapangan (dengan foto dan GPS)
- Rekap bulanan dan ekspor CSV
- Audit log
- Konfigurasi sistem

▶️ Video pengujian: [4_admin_web.mp4](docs/videos/4_admin_web.mp4)  

| **A1-1** Buka /admin tanpa login diarahkan ke halaman login | **A1-2** Sandi salah ditolak dengan pesan | **A1-3** Akun pegawai tidak bisa masuk Admin Web |
|---|---|---|
| <img src="docs/pengujian/admin/A1-1.jpg" width="300" alt="A1-1"> | <img src="docs/pengujian/admin/A1-2.jpg" width="300" alt="A1-2"> | <img src="docs/pengujian/admin/A1-3.jpg" width="300" alt="A1-3"> |

| **A2-2** Dashboard: absensi hari ini dari aplikasi tampil | **A3-1** Pegawai baru tersimpan dan tampil di daftar | **A3-2** Email ganda ditolak dengan pesan error |
|---|---|---|
| <img src="docs/pengujian/admin/A2-2.jpg" width="300" alt="A2-2"> | <img src="docs/pengujian/admin/A3-1.jpg" width="300" alt="A3-1"> | <img src="docs/pengujian/admin/A3-2.jpg" width="300" alt="A3-2"> |

| **A3-4** Pencarian hanya menampilkan pegawai yang dicari | **A3-6** Akun nonaktif ditolak saat login di aplikasi: Akun Anda nonaktif. Hubungi admin Puskesmas. | **A4-1** Titik Puskesmas dan radius 150 m tampil dengan peta |
|---|---|---|
| <img src="docs/pengujian/admin/A3-4.jpg" width="300" alt="A3-4"> | <img src="docs/pengujian/admin/A3-6.jpg" width="300" alt="A3-6"> | <img src="docs/pengujian/admin/A4-1.jpg" width="300" alt="A4-1"> |

| **A5-3** Roster: pegawai non-shift Pagi, pegawai shift bergantian | **A6-1** Daftar pengajuan dari aplikasi dengan status Menunggu Konfirmasi | **A6-3** Izin berstatus Diterima, catatan tersimpan |
|---|---|---|
| <img src="docs/pengujian/admin/A5-3.jpg" width="300" alt="A5-3"> | <img src="docs/pengujian/admin/A6-1.jpg" width="300" alt="A6-1"> | <img src="docs/pengujian/admin/A6-3.jpg" width="300" alt="A6-3"> |

| **A6-4** Koreksi berstatus Ditolak dengan catatan | **A6-5a** Detail tugas lapangan dengan foto dan koordinat GPS | **A6-5** Tugas lapangan berstatus Diterima |
|---|---|---|
| <img src="docs/pengujian/admin/A6-4.jpg" width="300" alt="A6-4"> | <img src="docs/pengujian/admin/A6-5a.jpg" width="300" alt="A6-5a"> | <img src="docs/pengujian/admin/A6-5.jpg" width="300" alt="A6-5"> |

| **A6-6** Pengajuan yang sudah diputuskan tidak punya tombol Terima/Tolak | **A7-1** Rekap per pegawai tampil | **A8-1** Keputusan tadi tercatat di audit log |
|---|---|---|
| <img src="docs/pengujian/admin/A6-6.jpg" width="300" alt="A6-6"> | <img src="docs/pengujian/admin/A7-1.jpg" width="300" alt="A7-1"> | <img src="docs/pengujian/admin/A8-1.jpg" width="300" alt="A8-1"> |

| **A9-1** Pengaturan sistem tampil per kelompok | **A1-5** Setelah keluar, Dashboard tidak bisa dibuka lagi |
|---|---|
| <img src="docs/pengujian/admin/A9-1.jpg" width="300" alt="A9-1"> | <img src="docs/pengujian/admin/A1-5.jpg" width="300" alt="A1-5"> |

Dicek langsung ke server (tanpa layar): **A3-1b** Akun baru bisa login di aplikasi (API); **A7-3** File CSV rekap terunduh dan berisi data pegawai.

#### Pegawai non-shift (aplikasi Android)

Pegawai dengan jam kerja tetap, shift Pagi 07.30–14.00 setiap Senin–Sabtu. Pengujian dimulai dari aplikasi yang baru dipasang.

**Fitur yang diuji:**

- Intro, onboarding, izin notifikasi, dan panduan awal
- Login (sandi salah ditolak)
- Jadwal kerja non-shift
- Check-in GPS di Puskesmas, check-in ganda ditolak
- Riwayat absensi
- Izin keluar jam kerja (tanpa alasan ditolak, lalu terkirim)
- Check-out dengan slider
- Notifikasi keputusan admin
- Profil dan menu Koreksi Absensi
- Keluar dari akun

▶️ Video pengujian: [1_pegawai_nonshift.mp4](docs/videos/1_pegawai_nonshift.mp4)  

| **B-1** Intro dan onboarding tampil | **B-4** Login sandi salah ditolak | **N-0** Izin notifikasi diminta setelah login | **B-7** Panduan awal muncul pertama kali |
|---|---|---|---|
| <img src="docs/pengujian/pegawai_nonshift/B-1.jpg" width="170" alt="B-1"> | <img src="docs/pengujian/pegawai_nonshift/B-4.jpg" width="170" alt="B-4"> | <img src="docs/pengujian/pegawai_nonshift/N-0.jpg" width="170" alt="N-0"> | <img src="docs/pengujian/pegawai_nonshift/B-7.jpg" width="170" alt="B-7"> |

| **C1-0** Beranda: shift Pagi, belum check-in | **C1-1** Jadwal non-shift: Pagi setiap hari kerja, Minggu libur | **C2-3** Check-in di dalam radius diterima, jam dan jarak tercatat | **C2-5a** Beranda: jam check-in terisi, slider check-out muncul |
|---|---|---|---|
| <img src="docs/pengujian/pegawai_nonshift/C1-0.jpg" width="170" alt="C1-0"> | <img src="docs/pengujian/pegawai_nonshift/C1-1.jpg" width="170" alt="C1-1"> | <img src="docs/pengujian/pegawai_nonshift/C2-3.jpg" width="170" alt="C2-3"> | <img src="docs/pengujian/pegawai_nonshift/C2-5a.jpg" width="170" alt="C2-5a"> |

| **C2-4** Check-in kedua ditolak | **C4-1** Riwayat menampilkan absensi hari ini | **C3-0** Daftar izin tampil (izin sakit diterima) | **C3-4b** Tanpa alasan tidak terkirim, tetap di formulir |
|---|---|---|---|
| <img src="docs/pengujian/pegawai_nonshift/C2-4.jpg" width="170" alt="C2-4"> | <img src="docs/pengujian/pegawai_nonshift/C4-1.jpg" width="170" alt="C4-1"> | <img src="docs/pengujian/pegawai_nonshift/C3-0.jpg" width="170" alt="C3-0"> | <img src="docs/pengujian/pegawai_nonshift/C3-4b.jpg" width="170" alt="C3-4b"> |

| **C3-4** Izin keluar terkirim, status Menunggu Konfirmasi | **C2-5** Check-out diterima | **B-13** Notifikasi keputusan admin tampil | **B-15** Profil sesuai data admin (nama, NIP, unit) |
|---|---|---|---|
| <img src="docs/pengujian/pegawai_nonshift/C3-4.jpg" width="170" alt="C3-4"> | <img src="docs/pengujian/pegawai_nonshift/C2-5.jpg" width="170" alt="C2-5"> | <img src="docs/pengujian/pegawai_nonshift/B-13.jpg" width="170" alt="B-13"> | <img src="docs/pengujian/pegawai_nonshift/B-15.jpg" width="170" alt="B-15"> |

| **B-15b** Menu Koreksi Absensi ada di Profil | **B-19** Kembali ke halaman login |
|---|---|
| <img src="docs/pengujian/pegawai_nonshift/B-15b.jpg" width="170" alt="B-15b"> | <img src="docs/pengujian/pegawai_nonshift/B-19.jpg" width="170" alt="B-19"> |

#### Pegawai shift (aplikasi Android)

Pegawai dengan jam kerja bergantian (Pagi, Siang, Malam). Hari pengujian: shift Malam 21.00–07.00.

**Fitur yang diuji:**

- Beranda dengan shift Malam
- Jadwal shift bergantian
- Check-in dari luar radius ditolak, peta bisa di-zoom dan digeser
- Check-in tepat waktu
- Koreksi absensi
- Pengajuan cuti
- Check-out saat tidak ada internet, lalu terkirim otomatis
- Keluar dari akun

▶️ Video pengujian: [2_pegawai_shift.mp4](docs/videos/2_pegawai_shift.mp4)  

| **D2-1** Beranda: shift hari ini Malam 21.00-07.00, belum check-in | **D1-1** Jadwal shift: Pagi dan Siang bergantian, Minggu libur | **D2-4** Ditolak: di luar radius 150 m, jarak tampil di peta | **P8** Peta asli bisa di-zoom dan digeser |
|---|---|---|---|
| <img src="docs/pengujian/pegawai_shift/D2-1.jpg" width="170" alt="D2-1"> | <img src="docs/pengujian/pegawai_shift/D1-1.jpg" width="170" alt="D1-1"> | <img src="docs/pengujian/pegawai_shift/D2-4.jpg" width="170" alt="D2-4"> | <img src="docs/pengujian/pegawai_shift/P8.jpg" width="170" alt="P8"> |

| **D2-2** Check-in diterima, tepat waktu sebelum shift Malam 21.00 | **D3-0** Halaman Koreksi Absensi bisa dibuka | **D3-1** Koreksi terkirim, kembali ke Beranda | **D4-1** Cuti terkirim, status Menunggu Konfirmasi |
|---|---|---|---|
| <img src="docs/pengujian/pegawai_shift/D2-2.jpg" width="170" alt="D2-2"> | <img src="docs/pengujian/pegawai_shift/D3-0.jpg" width="170" alt="D3-0"> | <img src="docs/pengujian/pegawai_shift/D3-1.jpg" width="170" alt="D3-1"> | <img src="docs/pengujian/pegawai_shift/D4-1.jpg" width="170" alt="D4-1"> |

| **D4-1b** Cuti muncul di daftar izin | **F-1** Check-out disimpan di perangkat (offline) | **F-3** Antrean terkirim otomatis, check-out tercatat | **B-19** Kembali ke halaman login |
|---|---|---|---|
| <img src="docs/pengujian/pegawai_shift/D4-1b.jpg" width="170" alt="D4-1b"> | <img src="docs/pengujian/pegawai_shift/F-1.jpg" width="170" alt="F-1"> | <img src="docs/pengujian/pegawai_shift/F-3.jpg" width="170" alt="F-3"> | <img src="docs/pengujian/pegawai_shift/B-19.jpg" width="170" alt="B-19"> |

#### Petugas lapangan (aplikasi Android)

Pegawai yang sering bertugas di luar gedung (posyandu, kunjungan rumah). Bukti kerja di luar memakai Tugas Lapangan.

**Fitur yang diuji:**

- Check-in dari posyandu ditolak dan ditawari Mode Tugas Lapangan
- Formulir tugas lapangan dengan GPS asli
- Kirim tanpa nama lokasi ditolak
- Izin kamera dan foto bukti kegiatan
- Tugas lapangan tampil di Riwayat > Lapangan
- Check-in setelah kembali ke Puskesmas
- Keluar dari akun

▶️ Video pengujian: [3_petugas_lapangan.mp4](docs/videos/3_petugas_lapangan.mp4)  

| **E1-1** Login berhasil, profil dan shift Pagi sesuai data admin | **E3-1** Check-in ditolak di luar radius, ditawari Mode Tugas Lapangan | **E2-0** Formulir tugas lapangan memakai GPS asli (bukan lokasi contoh) | **E2-3** Tanpa nama lokasi tidak terkirim, tetap di formulir |
|---|---|---|---|
| <img src="docs/pengujian/petugas_lapangan/E1-1.jpg" width="170" alt="E1-1"> | <img src="docs/pengujian/petugas_lapangan/E3-1.jpg" width="170" alt="E3-1"> | <img src="docs/pengujian/petugas_lapangan/E2-0.jpg" width="170" alt="E2-0"> | <img src="docs/pengujian/petugas_lapangan/E2-3.jpg" width="170" alt="E2-3"> |

| **E2-2** Izin kamera diminta dulu | **E2-2b** Foto kegiatan dari kamera terpasang | **E2-1** Tugas lapangan terkirim, kembali ke Beranda | **E2-1b** Tugas lapangan baru tampil dengan status Menunggu Konfirmasi |
|---|---|---|---|
| <img src="docs/pengujian/petugas_lapangan/E2-2.jpg" width="170" alt="E2-2"> | <img src="docs/pengujian/petugas_lapangan/E2-2b.jpg" width="170" alt="E2-2b"> | <img src="docs/pengujian/petugas_lapangan/E2-1.jpg" width="170" alt="E2-1"> | <img src="docs/pengujian/petugas_lapangan/E2-1b.jpg" width="170" alt="E2-1b"> |

| **E1-2** Check-in di Puskesmas diterima | **B-19** Kembali ke halaman login |
|---|---|
| <img src="docs/pengujian/petugas_lapangan/E1-2.jpg" width="170" alt="E1-2"> | <img src="docs/pengujian/petugas_lapangan/B-19.jpg" width="170" alt="B-19"> |

#### Notifikasi HP dan sinkronisasi otomatis

Keputusan admin langsung muncul sebagai notifikasi di HP walaupun aplikasi ditutup (Firebase Cloud Messaging), dengan cadangan pengecekan berkala. Absensi yang tersimpan saat offline terkirim di latar belakang.

**Fitur yang diuji:**

- Notifikasi seketika setelah admin memutuskan (Firebase)
- Notifikasi diketuk membuka halaman Notifikasi
- Check-out saat tidak ada internet disimpan di antrean HP
- Antrean terkirim di latar belakang tanpa membuka aplikasi

▶️ Video notifikasi seketika (Firebase): [7_notifikasi_firebase.mp4](docs/videos/7_notifikasi_firebase.mp4)  
▶️ Video notifikasi cadangan dan sinkronisasi: [6_notifikasi_dan_sinkronisasi.mp4](docs/videos/6_notifikasi_dan_sinkronisasi.mp4)  

| **N-4** Notifikasi muncul 3 detik setelah admin memutuskan | **N-5** Halaman Notifikasi terbuka | **N-1** Notifikasi 'Izin ditolak' beserta catatan admin muncul di HP | **N-2** Notifikasi diketuk langsung membuka halaman Notifikasi |
|---|---|---|---|
| <img src="docs/pengujian/notifikasi/N-4.jpg" width="170" alt="N-4"> | <img src="docs/pengujian/notifikasi/N-5.jpg" width="170" alt="N-5"> | <img src="docs/pengujian/notifikasi/N-1.jpg" width="170" alt="N-1"> | <img src="docs/pengujian/notifikasi/N-2.jpg" width="170" alt="N-2"> |

| **F-1** Check-out disimpan di antrean HP | **F-3** Beranda: jam check-out terisi, status Semua terkirim |
|---|---|
| <img src="docs/pengujian/notifikasi/F-1.jpg" width="170" alt="F-1"> | <img src="docs/pengujian/notifikasi/F-3.jpg" width="170" alt="F-3"> |

Dicek langsung ke server (tanpa layar): **F-4** Antrean terkirim di latar belakang tanpa membuka aplikasi.

#### Integrasi aplikasi dan Admin Web

Keputusan admin di Admin Web dicek dari HP setiap pegawai.

**Fitur yang diuji:**

- Notifikasi dan status izin pegawai non-shift
- Notifikasi koreksi ditolak dan cuti diterima pegawai shift
- Status tugas lapangan petugas lapangan

▶️ Video pengujian: [5_integrasi_aplikasi_web.mp4](docs/videos/5_integrasi_aplikasi_web.mp4)  

| **G-7** Pegawai non-shift menerima notifikasi izin keluar diterima beserta catatan admin | **G-7b** Status izin keluar pegawai non-shift berubah menjadi Diterima | **D3-4** Pegawai shift menerima notifikasi koreksi ditolak dengan catatan admin | **D4-2** Pegawai shift menerima notifikasi cuti diterima |
|---|---|---|---|
| <img src="docs/pengujian/integrasi/G-7.jpg" width="170" alt="G-7"> | <img src="docs/pengujian/integrasi/G-7b.jpg" width="170" alt="G-7b"> | <img src="docs/pengujian/integrasi/D3-4.jpg" width="170" alt="D3-4"> | <img src="docs/pengujian/integrasi/D4-2.jpg" width="170" alt="D4-2"> |

| **E2-4** Tugas lapangan petugas berstatus Diterima |
|---|
| <img src="docs/pengujian/integrasi/E2-4.jpg" width="170" alt="E2-4"> |

### Bug yang ditemukan dan diperbaiki

17 bug ditemukan selama pengujian. Penjelasan lengkap tiap bug (gejala, bagian kode yang salah, penyebab, cara
perbaikan, dan screenshot sebelum/sesudah) ada di [docs/BUG_DAN_PERBAIKAN.md](docs/BUG_DAN_PERBAIKAN.md).

| No | Bug | Perbaikan |
|---|---|---|
| B01 | Server tidak bisa dihubungi dari HP asli | Alamat API untuk HP asli dan APK rilis dipisah dari alamat emulator |
| B02 | Slider check-out muncul sebentar di Beranda | Slider hanya tampil setelah check-in |
| B03 | Tulisan contoh desain muncul sebelum data asli | Beranda memakai data terakhir atau tanda kosong |
| B04 | Lupa kata sandi tidak mengirim apa pun, email yang tampil email contoh | API `POST /api/forgot-password`, email, dan halaman sandi baru |
| B05 | Email lupa sandi sebagian berbahasa Inggris | Terjemahan di `lang/id.json` |
| B06 | Tautan dan CSS Admin Web rusak saat dibuka dari luar laptop | Laravel memakai HTTPS di belakang proxy |
| B07 | Peta check-in hanya gambar hiasan | Peta asli OpenStreetMap |
| B08 | Peta tidak bisa digeser, zoom selalu ke tengah | Geser satu jari, zoom tetap di posisi geseran |
| B09 | Peta kosong sebagian di jaringan lambat | Unduh paralel, unduh utuh, coba ulang |
| B10 | Formulir izin tidak punya pilihan Cuti | Pilihan Cuti ditambahkan |
| B11 | Layar "Pengajuan terkirim" menampilkan data contoh | Menampilkan data pengajuan yang baru dikirim |
| B12 | Formulir tugas lapangan menampilkan lokasi contoh | GPS asli, nama lokasi wajib diisi |
| B13 | Absensi dari antrean offline tercatat `online` | Aplikasi mengirim `source=offline` |
| B14 | Halaman Koreksi Absensi tidak bisa dibuka | Menu Koreksi Absensi di Profil |
| B15 | Aplikasi crash saat ambil foto tugas lapangan | Izin kamera diminta sebelum kamera dibuka |
| B16 | Server mati sendiri | Server dijalankan di terminal sendiri |
| B17 | Keputusan admin tidak muncul sebagai notifikasi di HP | Notifikasi seketika lewat Firebase, cadangan WorkManager |

| Contoh bug | Sebelum | Sesudah |
|---|---|---|
| B04 Lupa sandi | <img src="docs/bug/lupa_sandi_sebelum.jpg" width="150"> | <img src="docs/screenshots/v0.3/app_cek_email.jpg" width="150"> |
| B07 Peta | <img src="docs/bug/peta_sebelum.jpg" width="150"> | <img src="docs/screenshots/v0.3/app_luar_radius.jpg" width="150"> |
| B11 Pengajuan terkirim | <img src="docs/bug/terkirim_sebelum.jpg" width="150"> | <img src="docs/screenshots/v0.3/app_izin_terkirim.jpg" width="150"> |
| B14 Menu koreksi | <img src="docs/bug/koreksi_sebelum.jpg" width="150"> | <img src="docs/bug/koreksi_sesudah.jpg" width="150"> |

### Screenshot aplikasi Android

| Onboarding | Panduan awal | Beranda | Peta check-in |
|---|---|---|---|
| <img src="docs/screenshots/v0.3/app_onboarding.jpg" width="180" alt="Onboarding"> | <img src="docs/screenshots/v0.3/app_panduan.jpg" width="180" alt="Panduan awal"> | <img src="docs/screenshots/v0.3/app_beranda.jpg" width="180" alt="Beranda sebelum check-in"> | <img src="docs/screenshots/v0.3/app_checkin_peta.jpg" width="180" alt="Peta check-in"> |

| Check-in berhasil | Geser check-out | Di luar radius | Tersimpan offline |
|---|---|---|---|
| <img src="docs/screenshots/v0.3/app_checkin_berhasil.jpg" width="180" alt="Check-in berhasil"> | <img src="docs/screenshots/v0.3/app_checkout.jpg" width="180" alt="Beranda dengan slider check-out"> | <img src="docs/screenshots/v0.3/app_luar_radius.jpg" width="180" alt="Di luar radius"> | <img src="docs/screenshots/v0.3/app_offline.jpg" width="180" alt="Absensi tersimpan offline"> |

| Riwayat | Jadwal non-shift | Jadwal shift | Notifikasi |
|---|---|---|---|
| <img src="docs/screenshots/v0.3/app_riwayat.jpg" width="180" alt="Riwayat absensi"> | <img src="docs/screenshots/v0.3/app_jadwal_nonshift.jpg" width="180" alt="Jadwal pegawai non-shift"> | <img src="docs/screenshots/v0.3/app_jadwal_shift.jpg" width="180" alt="Jadwal pegawai shift"> | <img src="docs/screenshots/v0.3/app_notifikasi.jpg" width="180" alt="Notifikasi izin diterima"> |

| Daftar izin | Formulir cuti | Pengajuan terkirim | Tugas lapangan |
|---|---|---|---|
| <img src="docs/screenshots/v0.3/app_izin.jpg" width="180" alt="Daftar izin"> | <img src="docs/screenshots/v0.3/app_izin_cuti.jpg" width="180" alt="Formulir cuti"> | <img src="docs/screenshots/v0.3/app_izin_terkirim.jpg" width="180" alt="Pengajuan terkirim"> | <img src="docs/screenshots/v0.3/app_tugas_lapangan.jpg" width="180" alt="Formulir tugas lapangan"> |

| Riwayat tugas lapangan | Lupa kata sandi | Cek email | Profil |
|---|---|---|---|
| <img src="docs/screenshots/v0.3/app_tugas_lapangan_riwayat.jpg" width="180" alt="Riwayat tugas lapangan"> | <img src="docs/screenshots/v0.3/app_lupa_sandi.jpg" width="180" alt="Lupa kata sandi"> | <img src="docs/screenshots/v0.3/app_cek_email.jpg" width="180" alt="Cek email"> | <img src="docs/screenshots/v0.3/app_profil.jpg" width="180" alt="Profil"> |

### Screenshot Admin Web

| Dashboard | Data Pegawai | Lokasi Kerja |
|---|---|---|
| <img src="docs/screenshots/v0.3/web_dashboard.jpg" width="300" alt="Dashboard"> | <img src="docs/screenshots/v0.3/web_pegawai.jpg" width="300" alt="Data pegawai"> | <img src="docs/screenshots/v0.3/web_lokasi.jpg" width="300" alt="Lokasi kerja"> |

| Roster | Review izin | Review tugas lapangan |
|---|---|---|
| <img src="docs/screenshots/v0.3/web_roster.jpg" width="300" alt="Roster"> | <img src="docs/screenshots/v0.3/web_review.jpg" width="300" alt="Review izin"> | <img src="docs/screenshots/v0.3/web_review_tugas.jpg" width="300" alt="Review tugas lapangan"> |

| Rekap & Ekspor | Audit Log | Konfigurasi |
|---|---|---|
| <img src="docs/screenshots/v0.3/web_rekap.jpg" width="300" alt="Rekap"> | <img src="docs/screenshots/v0.3/web_audit.jpg" width="300" alt="Audit log"> | <img src="docs/screenshots/v0.3/web_konfigurasi.jpg" width="300" alt="Konfigurasi"> |

| Atur ulang kata sandi |
|---|
| <img src="docs/screenshots/v0.3/web_reset_sandi.jpg" width="300" alt="Halaman atur ulang kata sandi"> |

### Belum dikerjakan (lanjut ke v1.0.0)

- [ ] Kamera CameraX, token login di Android Keystore, dan penguncian perangkat terdaftar.
- [x] Hosting permanen dengan domain dan HTTPS (selesai: https://kelompok4.tkj3a.my.id).
- [ ] Jam absensi offline masih memakai jam saat data terkirim, belum jam saat disimpan di HP.
- [ ] Pengujian bersama pegawai Puskesmas dan evaluasi mitra.

## v1.0.0 – Rilis (rencana)

Minggu 15 (2 Desember 2026). Checkpoint: fitur utama final, CRUD, basis data, REST API, pengujian sistem, video, laporan PjBL.

- [x] Backend dan Admin Web dipasang di hosting dengan HTTPS (panduan di `backend/DEPLOY.md`).
- [x] APK rilis untuk pegawai (GitHub Releases, terhubung ke server hosting).
- [ ] Akun contoh diganti akun asli.
- [ ] Video demonstrasi dan laporan akhir PjBL.

---

## Menjalankan proyek

### Backend dan Admin Web
1. Jalankan MySQL (Laragon), lalu buat database dari `docs/database/simpres.sql`.
2. Di folder `backend`: `composer install`, salin `.env.example` menjadi `.env`, isi koneksi database, lalu `php artisan key:generate` dan `php artisan migrate`.
3. Jalankan `php artisan serve`. Admin Web ada di `http://127.0.0.1:8000/admin`.
4. Test otomatis: `php artisan test` (database `simpres_test`, dibuat dengan `tests/buat-database-test.ps1`).

### Aplikasi Android
1. Android Studio → **Open** → folder `SIMPRES`, tunggu Gradle sync.
2. Emulator memakai server laptop lewat `http://10.0.2.2:8000/api/`. Untuk HP asli atau hosting, isi
   `simpres.apiUrl=https://alamat-server/api/` di `local.properties`.
3. Jalankan konfigurasi `app` (Android 8.0 ke atas).

Akun contoh dari data awal (ganti sebelum online): `admin@simpres.test`, `siti@simpres.test`, `budi@simpres.test`, sandi `simpres123`.

## Struktur kode Android

| Folder | Isi |
|---|---|
| `data/` | `Api.kt` (Retrofit dan model), `Session.kt` (token dan antrean offline), `Locator.kt` (GPS), `Format.kt` (format tanggal dan status) |
| `ui/BaseFragment.kt` | Navigasi bersama, bottom navigation, cek koneksi, coroutine per halaman |
| `ui/<fitur>/*Screens.kt` | Satu Fragment per halaman: auth, home, attendance, leave, settings |
| `ui/CoachMarks.kt` | Panduan awal yang menyorot tombol penting, tampil sekali |
| `res/raw/` | Animasi Lottie intro dan onboarding |
| `res/layout` | 29 layout halaman + komponen baris daftar |

## Daftar halaman dan layout Android

| Halaman | Layout | | Halaman | Layout |
|---|---|---|---|---|
| 01 Splash | `fragment_splash` | | 16 Profil | `fragment_profile` (+ `dialog_logout`) |
| 02 Onboarding | `fragment_onboarding` | | 17 Konfigurasi | `fragment_config` |
| 03 Login | `fragment_login` | | 18 Lupa Kata Sandi | `fragment_forgot_password` |
| 04 Beranda | `fragment_home` | | 19 Tautan Terkirim | `fragment_reset_sent` |
| 05 Check-in | `fragment_checkin` | | 20 Check-out Berhasil | `fragment_checkout_success` |
| 06 Di Luar Radius | `fragment_outside_radius` | | 21 Izin & Cuti | `fragment_leave_list` |
| 07 Foto Wajah (opsional) | `fragment_face_photo` | | 22 + 23 Ajukan Izin | `fragment_leave_form` |
| 08 Check-in Berhasil | `fragment_checkin_success` | | 24 Izin Terkirim | `fragment_leave_sent` |
| 09 Tersimpan Offline | `fragment_offline_saved` | | 25 Detail Izin – Diterima | `fragment_leave_accepted` |
| 10 Tugas Lapangan | `fragment_field_duty` | | 26 Detail Izin – Ditolak | `fragment_leave_rejected` |
| 11 Kamera Bukti | `fragment_camera` | | 27 Notifikasi | `fragment_notification` |
| 12 Riwayat | `fragment_history` | | 28 Antrean Sinkronisasi | `fragment_sync` |
| 13 Detail Absensi | `fragment_attendance_detail` | | 29 Izin & Privasi | `fragment_privacy` |
| 14 Jadwal Kerja | `fragment_schedule` | | 30 Tentang Aplikasi | `fragment_about` |
| 15 Ajukan Koreksi | `fragment_correction` | | | |

---

PjBL Aplikasi Mobile · Kelompok 4 · Mitra UPTD Puskesmas Simbang
