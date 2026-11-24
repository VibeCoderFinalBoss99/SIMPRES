<?php

namespace App\Providers;

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

        // Email lupa sandi berbahasa Indonesia. Tautannya membuka halaman web untuk membuat sandi baru.
        ResetPassword::toMailUsing(function ($user, string $token) {
            $url = route('password.reset', ['token' => $token, 'email' => $user->email]);

            return (new MailMessage)
                ->subject('Atur Ulang Kata Sandi SIMPRES')
                ->greeting('Halo, '.$user->name)
                ->line('Kami menerima permintaan untuk mengatur ulang kata sandi akun SIMPRES Anda.')
                ->action('Buat Kata Sandi Baru', $url)
                ->line('Tautan ini berlaku '.config('auth.passwords.users.expire').' menit.')
                ->line('Jika Anda tidak meminta, abaikan email ini. Kata sandi Anda tidak berubah.')
                ->salutation('Salam, UPTD Puskesmas Simbang');
        });
    }
}
