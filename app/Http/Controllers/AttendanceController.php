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

use App\Services\FraudDetectionService;

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
        $today = Carbon::today()->toDateString();
        // 1. CEK HARI LIBUR (Dari Tabel Holidays)
        $holiday = Holiday::whereDate('date', $today)->first();
        if ($holiday) {
            return response()->json([
                'message' => "Hari ini adalah {$holiday->description}. Anda tidak perlu melakukan presensi.",
                'status'  => 'libur'
            ], 200);
        }

        // 2. CEK DUPLIKAT
        if ($user->attendanceLogs()->whereDate('check_in', $today)->exists()) {
            return response()->json(['message' => 'Anda sudah melakukan check-in hari ini.'], 409);
        }

        // 3. CEK IZIN DINAS
        $hasOnDutyAuth = OnDutyAuthorization::where('user_id', $user->id)
            ->where('status', 'approved') 
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->exists();

        // 4. CEK RADIUS MANUAL
        if (!$hasOnDutyAuth) {
            $officeLat = env('OFFICE_LATITUDE');
            $officeLon = env('OFFICE_LONGITUDE');
            $allowedRadius = env('ALLOWED_RADIUS_METERS');

            if ($officeLat && $officeLon && $allowedRadius) {
                $distanceKm = $this->calculateDistance(
                    (float) $request->latitude,
                    (float) $request->longitude,
                    (float) $officeLat,
                    (float) $officeLon
                );
                
                $distanceMeters = $distanceKm * 1000;

                if ($distanceMeters > (float) $allowedRadius) {
                    return response()->json([
                        'message'  => 'Anda berada di luar radius lokasi kerja yang diizinkan.',
                        'distance' => round($distanceMeters, 0) . ' meter'
                    ], 403);
                }
            }
        }

        // 5. HITUNG FRAUD SCORE
        $fraudService = new FraudDetectionService();
        $analysis = $fraudService->analyzeCheckIn(
            $user, 
            $request->latitude, 
            $request->longitude,
            $request->header('User-Agent')
        );

        // 6. CEK JADWAL SHIFT
        $todaySchedule = UserSchedule::where('user_id', $user->id)
            ->where('date', $today)
            ->with('shift')
            ->first();

        $status = 'Tepat Waktu';
        $currentTime = now();

        if ($todaySchedule) {
            $entryDeadline = Carbon::parse($todaySchedule->shift->start_time)->addMinutes(30);
            if ($currentTime->isAfter($entryDeadline)) {
                $status = 'Terlambat';
            }
        } else {
            $entryDeadline = Carbon::today()->setHour(9)->setMinute(0); 
            if ($currentTime->isAfter($entryDeadline)) {
                $status = 'Terlambat';
            }
        }

        // 7. SIMPAN DATA
        $log = $user->attendanceLogs()->create([
            'check_in'    => now(),
            'status'      => $status,
            'latitude'    => $request->latitude,
            'longitude'   => $request->longitude,
            
            // Simpan Data AI
            'risk_score'  => $analysis['score'],
            'risk_note'   => $analysis['note'],
            'device_info' => $request->header('User-Agent'),
        ]);

        return response()->json([
            'message' => "Check-in berhasil. Status: {$status}",
            'data' => $log
        ], 201);
    }

    // Fungsi Helper Jarak
    private function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371; 
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earthRadius * $c;
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

    public function logs(Request $request)
    {
        $query = AttendanceLog::with('user:id,name,email')->latest();

        if ($request->has('name')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->name . '%');
            });
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            try {
                 $startDate = Carbon::parse($request->start_date)->startOfDay();
                 $endDate = Carbon::parse($request->end_date)->endOfDay();
                 $query->whereBetween('check_in', [$startDate, $endDate]);
            } catch (\Exception $e) {
                 \Log::warning('Format tanggal filter logs salah: ' . $e->getMessage());
            }
        }

        if ($request->has('status') && in_array($request->status, ['Tepat Waktu', 'Terlambat'])) {
            $query->where('status', $request->status);
        }

        return $query->paginate(20);
    }

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

        $joinDate = Carbon::parse($user->created_at)->startOfDay();

        $logs = $user->attendanceLogs()
            ->whereMonth('check_in', $month)
            ->whereYear('check_in', $year)
            ->get()
            ->keyBy(fn($log) => Carbon::parse($log->check_in)->format('Y-m-d'));

        $leaves = $user->leaveRequests()->where('status', 'approved')->get();

        $holidays = Holiday::whereMonth('date', $month)
            ->whereYear('date', $year)
            ->get()
            ->keyBy(fn($holiday) => Carbon::parse($holiday->date)->format('Y-m-d'));

        $period = CarbonPeriod::create(
            Carbon::create($year, $month)->startOfMonth(),
            Carbon::create($year, $month)->endOfMonth()
        );

        $calendarData = [];
        $dailyStatuses = [];

        foreach ($period as $date) {

            $dateString = $date->format('Y-m-d');

            // ⛔ Abaikan semua tanggal sebelum karyawan dibuat
            if ($date->lt($joinDate)) {
                continue;
            }

            $day = [
                'date' => $dateString,
                'status' => null,
            ];

            if ($logs->has($dateString)) {
                $day['status'] = $logs[$dateString]->status;

            } elseif ($holidays->has($dateString)) {
                $day['status'] = 'Libur';

            } elseif ($date->isWeekend()) {
                $day['status'] = 'Libur';

            } else {
                $isOnLeave = $leaves->first(function ($leave) use ($date) {
                    $range = explode(' - ', $leave->duration);
                    return count($range) === 2 &&
                        $date->between(
                            Carbon::parse($range[0]),
                            Carbon::parse($range[1])
                        );
                });

                if ($isOnLeave) {
                    $day['status'] = 'Cuti';
                } elseif ($date->isPast() || $date->isToday()) {
                    $day['status'] = 'Alfa';
                }
            }

            $calendarData[] = $day;

            if ($day['status']) {
                $dailyStatuses[] = $day['status'];
            }
        }

        return response()->json([
            'calendar' => $calendarData,
            'summary' => array_count_values($dailyStatuses),
            'join_date_used' => $joinDate->toDateString()
        ]);
    }


}