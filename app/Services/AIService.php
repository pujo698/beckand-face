<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AIService
{
    protected $baseUrl = 'http://127.0.0.1:11434/api/generate';
    protected $model   = 'llama3.2';

    public function generatePerformanceReview(string $employeeName, array $stats, ?array $trend = null, array $fraudFlags = [])
    {
        $hadir      = (int)($stats['hadir'] ?? 0);
        $terlambat  = (int)($stats['terlambat'] ?? 0);
        $izin       = (int)($stats['izin'] ?? 0);
        $sakit      = (int)($stats['sakit'] ?? 0);
        $cuti       = (int)($stats['cuti'] ?? 0);
        $alfa       = (int)($stats['alfa'] ?? 0);

        $hadirTotal = $hadir + $terlambat;
        $totalHari  = max($hadirTotal + $izin + $sakit + $cuti + $alfa, 1);
        $persentase = round(($hadirTotal / $totalHari) * 100);

        $trendStatus = $trend['status'] ?? 'Tidak Ada Data';
        $trendText   = $trend['description'] ?? 'Tidak ada data pembanding.';

        // AUTO CATEGORY RULE
        $kategori = match (true) {
            $persentase >= 95 && $alfa === 0 => 'Sangat Baik',
            $persentase >= 85 && $alfa <= 1  => 'Baik',
            $persentase >= 70                => 'Cukup, Perlu Pembinaan',
            default                          => 'Perlu Perhatian Serius'
        };

        $prompt = "
Anda adalah sistem evaluasi HR.
Tulis laporan maksimal 5 kalimat, nada formal dan tegas (tanpa basa-basi).

Format:
1) Ringkasan tingkat kehadiran dan performa umum.
2) Sebutkan kekuatan singkat bila ada.
3) Tegaskan pelanggaran seperti keterlambatan atau alfa.
4) Jika ada indikasi fraud, beri kalimat 'perlu verifikasi lebih lanjut'.
5) Tutup dengan kategori: {$kategori}.

DATA PRESENSI:
- Nama: {$employeeName}
- Persentase kehadiran: {$persentase}%
- Hadir tepat waktu: {$hadir}
- Terlambat: {$terlambat}
- Izin: {$izin}
- Sakit: {$sakit}
- Cuti: {$cuti}
- Alfa: {$alfa}

TREND: {$trendStatus} → {$trendText}
RISIKO: " . ($fraudFlags ? implode(', ', $fraudFlags) : 'Tidak ada indikasi risiko') . "
";

        try {
            $response = Http::timeout(120)->post($this->baseUrl, [
                'model'  => $this->model,
                'prompt' => $prompt,
                'stream' => false,
                'options' => [
                    'temperature' => 0.25,
                    'num_predict' => 120,
                ],
            ]);

            $data = $response->json();
            return trim($data['response'] ?? "Tidak ada respon dari AI.");
        } catch (\Exception $e) {
            return "AI tidak dapat dihubungi. ({$e->getMessage()})";
        }
    }
}
