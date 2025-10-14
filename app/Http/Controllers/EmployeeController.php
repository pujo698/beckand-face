<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class EmployeeController extends Controller
{
    /*
    // --- DIKOMENTARI: Bagian Python tidak lagi digunakan ---
    protected $pythonApiUrl;

    public function __construct()
    {
        $this->pythonApiUrl = env('PYTHON_API_URL', 'http://127.0.0.1:5000');
    }
    */

    /**
     * Menampilkan data profil karyawan yang login & status absensi hari ini.
     */
    public function profile()
    {
        $user = Auth::user();
        $attendanceToday = $user->attendanceLogs()
            ->whereDate('check_in', Carbon::today())->first();

        $duration = null;
        // Hitung durasi hanya jika sudah check-in dan check-out
        if ($attendanceToday && $attendanceToday->check_out) {
            $checkInTime = Carbon::parse($attendanceToday->check_in);
            $checkOutTime = Carbon::parse($attendanceToday->check_out);
            
            // Menghitung selisih waktu dan memformatnya
            $duration = $checkInTime->diff($checkOutTime)->format('%h jam %i menit');
        }

        return response()->json([
            'user' => $user,
            'attendance_status_today' => $attendanceToday,
            'duration_today' => $duration // <-- Data baru untuk durasi kerja
        ]);
    }

    /**
     * Mengupdate profil (termasuk foto wajah).
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'name'  => 'sometimes|string|max:255',
            'phone' => 'sometimes|nullable|string',
            'photo' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $userData = $request->only(['name', 'phone']);

        // DIAKTIFKAN KEMBALI: Logika untuk menyimpan foto tetap berjalan.
        if ($request->hasFile('photo')) {
            // Hapus foto lama jika ada
            if ($user->photo) {
                Storage::disk('public')->delete($user->photo);
            }
            // Simpan foto baru
            $path = $request->file('photo')->store('photos', 'public');
            $userData['photo'] = $path;
        }

        /*
        // DIKOMENTARI: Bagian yang terhubung ke Python tetap nonaktif.
        try {
            $photoContents = file_get_contents($request->file('photo')->getRealPath());
            Http::attach('file', $photoContents, $request->file('photo')->getClientOriginalName())
                ->asMultipart()->post($this->pythonApiUrl . '/api/register_face', ['user_id' => $user->id]);
        } catch (\Exception $e) {
            Log::warning('Gagal mengupdate data wajah di API Python: ' . $e->getMessage());
        }
        */

        $user->update($userData);
        return response()->json(['message' => 'Profil berhasil diperbarui', 'user' => $user]);
    }
    /**
     * Menghitung statistik bulanan untuk karyawan.
     */
    public function monthlyStats(Request $request)
    {
        $user = Auth::user();
        $month = $request->query('month', now()->month);
        $year = $request->query('year', now()->year);

        // Menghitung jumlah hari terlambat
        $lateDays = $user->attendanceLogs()
            ->whereMonth('check_in', $month)
            ->whereYear('check_in', $year)
            ->where('status', 'Terlambat')
            ->count();
        
        // Menghitung jumlah hari cuti/sakit/izin yang disetujui
        $leaveDays = $user->leaveRequests()
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->where('status', 'approved')
            ->count();
            
        // Logika untuk Alfa (tidak absen padahal ada jadwal) lebih kompleks,
        // untuk sementara kita beri nilai statis.
        $absentDays = 1; // Contoh

        return response()->json([
            'terlambat' => $lateDays,
            'cuti' => $leaveDays,
            'alfa' => $absentDays,
            'total_hari_kerja' => 22 // Contoh, bisa dibuat dinamis
        ]);
    }
}