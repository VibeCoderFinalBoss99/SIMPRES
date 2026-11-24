# Dokumentasi API SIMPRES

API untuk aplikasi Android SIMPRES (presensi pegawai UPTD Puskesmas Simbang).
Contoh request siap pakai ada di `tests/api.http`.

- **Alamat dasar (lokal):** `http://127.0.0.1:8000/api`
- **Format:** semua respons JSON, pesan dalam bahasa Indonesia.
- **Header wajib:** `Accept: application/json`. Untuk body JSON tambahkan `Content-Type: application/json`.
- **Login:** endpoint selain `POST /login` butuh header `Authorization: Bearer <token>`.
- **Upload file:** pakai `multipart/form-data`. Ubah data dengan file lewat `POST` + field `_method=PUT`.
- **Tanggal dan jam:** tanggal `YYYY-MM-DD`, jam `HH:MM` atau `HH:MM:SS`, waktu `YYYY-MM-DD HH:MM:SS` (zona waktu server).
- **Status pengajuan:** `menunggu` (Menunggu Konfirmasi), `diterima`, `ditolak`.
- **Status absensi:** `hadir`, `terlambat`, `izin`, `alpa`.

## Kode status

| Kode | Arti | Bentuk respons |
|---|---|---|
| 200 | Berhasil | `{"message": "...", "data": {...}}` atau `{"data": ...}` |
| 201 | Data baru berhasil dibuat | `{"message": "...", "data": {...}}` |
| 401 | Belum login, token salah, atau sudah logout | `{"message": "Anda belum login atau token tidak valid. Silakan login kembali."}` |
| 403 | Tidak punya akses (data milik orang lain, bukan admin, akun nonaktif, pengajuan sudah diproses) | `{"message": "..."}` |
| 404 | Data atau alamat tidak ditemukan | `{"message": "Data atau alamat tidak ditemukan."}` |
| 405 | Metode request salah | `{"message": "Metode request tidak diizinkan untuk alamat ini."}` |
| 422 | Data tidak valid atau absensi ditolak | `{"message": "...", "errors": {"kolom": ["pesan"]}}` |

Contoh 422 validasi:
```json
{
  "message": "Email wajib diisi. (dan 1 kesalahan lainnya)",
  "errors": {
    "email": ["Email wajib diisi."],
    "password": ["Password wajib diisi."]
  }
}
```

Daftar yang memakai halaman (`/leaves`, `/corrections`, `/field-duties`, `/notifications`) berisi 20 data per halaman
dan tambahan `links` serta `meta` (`current_page`, `last_page`, `total`). Halaman berikutnya: `?page=2`.

## Daftar endpoint (33)

| No | Metode | Alamat | Keterangan |
|---|---|---|---|
| 1 | POST | `/login` | Login, mendapat token |
| 2 | POST | `/logout` | Logout (token dihapus) |
| 3 | GET | `/me` | Data pegawai yang login |
| 4 | GET | `/attendance/today` | Status absensi hari ini |
| 5 | POST | `/attendance/check-in` | Absen masuk |
| 6 | POST | `/attendance/check-out` | Absen pulang |
| 7 | GET | `/attendance?month=YYYY-MM` | Riwayat absensi per bulan |
| 8 | GET | `/attendance/{id}` | Detail absensi milik sendiri |
| 9 | GET | `/leaves?status=` | Daftar izin milik sendiri |
| 10 | POST | `/leaves` | Ajukan izin/cuti |
| 11 | GET | `/leaves/{id}` | Detail izin |
| 12 | PUT/PATCH | `/leaves/{id}` | Ubah izin yang masih menunggu |
| 13 | DELETE | `/leaves/{id}` | Batalkan izin yang masih menunggu |
| 14 | GET | `/leaves/{id}/attachment` | File lampiran izin |
| 15 | GET | `/corrections?status=` | Daftar koreksi absensi |
| 16 | POST | `/corrections` | Ajukan koreksi absensi |
| 17 | GET | `/corrections/{id}` | Detail koreksi |
| 18 | DELETE | `/corrections/{id}` | Batalkan koreksi yang masih menunggu |
| 19 | GET | `/field-duties?status=` | Daftar tugas lapangan |
| 20 | POST | `/field-duties` | Laporkan tugas lapangan + foto |
| 21 | GET | `/field-duties/{id}` | Detail tugas lapangan |
| 22 | DELETE | `/field-duties/{id}` | Batalkan tugas lapangan yang masih menunggu |
| 23 | GET | `/field-duties/{id}/photo` | Foto tugas lapangan |
| 24 | GET | `/schedules?month=YYYY-MM` | Jadwal kerja per bulan |
| 25 | GET | `/work-location` | Titik dan radius Puskesmas |
| 26 | POST | `/device-token` | Simpan token FCM |
| 27 | GET | `/notifications` | Daftar notifikasi + jumlah belum dibaca |
| 28 | POST | `/notifications/{id}/read` | Tandai satu notifikasi dibaca |
| 29 | POST | `/notifications/read-all` | Tandai semua notifikasi dibaca |
| 30 | PATCH | `/admin/leaves/{id}` | (Admin) terima/tolak izin |
| 31 | PATCH | `/admin/corrections/{id}` | (Admin) terima/tolak koreksi |
| 32 | PATCH | `/admin/field-duties/{id}` | (Admin) terima/tolak tugas lapangan |
| 33 | POST | `/forgot-password` | Kirim tautan atur ulang kata sandi ke email (tanpa token) |

