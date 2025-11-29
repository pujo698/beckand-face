<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AIService
{
    protected $baseUrl = 'http://127.0.0.1:11434/api/generate';
    protected $model   = 'llama3.2';

    /**
     * Generate ringkasan evaluasi presensi untuk HR.
     *
     * @param  string      $employeeName
     * @param  array       $stats       ['hadir','terlambat','izin','sakit','cuti','alfa']
     * @param  array|null  $trend       ['status','description'] atau null
     * @param  array       $fraudFlags  list string indikasi fraud/anomali
     * @return string
     */
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

        $trendText = $trend['description'] ?? 'Belum ada data pembanding untuk trend performa.';
        $trendStatus = $trend['status'] ?? 'none';

        $fraudSection = empty($fraudFlags)
            ? "Tidak ditemukan indikasi pola kecurangan yang menonjol pada periode ini."
            : "Terdapat beberapa pola yang perlu mendapat perhatian khusus:\n- " . implode("\n- ", $fraudFlags);

        $prompt = "
Anda adalah analis HR profesional yang membuat ringkasan evaluasi presensi untuk dibaca oleh HRD (BUKAN untuk dikirim langsung ke karyawan).

DATA PRESENSI:
- Nama karyawan: {$employeeName}
- Total hari kerja yang dihitung: {$totalHari} hari
- Persentase kehadiran: {$persentase}%
- Hadir tepat waktu: {$hadir} hari
- Terlambat: {$terlambat} hari
- Izin resmi: {$izin} hari
- Sakit: {$sakit} hari
- Cuti: {$cuti} hari
- Alfa (tanpa keterangan): {$alfa} hari

TREND PERFORMA:
- Status trend: {$trendStatus}
- Ringkasan trend: {$trendText}

ANALISIS RISIKO / POLA MENCURIGAKAN:
{$fraudSection}

TUGAS ANDA:
1. Buat satu paragraf evaluasi (3-4 kalimat) dengan bahasa Indonesia yang formal, netral, dan profesional.
2. Sorot poin kekuatan karyawan (jika ada), misalnya konsistensi hadir, penurunan keterlambatan, atau izin yang tertib.
3. Sorot juga area yang perlu perhatian, seperti banyak alfa, keterlambatan yang berulang, atau pola risiko dari bagian analisis risiko di atas.
4. Jika ada fraudFlags, jangan menuduh langsung, tapi gunakan frasa seperti 'perlu verifikasi', 'perlu penelaahan lebih lanjut', atau 'indikasi yang patut diperhatikan'.
5. Akhiri dengan satu kalimat kategori singkat, misalnya:
   - 'Kategori: Sangat Baik.'
   - 'Kategori: Baik.'
   - 'Kategori: Perlu Pembinaan.'
   - 'Kategori: Perlu Perhatian Serius.'
6. Jangan gunakan salam pembuka, jangan mengulang nama karyawan terlalu sering, dan jangan menyalin teks instruksi ini.
";

        try {
            $response = Http::timeout(180)->post($this->baseUrl, [
                'model'  => $this->model,
                'prompt' => $prompt,
                'stream' => false,
                'options' => [
                    'temperature' => 0.4,
                    'num_predict' => 220,
                ],
            ]);

            if ($response->failed()) {
                return "AI Error ({$response->status()}): gagal menghasilkan ringkasan analisis.";
            }

            $data = $response->json();

            if (!is_array($data) || !isset($data['response'])) {
                // Jika Ollama mengembalikan stream atau format aneh
                $raw = $response->body();
                return is_string($raw) && trim($raw) !== ''
                    ? trim($raw)
                    : "AI tidak mengembalikan respons yang valid.";
            }

            return trim($data['response'], "\" \n\r\t");

        } catch (\Exception $e) {
            return "Sistem AI tidak dapat dihubungi. Pastikan Ollama berjalan. ({$e->getMessage()})";
        }
    }
}
