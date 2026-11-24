# Skema Fitur dan Pengujian SIMPRES

Dokumen ini berisi semua fitur SIMPRES per peran, cara pakainya, dan langkah pengujian untuk
**Aplikasi Android** (pegawai) dan **Admin Web** (admin).

Cara memakai dokumen:
1. Baca bagian 1–2 untuk memahami fitur dan siapa yang memakainya.
2. Siapkan data uji di bagian 3.
3. Jalankan pengujian bagian 4 sesuai urutan. Isi kolom **Hasil** dengan ✅ (lulus) atau ❌ (gagal + catatan).
4. Ambil screenshot di langkah bertanda 📸 (daftar file di bagian 6).

Hasil pengujian 24 November 2026 per pengguna, lengkap dengan video, ada di [HASIL_PENGUJIAN.md](HASIL_PENGUJIAN.md).
Bug yang ditemukan dan perbaikannya ada di [BUG_DAN_PERBAIKAN.md](BUG_DAN_PERBAIKAN.md).

---

## 1. Peran dan platform

| Peran | Platform | Isi pekerjaan |
|---|---|---|
| **Admin** (bagian kepegawaian) | Admin Web di browser laptop (`/admin`) | Mengelola pegawai, lokasi, shift, jadwal, memutuskan pengajuan, melihat rekap |
| **Pegawai** | Aplikasi Android | Absen masuk dan pulang, mengajukan izin, koreksi, tugas lapangan, melihat riwayat dan jadwal |

Di database hanya ada dua role: `admin` dan `pegawai`. Pegawai dibedakan lagi menurut **jenis kerja**,
yang ditentukan oleh jadwal di Roster dan cara kerjanya:

| Jenis kerja | Ciri | Contoh akun uji |
|---|---|---|
| **Non-shift** | Jam tetap, shift Pagi (07.30–14.00) setiap hari kerja | Budi |
| **Shift** | Jam bergantian Pagi (07.30–14.00) dan Siang (14.00–21.00) | Siti |
| **Petugas lapangan** | Sering bekerja di luar gedung (posyandu, kunjungan rumah), bukti kerja lewat Tugas Lapangan | Rina (dibuat saat pengujian) |

Aturan yang berlaku untuk semua pegawai (diputuskan server, bukan HP):

| No | Aturan |
|---|---|
| R1 | Absensi sah jika jarak ke Puskesmas maksimal radius lokasi kerja (150 m) |
| R2 | Akurasi GPS maksimal 100 m (bisa diubah di Konfigurasi) |
| R3 | Lokasi palsu (mock location) ditolak |
| R4 | Satu check-in per hari, check-out hanya setelah check-in |
| R5 | Terlambat jika check-in lewat jam masuk shift + toleransi (10 menit). Menit terlambat dihitung dari jam masuk |
| R6 | Pegawai dengan izin diterima di hari itu (selain izin keluar) tidak perlu check-in |
| R7 | Pengajuan berstatus Menunggu Konfirmasi, Diterima, atau Ditolak. Hanya admin yang memutuskan |
| R8 | Pengajuan yang masih menunggu bisa dibatalkan pegawai. Yang sudah diputuskan tidak bisa diubah |

---

## 2. Daftar fitur

### 2a. Aplikasi Android (pegawai)