---

## 1. Login dan akun

### 1.1 `POST /login`

Request:
```json
{"email": "siti@simpres.test", "password": "simpres123"}
```

Respons 200:
```json
{
  "message": "Login berhasil.",
  "token": "2|KYiJidBrN2WXbXbQXnYkHt0BN8UxBTitIqnOsRDc605300e6",
  "token_type": "Bearer",
  "user": {
    "id": 2,
    "nip": "198501012010012001",
    "name": "Siti Aminah",
    "email": "siti@simpres.test",
    "phone": null,
    "position": "Perawat",
    "unit": "Rawat Jalan",
    "role": "pegawai",
    "work_location": {
      "id": 1, "name": "UPTD Puskesmas Simbang",
      "latitude": -5.0222565, "longitude": 119.6653682, "radius_m": 150
    }
  }
}
```

Gagal:
- 422 email atau password salah: `{"message": "Email atau password salah.", "errors": {"email": ["Email atau password salah."]}}`
- 422 kolom kosong: `{"message": "Email wajib diisi. (dan 1 kesalahan lainnya)", "errors": {...}}`
- 403 akun nonaktif: `{"message": "Akun Anda nonaktif. Hubungi admin Puskesmas."}`

Simpan `token` di HP, lalu kirim di setiap request: `Authorization: Bearer <token>`.

### 1.1b `POST /forgot-password`

Tanpa token, maksimal 5 permintaan per menit. `login` berisi NIP atau email.
```json
{"login": "198501012010012001"}
```
Respons 200 (email disamarkan):
```json
{"message": "Tautan atur ulang kata sandi sudah dikirim.", "email": "si**@simpres.test"}
```
Gagal 422: `NIP atau email tidak terdaftar atau akun nonaktif.`, `Tautan baru saja dikirim. Tunggu 1 menit sebelum meminta lagi.`

Email berisi tombol **Buat Kata Sandi Baru** ke halaman web `/reset-password/{token}` (berlaku 30 menit).
Setelah sandi diganti, semua token login pegawai itu dihapus.

### 1.2 `POST /logout`

Tanpa body. Respons 200: `{"message": "Logout berhasil."}`. Setelah itu token yang sama mendapat 401.

### 1.3 `GET /me`

Respons 200: `{"user": { ...sama seperti user di respons login... }}`. Tanpa token: 401.

---

## 2. Absensi

Keputusan sah atau tidaknya absensi dibuat di server:
1. jarak ke titik Puskesmas (rumus Haversine) maksimal `radius_m` (150 m),
2. akurasi GPS maksimal 100 m,
3. `is_mock = true` (lokasi palsu) ditolak,
4. satu check-in per hari,
5. check-out hanya setelah check-in dan belum check-out,
6. check-in setelah jam masuk shift + toleransi berstatus `terlambat`, `late_minutes` dihitung dari jam masuk shift,
7. pegawai dengan izin diterima (selain izin keluar) di hari itu tidak perlu check-in.

