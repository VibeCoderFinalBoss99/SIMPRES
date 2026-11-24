# Riwayat Perubahan SIMPRES

Format: setiap versi berisi bagian **Ditambah**, **Diubah**, dan **Diperbaiki**. Diurutkan dari versi paling awal. Versi yang belum dirilis ditandai *rencana*.

## [0.1.0] – 11 November 2026 (Minggu 12)

### Ditambah
- Proyek Android Kotlin dengan layout XML (package `com.simpres.app`, Android 8.0 ke atas) dan Navigation Component.
- 29 layout halaman, termasuk Splash, Onboarding, Login, Lupa Kata Sandi, Beranda, Tentang Aplikasi, dialog keluar.
- Tema terang dan gelap, font Lexend, ikon, dan latar komponen (kartu, chip, tombol, input).
- BaseFragment, bottom navigation, dan pemeriksaan koneksi internet.
- Alur check-in dengan aturan radius 150 m (data contoh), halaman Di Luar Radius dan Tersimpan Offline.
- Dokumentasi screenshot tampilan di README.

### Diperbaiki
- Jarak ikon pada tombol dan menu, ditambah animasi ikon ringan.

## [0.2.0] – 18 November 2026 (Minggu 13)

### Ditambah – Basis data dan REST API
- ERD 10 tabel (`docs/diagram/erd_simpres.png`), flowchart absensi dan izin, serta skrip `docs/database/simpres.sql` (pegawai, lokasi kerja, shift, jadwal, absensi, izin, koreksi, tugas lapangan, notifikasi, token perangkat).
- Proyek Laravel di `backend/` dengan login token Laravel Sanctum.
- 32 endpoint REST API (`backend/API.md`): login, absensi, riwayat, izin, koreksi, tugas lapangan, jadwal, lokasi, notifikasi.
- Aturan validasi absensi di server: jarak Haversine maksimal radius lokasi (150 m), batas akurasi GPS, penolakan lokasi palsu,
  satu check-in per hari, check-out setelah check-in, status terlambat sesuai shift, pegawai berizin tidak perlu check-in.
- CRUD izin dan cuti (dengan lampiran foto), koreksi absensi, tugas lapangan (dengan foto), file tersimpan di storage private.

### Ditambah – Admin Web
- Dashboard: kartu ringkasan, grafik kehadiran 7 hari, daftar perlu ditinjau, aktivitas terbaru.
- Review & Persetujuan: daftar dan detail berdampingan, terima atau tolak dengan catatan, notifikasi ke pegawai.
- Data Pegawai, Lokasi Kerja (peta dan radius), Roster mingguan dan pengaturan per bulan, Rekap & Ekspor CSV.
- Audit Log: jejak keputusan, penambahan pegawai, perubahan lokasi dan konfigurasi, bisa diekspor CSV.
- Konfigurasi: batas akurasi GPS dan penolakan lokasi palsu langsung dipakai server.

### Ditambah – Aplikasi Android
- Aplikasi tersambung ke REST API: login, Beranda dengan data hari ini, check-in dengan GPS asli, check-out dengan tombol geser,
  riwayat per bulan, jadwal, ajukan dan batalkan izin, koreksi absensi, tugas lapangan dengan foto kamera, notifikasi, profil, keluar.
- Antrean offline: absensi tanpa internet disimpan di HP dan dikirim ulang saat online.
- Intro animasi Lottie di Splash dan onboarding 3 halaman dengan animasi.
- Panduan awal (coach mark) yang menyorot tombol penting saat pertama kali masuk Beranda, bisa diulang dari Profil.
- Logo SIMPRES (pin lokasi, tanda centang, cincin radius emas) sebagai ikon aplikasi dan di halaman Login serta Tentang Aplikasi.
- Pilihan mode tampilan Ikuti sistem / Terang / Gelap di Pengaturan, tersimpan di HP.

### Diubah
- Ikon aplikasi tidak lagi memakai latar hijau bawaan Android Studio.
- Warna ikon status bar mengikuti mode terang atau gelap.
- Tugas lapangan yang diterima admin dihitung hadir di rekap.
- Tombol "Geser untuk Check-out" kini benar-benar bisa digeser.

