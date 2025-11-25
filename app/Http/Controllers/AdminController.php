<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Models\Holiday;
use App\Models\AttendanceLog;
use App\Models\LeaveRequest;

class AdminController extends Controller
{
    /**
     * Menampilkan daftar semua karyawan.
     */
    public function index(Request $request)
    {
        $query = User::where('role', 'employee')->latest();

        if ($request->has('position')) {
            $query->where('position', 'like', '%' . $request->position . '%');
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        return $query->get();
    }

    public function revokeUserTokens(User $user)
    {
        
        if ($user->role === 'admin') {
             return response()->json(['message' => 'Tidak dapat mencabut token admin.'], 403);
        }

        $user->tokens()->delete(); 

        return response()->json(['message' => 'Semua sesi login untuk ' . $user->name . ' telah dicabut.']);
    }

    /**
     * Menyimpan karyawan baru.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|string|email|max:255|unique:users',
            'position'   => 'required|string|max:255',
            'status'     => 'required|in:active,inactive',
            'phone'      => 'nullable|string',
            'password'   => 'required|string|min:8',
            'photo'      => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $path = null;
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('photos', 'public');
        }

        $user = User::create([
            'name'       => $request->name,
            'email'      => $request->email,
            'phone'      => $request->phone,
            'password'   => Hash::make($request->password),
            'role'       => 'employee',
            'position'   => $request->position,
            'status'     => $request->status,
            'photo'      => $path,
        ]);

        return response()->json($user, 201);
    }

    public function dashboardSummary()
    {
        $today = \Carbon\Carbon::today();
        $todayString = $today->toDateString();

        // --- STATISTIK KARTU (Sama seperti sebelumnya) ---
        $totalKaryawan = User::where('role', 'employee')->where('status', 'active')->count();
        $hadir = AttendanceLog::whereDate('check_in', $todayString)->where('status', 'Tepat Waktu')->count();
        $terlambat = AttendanceLog::whereDate('check_in', $todayString)->where('status', 'Terlambat')->count();
        
        // Logika Izin (Raw Query)
        $izin = LeaveRequest::where('status', 'approved')
            ->where(function($query) use ($todayString) {
                $query->whereRaw("STR_TO_DATE(SUBSTRING_INDEX(duration, ' - ', 1), '%Y-%m-%d') <= ?", [$todayString])
                      ->whereRaw("STR_TO_DATE(SUBSTRING_INDEX(duration, ' - ', -1), '%Y-%m-%d') >= ?", [$todayString]);
            })->count();

        // --- TAMBAHAN BARU: 5 Aktivitas Terakhir ---
        // Kita ambil data log absensi hari ini, urutkan dari yang terbaru
        $latestLogs = AttendanceLog::with('user') // Ambil relasi user agar muncul namanya
                    ->whereDate('check_in', $todayString)
                    ->orderBy('check_in', 'desc')
                    ->take(5) // Ambil 5 saja
                    ->get();

        return response()->json([
            'total_karyawan' => $totalKaryawan,
            'hadir' => $hadir,
            'terlambat' => $terlambat,
            'izin' => $izin,
            'latest_activities' => $latestLogs // <--- Kirim ke Vue
        ]);
    }
    /**
     * Mengupdate data karyawan.
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'position' => 'required|string|max:255',
            'status'   => 'required|in:active,inactive',
            'phone'    => 'nullable|string',
        ]);

        $user->update($request->only(['name', 'email', 'phone', 'position', 'status']));

        if ($request->hasFile('photo')) {
            $request->validate(['photo' => 'image|mimes:jpeg,png,jpg|max:2048']);
            if ($user->photo) Storage::disk('public')->delete($user->photo);
            $path = $request->file('photo')->store('photos', 'public');
            $user->update(['photo' => $path]);
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
        return response()->json(['message' => 'Karyawan berhasil dihapus.']);
    }

    /**
     * Menampilkan data user spesifik.
     */
    public function show(User $user)
    {
        return response()->json($user);
    }

    // ==============================================================
    // FUNGSI REKAP ABSENSI (TEPAT WAKTU, TERLAMBAT, CUTI/IZIN, ALFA)
    // ==============================================================
    public function attendanceRecapByDateRange(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date_format:Y-m-d',
            'end_date'   => 'required|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();
        $today = Carbon::today();

        $employeeQuery = User::where('role', 'employee')->where('status', 'active');
        if ($request->filled('name')) {
            $employeeQuery->where('name', 'like', '%' . $request->name . '%');
        }
        if ($request->filled('position')) {
            $employeeQuery->where('position', 'like', '%' . $request->position . '%');
        }
        $employees = $employeeQuery->get();
        $holidays = Holiday::whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                           ->pluck('date')
                           ->map(fn($date) => Carbon::parse($date)->format('Y-m-d'))
                           ->flip();
        $recapData = [];

        foreach ($employees as $employee) {
            $logs = $employee->attendanceLogs()
                ->whereBetween('check_in', [$startDate, $endDate])
                ->get()
                ->keyBy(fn($log) => Carbon::parse($log->check_in)->format('Y-m-d'));

            $leaves = $employee->leaveRequests()
                ->where('status', 'approved')
                ->where(function($query) use ($startDate, $endDate) {
                    $query->whereRaw("STR_TO_DATE(SUBSTRING_INDEX(duration, ' - ', 1), '%Y-%m-%d') <= ?", [$endDate->toDateString()])
                          ->whereRaw("STR_TO_DATE(SUBSTRING_INDEX(duration, ' - ', -1), '%Y-%m-%d') >= ?", [$startDate->toDateString()]);
                })
                ->get();

            $periodStart = $startDate->copy();
            $periodEnd = $endDate->copy()->isFuture() ? $today : $endDate->copy();
            $dailyStatuses = [];

            if ($periodEnd->greaterThanOrEqualTo($periodStart)) {
                $period = CarbonPeriod::create($periodStart, $periodEnd);
                foreach ($period as $date) {
                    $dateString = $date->format('Y-m-d');
                    $status = null;

                    if ($logs->has($dateString)) {
                        $status = $logs[$dateString]->status;
                    } elseif ($holidays->has($dateString) || $date->isWeekend()) {
                        continue;
                    } else {
                        $isOnLeave = $leaves->first(function ($leave) use ($date) {
                             $durationParts = explode(' - ', $leave->duration);
                             if (count($durationParts) === 2) { try { $startLeave = Carbon::parse($durationParts[0])->startOfDay(); $endLeave = Carbon::parse($durationParts[1])->endOfDay(); return $date->betweenIncluded($startLeave, $endLeave); } catch (\Exception $e) { return false; } }
                             elseif (count($durationParts) === 1) { try { return Carbon::parse($durationParts[0])->isSameDay($date); } catch (\Exception $e) { return false; } }
                             return false;
                        });
                        if ($isOnLeave) { $status = ucfirst($isOnLeave->type ?? 'Izin'); }
                        elseif ($date->isPast() || $date->isToday()) { $status = 'Alfa'; }
                    }
                    if ($status) { $dailyStatuses[] = $status; }
                }
            }

            $stats = array_count_values($dailyStatuses);
            $recapData[] = [
                'user_id' => $employee->id,
                'name'    => $employee->name,
                'position'=> $employee->position,
                'stats'   => [
                    'tepat_waktu' => $stats['Tepat Waktu'] ?? 0,
                    'terlambat'   => $stats['Terlambat'] ?? 0,
                    'cuti'        => ($stats['Cuti'] ?? 0) + ($stats['Sakit'] ?? 0) + ($stats['Izin'] ?? 0),
                    'alfa'        => $stats['Alfa'] ?? 0,
                ],
                'period_start' => $periodStart->toDateString(),
                'period_end'   => $periodEnd->toDateString(),
            ];
        }
        return response()->json($recapData);
    }

    // ==============================================================
    // FUNGSI REKAP KEJADIAN (CUTI, SAKIT, IZIN, ALFA)
    // ==============================================================
    public function leaveAbsenceRecap(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date_format:Y-m-d',
            'end_date'   => 'required|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();
        $today = Carbon::today();

        $employeeQuery = User::where('role', 'employee')->where('status', 'active');
        if ($request->filled('name')) {
            $employeeQuery->where('name', 'like', '%' . $request->name . '%');
        }
        if ($request->filled('position')) {
            $employeeQuery->where('position', 'like', '%' . $request->position . '%');
        }
        $employees = $employeeQuery->get();

        $holidays = Holiday::whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                           ->pluck('date')
                           ->map(fn($date) => Carbon::parse($date)->format('Y-m-d'))
                           ->flip();
        $recapData = [];

        foreach ($employees as $employee) {
            $stats = ['cuti' => 0, 'sakit' => 0, 'izin' => 0, 'alfa' => 0];
            $leaves = $employee->leaveRequests()
                ->where('status', 'approved')
                ->where(function($query) use ($startDate, $endDate) {
                    $query->whereRaw("STR_TO_DATE(SUBSTRING_INDEX(duration, ' - ', 1), '%Y-%m-%d') <= ?", [$endDate->toDateString()])
                          ->whereRaw("STR_TO_DATE(SUBSTRING_INDEX(duration, ' - ', -1), '%Y-%m-%d') >= ?", [$startDate->toDateString()]);
                })
                ->get();
            $logs = $employee->attendanceLogs()
                ->whereBetween('check_in', [$startDate, $endDate])
                ->get()
                ->keyBy(fn($log) => Carbon::parse($log->check_in)->format('Y-m-d'));

            $periodStart = $startDate->copy();
            $periodEnd = $endDate->copy()->isFuture() ? $today : $endDate->copy();

            if ($periodEnd->greaterThanOrEqualTo($periodStart)) {
                $period = CarbonPeriod::create($periodStart, $periodEnd);
                foreach ($period as $date) {
                    $dateString = $date->format('Y-m-d');
                    if ($date->isWeekend() || $holidays->has($dateString) || $logs->has($dateString)) {
                        continue;
                    }
                    $isOnLeave = $leaves->first(function ($leave) use ($date) {
                        $durationParts = explode(' - ', $leave->duration);
                        if (count($durationParts) === 2) { try { $startLeave = Carbon::parse($durationParts[0])->startOfDay(); $endLeave = Carbon::parse($durationParts[1])->endOfDay(); return $date->betweenIncluded($startLeave, $endLeave); } catch (\Exception $e) { return false; } }
                        elseif (count($durationParts) === 1) { try { return Carbon::parse($durationParts[0])->isSameDay($date); } catch (\Exception $e) { return false; } }
                        return false;
                    });
                    if ($isOnLeave) {
                        $leaveType = $isOnLeave->type ?? 'izin';
                        if (isset($stats[$leaveType])) { $stats[$leaveType]++; }
                        else { $stats['izin']++; }
                    } else {
                        $stats['alfa']++;
                    }
                }
            }

            $recapData[] = [
                'user_id' => $employee->id,
                'name'    => $employee->name,
                'position'=> $employee->position,
                'stats'   => $stats,
                'period_start' => $startDate->toDateString(),
                'period_end'   => $endDate->toDateString(),
            ];
        }
        return response()->json($recapData);
    }

    // ==============================================================
    // FUNGSI REKAP TOTAL HARI (CUTI, SAKIT, IZIN)
    // ==============================================================
    public function leaveDaysRecap(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date_format:Y-m-d',
            'end_date'   => 'required|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();

        $employeeQuery = User::where('role', 'employee')->where('status', 'active');
        if ($request->filled('name')) {
            $employeeQuery->where('name', 'like', '%' . $request->name . '%');
        }
        if ($request->filled('position')) {
            $employeeQuery->where('position', 'like', '%' . $request->position . '%');
        }
        $employees = $employeeQuery->get();
        
        $recapData = [];

        foreach ($employees as $employee) {
            $leaveDaysByType = ['cuti' => 0, 'sakit' => 0, 'izin' => 0];
            $totalLeaveDays = 0;

            $leaves = $employee->leaveRequests()
                ->where('status', 'approved')
                ->where(function($query) use ($startDate, $endDate) {
                    $query->whereRaw("STR_TO_DATE(SUBSTRING_INDEX(duration, ' - ', 1), '%Y-%m-%d') <= ?", [$endDate->toDateString()])
                          ->whereRaw("STR_TO_DATE(SUBSTRING_INDEX(duration, ' - ', -1), '%Y-%m-%d') >= ?", [$startDate->toDateString()]);
                })
                ->get();

            foreach ($leaves as $leave) {
                $durationParts = explode(' - ', $leave->duration);
                $leaveStartDate = null; $leaveEndDate = null;
                try {
                    if (count($durationParts) === 2) { $leaveStartDate = Carbon::parse($durationParts[0])->startOfDay(); $leaveEndDate = Carbon::parse($durationParts[1])->endOfDay(); }
                    elseif (count($durationParts) === 1) { $leaveStartDate = Carbon::parse($durationParts[0])->startOfDay(); $leaveEndDate = $leaveStartDate->copy()->endOfDay(); }
                } catch (\Exception $e) { \Log::warning("Format durasi cuti salah ID {$leave->id}: {$leave->duration}"); continue; }

                if ($leaveStartDate && $leaveEndDate) {
                    $actualStart = $leaveStartDate->max($startDate);
                    $actualEnd = $leaveEndDate->min($endDate);
                    if ($actualEnd->greaterThanOrEqualTo($actualStart)) {
                        $daysCount = $actualStart->diffInDaysFiltered(function(Carbon $date) {
                             // Hitung hanya hari kerja (Senin-Jumat)
                             return !$date->isWeekend();
                         }, $actualEnd) + ($actualStart->isWeekday() ? 1 : 0); // Tambah 1 jika start bukan weekend


                        // Ambil data libur nasional dalam rentang cuti yang dihitung
                         $holidaysInLeaveRange = Holiday::whereBetween('date', [$actualStart->toDateString(), $actualEnd->toDateString()])
                                                         ->pluck('date')
                                                         ->map(fn($hdate) => Carbon::parse($hdate)->format('Y-m-d'));

                         // Kurangi hari libur nasional dari hitungan hari kerja
                         $periodLeave = CarbonPeriod::create($actualStart, $actualEnd);
                         foreach($periodLeave as $dayInLeave) {
                              if($dayInLeave->isWeekend()) continue; // Sudah di-filter weekend
                              if($holidaysInLeaveRange->contains($dayInLeave->format('Y-m-d'))) {
                                   $daysCount = max(0, $daysCount - 1); // Kurangi jika hari kerja itu libur nasional
                              }
                         }


                        $totalLeaveDays += $daysCount;
                        $leaveType = $leave->type ?? 'izin';
                        if (isset($leaveDaysByType[$leaveType])) { $leaveDaysByType[$leaveType] += $daysCount; }
                        else { $leaveDaysByType['izin'] += $daysCount; }
                    }
                }
            }

            $recapData[] = [
                'user_id' => $employee->id,
                'name'    => $employee->name,
                'position'=> $employee->position,
                'total_leave_days' => $totalLeaveDays,
                'leave_days_by_type' => $leaveDaysByType,
                'period_start' => $startDate->toDateString(),
                'period_end'   => $endDate->toDateString(),
            ];
        }
        return response()->json($recapData);
    }

    public function setPassword(Request $request, User $user)
    {
        $request->validate([
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        if ($user->role === 'admin' && $user->id !== Auth::id()) {
             return response()->json(['message' => 'Anda tidak dapat mengganti password admin lain.'], 403);
        }
        
        // Ganti password user
        $user->forceFill([
            'password' => Hash::make($request->password)
        ])->save();

        $user->tokens()->delete();

        return response()->json(['message' => 'Password untuk ' . $user->name . ' telah berhasil diubah.']);
    }

    public function listPasswordRequests(Request $request)
    {
        $requests = PasswordResetRequest::with('user:id,name,email,phone,position')
                                        ->where('status', 'pending')
                                        ->latest()
                                        ->get();

        return response()->json($requests);
    }

    public function dailyAttendance(Request $request)
    {
        $date = $request->date ? \Carbon\Carbon::parse($request->date) : \Carbon\Carbon::today();
        $dateString = $date->toDateString();

        // Ambil semua karyawan aktif
        $employees = User::where('role', 'employee')->where('status', 'active')->get();
        $positionsList = User::where('role', 'employee')
                             ->distinct()
                             ->pluck('position');

        $data = [];
        $stats = [
            'total' => $employees->count(),
            'hadir' => 0,
            'izin' => 0,
            'tidak_hadir' => 0
        ];

        foreach ($employees as $emp) {
            $status = 'Tidak Hadir'; // Default
            $jamMasuk = '-';
            $jamPulang = '-';

            // 1. Cek di Log Absensi
            $log = AttendanceLog::where('user_id', $emp->id)
                                ->whereDate('check_in', $dateString)
                                ->first();
            
            if ($log) {
                $status = 'Hadir'; // Bisa juga 'Terlambat' tergantung $log->status
                if ($log->status === 'Terlambat') $status = 'Terlambat';
                
                $jamMasuk = \Carbon\Carbon::parse($log->check_in)->format('H:i');
                $jamPulang = $log->check_out ? \Carbon\Carbon::parse($log->check_out)->format('H:i') : '-';
                
                $stats['hadir']++;
            } else {
                // 2. Jika tidak absen, Cek di Data Cuti
                // (Menggunakan logika raw query yang sama seperti dashboard)
                $leave = LeaveRequest::where('user_id', $emp->id)
                    ->where('status', 'approved')
                    ->where(function($query) use ($dateString) {
                        $query->whereRaw("STR_TO_DATE(SUBSTRING_INDEX(duration, ' - ', 1), '%Y-%m-%d') <= ?", [$dateString])
                              ->whereRaw("STR_TO_DATE(SUBSTRING_INDEX(duration, ' - ', -1), '%Y-%m-%d') >= ?", [$dateString]);
                    })->first();

                if ($leave) {
                    $status = 'Izin/Sakit';
                    $stats['izin']++;
                } else {
                    $stats['tidak_hadir']++;
                }
            }

            // Masukkan ke array data
            $data[] = [
                'id' => $emp->id,
                'name' => $emp->name,
                'email' => $emp->email,
                'position' => $emp->position,
                'photo' => $emp->photo, 
                'status' => $status,
                'jam_masuk' => $jamMasuk,
                'jam_pulang' => $jamPulang
            ];
        }

        return response()->json([
            'date' => $dateString,
            'stats' => $stats,
            'positions' => $positionsList,
            'attendance_list' => $data
        ]);
    }

    // Hapus Log Absensi Hari Ini (Reset jadi Tidak Hadir)
    public function deleteAttendanceLog($userId)
    {
        $today = \Carbon\Carbon::today();
        
        $deleted = \App\Models\AttendanceLog::where('user_id', $userId)
                    ->whereDate('check_in', $today)
                    ->delete();

        if ($deleted) {
            return response()->json(['message' => 'Data absensi berhasil direset.']);
        }

        return response()->json(['message' => 'Tidak ada data absensi untuk dihapus.'], 404);
    }

    // UPDATE LOG BERDASARKAN USER ID (HARI INI)
    public function updateAttendanceLogByUser(Request $request, $userId)
    {
        $request->validate([
            'jam_masuk' => 'required',
            'jam_pulang' => 'nullable',
        ]);

        $today = \Carbon\Carbon::today();

        $log = \App\Models\AttendanceLog::where('user_id', $userId)
                    ->whereDate('check_in', $today)
                    ->firstOrFail();
        
        $date = \Carbon\Carbon::parse($log->check_in)->format('Y-m-d');
        
        $log->check_in = $date . ' ' . $request->jam_masuk;
        if ($request->jam_pulang) {
            $log->check_out = $date . ' ' . $request->jam_pulang;
        }
        $log->save();

        return response()->json(['message' => 'Jam berhasil diperbarui.']);
    }

}