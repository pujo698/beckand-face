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
use App\Models\Holiday; // Pastikan ini ada

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

        // ✅ CEK HARI LIBUR NASIONAL ATAU CUTI BERSAMA
        $holiday = Holiday::whereDate('date', $today)->first();
        if ($holiday) {
            return response()->json([
                'message' => "Hari ini adalah {$holiday->description}. Anda tidak perlu melakukan presensi.",
                // 'type'    => $holiday->type ?? 'nasional', // Hapus jika tabel tidak punya kolom type
                'status'  => 'libur'
            ], 200);
        }

        // ✅ CEK IZIN DINAS LUAR
        $hasOnDutyAuth = OnDutyAuthorization::where('user_id', $user->id)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->exists();

        // ✅ CEK LOKASI (jika tidak dinas luar)
        if (!$hasOnDutyAuth) {
            $officeLat = env('OFFICE_LATITUDE');
            $officeLon = env('OFFICE_LONGITUDE');
            $allowedRadius = env('ALLOWED_RADIUS_METERS');

            if (is_null($officeLat) || is_null($officeLon) || is_null($allowedRadius)) {
                \Log::warning('OFFICE_* env missing, skip radius check.');
            } else {
                $distance = $this->calculateDistance(
                    (float) $request->latitude,
                    (float) $request->longitude,
                    (float) $officeLat,
                    (float) $officeLon
                );

                if ($distance > (float) $allowedRadius) {
                    return response()->json([
                        'message'  => 'Anda berada di luar radius lokasi kerja yang diizinkan.',
                        'distance' => round($distance, 2)
                    ], 403);
                }
            }
        }

        // ✅ CEGAH CHECK-IN GANDA
        if ($user->attendanceLogs()->whereDate('check_in', $today)->exists()) {
            return response()->json(['message' => 'Anda sudah melakukan check-in hari ini.'], 409);
        }

        // ✅ TENTUKAN STATUS (TEPAT WAKTU / TERLAMBAT)
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
            $entryDeadline = now()->setHour(10)->setMinute(0)->setSecond(0);
            if ($currentTime->isAfter($entryDeadline)) {
                $status = 'Terlambat';
            }
        }

        // ✅ SIMPAN DATA ABSENSI
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

    // Fungsi logs() Anda sebelumnya punya kode filter yang di-komen, saya perbaiki:
    public function logs(Request $request) // Tambahkan Request $request
    {
        $query = AttendanceLog::with('user:id,name,email')->latest(); // Mulai query

        if ($request->has('name')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->name . '%');
            });
        }

        // Koreksi typo: 'strart_date' menjadi 'start_date'
        if ($request->has('start_date') && $request->has('end_date')) {
            // Pastikan format tanggal benar sebelum query
            try {
                 $startDate = Carbon::parse($request->start_date)->startOfDay();
                 $endDate = Carbon::parse($request->end_date)->endOfDay();
                 $query->whereBetween('check_in', [$startDate, $endDate]);
            } catch (\Exception $e) {
                 // Abaikan filter tanggal jika format salah
                 \Log::warning('Format tanggal filter logs salah: ' . $e->getMessage());
            }
        }

        if ($request->has('status') && in_array($request->status, ['Tepat Waktu', 'Terlambat'])) {
            $query->where('status', $request->status);
        }

        return $query->paginate(20); // Jalankan query
    }

    // Riwayat absensi user (tidak perlu diubah)
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

    /**
     * ================================================================
     * FUNGSI getAttendanceCalendar (SUDAH DIMODIFIKASI)
     * ================================================================
     */
    public function getAttendanceCalendar(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer',
        ]);

        $user = Auth::user();
        $month = $request->month;
        $year = $request->year;

        // Ambil data log absensi user
        $logs = $user->attendanceLogs()
            ->whereMonth('check_in', $month)->whereYear('check_in', $year)
            ->get()
            ->keyBy(fn($log) => Carbon::parse($log->check_in)->format('Y-m-d'));

        // Ambil data cuti user yang disetujui
        $leaves = $user->leaveRequests()->where('status', 'approved')->get();

        // ================================================================
        // PERUBAHAN 1: Ambil tanggal DAN deskripsi holiday
        // ================================================================
        $holidays = Holiday::whereMonth('date', $month)
                           ->whereYear('date', $year)
                           ->get()
                           ->keyBy(fn($holiday) => Carbon::parse($holiday->date)->format('Y-m-d')); // Key = Tanggal

        // Periode bulan yang diminta
        $period = CarbonPeriod::create(Carbon::create($year, $month)->startOfMonth(), Carbon::create($year, $month)->endOfMonth());
        $calendarData = [];

        // Loop setiap hari
        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            // Tambahkan field 'description' ke $dayData
            $dayData = ['date' => $dateString, 'check_in' => null, 'check_out' => null, 'status' => null, 'description' => null];

            if ($logs->has($dateString)) {
                // Jika ada log absensi di hari ini
                $log = $logs[$dateString];
                // Format waktu ke HH:mm:ss atau null jika belum checkout
                $dayData['check_in'] = Carbon::parse($log->check_in)->format('H:i:s');
                $dayData['check_out'] = $log->check_out ? Carbon::parse($log->check_out)->format('H:i:s') : null;
                $dayData['status'] = $log->status; // 'Tepat Waktu' atau 'Terlambat'

            // ================================================================
            // PERUBAHAN 2: Prioritaskan cek Holiday sebelum Weekend
            // ================================================================
            } else if ($holidays->has($dateString)) {
                // Jika hari ini ada di tabel holidays
                $dayData['status'] = 'Libur';
                $dayData['description'] = $holidays[$dateString]->description; // Ambil deskripsi

            } else if ($date->isWeekend()) {
                // Jika hari ini akhir pekan (dan BUKAN holiday)
                $dayData['status'] = 'Libur';
                $dayData['description'] = 'Akhir Pekan'; // Deskripsi default

            } else {
                // Cek Cuti
                $isOnLeave = $leaves->first(function ($leave) use ($date) {
                     // Logika cek rentang cuti Anda sudah benar
                     $durationParts = explode(' - ', $leave->duration);
                     if (count($durationParts) === 2) {
                          try {
                               $startLeave = Carbon::parse($durationParts[0])->startOfDay();
                               $endLeave = Carbon::parse($durationParts[1])->endOfDay();
                               return $date->betweenIncluded($startLeave, $endLeave);
                          } catch (\Exception $e) { return false; } // Tangani format durasi salah
                     }
                     // Handle jika format durasi hanya satu tanggal (opsional)
                     else if (count($durationParts) === 1) {
                          try {
                               return Carbon::parse($durationParts[0])->isSameDay($date);
                          } catch (\Exception $e) { return false; }
                     }
                     return false;
                });

                if ($isOnLeave) {
                    // Jika user sedang cuti
                    // Gunakan tipe cuti (izin/cuti/sakit) sebagai status
                    $dayData['status'] = ucfirst($isOnLeave->type ?? 'Izin'); // 'Cuti', 'Sakit', 'Izin'
                    $dayData['description'] = $isOnLeave->reason; // Ambil alasan cuti sebagai deskripsi
                } else if ($date->isPast() || $date->isToday()) {
                    // Jika bukan libur, bukan weekend, tidak cuti, dan sudah lewat
                    $dayData['status'] = 'Alfa';
                }
                // Jika bukan semua di atas (hari kerja di masa depan), status tetap null
            }
            $calendarData[] = $dayData;
        }
        return response()->json($calendarData);
    }
}