### Pengujian
- 133 test otomatis backend lulus (`php artisan test`).
- Alur aplikasi diuji di emulator: login, check-in, check-out, izin, riwayat, jadwal, notifikasi.
- Admin Web diuji di browser: semua halaman terbuka, persetujuan izin mengirim notifikasi ke aplikasi.

## [0.3.0] – 25 November 2026 (sedang berjalan, Minggu 14)

Minggu pengujian fitur dan perbaikan bug.

### Ditambah
- Lupa kata sandi lewat email: endpoint `POST /api/forgot-password` (cari akun lewat NIP atau email), email berbahasa
  Indonesia, halaman web `/reset-password` untuk membuat sandi baru. Semua login lama dikeluarkan setelah sandi diganti.
- Peta OpenStreetMap di halaman check-in dan di luar radius: radius 150 m, titik Puskesmas, posisi pegawai, bisa digeser dan di-zoom.
- Pilihan Cuti di formulir izin aplikasi (rentang tanggal, lampiran opsional).
- APK rilis bertanda tangan di GitHub Releases, dengan QR unduhan (`docs/qr_download_simpres.png`).
- Notifikasi HP seketika lewat Firebase Cloud Messaging untuk keputusan admin (izin, koreksi, tugas lapangan),
  dengan cadangan pengecekan berkala (WorkManager). Server: `PushNotifier` (FCM HTTP v1, tanpa package tambahan).
- Sinkronisasi otomatis antrean offline di latar belakang dengan WorkManager (`data/Background.kt`).
- Menu Koreksi Absensi di Profil.
- Skema fitur dan pengujian lengkap per peran (`docs/SKEMA_PENGUJIAN.md`) dan screenshot baru (`docs/screenshots/v0.3/`).

### Diperbaiki
- Halaman lupa kata sandi sebelumnya tidak mengirim apa pun dan menampilkan email contoh.
- Tulisan contoh desain dan slider check-out sempat muncul sebentar saat membuka Beranda.
- Layar "Pengajuan terkirim" menampilkan data contoh, sekarang data pengajuan yang baru dikirim.
- Formulir tugas lapangan menampilkan lokasi contoh, sekarang koordinat GPS asli dan nama lokasi wajib diisi.
- Absensi dari antrean offline tercatat `online` di server, sekarang `offline`.
- Server bisa diakses dari luar laptop (alamat dan tautan memakai HTTPS di belakang proxy).
- Halaman Koreksi Absensi tidak punya pintu masuk, sekarang ada menu Koreksi Absensi di Profil.
- Aplikasi crash saat menekan Ambil foto tugas lapangan (izin kamera belum diminta), sekarang izin diminta dulu.
- Peta kosong sebagian atau tidak muncul di jaringan lambat, sekarang tile diunduh paralel, utuh, dan dicoba ulang.
- Daftar lengkap 17 bug beserta penyebab dan perbaikannya: `docs/BUG_DAN_PERBAIKAN.md`.

### Pengujian
- 141 test otomatis backend lulus.
- Pengujian per peran direkam video (`docs/videos/`): admin 21/21, pegawai non-shift 17/17, pegawai shift 12/12,
  petugas lapangan 10/10, notifikasi Firebase 2/2, notifikasi cadangan dan sinkronisasi 5/5, integrasi 5/5. Total 72/72 (`docs/HASIL_PENGUJIAN.md`).
- Admin Web diuji di browser: dashboard, pegawai, lokasi, roster, review, rekap, audit log, konfigurasi.

### Belum dikerjakan (lanjut ke 1.0.0)
- CameraX, token di Android Keystore, penguncian perangkat.
- Jam absensi offline sesuai jam di HP. Hosting permanen dengan HTTPS.
- Token login terenkripsi dengan Android Keystore.
- Pengujian bersama pegawai Puskesmas dan evaluasi mitra.

## [1.0.0] – 2 Desember 2026 (rencana, Minggu 15)

### Ditambah
- Backend dan Admin Web dipasang di hosting dengan HTTPS.
- APK rilis untuk pegawai.
- Video demonstrasi dan laporan akhir PjBL.

### Diubah
- Akun contoh diganti akun asli pegawai, kata sandi awal dibuat oleh admin.
