# Hasil Pengujian per Peran

Pengujian 25 November 2026 dengan server dan data asli (Laravel + MySQL), aplikasi Android di emulator, dan Admin Web
di Chrome. Setiap peran diuji terpisah dan direkam video. Setiap langkah dicek otomatis dari teks di layar, lalu
dicatat ✅ (lulus) atau ❌ (gagal). Foto diambil langsung dari layar pada langkah itu. ID langkah mengikuti
[SKEMA_PENGUJIAN.md](SKEMA_PENGUJIAN.md).

| Peran / pengujian | Video | Hasil |
|---|---|---|
| Admin (Admin Web) | [4_admin_web.mp4](videos/4_admin_web.mp4) | Semua lulus |
| Pegawai non-shift (aplikasi Android) | [1_pegawai_nonshift.mp4](videos/1_pegawai_nonshift.mp4) | Semua lulus |
| Pegawai shift (aplikasi Android) | [2_pegawai_shift.mp4](videos/2_pegawai_shift.mp4) | Semua lulus |
| Petugas lapangan (aplikasi Android) | [3_petugas_lapangan.mp4](videos/3_petugas_lapangan.mp4) | Semua lulus |
| Notifikasi HP dan sinkronisasi otomatis | [7_notifikasi_firebase.mp4](videos/7_notifikasi_firebase.mp4), [6_notifikasi_dan_sinkronisasi.mp4](videos/6_notifikasi_dan_sinkronisasi.mp4) | Semua lulus |
| Integrasi aplikasi dan Admin Web | [5_integrasi_aplikasi_web.mp4](videos/5_integrasi_aplikasi_web.mp4) | Semua lulus |

Selain itu, `php artisan test` di folder `backend`: semua test lulus. Bug yang ditemukan selama pengujian dan
perbaikannya ada di [BUG_DAN_PERBAIKAN.md](BUG_DAN_PERBAIKAN.md).

## Admin (Admin Web)

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

▶️ Video pengujian: [4_admin_web.mp4](videos/4_admin_web.mp4)  

| **A1-1** Buka /admin tanpa login diarahkan ke halaman login | **A1-2** Sandi salah ditolak dengan pesan | **A1-3** Akun pegawai tidak bisa masuk Admin Web |
|---|---|---|
| <img src="pengujian/admin/A1-1.jpg" width="300" alt="A1-1"> | <img src="pengujian/admin/A1-2.jpg" width="300" alt="A1-2"> | <img src="pengujian/admin/A1-3.jpg" width="300" alt="A1-3"> |

| **A2-2** Dashboard: absensi hari ini dari aplikasi tampil | **A3-1** Pegawai baru tersimpan dan tampil di daftar | **A3-2** Email ganda ditolak dengan pesan error |
|---|---|---|
| <img src="pengujian/admin/A2-2.jpg" width="300" alt="A2-2"> | <img src="pengujian/admin/A3-1.jpg" width="300" alt="A3-1"> | <img src="pengujian/admin/A3-2.jpg" width="300" alt="A3-2"> |

| **A3-4** Pencarian hanya menampilkan pegawai yang dicari | **A3-6** Akun nonaktif ditolak saat login di aplikasi: Akun Anda nonaktif. Hubungi admin Puskesmas. | **A4-1** Titik Puskesmas dan radius 150 m tampil dengan peta |
|---|---|---|
| <img src="pengujian/admin/A3-4.jpg" width="300" alt="A3-4"> | <img src="pengujian/admin/A3-6.jpg" width="300" alt="A3-6"> | <img src="pengujian/admin/A4-1.jpg" width="300" alt="A4-1"> |

| **A5-3** Roster: pegawai non-shift Pagi, pegawai shift bergantian | **A6-1** Daftar pengajuan dari aplikasi dengan status Menunggu Konfirmasi | **A6-3** Izin berstatus Diterima, catatan tersimpan |
|---|---|---|
| <img src="pengujian/admin/A5-3.jpg" width="300" alt="A5-3"> | <img src="pengujian/admin/A6-1.jpg" width="300" alt="A6-1"> | <img src="pengujian/admin/A6-3.jpg" width="300" alt="A6-3"> |