| No | Fitur | Tujuan | Cara pakai |
|---|---|---|---|
| P1 | Splash dan intro | Membuka aplikasi dengan logo | Otomatis saat aplikasi dibuka |
| P2 | Onboarding 3 halaman | Menjelaskan cara kerja aplikasi untuk pengguna baru | Geser atau tekan Lanjut, bisa Lewati |
| P3 | Login | Masuk dengan akun dari admin | Isi email dan kata sandi, tekan Masuk |
| P4 | Lupa kata sandi | Membuat sandi baru lewat email | Login > Lupa kata sandi > isi NIP/email > buka tautan di email > isi sandi baru |
| P5 | Beranda | Ringkasan hari ini: shift, jam masuk/pulang, jarak, status kirim, aktivitas | Halaman pertama setelah login. Tarik ke bawah untuk memuat ulang |
| P6 | Panduan awal (coach mark) | Mengenalkan bagian Beranda | Muncul sekali saat pertama masuk Beranda |
| P7 | Check-in | Mencatat jam masuk dengan GPS | Tombol tengah di menu bawah > izinkan lokasi > otomatis terkirim |
| P8 | Peta check-in | Melihat posisi diri terhadap radius Puskesmas | Geser dengan satu jari, zoom dengan tombol +/−, cubit, atau ketuk dua kali |
| P9 | Di luar radius | Memberi tahu jarak jika absensi ditolak | Muncul otomatis jika jarak > 150 m |
| P10 | Check-out | Mencatat jam pulang | Geser slider check-out di Beranda (muncul setelah check-in) |
| P11 | Antrean offline | Absensi tidak hilang saat sinyal putus | Otomatis disimpan, lalu terkirim saat online. Status di menu Sinkronisasi |
| P12 | Riwayat | Melihat absensi per bulan | Menu Riwayat, pilih bulan |
| P13 | Jadwal | Melihat shift bulan ini | Menu Jadwal |
| P14 | Izin dan cuti | Mengajukan izin keluar jam kerja (dengan jam), sakit (dengan foto surat), cuti, lainnya | Profil > Izin & Cuti > Ajukan Izin > isi formulir > Kirim |
| P15 | Koreksi absensi | Membetulkan jam masuk/pulang hari ini yang salah atau lupa | Profil > Koreksi Absensi > pilih jam dan alasan > Kirim |
| P16 | Tugas lapangan | Bukti kerja di luar gedung dengan foto dan GPS | Check-in dari luar radius > Mode Tugas Lapangan > isi lokasi dan kegiatan > ambil foto > Kirim. Riwayatnya di Riwayat > Lapangan |
| P17 | Notifikasi | Kabar keputusan admin | Ikon lonceng di Beranda. Ketuk untuk menandai dibaca |
| P18 | Profil | Data diri dan lokasi kerja | Menu Profil |
| P19 | Pengaturan | Mode tampilan terang/gelap, ganti kata sandi | Profil > Pengaturan |
| P20 | Privasi dan izin | Penjelasan data yang dipakai, membuka pengaturan izin HP | Profil > Privasi |
| P21 | Tentang aplikasi | Versi dan informasi aplikasi | Profil > Tentang |
| P22 | Keluar | Mengakhiri sesi di HP | Profil > Keluar > konfirmasi |

### 2b. Admin Web (admin)

| No | Fitur | Tujuan | Cara pakai |
|---|---|---|---|
| W1 | Login admin | Hanya akun role admin yang bisa masuk | Buka `/admin`, isi email dan sandi |
| W2 | Dashboard | Ringkasan hari ini: hadir, terlambat, izin, belum absen, daftar absensi | Halaman pertama setelah login |
| W3 | Data Pegawai | Tambah, ubah, cari, aktif/nonaktifkan pegawai | Menu Data Pegawai |
| W4 | Lokasi Kerja | Mengatur titik Puskesmas dan radius, dengan peta | Menu Lokasi Kerja > ubah > Simpan |
| W5 | Roster: Shift | Menambah dan mengubah shift (jam masuk, pulang, toleransi) | Menu Roster > Shift |
| W6 | Roster: Jadwal | Memberi shift atau libur per pegawai per tanggal, per minggu atau per bulan | Menu Roster > pilih pegawai, tanggal, shift |
| W7 | Review & Persetujuan | Memutuskan izin, koreksi, tugas lapangan, melihat foto/lampiran | Menu Review > filter jenis/status > buka > Terima/Tolak dengan catatan |
| W8 | Rekap & Ekspor | Rekap absensi per bulan per pegawai, unduh CSV | Menu Rekap > pilih bulan > Ekspor CSV |
| W9 | Audit Log | Jejak siapa mengubah apa, filter tanggal dan aksi, unduh CSV | Menu Audit Log |
| W10 | Konfigurasi | Pengaturan sistem (akurasi GPS, tolak lokasi palsu, toleransi, dll.) | Menu Konfigurasi > ubah > Simpan |
| W11 | Lupa sandi (halaman web) | Halaman membuat sandi baru dari tautan email | Dibuka dari email |
| W12 | Keluar | Mengakhiri sesi admin | Tombol Keluar di sidebar |

