<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Models\Holiday;

class EmployeeController extends Controller
{
    /**
     * Menampilkan data profil karyawan yang login & status absensi hari ini.
     */
    public function profile()
    {
        $user = Auth::user();
        $attendanceToday = $user->attendanceLogs()
            ->whereDate('check_in', Carbon::today())->first();

        $duration = null;
        if ($attendanceToday && $attendanceToday->check_out) {
            $checkInTime = Carbon::parse($attendanceToday->check_in);
            $checkOutTime = Carbon::parse($attendanceToday->check_out);
            $duration = $checkInTime->diff($checkOutTime)->format('%h jam %i menit');
        }

        return response()->json([
            'user' => $user,
            'attendance_status_today' => $attendanceToday,
            'duration_today' => $duration
        ]);
    }

    /**
     * Mengupdate profil.
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

        if ($request->hasFile('photo')) {
            if ($user->photo) {
                Storage::disk('public')->delete($user->photo);
            }
            $path = $request->file('photo')->store('photos', 'public');
            $userData['photo'] = $path;
        }

        $user->update($userData);
        return response()->json(['message' => 'Profil berhasil diperbarui', 'user' => $user]);
    }
    
    /**
     * Menghitung statistik bulanan (Tepat Waktu, Terlambat, Cuti, Alfa) secara dinamis.
     */
    public function monthlyStats(Request $request)
    {
        $user = Auth::user();
        $month = $request->query('month', now()->month);
        $year = $request->query('year', now()->year);

        // Mengambil semua data relevan untuk bulan yang diminta
        $logs = $user->attendanceLogs()->whereMonth('check_in', $month)->whereYear('check_in', $year)->get()->keyBy(fn($log) => Carbon::parse($log->check_in)->format('Y-m-d'));
        $leaves = $user->leaveRequests()->where('status', 'approved')->get();
        $holidays = Holiday::whereMonth('date', $month)->whereYear('date', $year)->pluck('date')->map(fn($date) => Carbon::parse($date)->format('Y-m-d'))->flip();

        $period = CarbonPeriod::create(Carbon::create($year, $month)->startOfMonth(), Carbon::create($year, $month)->endOfMonth());
        
        $dailyStatuses = [];
        // Loop melalui setiap hari dalam sebulan untuk menentukan statusnya
        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            $status = null;

            if ($logs->has($dateString)) {
                $status = $logs[$dateString]->status; // 'Tepat Waktu' atau 'Terlambat'
            } else if ($holidays->has($dateString) || $date->isWeekend()) {
                // Abaikan hari libur, tidak dihitung sebagai apa pun
            } else {
                $isOnLeave = $leaves->first(function ($leave) use ($date) {
                    // Sesuaikan logika ini jika format 'duration' Anda berbeda
                    $range = explode(' - ', $leave->duration);
                    if (count($range) == 2) {
                        return $date->between(Carbon::parse($range[0]), Carbon::parse($range[1]));
                    }
                    return false;
                });

                if ($isOnLeave) {
                    $status = 'Cuti'; // Mengelompokkan semua jenis izin sebagai 'Cuti'
                } else if ($date->isPast() || $date->isToday()) {
                    // Jika bukan hari libur, tidak absen, dan tidak izin -> Alfa
                    $status = 'Alfa';
                }
            }
            if ($status) {
                $dailyStatuses[] = $status;
            }
        }

        // Menghitung jumlah dari setiap status
        $stats = array_count_values($dailyStatuses);
        $totalWorkingDays = ($stats['Tepat Waktu'] ?? 0) + ($stats['Terlambat'] ?? 0) + ($stats['Cuti'] ?? 0) + ($stats['Alfa'] ?? 0);

        return response()->json([
            'tepat_waktu' => $stats['Tepat Waktu'] ?? 0,
            'terlambat' => $stats['Terlambat'] ?? 0,
            'cuti' => $stats['Cuti'] ?? 0,
            'alfa' => $stats['Alfa'] ?? 0,
            'total_hari_kerja' => $totalWorkingDays,
        ]);
    }
}