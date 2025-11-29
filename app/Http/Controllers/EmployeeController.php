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
            ->whereDate('check_in', Carbon::today())
            ->first();

        $duration = null;

        if ($attendanceToday && $attendanceToday->check_out) {
            $checkInTime = Carbon::parse($attendanceToday->check_in);
            $checkOutTime = Carbon::parse($attendanceToday->check_out);
            $duration = $checkInTime
                ->diff($checkOutTime)
                ->format('%h jam %i menit');
        }

        return response()->json([
            'user' => $user,
            'attendance_status_today' => $attendanceToday,
            'duration_today' => $duration,
        ]);
    }

    /**
     * Mengupdate profil.
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            // 'name'  => 'sometimes|string|max:255',
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

        return response()->json([
            'message' => 'Profil berhasil diperbarui',
            'user' => $user,
        ]);
    }

    /**
     * Menghitung statistik bulanan (Tepat Waktu, Terlambat, Cuti, Alfa) secara dinamis.
     *
     * Catatan:
     * - Field yang dikirim ke frontend:
     *   - 'hadir'         → jumlah status "Tepat Waktu" (dipakai card "Hadir")
     *   - 'tepat_waktu'   → sama dengan 'hadir' (untuk konsistensi backend)
     *   - 'terlambat'
     *   - 'cuti'
     *   - 'alfa'
     *   - 'totalHariKerja' → total semua hari kerja (hadir + terlambat + cuti + alfa)
     */
    public function monthlyStats(Request $request)
    {
        // Ambil user yang login, kalau tidak ada, fallback ke user pertama (untuk route public/testing)
        $user = Auth::user() ?? \App\Models\User::first();

        if (!$user) {
            return response()->json([
                'error' => 'Tidak ada data user yang bisa digunakan.',
            ], 404);
        }

        // Baca bulan dan tahun dari query, default ke bulan & tahun sekarang
        $month = $request->query('month', now()->month);
        $year = $request->query('year', now()->year);

        // Gunakan tanggal join dari created_at user (fallback jika kosong)
        $joinDate = $user->created_at
            ? Carbon::parse($user->created_at)->startOfDay()
            : Carbon::today();

        $today = Carbon::today();

        // Ambil log kehadiran untuk bulan & tahun yang diminta
        $logs = $user->attendanceLogs()
            ->whereMonth('check_in', $month)
            ->whereYear('check_in', $year)
            ->get()
            ->keyBy(function ($log) {
                return Carbon::parse($log->check_in)->format('Y-m-d');
            });

        // Ambil semua cuti yang disetujui
        $leaves = $user->leaveRequests()
            ->where('status', 'approved')
            ->get();

        // Ambil semua tanggal libur nasional di bulan & tahun tersebut
        $holidays = Holiday::whereMonth('date', $month)
            ->whereYear('date', $year)
            ->pluck('date')
            ->map(function ($date) {
                return Carbon::parse($date)->format('Y-m-d');
            })
            ->flip();

        // Tentukan batas awal dan akhir periode yang dihitung
        $periodStart = Carbon::create($year, $month)->startOfMonth();
        $periodEnd = Carbon::create($year, $month)->endOfMonth();

        // Jika tanggal join lebih baru dari awal bulan → mulai dari joinDate
        if ($joinDate->greaterThan($periodStart)) {
            $periodStart = $joinDate;
        }

        // Jangan hitung hari setelah hari ini (masa depan)
        if ($periodEnd->greaterThan($today)) {
            $periodEnd = $today;
        }

        $period = CarbonPeriod::create($periodStart, $periodEnd);
        $dailyStatuses = [];

        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            $status = null;

            // Jika ada log kehadiran → ambil status dari log (Tepat Waktu / Terlambat)
            if ($logs->has($dateString)) {
                $status = $logs[$dateString]->status;
            }
            // Jika libur nasional atau weekend → tidak dihitung sebagai hari kerja
            elseif ($holidays->has($dateString) || $date->isWeekend()) {
                continue;
            }
            // Jika bukan libur dan tidak ada log → cek cuti, jika tidak cuti dianggap Alfa
            else {
                $isOnLeave = $leaves->first(function ($leave) use ($date) {
                    $range = explode(' - ', $leave->duration);

                    if (count($range) === 2) {
                        return $date->between(
                            Carbon::parse($range[0]),
                            Carbon::parse($range[1])
                        );
                    }

                    return false;
                });

                if ($isOnLeave) {
                    $status = 'Cuti';
                } elseif (($date->isPast() || $date->isToday()) && $date->greaterThanOrEqualTo($joinDate)) {
                    $status = 'Alfa';
                }
            }

            if ($status) {
                $dailyStatuses[] = $status;
            }
        }

        // Hitung total status per kategori
        $stats = array_count_values($dailyStatuses);

        $tepatWaktu = $stats['Tepat Waktu'] ?? 0;
        $terlambat = $stats['Terlambat'] ?? 0;
        $cuti = $stats['Cuti'] ?? 0;
        $alfa = $stats['Alfa'] ?? 0;

        // Total hari kerja = semua hari yang punya status (hadir/terlambat/cuti/alfa)
        $totalHariKerja = $tepatWaktu + $terlambat + $cuti + $alfa;

        return response()->json([
            'user' => $user->name,

            // Field yang dipakai card "Hadir" di frontend
            'hadir' => $tepatWaktu,

            // Field asli untuk keperluan lain / debugging
            'tepat_waktu' => $tepatWaktu,
            'terlambat' => $terlambat,
            'cuti' => $cuti,
            'alfa' => $alfa,

            // Total hari kerja untuk progress bar di frontend
            'totalHariKerja' => $totalHariKerja,

            'periode_dihitung' => [
                'mulai' => $periodStart->toDateString(),
                'akhir' => $periodEnd->toDateString(),
            ],
            'join_date' => $joinDate->toDateString(),
        ]);
    }
}