| **A6-4** Koreksi berstatus Ditolak dengan catatan | **A6-5a** Detail tugas lapangan dengan foto dan koordinat GPS | **A6-5** Tugas lapangan berstatus Diterima |
|---|---|---|
| <img src="pengujian/admin/A6-4.jpg" width="300" alt="A6-4"> | <img src="pengujian/admin/A6-5a.jpg" width="300" alt="A6-5a"> | <img src="pengujian/admin/A6-5.jpg" width="300" alt="A6-5"> |

| **A6-6** Pengajuan yang sudah diputuskan tidak punya tombol Terima/Tolak | **A7-1** Rekap per pegawai tampil | **A8-1** Keputusan tadi tercatat di audit log |
|---|---|---|
| <img src="pengujian/admin/A6-6.jpg" width="300" alt="A6-6"> | <img src="pengujian/admin/A7-1.jpg" width="300" alt="A7-1"> | <img src="pengujian/admin/A8-1.jpg" width="300" alt="A8-1"> |

| **A9-1** Pengaturan sistem tampil per kelompok | **A1-5** Setelah keluar, Dashboard tidak bisa dibuka lagi |
|---|---|
| <img src="pengujian/admin/A9-1.jpg" width="300" alt="A9-1"> | <img src="pengujian/admin/A1-5.jpg" width="300" alt="A1-5"> |

| ID | Yang diuji | Hasil |
|---|---|---|
| A1-1 | Buka /admin tanpa login diarahkan ke halaman login | ✅ Lulus |
| A1-2 | Sandi salah ditolak dengan pesan | ✅ Lulus |
| A1-3 | Akun pegawai tidak bisa masuk Admin Web | ✅ Lulus |
| A2-2 | Dashboard: absensi hari ini dari aplikasi tampil | ✅ Lulus |
| A3-1 | Pegawai baru tersimpan dan tampil di daftar | ✅ Lulus |
| A3-1b | Akun baru bisa login di aplikasi (API) | ✅ Lulus |
| A3-2 | Email ganda ditolak dengan pesan error | ✅ Lulus |
| A3-4 | Pencarian hanya menampilkan pegawai yang dicari | ✅ Lulus |
| A3-6 | Akun nonaktif ditolak saat login di aplikasi: Akun Anda nonaktif. Hubungi admin Puskesmas. | ✅ Lulus |
| A4-1 | Titik Puskesmas dan radius 150 m tampil dengan peta | ✅ Lulus |
| A5-3 | Roster: pegawai non-shift Pagi, pegawai shift bergantian | ✅ Lulus |
| A6-1 | Daftar pengajuan dari aplikasi dengan status Menunggu Konfirmasi | ✅ Lulus |
| A6-3 | Izin berstatus Diterima, catatan tersimpan | ✅ Lulus |
| A6-4 | Koreksi berstatus Ditolak dengan catatan | ✅ Lulus |
| A6-5a | Detail tugas lapangan dengan foto dan koordinat GPS | ✅ Lulus |
| A6-5 | Tugas lapangan berstatus Diterima | ✅ Lulus |
| A6-6 | Pengajuan yang sudah diputuskan tidak punya tombol Terima/Tolak | ✅ Lulus |
| A7-1 | Rekap per pegawai tampil | ✅ Lulus |
| A7-3 | File CSV rekap terunduh dan berisi data pegawai | ✅ Lulus |
| A8-1 | Keputusan tadi tercatat di audit log | ✅ Lulus |
| A9-1 | Pengaturan sistem tampil per kelompok | ✅ Lulus |
| A1-5 | Setelah keluar, Dashboard tidak bisa dibuka lagi | ✅ Lulus |

## Pegawai non-shift (aplikasi Android)

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

▶️ Video pengujian: [1_pegawai_nonshift.mp4](videos/1_pegawai_nonshift.mp4)  

| **B-1** Intro dan onboarding tampil | **B-4** Login sandi salah ditolak | **N-0** Izin notifikasi diminta setelah login | **B-7** Panduan awal muncul pertama kali |
|---|---|---|---|
| <img src="pengujian/pegawai_nonshift/B-1.jpg" width="170" alt="B-1"> | <img src="pengujian/pegawai_nonshift/B-4.jpg" width="170" alt="B-4"> | <img src="pengujian/pegawai_nonshift/N-0.jpg" width="170" alt="N-0"> | <img src="pengujian/pegawai_nonshift/B-7.jpg" width="170" alt="B-7"> |

