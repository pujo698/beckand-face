<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
        ]);

         $middleware->alias([
        'admin' => \App\Http\Middleware\AdminMiddleware::class,
        'employee' => \App\Http\Middleware\EmployeeMiddleware::class,
         ]);

        // $middleware->validateCsrfTokens(except: [
        //     'api/*'
        // ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withSchedule(function (Illuminate\Console\Scheduling\Schedule $schedule) {

        $schedule->call(function () {

            $today = now()->format('Y-m-d');

            $employees = \App\Models\User::where('role', 'employee')
                ->where('status', 'active')
                ->get();

            foreach ($employees as $employee) {

                if (\App\Models\AttendanceSummary::where('user_id',$employee->id)->where('date',$today)->exists()) {
                    continue;
                }

                $log = \App\Models\AttendanceLog::where('user_id',$employee->id)
                    ->whereDate('check_in',$today)
                    ->first();

                $leave = \App\Models\LeaveRequest::where('user_id',$employee->id)
                    ->where('status','approved')
                    ->whereRaw("? BETWEEN STR_TO_DATE(SUBSTRING_INDEX(duration,' - ',1),'%Y-%m-%d')
                                    AND STR_TO_DATE(SUBSTRING_INDEX(duration,' - ',-1),'%Y-%m-%d')", [$today])
                    ->first();

                $status = 'alfa';

                if ($log) {
                    $status = $log->status === 'Terlambat' ? 'terlambat' : 'hadir';
                } elseif ($leave) {
                    $status = strtolower($leave->type) ?? 'izin';
                }

                \App\Models\AttendanceSummary::create([
                    'user_id' => $employee->id,
                    'date' => $today,
                    'status' => $status
                ]);
            }

        })->dailyAt('23:59');

    })
    ->create();
