<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PasswordResetRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRules;

class AuthController extends Controller
{
    /**
     * Login dan buat token API.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password tidak sesuai.'],
            ]);
        }

        $token = $user->createToken('api-token-' . $user->id)->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ]);
    }

    /**
     * Logout dan hapus token saat ini.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout berhasil.'
        ]);
    }

    /**
     * Kirim permintaan reset password ke Admin.
     */
    public function requestPasswordReset(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'phone' => 'required|string',
        ]);

        $user = User::where('email', $request->email)
                    ->where('phone', $request->phone)
                    ->first();

        if (!$user) {
            return response()->json([
                'message' => 'Data email dan nomor telepon tidak cocok.'
            ], 404);
        }

        $existingRequest = PasswordResetRequest::where('user_id', $user->id)
                                               ->where('status', 'pending')
                                               ->exists();

        if ($existingRequest) {
            return response()->json([
                'message' => 'Permintaan sebelumnya masih ditinjau oleh Admin.'
            ], 409);
        }

        PasswordResetRequest::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'verification_details' => 'Phone: ' . $request->phone,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Permintaan reset password dikirim ke Admin.'
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        // Mengirim link (yang sudah kita kustomisasi di AuthServiceProvider)
        $status = Password::broker()->sendResetLink(
            $request->only('email')
        );

        if ($status == Password::RESET_LINK_SENT) {
            return response()->json(['message' => 'Link reset password telah dikirim ke email Anda.'], 200);
        }

        // Gagal (misal: terlalu cepat meminta ulang)
        return response()->json(['message' => $status], 400);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email|exists:users,email',
            'password' => ['required', 'confirmed', PasswordRules::defaults()], // Cek password & password_confirmation
        ]);

        // Coba reset menggunakan broker
        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                // Callback ini dieksekusi jika token valid
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60)); // Ganti token login

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status == Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Password Anda telah berhasil direset.'], 200);
        }

        // Gagal (token invalid, email salah, dll.)
        return response()->json(['message' => $status], 400);
    }
}
