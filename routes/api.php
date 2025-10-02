<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\LeaveController;

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
        Route::put('/users/{user}', [AdminController::class, 'update']);
        Route::delete('/users/{user}', [AdminController::class, 'destroy']);
        
        // Log absensi
        Route::get('/attendance-logs', [AttendanceController::class, 'logs']);
        
        // Permintaan cuti (approve/reject)
        Route::get('/leave-requests', [LeaveController::class, 'index']);
        Route::put('/leave-requests/{leaveRequest}/approve', [LeaveController::class, 'approve']);
        Route::put('/leave-requests/{leaveRequest}/reject', [LeaveController::class, 'reject']);
    });

    // ==============================
    // 🔹 Rute khusus Employee
    // ==============================
    Route::middleware('employee')->prefix('employee')->group(function () {
        // Profil karyawan
        Route::get('/profile', [EmployeeController::class, 'profile']);
        Route::post('/profile', [EmployeeController::class, 'updateProfile']); // POST dipakai untuk update + upload file
        
        // Absensi
        Route::post('/check-in', [AttendanceController::class, 'checkIn']);
        Route::post('/check-out', [AttendanceController::class, 'checkOut']);
        
        // Ajukan cuti
        Route::post('/leave-request', [LeaveController::class, 'store']);
    });
});
