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
            'support_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240',
        ]);

        $path = null;
        $originalName = null;
        if ($request->hasFile('support_file')) {
            $path = $request->file('support_file')->store('leave_support', 'public');
            $originalName = $request->file('support_file')->getClientOriginalName();
        }

        $leaveRequest = Auth::user()->leaveRequests()->create([
            'reason' => $request->reason,
            'duration' => $request->duration,
            'type' => $request->type ?? 'izin',
            'support_file' => $path,
            'support_file_original_name' => $originalName,

        ]);

        return response()->json(['message' => 'Permohonan izin berhasil diajukan.', 'data' => $leaveRequest], 201);
    }
    /**
     * Karyawan melihat riwayat permohonan izin miliknya.
     */
    public function history(Request $request)
    {
        $query = Auth::user()->leaveRequests()->latest();

        // Tambahkan filter status jika ada parameter 'status' di URL
        if ($request->has('status') && $request->status !== 'semua') {
            $query->where('status', $request->status);
        }

        return $query->get();
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

    /**
     * Admin melihat semua permohonan izin.
     */
    public function index(Request $request)
    {
        // Mulai kueri dengan mengambil relasi user
        $query = LeaveRequest::with('user:id,name,position')->latest();

        // Terapkan filter berdasarkan status (approved/rejected/pending)
        if ($request->has('status') && in_array($request->status, ['approved', 'rejected', 'pending'])) {
            $query->where('status', $request->status);
        }

        // Terapkan filter berdasarkan nama karyawan
        if ($request->has('name')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->name . '%');
            });
        }

        // Terapkan filter berdasarkan jabatan
        if ($request->has('position')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('position', 'like', '%' . $request->position . '%');
            });
        }

        // Eksekusi kueri dan kembalikan hasilnya
        return $query->get();
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