### 2.1 `GET /attendance/today`

Respons 200:
```json
{
  "data": {
    "date": "2026-11-17",
    "schedule": {"is_day_off": false, "shift": "Pagi", "start_time": "07:30:00", "end_time": "14:00:00"},
    "has_checked_in": false,
    "has_checked_out": false,
    "attendance": null
  }
}
```
`schedule` bernilai `null` jika hari itu tidak ada jadwal. Setelah check-in, `attendance` berisi data absensi (lihat 2.2).

### 2.2 `POST /attendance/check-in`

Request:
```json
{
  "latitude": -5.0224565,
  "longitude": 119.6653682,
  "accuracy": 10,
  "is_mock": false,
  "client_uuid": "0b7d3c1e-2f4a-4b5c-8d6e-7f8091a2b3c4",
  "device_info": "Samsung A15, Android 14"
}
```
| Kolom | Aturan |
|---|---|
| latitude | wajib, angka -90 s.d. 90 |
| longitude | wajib, angka -180 s.d. 180 |
| accuracy | wajib, angka >= 0 (meter, dari GPS HP) |
| is_mock | wajib, true/false |
| client_uuid | opsional, UUID buatan HP. Jika dikirim ulang, server mengembalikan data yang sama (200) tanpa membuat data baru |
| source | opsional, `online` (bawaan) atau `offline` jika dikirim dari antrean HP |
| device_info | opsional, maksimal 255 karakter |

Respons 201:
```json
{
  "message": "Check-in berhasil.",
  "data": {
    "id": 1,
    "date": "2026-11-17",
    "status": "hadir",
    "late_minutes": 0,
    "check_in_at": "2026-11-17 07:25:00",
    "check_in_lat": -5.0224565,
    "check_in_lng": 119.6653682,
    "check_in_accuracy_m": 10,
    "check_in_distance_m": 22.2,
    "check_out_at": null,
    "check_out_lat": null,
    "check_out_lng": null,
    "check_out_accuracy_m": null,
    "check_out_distance_m": null,
    "source": "online",
    "client_uuid": "0b7d3c1e-2f4a-4b5c-8d6e-7f8091a2b3c4"
  }
}
```
Jika terlambat: `"message": "Check-in berhasil. Anda terlambat 25 menit."`, `status` `terlambat`, `late_minutes` 25.

Ditolak (422), selalu berisi `message` dan `distance_m`:
```json
{"message": "Anda berada di luar radius Puskesmas (jarak 200 m, maksimal 150 m).", "distance_m": 200.2}
```
Pesan lain: `Akurasi GPS terlalu rendah (500 m, maksimal 100 m). ...`, `Terdeteksi lokasi palsu (fake GPS). ...`,
`Anda sudah check-in hari ini.`, `Anda memiliki izin yang sudah diterima untuk hari ini, jadi tidak perlu check-in.`,
`Lokasi kerja Anda belum diatur. Hubungi admin Puskesmas.`

### 2.3 `POST /attendance/check-out`

Body sama dengan check-in (`client_uuid` tidak dipakai). Aturan lokasi sama dengan check-in.

Respons 200: `{"message": "Check-out berhasil.", "data": { ...check_out_at dan koordinat pulang terisi... }}`

Ditolak 422: `Anda belum check-in hari ini.`, `Anda sudah check-out hari ini.`, atau pesan lokasi/akurasi/lokasi palsu.

### 2.4 `GET /attendance?month=2026-11`

Tanpa `month` = bulan ini. Respons 200 (terbaru dulu):
```json
{"data": [ { ...absensi seperti 2.2... } ], "month": "2026-11"}
```
422 jika format bulan salah: `{"errors": {"month": ["Bulan harus berformat Y-m."]}}`

### 2.5 `GET /attendance/{id}`

Respons 200: `{"data": { ...absensi... }}`. Absensi milik pegawai lain: 404.

---

## 3. Izin dan cuti

