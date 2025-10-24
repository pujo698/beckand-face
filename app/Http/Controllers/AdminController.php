<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
// TAMBAHAN: Use statement untuk fungsi rekap
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
        return $query->get();
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

        $employees = User::where('role', 'employee')->where('status', 'active')->get();
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

        $employees = User::where('role', 'employee')->where('status', 'active')->get();
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

        $employees = User::where('role', 'employee')->where('status', 'active')->get();
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

}