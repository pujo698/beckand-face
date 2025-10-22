<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\OnDutyAuthorization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Models\UserSchedule;
use App\Traits\Haversine;
use App\Models\Holiday;

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

        // Cek izin dinas luar
        $hasOnDutyAuth = OnDutyAuthorization::where('user_id', $user->id)
            ->where('start_date', '<=', now()->toDateString())
            ->where('end_date', '>=', now()->toDateString())
            ->exists();

        // Hanya cek lokasi jika tidak dinas luar
        if (!$hasOnDutyAuth) {
            $officeLat = env('OFFICE_LATITUDE');
            $officeLon = env('OFFICE_LONGITUDE');
            $allowedRadius = env('ALLOWED_RADIUS_METERS');

            if (is_null($officeLat) || is_null($officeLon) || is_null($allowedRadius)) {
                \Log::warning('OFFICE_* env missing, skip radius check.');
            } else {
                $officeLat = (float) $officeLat;
                $officeLon = (float) $officeLon;
                $allowedRadius = (float) $allowedRadius;

                $distance = $this->calculateDistance(
                    (float) $request->latitude,
                    (float) $request->longitude,
                    $officeLat,
                    $officeLon
                );

                if ($distance > $allowedRadius) {
                    return response()->json([
                        'message' => 'Anda berada di luar radius lokasi kerja yang diizinkan.',
                        'distance' => round($distance, 2)
                    ], 403);
                }
            }
        }

        // Cegah check-in ganda
        if ($user->attendanceLogs()->whereDate('check_in', now())->exists()) {
            return response()->json(['message' => 'Anda sudah melakukan check-in hari ini.'], 409);
        }

        // Tentukan status (Tepat Waktu / Terlambat)
        $todaySchedule = UserSchedule::where('user_id', $user->id)
            ->where('date', now()->toDateString())
            ->with('shift')->first();

        $status = 'Tepat Waktu';
        $currentTime = now();

        if ($todaySchedule) {
            $entryDeadline = Carbon::parse($todaySchedule->shift->start_time)->addMinutes(30);
            if ($currentTime->isAfter($entryDeadline)) {
                $status = 'Terlambat';
            }
        } else {
            $entryDeadline = now()->setHour(8)->setMinute(30);
            if ($currentTime->isAfter($entryDeadline)) {
                $status = 'Terlambat';
            }
        }

        // Simpan data absensi
        $log = $user->attendanceLogs()->create([
            'check_in'  => now(),
            'status'    => $status,
            'latitude'  => $request->latitude,
            'longitude' => $request->longitude,
        ]);

        return response()->json([
            'message' => "Check-in berhasil. Status: {$status}",
            'data' => $log
        ], 201);
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

        if ($request->has('name')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->name . '%');
            });
        }

        if ($request->has('strart_date') && $request->has('end_date')) {
            $query->whereBetween('check_in', [$request->start_date, $request->end_date]);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return $query->paginate(20);
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

    public function getAttendanceCalendar(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer',
        ]);

        $user = Auth::user();
        $month = $request->month;
        $year = $request->year;

        // Ambil semua data relevan dalam satu bulan
        $logs = $user->attendanceLogs()
            ->whereMonth('check_in', $month)->whereYear('check_in', $year)->get()->keyBy(fn($log) => Carbon::parse($log->check_in)->format('Y-m-d'));

        $leaves = $user->leaveRequests()->where('status', 'approved')->get();
        $holidays = Holiday::whereMonth('date', $month)->whereYear('date', $year)->pluck('date')->map(fn($date) => Carbon::parse($date)->format('Y-m-d'))->flip();

        $period = CarbonPeriod::create(Carbon::create($year, $month)->startOfMonth(), Carbon::create($year, $month)->endOfMonth());
        $calendarData = [];

        // Loop melalui setiap hari dalam sebulan untuk menentukan statusnya
        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            $dayData = ['date' => $dateString, 'check_in' => null, 'check_out' => null, 'status' => null];

            if ($logs->has($dateString)) {
                $log = $logs[$dateString];
                $dayData['check_in'] = $log->check_in;
                $dayData['check_out'] = $log->check_out;
                $dayData['status'] = $log->status; // 'Tepat Waktu' atau 'Terlambat'
            } else if ($holidays->has($dateString) || $date->isWeekend()) {
                $dayData['status'] = 'Libur';
            } else {
                $isOnLeave = $leaves->first(function ($leave) use ($date) {
                    $range = explode(' - ', $leave->duration);
                    if (count($range) == 2) {
                        return $date->between(Carbon::parse($range[0]), Carbon::parse($range[1]));
                    }
                    return false;
                });

                if ($isOnLeave) {
                    $dayData['status'] = $isOnLeave->type; // 'cuti' atau 'sakit' atau 'izin'
                } else if ($date->isPast() || $date->isToday()) {
                    $dayData['status'] = 'Alfa';
                }
            }
            $calendarData[] = $dayData;
        }
        return response()->json($calendarData);
    }
}