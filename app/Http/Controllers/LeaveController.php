<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeaveController extends Controller
{
    /**
     * Karyawan membuat permohonan izin baru.
     */
    public function store(Request $request)
    {
        $request->validate([
            'reason' => 'required|string',
            'duration' => 'required|string',
            'type' => 'nullable|in:izin,cuti,sakit',
            'support_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        $path = null;
        if ($request->hasFile('support_file')) {
            $path = $request->file('support_file')->store('leave_support', 'public');
        }

        $leaveRequest = Auth::user()->leaveRequests()->create([
            'reason' => $request->reason,
            'duration' => $request->duration,
            'type' => $request->type ?? 'izin',
            'support_file' => $path,
        ]);

        return response()->json(['message' => 'Permohonan izin berhasil diajukan.', 'data' => $leaveRequest], 201);
    }

    // ===================================================================
    // ===            AWAL DARI BAGIAN YANG DITAMBAHKAN                ===
    // ===================================================================

    /**
     * Karyawan melihat riwayat permohonan izin miliknya.
     */
    public function history()
    {
        return Auth::user()->leaveRequests()->latest()->get();
    }

    /**
     * Karyawan melihat detail satu permohonan izin.
     */
    public function show(LeaveRequest $leaveRequest)
    {
        // Keamanan: Pastikan karyawan hanya bisa melihat izin miliknya sendiri
        if ($leaveRequest->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Memuat data relasi admin yang menyetujui/menolak (approver)
        $leaveRequest->load('approver:id,name');

        return response()->json($leaveRequest);
    }

    // ===================================================================
    // ===             AKHIR DARI BAGIAN YANG DITAMBAHKAN              ===
    // ===================================================================


    /**
     * Admin melihat semua permohonan izin.
     */
    public function index()
    {
        return LeaveRequest::with('user:id,name')->latest()->get();
    }

    /**
     * Admin menyetujui permohonan izin.
     */
    public function approve(LeaveRequest $leaveRequest)
    {
        $leaveRequest->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
        ]);
        return response()->json(['message' => 'Permohonan izin disetujui.', 'data' => $leaveRequest]);
    }

    /**
     * Admin menolak permohonan izin.
     */
    public function reject(LeaveRequest $leaveRequest)
    {
        $leaveRequest->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
        ]);
        return response()->json(['message' => 'Permohonan izin ditolak.', 'data' => $leaveRequest]);
    }
}