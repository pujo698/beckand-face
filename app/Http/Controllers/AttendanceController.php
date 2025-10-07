<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\UserSchedule;


class AttendanceController extends Controller
{
    protected $pythonApiUrl;

    public function __construct()
    {
        $this->pythonApiUrl = env('PYTHON_API_URL', 'http://127.0.0.1:5000');
    }

    /**
     * Fungsi private untuk mengirim gambar ke API Python.
     */
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

    /**
     * Menangani absensi masuk (check-in).
     */
    public function checkIn(Request $request)
    {
        $request->validate(['photo' => 'required|image']);
        $user = Auth::user();
        
        if ($user->attendanceLogs()->whereDate('check_in', Carbon::today())->exists()) {
            return response()->json(['message' => 'Anda sudah melakukan check-in hari ini.'], 409);
        }
        $todaySchedule = UserSchedule::where('user_id', $user->id)
            ->where('date', Carbon::today())
            ->with('shift')->first();

        if (!$todaySchedule) {
            return response()->json(['message' => 'Anda tidak memiliki jadwal kerja hari ini.'], 403);
        }

        $response = $this->verifyFace($request);
        if ($response === null) {
            return response()->json(['message' => 'Tidak dapat terhubung ke layanan verifikasi.'], 503);
        }
        if (!$response->successful() || $response->json('user_id') != $user->id) {
            return response()->json(['message' => 'Verifikasi wajah gagal.', 'details' => $response->json()], 401);
        }
        
        // Tentukan batas waktu masuk (jam 8 pagi)
        $entryDeadline = Carbon::parse($todaySchedule->shift->start_time);
        $currentTime = now();
        $status = ($currentTime->isAfter($entryDeadline)) ? 'Terlambat' : 'Tepat Waktu';

        $log = AttendanceLog::create([
            'user_id' => $user->id,
            'check_in' => $currentTime,
            'status' => $status
        ]);
        
        return response()->json(['message' => 'Check-in berhasil. Status: ' . $status, 'data' => $log], 201);
    }

    /**
     * Menangani absensi keluar (check-out).
     */
    public function checkOut(Request $request)
    {
        $request->validate(['photo' => 'required|image']);
        $user = Auth::user();

        $log = $user->attendanceLogs()->whereDate('check_in', Carbon::today())->whereNull('check_out')->first();
        if (!$log) {
            return response()->json(['message' => 'Tidak ditemukan data check-in aktif untuk hari ini.'], 404);
        }

        $response = $this->verifyFace($request);
        if ($response === null) {
            return response()->json(['message' => 'Tidak dapat terhubung ke layanan verifikasi.'], 503);
        }
        if (!$response->successful() || $response->json('user_id') != $user->id) {
            return response()->json(['message' => 'Verifikasi wajah gagal.', 'details' => $response->json()], 401);
        }
        
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