<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http; // Butuh HTTP Client
use App\Models\Holiday;              // Butuh Model
use Carbon\Carbon;                    // Butuh Carbon

// Ganti nama class jika mau (misal: FetchGoogleHolidays)
class ScrapeHolidays extends Command
{
    /**
     * Tanda tangan command (ganti jika Anda ganti nama class)
     */
    // protected $signature = 'app:fetch-google-holidays {--year=}'; // Contoh nama baru
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
    private $calendarId = 'en.indonesian#holiday@group.v.calendar.google.com';

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
        $timeMin = Carbon::create($year, 1, 1)->startOfDay()->toRfc3339String(); // Format RFC3339
        $timeMax = Carbon::create($year, 12, 31)->endOfDay()->toRfc3339String(); // Format RFC3339

        // Ganti {calendarId} di URL API dengan ID kalender yang benar
        $fullApiUrl = str_replace('{calendarId}', urlencode($this->calendarId), $this->apiUrl);

        try {
            // Lakukan GET request ke Google Calendar API
            $response = Http::acceptJson()->get($fullApiUrl, [
                'key' => $apiKey,             // Sertakan API Key
                'timeMin' => $timeMin,          // Rentang awal
                'timeMax' => $timeMax,          // Rentang akhir
                'singleEvents' => 'true',       // Penting: Memecah event berulang
                'orderBy' => 'startTime',     // Urutkan berdasarkan tanggal
                'maxResults' => 250,          // Batas maksimal (250 cukup untuk setahun)
            ]);

            // Cek jika request gagal
            if (!$response->successful()) {
                $this->error("❌ Gagal mengambil data dari Google API. Status: " . $response->status());
                $this->line("   URL: " . $fullApiUrl);
                // Coba tampilkan pesan error dari Google jika ada
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
                // Ambil tanggal ('start' -> 'date') dan nama libur ('summary')
                 // Google API mengembalikan tanggal dalam format 'YYYY-MM-DD' untuk event seharian
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
            return $totalErrors > 0 ? 1 : 0; // Kembalikan kode error jika ada masalah

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