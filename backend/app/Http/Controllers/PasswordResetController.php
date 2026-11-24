<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/** Halaman web untuk membuat kata sandi baru dari tautan di email lupa sandi. */
class PasswordResetController extends Controller
{
    public function show(Request $request, string $token): View
    {
        return view('reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Ulangi kata sandi tidak sama.',
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->update(['password' => $password]);
            // Semua login lama di HP dikeluarkan, jadi harus masuk lagi dengan sandi baru.
            $user->tokens()->delete();
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Tautan tidak berlaku atau sudah kedaluwarsa. Minta tautan baru dari aplikasi.']);
        }

        return back()->with('success', 'Kata sandi berhasil diubah. Silakan masuk di aplikasi SIMPRES dengan sandi baru.');
    }
}