| **C1-0** Beranda: shift Pagi, belum check-in | **C1-1** Jadwal non-shift: Pagi setiap hari kerja, Minggu libur | **C2-3** Check-in di dalam radius diterima, jam dan jarak tercatat | **C2-5a** Beranda: jam check-in terisi, slider check-out muncul |
|---|---|---|---|
| <img src="pengujian/pegawai_nonshift/C1-0.jpg" width="170" alt="C1-0"> | <img src="pengujian/pegawai_nonshift/C1-1.jpg" width="170" alt="C1-1"> | <img src="pengujian/pegawai_nonshift/C2-3.jpg" width="170" alt="C2-3"> | <img src="pengujian/pegawai_nonshift/C2-5a.jpg" width="170" alt="C2-5a"> |

| **C2-4** Check-in kedua ditolak | **C4-1** Riwayat menampilkan absensi hari ini | **C3-0** Daftar izin tampil (izin sakit diterima) | **C3-4b** Tanpa alasan tidak terkirim, tetap di formulir |
|---|---|---|---|
| <img src="pengujian/pegawai_nonshift/C2-4.jpg" width="170" alt="C2-4"> | <img src="pengujian/pegawai_nonshift/C4-1.jpg" width="170" alt="C4-1"> | <img src="pengujian/pegawai_nonshift/C3-0.jpg" width="170" alt="C3-0"> | <img src="pengujian/pegawai_nonshift/C3-4b.jpg" width="170" alt="C3-4b"> |

| **C3-4** Izin keluar terkirim, status Menunggu Konfirmasi | **C2-5** Check-out diterima | **B-13** Notifikasi keputusan admin tampil | **B-15** Profil sesuai data admin (nama, NIP, unit) |
|---|---|---|---|
| <img src="pengujian/pegawai_nonshift/C3-4.jpg" width="170" alt="C3-4"> | <img src="pengujian/pegawai_nonshift/C2-5.jpg" width="170" alt="C2-5"> | <img src="pengujian/pegawai_nonshift/B-13.jpg" width="170" alt="B-13"> | <img src="pengujian/pegawai_nonshift/B-15.jpg" width="170" alt="B-15"> |

| **B-15b** Menu Koreksi Absensi ada di Profil | **B-19** Kembali ke halaman login |
|---|---|
| <img src="pengujian/pegawai_nonshift/B-15b.jpg" width="170" alt="B-15b"> | <img src="pengujian/pegawai_nonshift/B-19.jpg" width="170" alt="B-19"> |

| ID | Yang diuji | Hasil |
|---|---|---|
| B-1 | Intro dan onboarding tampil | ✅ Lulus |
| B-4 | Login sandi salah ditolak | ✅ Lulus |
| N-0 | Izin notifikasi diminta setelah login | ✅ Lulus |
| B-7 | Panduan awal muncul pertama kali | ✅ Lulus |
| C1-0 | Beranda: shift Pagi, belum check-in | ✅ Lulus |
| C1-1 | Jadwal non-shift: Pagi setiap hari kerja, Minggu libur | ✅ Lulus |
| C2-3 | Check-in di dalam radius diterima, jam dan jarak tercatat | ✅ Lulus |
| C2-5a | Beranda: jam check-in terisi, slider check-out muncul | ✅ Lulus |
| C2-4 | Check-in kedua ditolak | ✅ Lulus |
| C4-1 | Riwayat menampilkan absensi hari ini | ✅ Lulus |
| C3-0 | Daftar izin tampil (izin sakit diterima) | ✅ Lulus |
| C3-4b | Tanpa alasan tidak terkirim, tetap di formulir | ✅ Lulus |
| C3-4 | Izin keluar terkirim, status Menunggu Konfirmasi | ✅ Lulus |
| C2-5 | Check-out diterima | ✅ Lulus |
| B-13 | Notifikasi keputusan admin tampil | ✅ Lulus |
| B-15 | Profil sesuai data admin (nama, NIP, unit) | ✅ Lulus |
| B-15b | Menu Koreksi Absensi ada di Profil | ✅ Lulus |
| B-19 | Kembali ke halaman login | ✅ Lulus |

