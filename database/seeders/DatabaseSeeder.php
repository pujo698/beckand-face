<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Shift;
use App\Models\Holiday;
use App\Models\AttendanceLog;
use App\Models\LeaveRequest;
use App\Models\UserSchedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'position' => 'Administrator',
                'status' => 'active',
            ]
        );
        $this->command->info('Admin (admin@example.com) checked/created.');

        User::firstOrCreate(
            ['email' => 'karyawan@example.com'],
            [
                'name' => 'Karyawan User',
                'password' => Hash::make('karyawan123'),
                'role' => 'employee',
                'position' => 'Staff Contoh',
                'status' => 'active',
            ]
        );
        $this->command->info('Example employee (karyawan@example.com) checked/created.');

        $shiftPagi = Shift::firstOrCreate(
            ['name' => 'Shift Pagi'],
            ['start_time' => '08:00:00', 'end_time' => '17:00:00']
        );

        $shiftMalam = Shift::firstOrCreate(
            ['name' => 'Shift Malam'],
            ['start_time' => '20:00:00', 'end_time' => '05:00:00']
        );
        $this->command->info('Dummy shifts (Pagi & Malam) checked/created.');

        Holiday::firstOrCreate(
            ['date' => now()->subDays(10)->toDateString()],
            ['description' => 'Libur Nasional Contoh 1']
        );
        Holiday::firstOrCreate(
            ['date' => now()->subDays(25)->toDateString()],
            ['description' => 'Libur Nasional Contoh 2']
        );
        $holidays = Holiday::pluck('date')->map(fn($date) => Carbon::parse($date)->format('Y-m-d'));

        $existingEmployeesCount = User::where('role', 'employee')->count();
        $employeesToCreate = 15 - $existingEmployeesCount;

        if ($employeesToCreate > 0) {
            $employees = User::factory($employeesToCreate)->create();
            $this->command->info("{$employeesToCreate} new dummy employees created (password: 'password').");
        } else {
            $this->command->info('Minimum 15 employees already exist. Skipping factory.');
            $employees = User::where('role', 'employee')->take(15)->get();
        }

        $this->command->info('Generating histories for dummy employees (60 days)...');
        $period = CarbonPeriod::create(now()->subDays(60), now()->subDay());

        foreach ($employees as $employee) {
            if ($employee->attendanceLogs()->count() > 0) {
                $this->command->warn("-> Skipping history for {$employee->email} (already has data).");
                continue;
            }

            $this->command->info("-> Generating data for {$employee->email}...");

            foreach ($period as $date) {
                if ($date->isWeekend() || $holidays->contains($date->format('Y-m-d'))) {
                    continue;
                }

                $assignedShift = ($employee->id % 2 == 0) ? $shiftPagi : $shiftMalam;

                UserSchedule::create([
                    'user_id' => $employee->id,
                    'shift_id' => $assignedShift->id,
                    'date' => $date->toDateString(),
                ]);

                $rand = rand(1, 100);

                if ($rand <= 80) {
                    $isLate = rand(1, 10) <= 2;
                    $status = $isLate ? 'Terlambat' : 'Tepat Waktu';
                    $shiftStartTime = Carbon::parse($assignedShift->start_time);
                    $checkIn = $date->copy()->setTime($shiftStartTime->hour, $shiftStartTime->minute);
                    if ($isLate) {
                        $checkIn->addMinutes(rand(31, 120));
                    } else {
                        $checkIn->subMinutes(rand(0, 30));
                    }
                    $checkOut = $checkIn->copy()->addHours(rand(8, 9));

                    AttendanceLog::create([
                        'user_id' => $employee->id,
                        'check_in' => $checkIn,
                        'check_out' => $checkOut,
                        'status' => $status,
                        'latitude' => -6.917,
                        'longitude' => 107.619,
                    ]);
                } elseif ($rand > 80 && $rand <= 85) {
                    $types = ['cuti', 'sakit', 'izin'];
                    $type = $types[array_rand($types)];
                    LeaveRequest::create([
                        'user_id' => $employee->id,
                        'reason' => 'Alasan dummy ' . $type,
                        'duration' => $date->format('Y-m-d'),
                        'type' => $type,
                        'status' => 'approved',
                        'approved_by' => $admin->id,
                    ]);
                }
            }
        }

        $this->command->info('Dummy data generation complete.');
    }
}
