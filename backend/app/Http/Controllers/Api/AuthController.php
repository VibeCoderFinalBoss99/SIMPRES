<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password salah.',
            ]);
        }

        // Dicek setelah password benar, supaya status akun tidak bocor ke orang lain.
        if (! $user->is_active) {
            return response()->json([
                'message' => 'Akun Anda nonaktif. Hubungi admin Puskesmas.',
            ], 403);
        }

        $token = $user->createToken('android')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user->load('workLocation')),
        ]);
    }

    /** Lupa sandi: kirim tautan atur ulang ke email akun yang dicari lewat NIP atau email. */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['login' => 'required|string|max:255'], [
            'login.required' => 'NIP atau email wajib diisi.',
        ]);
        $login = trim($request->login);
        $user = User::where('email', $login)->orWhere('nip', $login)->first();

        if (! $user || ! $user->is_active) {
            throw ValidationException::withMessages(['login' => 'NIP atau email tidak terdaftar atau akun nonaktif.']);
        }

        $status = Password::sendResetLink(['email' => $user->email]);
        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages(['login' => 'Tautan baru saja dikirim. Tunggu 1 menit sebelum meminta lagi.']);
        }
        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages(['login' => 'Tautan gagal dikirim. Coba lagi nanti.']);
        }

        return response()->json([
            'message' => 'Tautan atur ulang kata sandi sudah dikirim.',
            'email' => $this->maskEmail($user->email),
        ]);
    }

    /** budi.santoso@gmail.com menjadi bu**********@gmail.com, supaya email lengkap tidak terlihat orang lain. */
    private function maskEmail(string $email): string
    {
        [$name, $domain] = explode('@', $email, 2);

        return mb_substr($name, 0, 2).str_repeat('*', max(mb_strlen($name) - 2, 1)).'@'.$domain;
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()->load('workLocation')),
        ]);
    }
}
