<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Menangani permintaan login dan membuat API token.
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
                'email' => ['Credentials do not match.'],
            ]);
        }

        $token = $user->createToken('api-token-' . $user->id)->plainTextToken;
        return response()->json(['user' => $user, 'token' => $token]);
    }

    /**
     * Menangani permintaan logout.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout successful.']);
    }

    /**
     * Menangani permintaan reset password.
     */
    public function requestPasswordReset(Request $request)
    {
        // Validasi input dari form "Lupa Password"
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'phone' => 'required|string', // Contoh validasi data diri (misal: No. Telepon)
            // Tambahkan validasi lain jika perlu (misal: NIK)
        ]);

        // Verifikasi apakah email dan telepon cocok dengan user yang sama
        $user = User::where('email', $request->email)
                    ->where('phone', $request->phone) // Pastikan kolom 'phone' ada di tabel users
                    ->first();

        if (!$user) {
            return response()->json(['message' => 'Data email dan nomor telepon tidak cocok.'], 404);
        }

        // Cek apakah sudah ada permintaan pending
        $existingRequest = PasswordResetRequest::where('user_id', $user->id)
                                            ->where('status', 'pending')
                                            ->exists();

        if ($existingRequest) {
            return response()->json(['message' => 'Permintaan Anda sebelumnya masih ditinjau oleh Admin.'], 409); // 409 Conflict
        }

        // Simpan permintaan ke database
        PasswordResetRequest::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'verification_details' => 'Phone: ' . $request->phone, // Simpan info verifikasi
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Permintaan reset password telah dikirim ke Admin. Harap tunggu konfirmasi dari Admin.'
        ]);
    }
}