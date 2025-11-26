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
        // A. Query Dasar
        $query = LeaveRequest::with('user:id,name,position')->latest();

        // 1. Filter Status
        // Hanya jalan jika status yang dikirim adalah: pending, approved, atau rejected
        if ($request->filled('status') && in_array($request->status, ['pending', 'approved', 'rejected'])) {
            $query->where('status', $request->status);
        }

        // 2. Filter Jenis Cuti (Perbaikan Logic)
        // Hanya jalan jika type terisi DAN bukan 'Semua Jenis'
        if ($request->filled('type') && $request->type !== 'Semua Jenis') {
            $query->where('type', $request->type);
        }

        // 3. Filter Search Nama (PENTING: Pakai filled, bukan has)
        // Ini mencegah query jalan saat search box kosong
        if ($request->filled('search')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%');
            });
        }

        // Ambil Data
        $leaves = $query->get();

        // B. Hitung Statistik (Tetap sama)
        $stats = [
            'total'    => LeaveRequest::count(),
            'pending'  => LeaveRequest::where('status', 'pending')->count(),
            'approved' => LeaveRequest::where('status', 'approved')->count(),
            'rejected' => LeaveRequest::where('status', 'rejected')->count(),
        ];

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