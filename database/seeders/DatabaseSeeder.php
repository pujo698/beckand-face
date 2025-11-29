<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Holiday; 
use App\Models\AttendanceLog;
use App\Models\LeaveRequest;
use App\Models\OnDutyAuthorization;
use App\Models\Shift;
use App\Models\UserSchedule;
use App\Models\AttendanceSummary;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🔄 Menambahkan data simulasi bulan ini & bulan lalu tanpa menghapus data lama...');

        /** -------------------------------------------------
         * 1. ADMIN
         ---------------------------------------------------*/
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'position' => 'Super Admin',
                'status' => 'active'
            ]
        );

        /** -------------------------------------------------
         * 2. SHIFT WAJIB ADA
         ---------------------------------------------------*/
        $shiftPagi = Shift::firstOrCreate(
            ['name' => 'Shift Pagi'],
            ['start_time' => '08:00:00', 'end_time' => '17:00:00']
        );

        /** -------------------------------------------------
         * 3. SAMPLE EMPLOYEE
         ---------------------------------------------------*/
        $budi = User::firstOrCreate(['email' => 'budi@example.com'], [
            'name' => 'Budi Santoso',
            'password' => Hash::make('password123'),
            'role' => 'employee',
            'position' => 'Sales',
            'status' => 'active',
            'phone' => '08123456789',
        ]);

        $lela = User::firstOrCreate(['email' => 'lela@example.com'], [
            'name' => 'Lela Marquardt',
            'password' => Hash::make('password123'),
            'role' => 'employee',
            'position' => 'Marketing',
            'status' => 'active',
        ]);

        if (User::where('role','employee')->count() < 10) {
            User::factory(8)->create([
                'role' => 'employee',
                'password' => Hash::make('password123')
            ]);
        }

        $employees = User::where('role','employee')->get();

        /** -------------------------------------------------
         * 4. RANGE TANGGAL: BULAN LALU → BULAN INI
         ---------------------------------------------------*/
        $start = now()->subMonth()->startOfMonth();
        $end   = now()->today();

        $period = CarbonPeriod::create($start,$end);
        $holidays = Holiday::pluck('date')->map(fn($d)=>Carbon::parse($d)->format('Y-m-d'));

        $officeLat = -6.175392;
        $officeLng = 106.827153;

        foreach ($employees as $emp) {

            foreach ($period as $date) {

                $dateStr = $date->format('Y-m-d');

                // skip weekend or holiday
                if ($date->isWeekend() || $holidays->contains($dateStr)) continue;

                // skip jika jadwal sudah ada
                UserSchedule::firstOrCreate([
                    'user_id' => $emp->id,
                    'date'    => $dateStr
                ],[
                    'shift_id'=> $shiftPagi->id
                ]);

                // jika log untuk tanggal ini sudah ada → skip (tidak overwrite)
                if (AttendanceLog::where('user_id',$emp->id)->whereDate('check_in',$dateStr)->exists()) {
                    $this->ensureSummaryExists($emp->id,$dateStr);
                    continue;
                }

                $rand = rand(1,100);

                // -----------------------------------
                // Khusus Budi & Lela (simulasi buruk)
                // -----------------------------------
                if ($emp->id == $budi->id || $emp->id == $lela->id) {

                    if ($rand <= 30) { // Alfa
                        $this->storeSummary($emp->id,$dateStr,'alfa');
                        continue;
                    }

                    if ($rand > 30 && $rand <= 50) { // Izin/Sakit
                        LeaveRequest::firstOrCreate([
                            'user_id'=>$emp->id,
                            'duration'=>"$dateStr - $dateStr",
                            'type'=>'sakit',
                        ],[
                            'reason'=>'Sakit',
                            'status'=>'approved',
                            'approved_by'=>$admin->id
                        ]);

                        $this->storeSummary($emp->id,$dateStr,'izin');
                        continue;
                    }
                } else {
                    if ($rand <= 5) { // Alfa normal
                        $this->storeSummary($emp->id,$dateStr,'alfa');
                        continue;
                    }
                }

                // -----------------------------------
                // Create attendance log
                // -----------------------------------
                $late = rand(1,100) <= 20;
                $status = $late ? 'Terlambat' : 'Tepat Waktu';

                $checkIn = $date->copy()->setTime(8,0,0);
                $checkIn = $late ? $checkIn->addMinutes(rand(5,60)) : $checkIn->subMinutes(rand(0,10));
                $checkOut = $checkIn->copy()->addHours(8);

                AttendanceLog::create([
                    'user_id'=>$emp->id,
                    'check_in'=>$checkIn,
                    'check_out'=>$checkOut,
                    'status'=>$status,
                    'latitude'=>$officeLat,
                    'longitude'=>$officeLng,
                    'risk_score'=>rand(0,10),
                    'risk_note'=>'OK',
                    'device_info'=>'SeederDevice',
                ]);

                $this->storeSummary($emp->id,$dateStr,$late?'terlambat':'hadir');
            }
        }

        $this->command->info("✔ Done — data absensi simulasi berhasil ditambahkan.");
    }

    private function ensureSummaryExists($userId,$date)
    {
        AttendanceSummary::firstOrCreate([
            'user_id'=>$userId,
            'date'=>$date
        ],[
            'status'=>'hadir'
        ]);
    }

    private function storeSummary($userId,$date,$status)
    {
        AttendanceSummary::updateOrCreate(
            ['user_id'=>$userId,'date'=>$date],
            ['status'=>$status]
        );
    }
}
