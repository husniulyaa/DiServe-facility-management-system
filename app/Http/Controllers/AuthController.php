<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', strtolower($request->email))->first();

        $isValid = $user && Hash::check($request->password, $user->password);

        // Email tidak ditemukan atau password salah
        if (!$user || !$isValid) {
            return response()->json([
                'message' => 'Email atau password salah.',
            ], 401);
        }

        // Akun belum aktif
        if (!$user->isActive()) {
            return response()->json([
                'message' => 'Akun belum aktif atau sedang dinonaktifkan. Silakan hubungi admin.',
            ], 403);
        }

        // Hapus token lama agar login menggunakan token terbaru
        $user->tokens()->delete();

        // Buat token Sanctum
        $expiresAt = now()->addMinutes(120);
        $token = $user->createToken('auth_token', ['*'], $expiresAt)->plainTextToken;

        // Front-end login.js expects 'user' for pengguna, 'petugas' for petugas, 'admin' for admin
        $normalizedRole = strtolower($user->role);
        $frontendRole = in_array($normalizedRole, ['pengguna', 'user']) ? 'user' : $normalizedRole;

        return response()->json([
            'message' => 'Login berhasil.',
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'identity_number' => $user->identity_number,
                'email' => $user->email,
                'role' => $frontendRole,
                'raw_role' => $user->role,
                'status' => $user->status,
            ],
        ], 200);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        // Ignore any role or status sent from frontend to prevent privilege escalation
        $user = User::create([
            'name' => $request->name,
            'identity_number' => $request->identity_number,
            'email' => strtolower($request->email),
            'password' => Hash::make($request->password),
            'role' => preg_match('/@(facility|facillity|officer)\.undip\.ac\.id$/i', strtolower($request->email)) ? 'petugas' : 'pengguna',
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Pendaftaran akun berhasil. Akun Anda sedang menunggu verifikasi oleh administrator.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role === 'petugas' ? 'petugas' : 'user',
                'status' => $user->status,
            ],
        ], 201);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        if (config('mail.default') === 'log') {
            return response()->json([
                'message' => 'Layanan email belum dikonfigurasi untuk mengirim ke inbox. Hubungi administrator.',
            ], 503);
        }

        $email = strtolower($request->email);
        $user = User::where('email', $email)->first();

        if ($user) {
            $token = Str::random(64);
            $createdAt = now();
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                ['token' => Hash::make($token), 'created_at' => $createdAt]
            );
            try {
                Mail::to($user->email)->send(new \App\Mail\ResetPasswordMail($user, $token));
            } catch (\Throwable $exception) {
                DB::transaction(function () use ($email, $token, $createdAt) {
                    $record = DB::table('password_reset_tokens')
                        ->where('email', $email)
                        ->where('created_at', $createdAt)
                        ->lockForUpdate()
                        ->first();

                    if ($record && Hash::check($token, $record->token)) {
                        DB::table('password_reset_tokens')->where('email', $email)->delete();
                    }
                });

                Log::error('Password reset email delivery failed.', [
                    'user_id' => $user->id,
                    'exception' => $exception::class,
                ]);
            }
        }

        return response()->json([
            'message' => 'Permintaan reset telah diproses. Jika alamat tersebut terdaftar dan email dapat dikirim, tautan reset akan masuk ke inbox. Periksa juga folder spam; jika tidak menerima email, coba lagi atau hubungi administrator.',
        ], 202);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                function ($attribute, $value, $fail) {
                    if (!preg_match('/[a-z]/', $value)
                        || !preg_match('/[A-Z]/', $value)
                        || !preg_match('/[0-9]/', $value)
                        || !preg_match('/[@$!%*?&#_]/', $value)) {
                        $fail('Password harus memiliki huruf kecil, huruf besar, angka, dan karakter khusus.');
                    }
                },
            ],
        ]);

        $email = strtolower($request->email);
        $user = DB::transaction(function () use ($email, $request) {
            $record = DB::table('password_reset_tokens')
                ->where('email', $email)
                ->lockForUpdate()
                ->first();

            if (!$record || !$record->created_at || Carbon::parse($record->created_at)->addMinutes(60)->isPast() || !Hash::check($request->token, $record->token)) {
                return null;
            }

            $user = User::where('email', $email)->lockForUpdate()->first();
            if (!$user) {
                return null;
            }

            $user->password = $request->password;
            $user->save();
            $user->tokens()->delete();
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return $user;
        });

        if (!$user) {
            return response()->json(['message' => 'Tautan reset password tidak valid atau sudah kedaluwarsa.'], 422);
        }

        return response()->json(['message' => 'Password berhasil diubah. Silakan masuk dengan password baru.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $frontendRole = in_array($user->role, ['pengguna', 'user']) ? 'user' : $user->role;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'identity_number' => $user->identity_number,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $frontendRole,
                'raw_role' => $user->role,
                'status' => $user->status,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }
}