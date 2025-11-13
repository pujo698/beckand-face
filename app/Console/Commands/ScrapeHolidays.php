<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http; 
use App\Models\Holiday;              
use Carbon\Carbon;                   

class ScrapeHolidays extends Command
{
    /**
     * Tanda tangan command (ganti jika Anda ganti nama class)
     */
    protected $signature = 'app:scrape-holidays {--year=}';

    /**
     * Deskripsi command
     */
    protected $description = 'Mengambil data hari libur nasional Indonesia dari Google Calendar API';

    /**
     * URL dasar Google Calendar API (Events: list endpoint)
     */
    private $apiUrl = 'https://www.googleapis.com/calendar/v3/calendars/{calendarId}/events';

    /**
     * ID Kalender Hari Libur Indonesia
     */
    private $calendarId = 'id.indonesian#holiday@group.v.calendar.google.com';

    /**
     * Fungsi utama command
     */
    public function handle()
    {
        $year = $this->option('year') ?? date('Y');
        $this->info("📅 Mengambil data hari libur tahun {$year} dari Google Calendar API...");

        // Ambil API Key dari file .env
        $apiKey = env('GOOGLE_CALENDAR_API_KEY');
        if (!$apiKey) {
            $this->error("❌ GOOGLE_CALENDAR_API_KEY tidak ditemukan di file .env!");
            return 1;
        }

        // Tentukan rentang waktu (1 Jan - 31 Des tahun yang diminta)
        $timeMin = Carbon::create($year, 1, 1)->startOfDay()->toRfc3339String(); 
        $timeMax = Carbon::create($year, 12, 31)->endOfDay()->toRfc3339String();

        $fullApiUrl = str_replace('{calendarId}', urlencode($this->calendarId), $this->apiUrl);

        try {
            // Lakukan GET request ke Google Calendar API
            $response = Http::acceptJson()->get($fullApiUrl, [
                'key' => $apiKey,             
                'timeMin' => $timeMin,          
                'timeMax' => $timeMax,          
                'singleEvents' => 'true',       
                'orderBy' => 'startTime',     
                'maxResults' => 250,          
            ]);

            // Cek jika request gagal
            if (!$response->successful()) {
                $this->error("❌ Gagal mengambil data dari Google API. Status: " . $response->status());
                $this->line("   URL: " . $fullApiUrl);
                $errorData = $response->json();
                if (isset($errorData['error']['message'])) {
                    $this->line("   Pesan Error Google: " . $errorData['error']['message']);
                } else {
                    $this->line("   Body Respons Error: " . substr($response->body(), 0, 300));
                }
                return 1;
            }

            // Ambil data 'items' (daftar event/libur) dari respons JSON
            $holidaysData = $response->json('items');

            // Cek jika data kosong atau format tidak sesuai
            if (empty($holidaysData) || !is_array($holidaysData)) {
                $this->warn("⚠️ Google API tidak mengembalikan data libur (items) untuk tahun {$year}.");
                return 1;
            }

            $savedCount = 0;
            $totalErrors = 0;

            // Loop melalui setiap item (event libur) dari API
            foreach ($holidaysData as $holiday) {
                $dateString = $holiday['start']['date'] ?? null;
                $description = $holiday['summary'] ?? null;

                // Lewati jika data tidak lengkap
                if (!$dateString || !$description) {
                    $this->warn("   ⚠️ Data item API tidak lengkap atau format event salah, dilewati.");
                    continue;
                }

                // Coba simpan ke database
                if ($this->saveHoliday($dateString, $description)) {
                    $savedCount++;
                } else {
                    $totalErrors++;
                }
            }

            if ($totalErrors > 0) {
                 $this->warn("⚠️ Proses selesai dengan {$totalErrors} error saat menyimpan.");
            }
            $this->info("✅ Proses selesai. {$savedCount} data hari libur disimpan/diperbarui.");
            return $totalErrors > 0 ? 1 : 0;

        } catch (\Exception $e) {
            $this->error("❌ Terjadi error: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Fungsi helper untuk menyimpan ke database
     * Menerima string tanggal YYYY-MM-DD dari API
     */
    private function saveHoliday(string $dateString, string $description) : bool
    {
        try {
            // Langsung gunakan tanggal dari API karena sudah YYYY-MM-DD
            $formattedDate = $dateString;

            // Validasi sederhana (opsional, karena API Google seharusnya valid)
            Carbon::parse($formattedDate);

            // Simpan atau update data
            Holiday::updateOrCreate(
                ['date' => $formattedDate],
                ['description' => $description]
            );

            $this->line("      💾 Disimpan: {$formattedDate} - {$description}");
            return true;

        } catch (\Exception $e) {
            $this->error("      ⚠️ Gagal parse/simpan tanggal: '{$dateString}' ({$e->getMessage()})");
            return false;
        }
    }
}