## Pegawai shift (aplikasi Android)

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

▶️ Video pengujian: [2_pegawai_shift.mp4](videos/2_pegawai_shift.mp4)  

| **D2-1** Beranda: shift hari ini Malam 21.00-07.00, belum check-in | **D1-1** Jadwal shift: Pagi dan Siang bergantian, Minggu libur | **D2-4** Ditolak: di luar radius 150 m, jarak tampil di peta | **P8** Peta asli bisa di-zoom dan digeser |
|---|---|---|---|
| <img src="pengujian/pegawai_shift/D2-1.jpg" width="170" alt="D2-1"> | <img src="pengujian/pegawai_shift/D1-1.jpg" width="170" alt="D1-1"> | <img src="pengujian/pegawai_shift/D2-4.jpg" width="170" alt="D2-4"> | <img src="pengujian/pegawai_shift/P8.jpg" width="170" alt="P8"> |

| **D2-2** Check-in diterima, tepat waktu sebelum shift Malam 21.00 | **D3-0** Halaman Koreksi Absensi bisa dibuka | **D3-1** Koreksi terkirim, kembali ke Beranda | **D4-1** Cuti terkirim, status Menunggu Konfirmasi |
|---|---|---|---|
| <img src="pengujian/pegawai_shift/D2-2.jpg" width="170" alt="D2-2"> | <img src="pengujian/pegawai_shift/D3-0.jpg" width="170" alt="D3-0"> | <img src="pengujian/pegawai_shift/D3-1.jpg" width="170" alt="D3-1"> | <img src="pengujian/pegawai_shift/D4-1.jpg" width="170" alt="D4-1"> |

| **D4-1b** Cuti muncul di daftar izin | **F-1** Check-out disimpan di perangkat (offline) | **F-3** Antrean terkirim otomatis, check-out tercatat | **B-19** Kembali ke halaman login |
|---|---|---|---|
| <img src="pengujian/pegawai_shift/D4-1b.jpg" width="170" alt="D4-1b"> | <img src="pengujian/pegawai_shift/F-1.jpg" width="170" alt="F-1"> | <img src="pengujian/pegawai_shift/F-3.jpg" width="170" alt="F-3"> | <img src="pengujian/pegawai_shift/B-19.jpg" width="170" alt="B-19"> |

| ID | Yang diuji | Hasil |
|---|---|---|
| D2-1 | Beranda: shift hari ini Malam 21.00-07.00, belum check-in | ✅ Lulus |
| D1-1 | Jadwal shift: Pagi dan Siang bergantian, Minggu libur | ✅ Lulus |
| D2-4 | Ditolak: di luar radius 150 m, jarak tampil di peta | ✅ Lulus |
| P8 | Peta asli bisa di-zoom dan digeser | ✅ Lulus |
| D2-2 | Check-in diterima, tepat waktu sebelum shift Malam 21.00 | ✅ Lulus |
| D3-0 | Halaman Koreksi Absensi bisa dibuka | ✅ Lulus |
| D3-1 | Koreksi terkirim, kembali ke Beranda | ✅ Lulus |
| D4-1 | Cuti terkirim, status Menunggu Konfirmasi | ✅ Lulus |
| D4-1b | Cuti muncul di daftar izin | ✅ Lulus |
| F-1 | Check-out disimpan di perangkat (offline) | ✅ Lulus |
| F-3 | Antrean terkirim otomatis, check-out tercatat | ✅ Lulus |
| B-19 | Kembali ke halaman login | ✅ Lulus |

## Petugas lapangan (aplikasi Android)

Pegawai yang sering bertugas di luar gedung (posyandu, kunjungan rumah). Bukti kerja di luar memakai Tugas Lapangan.

**Fitur yang diuji:**