---

## 3. Persiapan data uji

| Akun | Peran | Jenis kerja | Sandi awal |
|---|---|---|---|
| `admin@simpres.test` | Admin | - | `simpres123` |
| `budi@simpres.test` | Pegawai | Non-shift | `simpres123` |
| `siti@simpres.test` | Pegawai | Shift | `simpres123` |
| `rina@simpres.test` | Pegawai | Petugas lapangan | dibuat di W3-1 |

Alat yang dibutuhkan:
- Laptop: Laragon menyala, server Laravel berjalan, browser untuk Admin Web.
- HP Android dengan APK SIMPRES. HP kedua atau emulator untuk menguji dua pegawai sekaligus.
- Untuk uji jarak tanpa pergi ke lokasi: emulator Android dengan perintah `adb emu geo fix <bujur> <lintang>`.
  Di Puskesmas: `adb emu geo fix 119.6653682 -5.0224565`. Sekitar 220 m: `adb emu geo fix 119.6653682 -5.0242565`.
- Untuk uji jam terlambat: ubah jam masuk shift di W5 supaya tidak perlu menunggu jamnya.

Ganti semua sandi `simpres123` setelah pengujian selesai.

---

## 4. Skenario pengujian

### A. Admin Web

**A1. Login dan keamanan (W1, W12)**

| ID | Langkah | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| A1-1 | Buka `/admin` tanpa login | Diarahkan ke halaman login | |
| A1-2 | Login dengan sandi salah | Pesan error, isian email tetap ada | |
| A1-3 | Login dengan akun pegawai (Siti) | Ditolak, hanya admin yang boleh | |
| A1-4 | Login `admin@simpres.test` 📸 `web_login` | Masuk ke Dashboard, sidebar tampil | |
| A1-5 | Keluar, lalu tekan Back di browser | Kembali ke login, Dashboard tidak tampil | |

**A2. Dashboard (W2)**

| ID | Langkah | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| A2-1 | Buka Dashboard sebelum ada yang absen | Semua pegawai terhitung belum absen | |
| A2-2 | Setelah Budi dan Siti check-in (C2-3, D2-2), muat ulang 📸 `web_dashboard` | Jumlah hadir/terlambat bertambah, nama muncul di tabel | |
| A2-3 | Setelah izin Budi diterima untuk hari ini | Budi terhitung izin | |

**A3. Data Pegawai (W3)**

| ID | Langkah | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| A3-1 | Tambah pegawai Rina (NIP, nama, email `rina@simpres.test`, jabatan Petugas Promkes, unit, sandi) 📸 `web_pegawai` | Tersimpan, muncul di daftar | |
| A3-2 | Tambah pegawai dengan email atau NIP yang sudah dipakai | Pesan error, data tidak tersimpan | |
| A3-3 | Tambah pegawai dengan kolom wajib kosong | Pesan error per kolom | |
| A3-4 | Cari "Rina" di kotak pencarian | Hanya Rina yang tampil | |
| A3-5 | Ubah jabatan Rina | Perubahan tersimpan | |
| A3-6 | Nonaktifkan Rina, lalu Rina login di HP | Login ditolak "Akun Anda nonaktif" | |
| A3-7 | Aktifkan lagi Rina | Rina bisa login | |
| A3-8 | Cek di HeidiSQL kolom `password` Rina | Berupa hash (diawali `$2y$`), bukan teks asli | |

