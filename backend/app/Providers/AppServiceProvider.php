<?php

namespace App\Providers;

use App\Models\WorkLocation;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Tampilan pagination Admin Web memakai public/css/admin.css (tanpa Tailwind).
        Paginator::defaultView('admin.pagination');

        // Email lupa sandi berbahasa Indonesia, versi HTML dan teks biasa (email dengan dua versi lebih jarang
        // dianggap spam). Tautannya membuka halaman web untuk membuat sandi baru.
        ResetPassword::toMailUsing(function ($user, string $token) {
            $minutes = (int) config('auth.passwords.users.expire');
            $location = WorkLocation::first();

            return (new MailMessage)
                ->subject('Atur ulang kata sandi akun SIMPRES Anda')
                ->view(['html' => 'emails.reset-password', 'text' => 'emails.reset-password-text'], [
                    'user' => $user,
                    'url' => route('password.reset', ['token' => $token, 'email' => $user->email]),
                    'minutes' => $minutes,
                    'requestedAt' => now()->translatedFormat('l, d F Y, H.i').' WITA',
                    'expiresAt' => now()->addMinutes($minutes)->format('H.i').' WITA',
                    'office' => $location?->name ?? 'UPTD Puskesmas Simbang',
                    'address' => $location?->address,
                ]);
        });
    }
}