| Kolom | Aturan |
|---|---|
| type | wajib: `izin_keluar`, `sakit`, `cuti`, `lainnya` |
| start_date, end_date | wajib, `YYYY-MM-DD`, `end_date` tidak boleh sebelum `start_date` |
| start_time, end_time | wajib jika `izin_keluar`, `HH:MM`, jam selesai setelah jam mulai |
| reason | wajib, maksimal 1000 karakter |
| lampiran | file jpg/png maksimal 2 MB, wajib jika `sakit` |

Izin yang diterima admin (selain izin keluar) dicatat otomatis sebagai absensi berstatus `izin` untuk setiap harinya.

### 3.1 `GET /leaves?status=menunggu`

`status` opsional. Respons 200:
```json
{
  "data": [
    {
      "id": 1,
      "type": "cuti",
      "start_date": "2026-11-20",
      "end_date": "2026-11-21",
      "start_time": null,
      "end_time": null,
      "reason": "Acara keluarga",
      "has_attachment": false,
      "attachment_url": null,
      "status": "menunggu",
      "admin_note": null,
      "reviewed_at": null,
      "created_at": "2026-11-17 16:52:55"
    }
  ],
  "links": {"first": "...", "last": "...", "prev": null, "next": null},
  "meta": {"current_page": 1, "last_page": 1, "per_page": 20, "total": 1}
}
```

### 3.2 `POST /leaves`

JSON (tanpa file):
```json
{"type": "cuti", "start_date": "2026-11-20", "end_date": "2026-11-21", "reason": "Acara keluarga"}
```
Dengan lampiran: `multipart/form-data` berisi kolom di atas + file `lampiran`.

Respons 201: `{"message": "Pengajuan izin berhasil dikirim dan menunggu konfirmasi admin.", "data": { ...seperti 3.1... }}`.
Jika ada lampiran: `"has_attachment": true, "attachment_url": "http://.../api/leaves/2/attachment"`.

Gagal 422, contoh:
- `{"errors": {"end_date": ["Tanggal selesai tidak boleh sebelum tanggal mulai."]}}`
- `{"errors": {"lampiran": ["Izin sakit wajib melampirkan foto surat keterangan (jpg/png)."]}}`
- `{"errors": {"start_time": ["Jam mulai wajib diisi jika jenis adalah izin keluar."]}}`
- `{"errors": {"lampiran": ["Lampiran maksimal 2048 KB."]}}`

### 3.3 `GET /leaves/{id}`

Respons 200: `{"data": { ... }}`. Milik pegawai lain: 403 `{"message": "Anda tidak punya akses ke izin ini."}`.

### 3.4 `PUT /leaves/{id}`

Cukup kirim kolom yang berubah, kolom lain memakai nilai lama:
```json
{"reason": "Menghadiri pernikahan saudara"}
```
Respons 200: `{"message": "Pengajuan izin berhasil diubah.", "data": { ... }}`.
Untuk mengganti lampiran: `POST /leaves/{id}` multipart dengan `_method=PUT` dan file `lampiran`.

Gagal: 403 milik orang lain, 403 `{"message": "Izin yang sudah diproses admin tidak bisa diubah atau dibatalkan."}`, 422 validasi.

### 3.5 `DELETE /leaves/{id}`

Respons 200: `{"message": "Pengajuan izin berhasil dibatalkan."}` (lampiran ikut dihapus). Gagal: 403 seperti 3.4.

### 3.6 `GET /leaves/{id}/attachment`

Respons 200 berupa file gambar (`image/jpeg` / `image/png`), untuk pemilik dan admin.
Pegawai lain: 403. Tidak ada lampiran: 404 `{"message": "Izin ini tidak punya lampiran."}`.
File tidak bisa dibuka lewat `/storage/...` karena disimpan di storage private.

---

## 4. Koreksi absensi

| Kolom | Aturan |
|---|---|
| date | wajib, `YYYY-MM-DD`, tidak boleh setelah hari ini |
| corrected_check_in | `HH:MM`, wajib jika `corrected_check_out` kosong |
| corrected_check_out | `HH:MM`, wajib jika `corrected_check_in` kosong, harus setelah jam masuk jika keduanya diisi |
| reason | wajib, maksimal 1000 karakter |
| lampiran | opsional, jpg/png maksimal 2 MB (multipart) |

