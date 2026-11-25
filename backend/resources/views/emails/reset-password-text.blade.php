SIMPRES - Sistem Presensi {!! $office !!}

ATUR ULANG KATA SANDI

Halo {!! $user->name !!},

Kami menerima permintaan untuk mengatur ulang kata sandi akun SIMPRES Anda.
Buka alamat berikut untuk membuat kata sandi baru:

{!! $url !!}

Akun: {!! $user->email !!}
@if ($user->nip)
NIP: {!! $user->nip !!}
@endif
Waktu permintaan: {!! $requestedAt !!}
Berlaku sampai: pukul {!! $expiresAt !!} ({!! $minutes !!} menit)

Untuk keamanan akun Anda:
- Tautan hanya bisa dipakai satu kali. Jika meminta lagi, tautan lama tidak berlaku.
- Jangan berikan tautan ini kepada siapa pun, termasuk petugas Puskesmas.
- Setelah kata sandi diganti, semua HP yang masih login akan diminta masuk ulang.
- Jika Anda tidak meminta pengaturan ulang, abaikan email ini. Kata sandi Anda tidak berubah.

--
Email ini dikirim oleh sistem SIMPRES karena ada permintaan atur ulang kata sandi untuk akun {!! $user->email !!}.
Untuk bantuan, hubungi admin kepegawaian {!! $office !!}.
{!! $office !!}@if ($address) - {!! $address !!}@endif