- Check-in dari posyandu ditolak dan ditawari Mode Tugas Lapangan
- Formulir tugas lapangan dengan GPS asli
- Kirim tanpa nama lokasi ditolak
- Izin kamera dan foto bukti kegiatan
- Tugas lapangan tampil di Riwayat > Lapangan
- Check-in setelah kembali ke Puskesmas
- Keluar dari akun

▶️ Video pengujian: [3_petugas_lapangan.mp4](videos/3_petugas_lapangan.mp4)  

| **E1-1** Login berhasil, profil dan shift Pagi sesuai data admin | **E3-1** Check-in ditolak di luar radius, ditawari Mode Tugas Lapangan | **E2-0** Formulir tugas lapangan memakai GPS asli (bukan lokasi contoh) | **E2-3** Tanpa nama lokasi tidak terkirim, tetap di formulir |
|---|---|---|---|
| <img src="pengujian/petugas_lapangan/E1-1.jpg" width="170" alt="E1-1"> | <img src="pengujian/petugas_lapangan/E3-1.jpg" width="170" alt="E3-1"> | <img src="pengujian/petugas_lapangan/E2-0.jpg" width="170" alt="E2-0"> | <img src="pengujian/petugas_lapangan/E2-3.jpg" width="170" alt="E2-3"> |

| **E2-2** Izin kamera diminta dulu | **E2-2b** Foto kegiatan dari kamera terpasang | **E2-1** Tugas lapangan terkirim, kembali ke Beranda | **E2-1b** Tugas lapangan baru tampil dengan status Menunggu Konfirmasi |
|---|---|---|---|
| <img src="pengujian/petugas_lapangan/E2-2.jpg" width="170" alt="E2-2"> | <img src="pengujian/petugas_lapangan/E2-2b.jpg" width="170" alt="E2-2b"> | <img src="pengujian/petugas_lapangan/E2-1.jpg" width="170" alt="E2-1"> | <img src="pengujian/petugas_lapangan/E2-1b.jpg" width="170" alt="E2-1b"> |

| **E1-2** Check-in di Puskesmas diterima | **B-19** Kembali ke halaman login |
|---|---|
| <img src="pengujian/petugas_lapangan/E1-2.jpg" width="170" alt="E1-2"> | <img src="pengujian/petugas_lapangan/B-19.jpg" width="170" alt="B-19"> |

| ID | Yang diuji | Hasil |
|---|---|---|
| E1-1 | Login berhasil, profil dan shift Pagi sesuai data admin | ✅ Lulus |
| E3-1 | Check-in ditolak di luar radius, ditawari Mode Tugas Lapangan | ✅ Lulus |
| E2-0 | Formulir tugas lapangan memakai GPS asli (bukan lokasi contoh) | ✅ Lulus |
| E2-3 | Tanpa nama lokasi tidak terkirim, tetap di formulir | ✅ Lulus |
| E2-2 | Izin kamera diminta dulu | ✅ Lulus |
| E2-2b | Foto kegiatan dari kamera terpasang | ✅ Lulus |
| E2-1 | Tugas lapangan terkirim, kembali ke Beranda | ✅ Lulus |
| E2-1b | Tugas lapangan baru tampil dengan status Menunggu Konfirmasi | ✅ Lulus |
| E1-2 | Check-in di Puskesmas diterima | ✅ Lulus |
| B-19 | Kembali ke halaman login | ✅ Lulus |

## Notifikasi HP dan sinkronisasi otomatis

Keputusan admin langsung muncul sebagai notifikasi di HP walaupun aplikasi ditutup (Firebase Cloud Messaging), dengan cadangan pengecekan berkala. Absensi yang tersimpan saat offline terkirim di latar belakang.

**Fitur yang diuji:**

- Notifikasi seketika setelah admin memutuskan (Firebase)
- Notifikasi diketuk membuka halaman Notifikasi
- Check-out saat tidak ada internet disimpan di antrean HP
- Antrean terkirim di latar belakang tanpa membuka aplikasi

▶️ Video notifikasi seketika (Firebase): [7_notifikasi_firebase.mp4](videos/7_notifikasi_firebase.mp4)  
▶️ Video notifikasi cadangan dan sinkronisasi: [6_notifikasi_dan_sinkronisasi.mp4](videos/6_notifikasi_dan_sinkronisasi.mp4)  