**A4. Lokasi Kerja (W4)**

| ID | Langkah | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| A4-1 | Buka Lokasi Kerja 📸 `web_lokasi` | Peta tampil dengan titik Puskesmas, radius 150 m | |
| A4-2 | Ubah radius menjadi 100, lalu check-in dari jarak sekitar 120 m | Check-in ditolak "di luar radius" | |
| A4-3 | Kembalikan radius ke 150, check-in dari 120 m | Diterima | |
| A4-4 | Isi koordinat di luar batas (misalnya lintang 200) | Pesan error | |

**A5. Roster: Shift dan Jadwal (W5, W6)**

| ID | Langkah | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| A5-1 | Lihat daftar shift | Pagi 07.30–14.00 dan Siang 14.00–21.00 | |
| A5-2 | Tambah shift "Malam" 21.00–07.30 toleransi 10 | Tersimpan dan bisa dipilih di jadwal | |
| A5-3 | Jadwal **Budi (non-shift)**: Pagi Senin–Jumat bulan ini 📸 `web_roster` | Tampil di tabel minggu | |
| A5-4 | Jadwal **Siti (shift)**: Pagi dan Siang bergantian, termasuk hari ini | Tampil di tabel minggu | |
| A5-5 | Jadwal **Rina (petugas lapangan)**: Pagi Senin–Jumat | Tampil di tabel minggu | |
| A5-6 | Simpan jadwal ganda (pegawai dan tanggal sama) | Ditolak atau menimpa jadwal lama, tidak ada dua baris | |
| A5-7 | Tandai satu hari Siti sebagai libur | Di HP Siti hari itu tertulis Libur | |

**A6. Review & Persetujuan (W7)**

| ID | Langkah | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| A6-1 | Buka Review, filter jenis Izin, status Menunggu | Izin dari C3-1 tampil | |
| A6-2 | Buka izin sakit Budi, buka lampiran fotonya | Foto surat tampil | |
| A6-3 | Terima izin Budi dengan catatan 📸 `web_review` | Status Diterima, notifikasi masuk ke HP Budi | |
| A6-4 | Buka koreksi Siti, Tolak dengan catatan "Tidak ada bukti" | Status Ditolak, catatan tampil di HP Siti | |
| A6-5 | Buka tugas lapangan Rina, lihat foto, Terima 📸 `web_review_tugas` | Status Diterima | |
| A6-6 | Buka lagi pengajuan yang sudah diputuskan | Tombol Terima/Tolak tidak muncul | |
| A6-7 | Terima koreksi jam pulang (D3-1 diulang dan diterima) | Jam pulang di Riwayat dan Rekap ikut berubah | |
| A6-8 | Filter status Diterima dan Ditolak | Daftar sesuai filter | |

**A7. Rekap & Ekspor (W8)**

| ID | Langkah | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| A7-1 | Buka Rekap bulan ini 📸 `web_rekap` | Jumlah hadir, terlambat, izin per pegawai sesuai data | |
| A7-2 | Ganti ke bulan lalu | Angka berubah sesuai bulan | |
| A7-3 | Ekspor CSV, buka di Excel | File terunduh, kolom rapi, angka sama dengan layar | |

**A8. Audit Log (W9)**

| ID | Langkah | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| A8-1 | Buka Audit Log 📸 `web_audit` | Perubahan dari A3–A6 tercatat dengan waktu dan pelaku | |
| A8-2 | Filter tanggal hari ini dan satu jenis aksi | Hanya data yang cocok tampil | |
| A8-3 | Unduh CSV | File terunduh dan berisi data yang sama | |

**A9. Konfigurasi (W10)**

| ID | Langkah | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| A9-1 | Buka Konfigurasi 📸 `web_konfigurasi` | Semua pengaturan tampil per kelompok | |
| A9-2 | Ubah batas akurasi GPS menjadi 5 m, lalu check-in dari HP | Ditolak "akurasi GPS terlalu rendah". Kembalikan ke 100 | |
| A9-3 | Kosongkan isian angka lalu Simpan | Pesan error, data lama tidak berubah | |

