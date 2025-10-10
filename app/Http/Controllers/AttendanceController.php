<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
// use Illuminate\Support\Facades\Http; // Tidak lagi digunakan
// use Illuminate\Support\Facades\Log;   // Tidak lagi digunakan
use Carbon\Carbon;
use App\Models\UserSchedule;


class AttendanceController extends Controller
{
    /*
    // --- DIKOMENTARI: Bagian Python tidak lagi digunakan ---
    protected $pythonApiUrl;

    public function __construct()
    {
        $this->pythonApiUrl = env('PYTHON_API_URL', 'http://127.0.0.1:5000');
    }

    private function verifyFace(Request $request)
    {
        try {
            $photoContents = file_get_contents($request->file('photo')->getRealPath());
            return Http::attach('file', $photoContents, 'attendance_photo.jpg')
                ->asMultipart()->post($this->pythonApiUrl . '/api/attendance');
        } catch (\Exception $e) {
            Log::error('Tidak dapat terhubung ke API Python untuk absensi: ' . $e->getMessage());
            return null;
        }
    }
    */

    /**
     * Menangani absensi masuk (check-in) tanpa verifikasi wajah.
     */
    public function checkIn(Request $request)
    {
        // DIUBAH: Validasi foto tidak lagi diperlukan
        $request->validate([
            // Anda bisa menambahkan validasi lokasi di sini jika perlu
            // 'latitude'  => 'required|numeric',
            // 'longitude' => 'required|numeric',
        ]);

        $user = Auth::user();
        
        if ($user->attendanceLogs()->whereDate('check_in', Carbon::today())->exists()) {
            return response()->json(['message' => 'Anda sudah melakukan check-in hari ini.'], 409);
        }

        // Logika jadwal/shift tetap sama
        $todaySchedule = UserSchedule::where('user_id', $user->id)
            ->where('date', Carbon::today())
            ->with('shift')->first();

        $status = 'Tepat Waktu';
        if ($todaySchedule) {
            $entryDeadline = Carbon::parse($todaySchedule->shift->start_time);
            if (now()->isAfter($entryDeadline)) {
                $status = 'Terlambat';
            }
        } else {
            $entryDeadline = Carbon::today()->setHour(8)->setMinute(0);
            if (now()->isAfter($entryDeadline)) {
                $status = 'Terlambat';
            }
        }

        /*
        // --- DIKOMENTARI: Blok verifikasi wajah tidak lagi dipanggil ---
        $response = $this->verifyFace($request);
        if ($response === null) {
            return response()->json(['message' => 'Tidak dapat terhubung ke layanan verifikasi.'], 503);
        }
        if (!$response->successful() || $response->json('user_id') != $user->id) {
            return response()->json(['message' => 'Verifikasi wajah gagal.', 'details' => $response->json()], 401);
        }
        */
        
        $log = $user->attendanceLogs()->create([
            'check_in' => now(),
            'status'   => $status
        ]);
        
        return response()->json(['message' => 'Check-in berhasil. Status: ' . $status, 'data' => $log], 201);
    }

    /**
     * Menangani absensi keluar (check-out) tanpa verifikasi wajah.
     */
    public function checkOut(Request $request)
    {
        // DIUBAH: Validasi foto tidak lagi diperlukan
        // $request->validate(['photo' => 'required|image']);

        $user = Auth::user();

        $log = $user->attendanceLogs()->whereDate('check_in', Carbon::today())->whereNull('check_out')->first();
        if (!$log) {
            return response()->json(['message' => 'Tidak ditemukan data check-in aktif untuk hari ini.'], 404);
        }

        /*
        // --- DIKOMENTARI: Blok verifikasi wajah tidak lagi dipanggil ---
        $response = $this->verifyFace($request);
        if ($response === null) {
            return response()->json(['message' => 'Tidak dapat terhubung ke layanan verifikasi.'], 503);
        }
        if (!$response->successful() || $response->json('user_id') != $user->id) {
            return response()->json(['message' => 'Verifikasi wajah gagal.', 'details' => $response->json()], 401);
        }
        */
        
        $log->update(['check_out' => now()]);
        return response()->json(['message' => 'Check-out berhasil.', 'data' => $log]);
    }

    /**
     * Menampilkan semua log absensi (untuk Admin).
     */
    public function logs()
    {
        return AttendanceLog::with('user:id,name,email')->latest()->paginate(20);
    }
}