| **N-4** Notifikasi muncul 3 detik setelah admin memutuskan | **N-5** Halaman Notifikasi terbuka | **N-1** Notifikasi 'Izin ditolak' beserta catatan admin muncul di HP | **N-2** Notifikasi diketuk langsung membuka halaman Notifikasi |
|---|---|---|---|
| <img src="pengujian/notifikasi/N-4.jpg" width="170" alt="N-4"> | <img src="pengujian/notifikasi/N-5.jpg" width="170" alt="N-5"> | <img src="pengujian/notifikasi/N-1.jpg" width="170" alt="N-1"> | <img src="pengujian/notifikasi/N-2.jpg" width="170" alt="N-2"> |

| **F-1** Check-out disimpan di antrean HP | **F-3** Beranda: jam check-out terisi, status Semua terkirim |
|---|---|
| <img src="pengujian/notifikasi/F-1.jpg" width="170" alt="F-1"> | <img src="pengujian/notifikasi/F-3.jpg" width="170" alt="F-3"> |

| ID | Yang diuji | Hasil |
|---|---|---|
| N-4 | Notifikasi muncul 3 detik setelah admin memutuskan | ✅ Lulus |
| N-5 | Halaman Notifikasi terbuka | ✅ Lulus |
| N-1 | Notifikasi 'Izin ditolak' beserta catatan admin muncul di HP | ✅ Lulus |
| N-2 | Notifikasi diketuk langsung membuka halaman Notifikasi | ✅ Lulus |
| F-1 | Check-out disimpan di antrean HP | ✅ Lulus |
| F-4 | Antrean terkirim di latar belakang tanpa membuka aplikasi | ✅ Lulus |
| F-3 | Beranda: jam check-out terisi, status Semua terkirim | ✅ Lulus |

## Integrasi aplikasi dan Admin Web

Keputusan admin di Admin Web dicek dari HP setiap pegawai.

**Fitur yang diuji:**

- Notifikasi dan status izin pegawai non-shift
- Notifikasi koreksi ditolak dan cuti diterima pegawai shift
- Status tugas lapangan petugas lapangan

▶️ Video pengujian: [5_integrasi_aplikasi_web.mp4](videos/5_integrasi_aplikasi_web.mp4)  

| **G-7** Pegawai non-shift menerima notifikasi izin keluar diterima beserta catatan admin | **G-7b** Status izin keluar pegawai non-shift berubah menjadi Diterima | **D3-4** Pegawai shift menerima notifikasi koreksi ditolak dengan catatan admin | **D4-2** Pegawai shift menerima notifikasi cuti diterima |
|---|---|---|---|
| <img src="pengujian/integrasi/G-7.jpg" width="170" alt="G-7"> | <img src="pengujian/integrasi/G-7b.jpg" width="170" alt="G-7b"> | <img src="pengujian/integrasi/D3-4.jpg" width="170" alt="D3-4"> | <img src="pengujian/integrasi/D4-2.jpg" width="170" alt="D4-2"> |

| **E2-4** Tugas lapangan petugas berstatus Diterima |
|---|
| <img src="pengujian/integrasi/E2-4.jpg" width="170" alt="E2-4"> |

| ID | Yang diuji | Hasil |
|---|---|---|
| G-7 | Pegawai non-shift menerima notifikasi izin keluar diterima beserta catatan admin | ✅ Lulus |
| G-7b | Status izin keluar pegawai non-shift berubah menjadi Diterima | ✅ Lulus |
| D3-4 | Pegawai shift menerima notifikasi koreksi ditolak dengan catatan admin | ✅ Lulus |
| D4-2 | Pegawai shift menerima notifikasi cuti diterima | ✅ Lulus |
| E2-4 | Tugas lapangan petugas berstatus Diterima | ✅ Lulus |

## Data uji

Data uji dibuat lewat API dan Admin Web yang sama dengan pemakaian nyata: pegawai non-shift, pegawai shift,
petugas lapangan, shift Pagi/Siang/Malam, roster November 2026, riwayat absensi, serta pengajuan izin, koreksi, dan
tugas lapangan. Ganti semua sandi `simpres123` sebelum aplikasi dipakai sungguhan.