**A10. Lupa sandi admin (W11)**

| ID | Langkah | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| A10-1 | Minta tautan lupa sandi untuk akun admin (lewat aplikasi atau API) | Email berisi tombol Buat Kata Sandi Baru | |
| A10-2 | Buka tautan, isi sandi baru dan ulangi 📸 `web_reset_sandi` | "Kata sandi berhasil diubah", login admin dengan sandi baru berhasil | |
| A10-3 | Buka tautan yang sama sekali lagi | "Tautan tidak berlaku atau sudah kedaluwarsa" | |

---

### B. Aplikasi Android: fitur umum (semua pegawai)

| ID | Fitur | Langkah | Hasil yang diharapkan | Hasil |
|---|---|---|---|---|
| B-1 | P1, P2 | Pasang APK, buka pertama kali 📸 `app_onboarding` | Intro logo, lalu onboarding 3 halaman | |
| B-2 | P2 | Tekan Lewati | Langsung ke halaman login | |
| B-3 | P3 | Login dengan kolom kosong | Pesan "isi email dan kata sandi" | |
| B-4 | P3 | Login dengan sandi salah | Pesan "Email atau password salah" | |
| B-5 | P3 | Login benar | Masuk ke Beranda | |
| B-6 | P3 | Matikan server, lalu login | Pesan "Server tidak bisa dihubungi" | |
| B-7 | P6 | Panduan awal di Beranda 📸 `app_panduan` | 5 langkah panduan, bisa Lanjut atau Lewati. Tidak muncul lagi setelah selesai | |
| B-8 | P5 | Beranda sebelum absen 📸 `app_beranda` | Shift hari ini, "Belum check-in", tidak ada tulisan contoh desain | |
| B-9 | P5 | Pindah ke menu lain lalu kembali | Data tidak berkedip ke tulisan contoh | |
| B-10 | P4 | Lupa kata sandi, isi NIP 📸 `app_lupa_sandi`, `app_cek_email` | Layar "Cek email kamu" dengan email akun (disamarkan) | |
| B-11 | P4 | Isi NIP/email yang tidak terdaftar | Pesan "tidak terdaftar" | |
| B-12 | P4 | Tekan "Kirim ulang tautan" sebelum 60 detik | Tombol belum aktif, ada hitung mundur | |
| B-13 | P17 | Buka notifikasi setelah admin memutuskan pengajuan 📸 `app_notifikasi` | Notifikasi baru di atas, tanda belum dibaca | |
| B-14 | P17 | Ketuk notifikasi | Ditandai dibaca, jumlah di lonceng berkurang | |
| B-15 | P18 | Buka Profil 📸 `app_profil` | Nama, NIP, jabatan, unit, lokasi kerja sesuai data admin | |
| B-16 | P19 | Pengaturan > mode gelap, lalu terang 📸 `app_beranda_gelap` | Tampilan berubah dan tetap setelah aplikasi dibuka ulang | |
| B-17 | P20 | Privasi > Buka pengaturan izin | Halaman pengaturan aplikasi di HP terbuka | |
| B-18 | P21 | Tentang aplikasi | Versi aplikasi tampil | |
| B-19 | P22 | Keluar > konfirmasi, lalu tekan Back | Kembali ke login, Beranda tidak bisa dibuka lagi | |
| B-20 | P3 | Tutup aplikasi lalu buka lagi (tanpa keluar) | Langsung ke Beranda, tidak perlu login ulang | |

---

### C. Pegawai Non-shift (Budi)

