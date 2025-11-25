<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeaveController extends Controller
{
    // ==========================================================
    // 1. BAGIAN EMPLOYEE (Karyawan) - TETAP SAMA
    // ==========================================================

    public function store(Request $request)
    {
        $request->validate([
            'reason' => 'required|string',
            'duration' => 'required|string',
            'type' => 'nullable|in:izin,cuti,sakit',
            'support_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
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
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ]);

        return response()->json(['message' => 'Permohonan izin berhasil diajukan.', 'data' => $leaveRequest], 201);
    }

    public function history(Request $request)
    {
        $query = Auth::user()->leaveRequests()->latest();
        if ($request->has('status') && $request->status !== 'semua') {
            $query->where('status', $request->status);
        }
        return $query->get();
    }

    public function show(LeaveRequest $leaveRequest)
    {
        if ($leaveRequest->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $leaveRequest->load('approver:id,name');
        return response()->json($leaveRequest);
    }

    // ==========================================================
    // 2. BAGIAN ADMIN (RESTful Standard) - UPDATED
    // ==========================================================

    /**
     * Admin: Menampilkan daftar cuti BESERTA statistik.
     * Method: GET /api/admin/leave-requests
     */
    public function index(Request $request)
    {
        // A. Query Data Utama
        $query = LeaveRequest::with('user:id,name,position')->latest();

        // Filter Status
        if ($request->has('status') && in_array($request->status, ['approved', 'rejected', 'pending'])) {
            $query->where('status', $request->status);
        }
        // Filter Jenis
        if ($request->filled('type') && $request->type !== 'Semua Jenis') {
            $query->where('type', $request->type);
        }

        // Filter Search Nama
        if ($request->has('search')) { // Frontend kita pakai param 'search'
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%');
            });
        }

        // (Filter tambahan Anda tetap saya pertahankan, bagus untuk pengembangan nanti)
        if ($request->has('name')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->name . '%');
            });
        }
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $leaves = $query->get();

        // B. Hitung Statistik (Untuk 4 Kartu di Dashboard Web)
        // Kita hitung semua data tanpa filter agar kartu statistik tetap menunjukkan total keseluruhan
        $stats = [
            'total'    => LeaveRequest::count(),
            'pending'  => LeaveRequest::where('status', 'pending')->count(),
            'approved' => LeaveRequest::where('status', 'approved')->count(),
            'rejected' => LeaveRequest::where('status', 'rejected')->count(),
        ];

        // C. Return Format Gabungan
        return response()->json([
            'stats'  => $stats,
            'leaves' => $leaves
        ]);
    }

    /**
     * Admin: Mengubah status (Approve/Reject)
     * Method: PUT /api/admin/leave-requests/{id}/status
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected'
        ]);

        $leaveRequest = LeaveRequest::findOrFail($id);

        $leaveRequest->update([
            'status' => $request->status,
            'approved_by' => Auth::id(), // Otomatis catat siapa yang approve
        ]);

        return response()->json([
            'message' => 'Status permohonan berhasil diperbarui menjadi ' . $request->status,
            'data' => $leaveRequest
        ]);
    }
}