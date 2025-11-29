<?php

namespace App\Services;

class TrendService
{
    public function calculate(array $current, array $previous)
    {
        // Jika tidak ada data bulan sebelumnya
        if (!$previous || array_sum($previous) === 0) {
            return [
                'status' => 'no-history',
                'description' => 'Belum ada data pembanding untuk analisis tren.',
                'change' => null
            ];
        }

        // Helper function untuk menghitung perubahan %
        $percent = fn($now, $past) => ($past > 0) 
            ? round((($now - $past) / $past) * 100) 
            : ($now > 0 ? 100 : 0);

        // Hitung perubahan indikator penting
        $change = [
            'hadir'      => $percent($current['hadir'], $previous['hadir']),
            'terlambat'  => $percent($current['terlambat'], $previous['terlambat']),
            'izin'       => $percent($current['izin'], $previous['izin']),
            'cuti'       => $percent($current['cuti'], $previous['cuti']),
            'alfa'       => $percent($current['alfa'], $previous['alfa']),
        ];

        // Logika status akhir
        $status = match (true) {
            $change['alfa'] > 30 => 'declining',
            $change['terlambat'] > 20 => 'declining',
            $change['hadir'] > 10 && $change['terlambat'] < 0 && $change['alfa'] <= 0 => 'improving',
            default => 'stable'
        };

        // Deskripsi trend ringkas
        $description = match ($status) {
            'improving' => "Performa menunjukkan peningkatan, khususnya dalam konsistensi kehadiran.",
            'declining' => "Terdapat penurunan performa, terutama pada absensi keterlambatan atau alfa.",
            default     => "Performa kedisiplinan relatif stabil dibandingkan bulan sebelumnya."
        };

        return [
            'status' => $status,
            'change' => $change,
            'description' => $description
        ];
    }
}