`attendance_id` diisi otomatis jika di tanggal itu sudah ada absensi. Jika koreksi diterima admin, jam di absensi
diganti; jika jam masuk dikoreksi, status hadir/terlambat dihitung ulang; jika belum ada absensi, absensi baru dibuat.

### 4.1 `GET /corrections?status=`

Respons 200 (berhalaman):
```json
{
  "data": [
    {
      "id": 1,
      "attendance_id": 1,
      "date": "2026-11-17",
      "corrected_check_in": null,
      "corrected_check_out": "14:00:00",
      "reason": "Lupa absen pulang",
      "has_attachment": false,
      "status": "menunggu",
      "admin_note": null,
      "reviewed_at": null,
      "created_at": "2026-11-17 16:56:21"
    }
  ],
  "links": {...},
  "meta": {...}
}
```

### 4.2 `POST /corrections`

```json
{"date": "2026-11-17", "corrected_check_out": "14:00", "reason": "Lupa absen pulang"}
```
Respons 201: `{"message": "Pengajuan koreksi absensi berhasil dikirim dan menunggu konfirmasi admin.", "data": {...}}`

Gagal 422: `{"errors": {"corrected_check_in": ["Isi jam masuk atau jam pulang yang dikoreksi (minimal salah satu)."], ...}}`,
`Tanggal koreksi tidak boleh setelah hari ini.`, `Jam pulang harus setelah jam masuk.`

### 4.3 `GET /corrections/{id}` dan 4.4 `DELETE /corrections/{id}`

Detail: 200 `{"data": {...}}`. Batal: 200 `{"message": "Pengajuan koreksi berhasil dibatalkan."}`.
Milik orang lain: 403 `{"message": "Anda tidak punya akses ke koreksi ini."}`.
Sudah diproses: 403 `{"message": "Koreksi yang sudah diproses admin tidak bisa dibatalkan."}`.

---

## 5. Tugas lapangan

| Kolom | Aturan |
|---|---|
| date | wajib, `YYYY-MM-DD`, tidak boleh setelah hari ini |
| location_name | wajib, maksimal 150 karakter |
| description | wajib, maksimal 2000 karakter |
| latitude, longitude | opsional, angka |
| foto | wajib, file **jpg** maksimal 2 MB |

### 5.1 `POST /field-duties` (multipart/form-data)

Kolom: `date`, `location_name`, `description`, `latitude`, `longitude`, file `foto`.

Respons 201:
```json
{
  "message": "Laporan tugas lapangan berhasil dikirim dan menunggu konfirmasi admin.",
  "data": {
    "id": 1,
    "date": "2026-11-17",
    "location_name": "Posyandu Melati",
    "description": "Imunisasi balita",
    "latitude": -5.03,
    "longitude": 119.67,
    "photo_url": "http://127.0.0.1:8000/api/field-duties/1/photo",
    "status": "menunggu",
    "admin_note": null,
    "reviewed_at": null,
    "created_at": "2026-11-17 16:56:23"
  }
}
```
Gagal 422: `Foto kegiatan wajib dilampirkan (jpg, maksimal 2 MB).`, `Foto maksimal 2048 KB.`, `Foto harus berformat jpg.`,
`Nama lokasi wajib diisi.`, `Keterangan wajib diisi.`

### 5.2 `GET /field-duties?status=`, 5.3 `GET /field-duties/{id}`, 5.4 `DELETE /field-duties/{id}`

Sama polanya dengan koreksi. Pesan 403: `Anda tidak punya akses ke tugas lapangan ini.` /
`Tugas lapangan yang sudah diproses admin tidak bisa dibatalkan.` Batal 200: `Laporan tugas lapangan berhasil dibatalkan.`

### 5.5 `GET /field-duties/{id}/photo`

Respons 200 berupa file `image/jpeg`, untuk pemilik dan admin. Pegawai lain: 403.

---

## 6. Jadwal, lokasi, token FCM

### 6.1 `GET /schedules?month=2026-11`

