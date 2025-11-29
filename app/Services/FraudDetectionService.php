<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\AttendanceLog;
use App\Models\OnDutyAuthorization;

class FraudDetectionService
{
    public function analyzeCheckIn($user, $latitude, $longitude, $deviceInfo = null)
    {
        $score = 0;
        $notes = [];
        $today = Carbon::today()->toDateString();

        $isWFH = OnDutyAuthorization::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->exists();

        $officeLat = config('services.office.latitude', -6.175392);
        $officeLng = config('services.office.longitude', 106.827153);
        $maxRadiusKm = config('services.office.radius_km', 0.1);

        if ($latitude && $longitude) {
            $distance = $this->calculateDistance($latitude, $longitude, $officeLat, $officeLng);
            
            if ($isWFH) {
                $notes[] = "Mode WFH/Dinas.";
            } else {
                if ($distance > 50) {
                    $score += 80;
                    $notes[] = "Lokasi sangat jauh ({$distance} km).";
                } elseif ($distance > $maxRadiusKm) {
                    $score += 50;
                    $notes[] = "Di luar radius kantor.";
                }
            }
        }

        $historyLogs = AttendanceLog::where('user_id', $user->id)
                                    ->latest()
                                    ->take(3)
                                    ->get();
        
        if ($historyLogs->count() >= 3) {
            $consecutiveMatch = 0;
            
            foreach ($historyLogs as $log) {
                if (
                    number_format((float)$log->latitude, 6) === number_format((float)$latitude, 6) &&
                    number_format((float)$log->longitude, 6) === number_format((float)$longitude, 6)
                ) {
                    $consecutiveMatch++;
                } else {
                    break;
                }
            }


            if ($consecutiveMatch >= 3) {
                $score += 80; 
                $notes[] = "Koordinat statis terdeteksi 4x berturut-turut (Indikasi Fake GPS).";
            }
        }

        $lastLog = $historyLogs->first(); 
        if ($lastLog && $lastLog->created_at->isToday()) {
             $distDiff = $this->calculateDistance($latitude, $longitude, $lastLog->latitude, $lastLog->longitude);
             $timeDiff = Carbon::now()->diffInMinutes($lastLog->created_at);
             
             if ($distDiff > 5 && $timeDiff < 2) {
                 $score += 100;
                 $notes[] = "Terdeteksi perpindahan lokasi tidak wajar (Teleportasi).";
             }
        }

        $hour = Carbon::now()->hour;
        if ($hour < 5 || $hour > 23) {
            $score += 20;
            $notes[] = "Absen jam tidak wajar.";
        }

        return [
            'score' => min($score, 100),
            'note'  => implode(' ', $notes)
        ];
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371; 
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earthRadius * $c;
    }
}