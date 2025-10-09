<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    protected $pythonApiUrl;

    public function __construct()
    {
        // Ambil URL dari file .env, dengan default jika tidak ada
        $this->pythonApiUrl = env('PYTHON_API_URL', 'http://127.0.0.1:5000');
    }

    /**
     * Menampilkan daftar semua karyawan.
     */
    public function index()
    {
        return User::where('role', 'employee')->latest()->get();
    }

    /**
     * Menyimpan karyawan baru.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|string|email|max:255|unique:users',
            'position'   => 'required|string|max:255',         // <-- BARIS DITAMBAHKAN
            'status'     => 'required|in:active,inactive',     // <-- BARIS DITAMBAHKAN
            'phone'      => 'nullable|string',
            'password'   => 'required|string|min:8',
            'photo'      => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $path = $request->file('photo')->store('photos', 'public');

        $user = User::create([
            'name'       => $request->name,
            'email'      => $request->email,
            'phone'      => $request->phone,
            'password'   => Hash::make($request->password),
            'role'       => 'employee',
            'position'   => $request->position,                // <-- BARIS DITAMBAHKAN
            'status'     => $request->status,                  // <-- BARIS DITAMBAHKAN
            'photo'      => $path,
        ]);

        // --- BAGIAN INTEGRASI PYTHON ---
        
        try {
            $photoContents = file_get_contents($request->file('photo')->getRealPath());
            $response = Http::attach('file', $photoContents, $request->file('photo')->getClientOriginalName())
                ->asMultipart()->post($this->pythonApiUrl . '/api/register_face', [
                    'user_id' => $user->id, // Kirim ID user dari Laravel
                ]);

            if (!$response->successful()) {
                // Rollback: Hapus user & foto jika registrasi AI gagal
                $user->delete();
                Storage::disk('public')->delete($path);
                Log::error('Python API Error: ' . $response->body());
                return response()->json(['message' => 'Gagal mendaftarkan wajah ke layanan AI'], 500);
            }
        } catch (\Exception $e) {
            // Rollback jika API Python tidak aktif
            $user->delete();
            Storage::disk('public')->delete($path);
            Log::error('Tidak dapat terhubung ke API Python: ' . $e->getMessage());
            return response()->json(['message' => 'Tidak dapat terhubung ke layanan verifikasi'], 503);
        }
        
        return response()->json($user, 201);
    }

    /**
     * Mengupdate data karyawan.
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'position' => 'required|string|max:255',         // <-- BARIS DITAMBAHKAN
            'status'   => 'required|in:active,inactive',     // <-- BARIS DITAMBAHKAN
            'phone'    => 'nullable|string',
        ]);

        // <-- BAGIAN DIUBAH: Tambahkan 'position' dan 'status' ke update -->
        $user->update($request->only(['name', 'email', 'phone', 'position', 'status']));

        // Jika ada foto baru, update juga di Laravel
        if ($request->hasFile('photo')) {
            $request->validate(['photo' => 'image|mimes:jpeg,png,jpg|max:2048']);
            if ($user->photo) Storage::disk('public')->delete($user->photo);
            $path = $request->file('photo')->store('photos', 'public');
            $user->update(['photo' => $path]);

            // --- BAGIAN INTEGRASI PYTHON ---
            
            try {
                $photoContents = file_get_contents($request->file('photo')->getRealPath());
                Http::attach('file', $photoContents, $request->file('photo')->getClientOriginalName())
                    ->asMultipart()->post($this->pythonApiUrl . '/api/register_face', ['user_id' => $user->id]);
            } catch (\Exception $e) {
                Log::warning('Gagal mengupdate data wajah di API Python: ' . $e->getMessage());
            }
        }

        return response()->json($user);
    }

    /**
     * Menghapus karyawan.
     */
    public function destroy(User $user)
    {
        if ($user->photo) {
            Storage::disk('public')->delete($user->photo);
        }
        $user->delete();
        // Disarankan: buat endpoint di Python untuk menghapus encoding juga
        return response()->json(['message' => 'Karyawan berhasil dihapus.']);
    }

    /**
     * Menampilkan data user spesifik.
     */
    public function show(User $user)
    {
        return response()->json($user);
    }
}