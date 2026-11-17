# Riwayat Perubahan SIMPRES

Format: setiap versi berisi bagian **Ditambah**, **Diubah**, dan **Diperbaiki**. Versi yang belum dirilis ditandai *rencana*.

## [1.0.0] – 2 Desember 2026 (rencana, Minggu 15)

### Ditambah
- Backend dan Admin Web dipasang di hosting dengan HTTPS.
- APK rilis untuk pegawai.
- Video demonstrasi dan laporan akhir PjBL.

### Diubah
- Akun contoh diganti akun asli pegawai, kata sandi awal dibuat oleh admin.

## [0.3.0] – 25 November 2026 (rencana, Minggu 14)

### Ditambah
- Notifikasi push Firebase Cloud Messaging saat izin, koreksi, atau tugas lapangan diputuskan.
- Pengiriman ulang antrean offline otomatis (WorkManager).
- Kamera bukti tugas lapangan (CameraX) dan peta di halaman check-in.
- Token login terenkripsi dengan Android Keystore.

### Pengujian
- Pengujian sistem bersama pegawai Puskesmas, evaluasi mitra, dan demo prototipe.

## [0.2.0] – 18 November 2026 (Minggu 13)

### Ditambah – Basis data dan REST API
- Rancangan ERD 10 tabel dan skrip `docs/database/simpres.sql` (pegawai, lokasi kerja, shift, jadwal, absensi, izin, koreksi, tugas lapangan, notifikasi, token perangkat).
- Proyek Laravel di `backend/` dengan login token Laravel Sanctum.
- 32 endpoint REST API (`backend/API.md`): login, absensi, riwayat, izin, koreksi, tugas lapangan, jadwal, lokasi, notifikasi.
- Aturan validasi absensi di server: jarak Haversine maksimal radius lokasi (150 m), batas akurasi GPS, penolakan lokasi palsu,
  satu check-in per hari, check-out setelah check-in, status terlambat sesuai shift, pegawai berizin tidak perlu check-in.
- CRUD izin dan cuti (dengan lampiran foto), koreksi absensi, tugas lapangan (dengan foto), file tersimpan di storage private.

### Ditambah – Admin Web (sesuai desain Figma A1–A8)
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

### Diubah
- Tugas lapangan yang diterima admin dihitung hadir di rekap.
- Tombol "Geser untuk Check-out" kini benar-benar bisa digeser.

### Pengujian
- 133 test otomatis backend lulus (`php artisan test`).
- Alur aplikasi diuji di emulator: login, check-in, check-out, izin, riwayat, jadwal, notifikasi.
- Admin Web diuji di browser: semua halaman terbuka, persetujuan izin mengirim notifikasi ke aplikasi.

## [0.1.0] – 11 November 2026 (Minggu 12)

### Ditambah
- Proyek Android Kotlin dengan layout XML (package `com.simpres.app`, Android 8.0 ke atas) dan Navigation Component.
- 29 layout halaman dari desain Figma, termasuk Splash, Onboarding, Login, Lupa Kata Sandi, Beranda, Tentang Aplikasi, dialog keluar.
- Tema terang dan gelap, font Lexend, ikon, dan latar komponen (kartu, chip, tombol, input).
- BaseFragment, bottom navigation, dan pemeriksaan koneksi internet.
- Alur check-in dengan aturan radius 150 m (data contoh), halaman Di Luar Radius dan Tersimpan Offline.
- Dokumentasi screenshot tampilan di README.

### Diperbaiki
- Jarak ikon pada tombol dan menu, ditambah animasi ikon ringan.
