<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Atur ulang kata sandi akun SIMPRES</title>
</head>
<body style="margin:0;padding:0;background:#f3f5f9;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
{{-- Teks pratinjau yang tampil di daftar email. --}}
<div style="display:none;max-height:0;overflow:hidden;">Tautan untuk membuat kata sandi baru akun SIMPRES Anda, berlaku {{ $minutes }} menit.</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f5f9;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;border:1px solid #e3e8f2;">
                <tr>
                    <td style="padding:24px 28px;border-bottom:1px solid #e3e8f2;">
                        <table role="presentation" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="padding-right:12px;"><img src="{{ asset('img/logo.png') }}" width="44" height="44" alt="SIMPRES" style="display:block;border-radius:10px;"></td>
                                <td>
                                    <div style="font-size:17px;font-weight:bold;color:#1b3f8f;">SIMPRES</div>
                                    <div style="font-size:12px;color:#6b7280;">Sistem Presensi {{ $office }}</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px;">
                        <h1 style="margin:0 0 16px;font-size:20px;color:#111827;">Atur ulang kata sandi</h1>
                        <p style="margin:0 0 14px;font-size:15px;line-height:1.6;">Halo {{ $user->name }},</p>
                        <p style="margin:0 0 18px;font-size:15px;line-height:1.6;">
                            Kami menerima permintaan untuk mengatur ulang kata sandi akun SIMPRES Anda.
                            Tekan tombol di bawah ini untuk membuat kata sandi baru.
                        </p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f8fc;border-radius:8px;margin:0 0 22px;">
                            <tr><td style="padding:14px 16px;font-size:13px;line-height:1.8;color:#374151;">
                                <strong>Akun:</strong> {{ $user->email }}<br>
                                @if ($user->nip)<strong>NIP:</strong> {{ $user->nip }}<br>@endif
                                <strong>Waktu permintaan:</strong> {{ $requestedAt }}<br>
                                <strong>Berlaku sampai:</strong> pukul {{ $expiresAt }} ({{ $minutes }} menit)
                            </td></tr>
                        </table>

                        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 22px;">
                            <tr>
                                <td style="border-radius:8px;background:#1b3f8f;">
                                    <a href="{{ $url }}" target="_blank" style="display:inline-block;padding:13px 26px;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;">Buat kata sandi baru</a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0 0 6px;font-size:13px;color:#6b7280;">Jika tombol tidak bisa ditekan, salin alamat berikut ke browser:</p>
                        <p style="margin:0 0 22px;font-size:12px;word-break:break-all;"><a href="{{ $url }}" style="color:#1b3f8f;">{{ $url }}</a></p>

                        <p style="margin:0 0 8px;font-size:14px;font-weight:bold;color:#111827;">Untuk keamanan akun Anda</p>
                        <ul style="margin:0 0 6px;padding-left:20px;font-size:13px;line-height:1.7;color:#374151;">
                            <li>Tautan hanya bisa dipakai satu kali. Jika meminta lagi, tautan lama tidak berlaku.</li>
                            <li>Jangan berikan tautan ini kepada siapa pun, termasuk petugas Puskesmas.</li>
                            <li>Setelah kata sandi diganti, semua HP yang masih login akan diminta masuk ulang.</li>
                            <li>Jika Anda tidak meminta pengaturan ulang, abaikan email ini. Kata sandi Anda tidak berubah.</li>
                        </ul>
                    </td>
                </tr>
                <tr>
                    <td style="padding:18px 28px;background:#f9fafb;border-top:1px solid #e3e8f2;border-radius:0 0 12px 12px;font-size:12px;line-height:1.6;color:#6b7280;">
                        Email ini dikirim oleh sistem SIMPRES karena ada permintaan atur ulang kata sandi untuk akun {{ $user->email }}.
                        Untuk bantuan, hubungi admin kepegawaian {{ $office }}.<br>
                        <strong style="color:#374151;">{{ $office }}</strong>@if ($address) · {{ $address }}@endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
