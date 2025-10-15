<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\OvertimeController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\OnDutyAuthorizationController;
use App\Http\Controllers\HolidayController;

// Endpoint publik (tidak butuh token)
Route::post('/login', [AuthController::class, 'login']);

// Endpoint dengan autentikasi Sanctum
Route::middleware('auth:sanctum')->group(function () {
    // Logout
    Route::post('/logout', [AuthController::class, 'logout']);

    // Data user yang sedang login
    Route::get('/user', fn(Request $request) => $request->user());

    // ==============================
    // 🔹 Rute khusus Admin
    // ==============================
    Route::middleware('admin')->prefix('admin')->group(function () {
        // Kelola User
        Route::get('/users', [AdminController::class, 'index']);
        Route::post('/users', [AdminController::class, 'store']);
        // BARU: Menampilkan data user tunggal berdasarkan ID
        Route::get('/users/{user}', [AdminController::class, 'show']);
        Route::put('/users/{user}', [AdminController::class, 'update']);
        Route::delete('/users/{user}', [AdminController::class, 'destroy']);
        
        // Log absensi
        Route::get('/attendance-logs', [AttendanceController::class, 'logs']);
        
        // Permintaan izin cuti (approve/reject)
        Route::get('/leave-requests', [LeaveController::class, 'index']);
        Route::put('/leave-requests/{leaveRequest}/approve', [LeaveController::class, 'approve']);
        Route::put('/leave-requests/{leaveRequest}/reject', [LeaveController::class, 'reject']);

        // Pengajuan lembur (approve/reject)
        Route::get('/overtimes', [OvertimeController::class, 'index']);
        Route::put('/overtimes/{overtime}/approve', [OvertimeController::class, 'approve']);
        Route::put('/overtimes/{overtime}/reject', [OvertimeController::class, 'reject']);

        // Kelola Shift
        Route::get('/shifts', [ShiftController::class, 'index']);
        Route::post('/shifts', [ShiftController::class, 'store']);
        Route::put('/shifts/{shift}', [ShiftController::class, 'update']);
        Route::delete('/shifts/{shift}', [ShiftController::class, 'destroy']);

        // Kelola Jadwal Kerja
        Route::post('/schedules', [ScheduleController::class, 'store']);
        Route::delete('/schedules/user/{user}/date/{date}', [ScheduleController::class, 'destroy']);

        // laporan 
        Route::get('/reports/attendance', [ReportController::class, 'exportAttendance']);
        
        // Kelola Tugas
        Route::post('/tasks', [TaskController::class, 'store']);

        // Kelola Izin Dinas Luar
        Route::get('/on-duty-authorizations', [OnDutyAuthorizationController::class, 'index']);
        Route::post('/on-duty-authorizations', [OnDutyAuthorizationController::class, 'store']);
        Route::delete('/on-duty-authorizations/{onDutyAuthorization}', [OnDutyAuthorizationController::class, 'destroy']);

        // Kelola Hari Libur
        Route::get('/holidays', [HolidayController::class, 'index']);
        Route::post('/holidays', [HolidayController::class, 'store']);
        Route::delete('/holidays/{holiday}', [HolidayController::class, 'destroy']);
    }); 

    // ==============================
    // 🔹 Rute khusus Employee
    // ==============================
    Route::middleware('employee')->prefix('employee')->group(function () {
        // Profil karyawan
        Route::get('/profile', [EmployeeController::class, 'profile']);
        Route::post('/profile', [EmployeeController::class, 'updateProfile']); // POST dipakai untuk update + upload file
        Route::get('/stats', [EmployeeController::class, 'monthlyStats']);
        
        // Absensi
        Route::post('/check-in', [AttendanceController::class, 'checkIn']);
        Route::post('/check-out', [AttendanceController::class, 'checkOut']);
        
        // Ajukan izin cuti
        Route::post('/leave-request', [LeaveController::class, 'store']);
        Route::get('/leave-requests/history', [LeaveController::class, 'history']);
        Route::get('/leave-requests/{leaveRequest}', [LeaveController::class, 'show']);
        
        // Ajukan Lembur 
        Route::post('/overtimes', [OvertimeController::class, 'store']);

        // Lihat jadwal kerja
        Route::get('/schedules', [ScheduleController::class, 'index']);

        // Lihat tugas saya
        Route::get('/tasks', [TaskController::class, 'myTasks']);
        Route::put('/tasks/{task}/status', [TaskController::class, 'updateTaskStatus']);
       
        // lihat riwayat absensi
        Route::get('/attendances/history', [AttendanceController::class, 'history']);
        Route::get('/attendances/calendar', [AttendanceController::class, 'getAttendanceCalendar']);
    });
});