| ID | Fitur | Langkah | Hasil yang diharapkan | Hasil |
|---|---|---|---|---|
| C1-1 | P13 | Buka Jadwal 📸 `app_jadwal_nonshift` | Pagi 07.30–14.00 pada hari kerja | |
| C2-1 | P7, P8 | Buka check-in di Puskesmas 📸 `app_checkin_peta` | Peta asli, titik biru di dalam lingkaran, jarak dan akurasi tampil | |
| C2-2 | P8 | Geser peta dan zoom 📸 `app_peta_zoom` | Peta bergeser dan membesar, tidak kembali ke tengah | |
| C2-3 | P7 | Check-in sebelum 07.40 📸 `app_checkin_berhasil` | "Check-in berhasil", status Hadir | |
| C2-4 | P7 | Check-in lagi di hari yang sama | Ditolak "sudah check-in" (R4) | |
| C2-5 | P10 | Geser check-out 📸 `app_checkout` | Jam pulang terisi | |
| C2-6 | P10 | Check-out lagi | Ditolak "sudah check-out" | |
| C2-7 | P7 | (Hari lain) check-in jam 07.45 | Status Terlambat 15 menit (R5) | |
| C3-1 | P14 | Ajukan izin **sakit** tanpa foto | Ditolak, foto surat wajib | |
| C3-2 | P14 | Ajukan izin sakit dengan foto 📸 `app_izin_form` | Status Menunggu Konfirmasi | |
| C3-3 | P14 | Ajukan cuti dengan tanggal selesai sebelum tanggal mulai | Ditolak | |
| C3-4 | P14 | Ajukan **izin keluar** tanpa jam | Ditolak, jam wajib untuk izin keluar | |
| C3-5 | P14 | Setelah admin menerima (A6-3) | Status Diterima, notifikasi masuk | |
| C3-6 | R6 | Check-in di hari izin sakit yang diterima | Ditolak "tidak perlu check-in" | |
| C4-1 | P12 | Buka Riwayat bulan ini 📸 `app_riwayat` | Absensi hari ini dan hari izin tampil | |

---

### D. Pegawai Shift (Siti)

| ID | Fitur | Langkah | Hasil yang diharapkan | Hasil |
|---|---|---|---|---|
| D1-1 | P13 | Buka Jadwal 📸 `app_jadwal_shift` | Pagi dan Siang bergantian, hari libur tertulis Libur | |
| D2-1 | P5 | Di hari shift Siang, buka Beranda | Shift Siang 14.00–21.00 | |
| D2-2 | P7, R5 | Shift Siang, check-in jam 13.55 | Hadir (dinilai dari jam Siang, bukan Pagi) | |
| D2-3 | P7, R5 | Shift Siang, check-in jam 14.20 | Terlambat 20 menit | |
| D2-4 | P9, R1 | Check-in dari jarak sekitar 220 m 📸 `app_luar_radius` | Halaman "Di luar radius" dengan jarak dan peta | |
| D2-5 | R2 | Check-in dengan GPS lemah (di dalam gedung tertutup) | Ditolak "akurasi GPS terlalu rendah" jika > 100 m | |
| D3-1 | P15 | Ajukan koreksi jam pulang kemarin 📸 `app_koreksi` | Status Menunggu Konfirmasi | |
| D3-2 | P15 | Ajukan koreksi tanpa jam sama sekali | Ditolak, minimal satu jam diisi | |
| D3-3 | P15 | Ajukan koreksi untuk tanggal besok | Ditolak, tidak boleh setelah hari ini | |
| D3-4 | P15 | Setelah admin menolak (A6-4) | Status Ditolak dan catatan admin tampil | |
| D4-1 | P14, R8 | Ajukan cuti 2 hari, lalu batalkan sebelum diputuskan | Pengajuan hilang dari daftar | |
| D4-2 | R8 | Coba ubah atau batalkan izin yang sudah diterima | Tidak bisa | |

---

### E. Petugas Lapangan (Rina)

