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
    // Ambil user yang login, kalau tidak ada, fallback ke user pertama (untuk route public)
    $user = Auth::user() ?? \App\Models\User::first();

    if (!$user) {
        return response()->json([
            'error' => 'Tidak ada data user yang bisa digunakan.'
        ], 404);
    }

    $month = $request->query('month', now()->month);
    $year = $request->query('year', now()->year);

    // Gunakan joinDate dari created_at user (fallback jika kosong)
    $joinDate = $user->created_at ? Carbon::parse($user->created_at)->startOfDay() : Carbon::today();
    $today = Carbon::today();

    // Ambil log kehadiran untuk bulan & tahun yang diminta
    $logs = $user->attendanceLogs()
        ->whereMonth('check_in', $month)
        ->whereYear('check_in', $year)
        ->get()
        ->keyBy(fn($log) => Carbon::parse($log->check_in)->format('Y-m-d'));

    // Ambil semua cuti yang disetujui
    $leaves = $user->leaveRequests()
        ->where('status', 'approved')
        ->get();

    // Ambil semua tanggal libur nasional
    $holidays = \App\Models\Holiday::whereMonth('date', $month)
        ->whereYear('date', $year)
        ->pluck('date')
        ->map(fn($date) => Carbon::parse($date)->format('Y-m-d'))
        ->flip();

    // Tentukan batas awal dan akhir periode
    $periodStart = Carbon::create($year, $month)->startOfMonth();
    $periodEnd = Carbon::create($year, $month)->endOfMonth();

    // Gunakan tanggal join jika lebih baru dari awal bulan
    if ($joinDate->greaterThan($periodStart)) {
        $periodStart = $joinDate;
    }

    // Jangan hitung masa depan
    if ($periodEnd->greaterThan($today)) {
        $periodEnd = $today;
    }

    $period = CarbonPeriod::create($periodStart, $periodEnd);
    $dailyStatuses = [];

    foreach ($period as $date) {
        $dateString = $date->format('Y-m-d');
        $status = null;

        if ($logs->has($dateString)) {
            $status = $logs[$dateString]->status; // Tepat Waktu / Terlambat
        } elseif ($holidays->has($dateString) || $date->isWeekend()) {
            continue; // Lewati libur dan weekend
        } else {
            $isOnLeave = $leaves->first(function ($leave) use ($date) {
                $range = explode(' - ', $leave->duration);
                if (count($range) == 2) {
                    return $date->between(Carbon::parse($range[0]), Carbon::parse($range[1]));
                }
                return false;
            });

            if ($isOnLeave) {
                $status = 'Cuti';
            } elseif ($date->isPast() || $date->isToday()) {
                $status = 'Alfa';
            }
        }

        if ($status) {
            $dailyStatuses[] = $status;
        }
    }

    // Hitung total status
    $stats = array_count_values($dailyStatuses);

    return response()->json([
        'user' => $user->name,
        'tepat_waktu' => $stats['Tepat Waktu'] ?? 0,
        'terlambat' => $stats['Terlambat'] ?? 0,
        'cuti' => $stats['Cuti'] ?? 0,
        'alfa' => $stats['Alfa'] ?? 0,
        'periode_dihitung' => [
            'mulai' => $periodStart->toDateString(),
            'akhir' => $periodEnd->toDateString(),
        ],
        'join_date' => $joinDate->toDateString(),
    ]);
}


}