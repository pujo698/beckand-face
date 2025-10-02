<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class EmployeeController extends Controller
{
    protected $pythonApiUrl;

    public function __construct()
    {
        $this->pythonApiUrl = env('PYTHON_API_URL', 'http://127.0.0.1:5000');
    }

    /**
     * Menampilkan data profil karyawan yang login & status absensi hari ini.
     */
    public function profile()
    {
        $user = Auth::user();
        $attendanceToday = $user->attendanceLogs()
            ->whereDate('check_in', Carbon::today())->first();

        return response()->json([
            'user' => $user,
            'attendance_status_today' => $attendanceToday
        ]);
    }

    /**
     * Mengupdate profil (termasuk foto wajah).
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|nullable|string',
            'photo' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $userData = $request->only(['name', 'phone']);

        if ($request->hasFile('photo')) {
            if ($user->photo) Storage::disk('public')->delete($user->photo);
            $path = $request->file('photo')->store('photos', 'public');
            $userData['photo'] = $path;

            try {
                $photoContents = file_get_contents($request->file('photo')->getRealPath());
                Http::attach('file', $photoContents, $request->file('photo')->getClientOriginalName())
                    ->asMultipart()->post($this->pythonApiUrl . '/api/register_face', ['user_id' => $user->id]);
            } catch (\Exception $e) {
                Log::warning('Gagal mengupdate data wajah di API Python: ' . $e->getMessage());
            }
        }

        $user->update($userData);
        return response()->json(['message' => 'Profil berhasil diperbarui', 'user' => $user]);
    }
}