| ID | Fitur | Langkah | Hasil yang diharapkan | Hasil |
|---|---|---|---|---|
| E1-1 | P3 | Login dengan akun baru dari A3-1 | Masuk, data profil sesuai yang diisi admin | |
| E1-2 | P7 | Check-in di Puskesmas sebelum berangkat | Diterima | |
| E2-1 | P16 | Ajukan tugas lapangan: lokasi, kegiatan, foto kamera 📸 `app_tugas_lapangan` | Status Menunggu Konfirmasi, koordinat GPS ikut terkirim | |
| E2-2 | P16 | Ajukan tanpa foto | Ditolak, foto wajib | |
| E2-3 | P16 | Ajukan tanpa nama lokasi atau keterangan | Ditolak dengan pesan per kolom | |
| E2-4 | P16 | Setelah admin menerima (A6-5) | Status Diterima, notifikasi masuk | |
| E3-1 | R1 | Check-in dari lokasi posyandu (di luar 150 m) | Tetap ditolak. Bukti kerja di luar memakai tugas lapangan | |
| E3-2 | P10 | Kembali ke Puskesmas, check-out | Diterima | |

---

### F. Offline dan sinkronisasi (P11)

| ID | Langkah | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| F-1 | Matikan data dan Wi-Fi HP, lalu check-in 📸 `app_offline` | "Disimpan offline", antrean bertambah | |
| F-2 | Buka menu Sinkronisasi | Data menunggu tampil | |
| F-3 | Nyalakan internet, buka Beranda atau tekan Kirim sekarang | Data terkirim, antrean kosong, status "Aman" | |
| F-4 | Cek Admin Web | Absensi tercatat dengan sumber offline | |
| F-5 | Kirim ulang data yang sama (tekan Kirim dua kali) | Tidak ada absensi ganda | |

---

### G. Alur lengkap aplikasi ↔ website (integrasi)

Satu skenario dari awal sampai akhir untuk membuktikan aplikasi dan website saling terhubung.

| ID | Pelaku | Langkah | Hasil yang diharapkan | Hasil |
|---|---|---|---|---|
| G-1 | Admin | Tambah pegawai baru dan buat jadwal hari ini | Tersimpan | |
| G-2 | Pegawai | Login di HP, lihat Beranda | Shift hari ini sesuai jadwal dari admin | |
| G-3 | Pegawai | Check-in di Puskesmas | Diterima | |
| G-4 | Admin | Muat ulang Dashboard | Pegawai muncul sebagai hadir/terlambat | |
| G-5 | Pegawai | Ajukan izin keluar dengan jam | Menunggu Konfirmasi | |
| G-6 | Admin | Terima izin di Review | Status Diterima | |
| G-7 | Pegawai | Buka notifikasi dan daftar izin | Notifikasi dan status Diterima tampil | |
| G-8 | Pegawai | Check-out | Diterima | |
| G-9 | Admin | Buka Rekap dan Audit Log | Absensi dan keputusan izin tercatat | |

---

### H. Keamanan dan aturan server

| ID | Langkah | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| H-1 | Pasang aplikasi lokasi palsu, aktifkan di Opsi Pengembang, lalu check-in | Ditolak "lokasi palsu" (R3) | |
| H-2 | Postman: login Budi, buka `GET /api/leaves/{id}` milik Siti | 403 | |
| H-3 | Postman: token Siti ke `PATCH /api/admin/leaves/{id}` | 403, pegawai tidak bisa memutuskan | |
| H-4 | Buka alamat lampiran atau foto tanpa login | Tidak bisa dibuka (401/403) | |
| H-5 | Postman: `GET /api/me` tanpa token | 401 | |
| H-6 | Logout di HP, lalu pakai token lama di Postman | 401 | |
| H-7 | Ganti sandi lewat lupa sandi, lalu buka aplikasi di HP yang masih login | Diminta login ulang | |

---

### I. Pengujian otomatis

| ID | Langkah | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| I-1 | Di folder `backend`, jalankan `php artisan test` | Semua test lulus (137 test) | |
| I-2 | `php artisan route:list --path=api` | 33 endpoint API terdaftar | |

---

## 5. Ringkasan hasil

