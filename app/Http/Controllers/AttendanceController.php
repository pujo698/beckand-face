<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\OnDutyAuthorization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\UserSchedule;
use App\Traits\Haversine;

class AttendanceController extends Controller
{
    use Haversine;

    public function checkIn(Request $request)
    {
        $request->validate([
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $user = Auth::user();

        // Cek apakah karyawan punya izin dinas luar yang aktif untuk hari ini
        $hasOnDutyAuth = OnDutyAuthorization::where('user_id', $user->id)
            ->where('start_date', '<=', now()->toDateString())
            ->where('end_date', '>=', now()->toDateString())
            ->exists();
        
        // Jika TIDAK punya izin dinas, maka jalankan pemeriksaan lokasi
        if (!$hasOnDutyAuth) {
            
            $officeLat = env('OFFICE_LATITUDE');
            $officeLon = env('OFFICE_LONGITUDE');
            $allowedRadius = env('ALLOWED_RADIUS_METERS');

            $distance = $this->calculateDistance(
                $request->latitude, $request->longitude, $officeLat, $officeLon
            );

            if ($distance > $allowedRadius) {
                return response()->json(['message' => 'Anda berada di luar radius lokasi kerja yang diizinkan.'], 403);
            }
        }

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
        
        $log = $user->attendanceLogs()->create([
            'check_in'  => now(),
            'status'    => $status,
            'latitude'  => $request->latitude,
            'longitude' => $request->longitude,
        ]);
        
        return response()->json(['message' => 'Check-in berhasil. Status: ' . $status, 'data' => $log], 201);
    }

    public function checkOut(Request $request)
    {
        $user = Auth::user();

        $log = $user->attendanceLogs()->whereDate('check_in', Carbon::today())->whereNull('check_out')->first();
        if (!$log) {
            return response()->json(['message' => 'Tidak ditemukan data check-in aktif untuk hari ini.'], 404);
        }
        
        $log->update(['check_out' => now()]);
        return response()->json(['message' => 'Check-out berhasil.', 'data' => $log]);
    }

    public function logs()
    {
        return AttendanceLog::with('user:id,name,email')->latest()->paginate(20);
    }
    // Riwayat absensi user 
    public function history(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer',
        ]);

        return Auth::user()->attendanceLogs()
            ->whereMonth('check_in', $request->month)
            ->whereYear('check_in', $request->year)
            ->orderBy('check_in', 'asc')
            ->get();
    }
}