Respons 200 (urut tanggal):
```json
{
  "data": [
    {
      "id": 1,
      "date": "2026-11-17",
      "is_day_off": false,
      "shift": {"id": 1, "name": "Pagi", "start_time": "07:30:00", "end_time": "14:00:00", "late_tolerance_min": 10}
    },
    {"id": 2, "date": "2026-11-18", "is_day_off": true, "shift": null}
  ],
  "month": "2026-11"
}
```

### 6.2 `GET /work-location`

Respons 200:
```json
{
  "data": {
    "id": 1,
    "name": "UPTD Puskesmas Simbang",
    "address": "Simbang",
    "latitude": -5.0222565,
    "longitude": 119.6653682,
    "radius_m": 150,
    "is_active": true
  }
}
```
Belum diatur: 404 `{"message": "Lokasi kerja Anda belum diatur. Hubungi admin Puskesmas."}`. Tanpa token: 401.

### 6.3 `POST /device-token`

```json
{"fcm_token": "token-dari-firebase", "platform": "android"}
```
Respons 201 (token baru) atau 200 (token sudah ada, dipindah ke akun yang sedang login):
```json
{"message": "Token perangkat berhasil disimpan.", "data": {"id": 1, "platform": "android", "last_used_at": "2026-11-17 16:59:00"}}
```
Gagal 422: `{"errors": {"fcm_token": ["Token FCM wajib diisi."]}}`

Setiap kali admin menerima atau menolak pengajuan, server mengirim notifikasi lewat Firebase Cloud Messaging ke semua
token milik pegawai itu (`App\Services\PushNotifier`). Pesan berupa data: `id`, `type`, `title`, `body`.
Token yang ditolak Firebase (aplikasi sudah dihapus) otomatis dihapus dari server.

---

## 7. Notifikasi

Notifikasi dibuat otomatis setiap admin menerima atau menolak izin, koreksi, atau tugas lapangan.

### 7.1 `GET /notifications`

Respons 200 (terbaru dulu, berhalaman):
```json
{
  "data": [
    {
      "id": 1,
      "type": "izin",
      "title": "Izin diterima",
      "body": "Pengajuan cuti tanggal 20/11/2026 s.d. 21/11/2026 diterima oleh admin. Catatan: Selamat",
      "data": {"submission_type": "izin", "submission_id": 1, "status": "diterima"},
      "is_read": false,
      "read_at": null,
      "created_at": "2026-11-17 16:58:58"
    }
  ],
  "links": {...},
  "meta": {...},
  "unread_count": 1
}
```
`type`: `izin`, `koreksi`, atau `tugas_lapangan`. `data.submission_id` dipakai untuk membuka detail pengajuan.

### 7.2 `POST /notifications/{id}/read`

Respons 200:
```json
{"message": "Notifikasi ditandai sudah dibaca.", "data": { ...is_read true... }, "unread_count": 0}
```
Notifikasi milik orang lain: 404.

### 7.3 `POST /notifications/read-all`

Respons 200: `{"message": "3 notifikasi ditandai sudah dibaca.", "unread_count": 0}`

---

## 8. Admin (token akun role admin)

Pegawai biasa mendapat 403 `{"message": "Hanya admin yang boleh mengakses fitur ini."}`.
Admin Web (`/admin`) memakai logika yang sama.

### 8.1 `PATCH /admin/leaves/{id}`, 8.2 `PATCH /admin/corrections/{id}`, 8.3 `PATCH /admin/field-duties/{id}`

```json
{"status": "diterima", "admin_note": "Disetujui"}
```
| Kolom | Aturan |
|---|---|
| status | wajib: `diterima` atau `ditolak` |
| admin_note | opsional, maksimal 255 karakter |

Respons 200, contoh izin:
```json
{"message": "Izin diterima.", "data": { ...status "diterima", admin_note, reviewed_at terisi... }}
```
Pesan lain: `Izin ditolak.`, `Koreksi absensi diterima.`, `Tugas lapangan ditolak.`, dan seterusnya.

Gagal 422:
- `{"errors": {"status": ["Status harus diterima atau ditolak."]}}`
- `{"errors": {"status": ["Pengajuan ini sudah diputuskan sebelumnya."]}}`