| Bagian | Jumlah uji | Lulus | Gagal | Catatan |
|---|---|---|---|---|
| A. Admin Web | 47 | | | |
| B. Fitur umum aplikasi | 20 | | | |
| C. Non-shift | 15 | | | |
| D. Shift | 12 | | | |
| E. Petugas lapangan | 8 | | | |
| F. Offline | 5 | | | |
| G. Integrasi | 9 | | | |
| H. Keamanan | 7 | | | |
| I. Otomatis | 2 | | | |

---

## 6. Daftar screenshot untuk README

> Status 24 November 2026: screenshot sudah diambil dari emulator dan browser lalu dipasang di README bagian v0.3.0.
> Yang belum ada: `web_login`, `app_peta_zoom` (zoom sudah terlihat di `app_checkin_peta`), `app_beranda_gelap`,
> dan `app_koreksi`. Tambahan di luar daftar: `app_izin`, `app_izin_cuti`, `app_izin_terkirim`, `app_tugas_lapangan_riwayat`.

Simpan di `docs/screenshots/v0.3/`. HP: tombol power + volume bawah. Web: browser laptop dengan lebar penuh.
Ambil setelah bagian C–G selesai supaya tabel dan angka sudah berisi data. Samarkan data pribadi asli
(NIP, nomor HP) karena repo ini publik.

| File | Diambil di | Bagian README |
|---|---|---|
| `app_onboarding.jpg` | B-1 | Aplikasi Android |
| `app_panduan.jpg` | B-7 | Aplikasi Android |
| `app_beranda.jpg` | B-8 (ulangi setelah check-in agar jam terisi) | Aplikasi Android |
| `app_beranda_gelap.jpg` | B-16 | Tampilan |
| `app_lupa_sandi.jpg`, `app_cek_email.jpg` | B-10 | Baru: Lupa kata sandi |
| `app_notifikasi.jpg` | B-13 | Aplikasi Android (ganti yang lama) |
| `app_profil.jpg` | B-15 | Aplikasi Android (ganti yang lama) |
| `app_jadwal_nonshift.jpg` | C1-1 | Baru: Jenis kerja pegawai |
| `app_jadwal_shift.jpg` | D1-1 | Baru: Jenis kerja pegawai |
| `app_checkin_peta.jpg` | C2-1 | Baru: Peta check-in |
| `app_peta_zoom.jpg` | C2-2 | Baru: Peta check-in |
| `app_checkin_berhasil.jpg` | C2-3 | Aplikasi Android (ganti yang lama) |
| `app_checkout.jpg` | C2-5 | Aplikasi Android |
| `app_luar_radius.jpg` | D2-4 | Baru: Peta check-in |
| `app_izin_form.jpg` | C3-2 | Aplikasi Android |
| `app_koreksi.jpg` | D3-1 | Aplikasi Android |
| `app_tugas_lapangan.jpg` | E2-1 | Aplikasi Android |
| `app_riwayat.jpg` | C4-1 | Aplikasi Android (ganti yang lama) |
| `app_offline.jpg` | F-1 | Aplikasi Android |
| `web_login.jpg` | A1-4 | Admin Web |
| `web_dashboard.jpg` | A2-2 | Admin Web (ganti yang lama) |
| `web_pegawai.jpg` | A3-1 | Admin Web |
| `web_lokasi.jpg` | A4-1 | Admin Web |
| `web_roster.jpg` | A5-3 | Admin Web (ganti yang lama) |
| `web_review.jpg`, `web_review_tugas.jpg` | A6-3, A6-5 | Admin Web (ganti yang lama) |
| `web_rekap.jpg` | A7-1 | Admin Web (ganti yang lama) |
| `web_audit.jpg` | A8-1 | Admin Web (ganti yang lama) |
| `web_konfigurasi.jpg` | A9-1 | Admin Web (ganti yang lama) |
| `web_reset_sandi.jpg` | A10-2 | Baru: Lupa kata sandi |
| `qr_download_simpres.png` | sudah ada di `docs/` | Baru: Unduh aplikasi |
