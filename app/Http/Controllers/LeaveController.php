<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Holiday;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class LeaveController extends Controller
{
    // ==========================================================
    // BAGIAN EMPLOYEE
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
    // BAGIAN ADMIN
    // ==========================================================

    /**
     * GET: /api/admin/leave-requests
     */
    public function index(Request $request)
    {
        $query = LeaveRequest::with('user:id,name,position')->latest();

        if ($request->filled('status') && in_array($request->status, ['pending', 'approved', 'rejected'])) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type') && $request->type !== 'Semua Jenis') {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%');
            });
        }

        $leaves = $query->get();

        // **Fix Statistik Sesuai Hari Kerja (Weekday + Libur Nasional)**
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
     * PUT: /api/admin/leave-requests/{id}/status
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected'
        ]);

        $leaveRequest = LeaveRequest::findOrFail($id);

        $leaveRequest->update([
            'status' => $request->status,
            'approved_by' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Status permohonan berhasil diperbarui menjadi ' . $request->status,
            'data' => $leaveRequest
        ]);
    }


    // ==========================================================
    // 🔥 API BARU (Untuk AI + UI agar konsisten hitung hari)
    // GET: /api/admin/user/{id}/leave-breakdown
    // ==========================================================
    public function leaveBreakdown($userId)
    {
        $leaves = LeaveRequest::where('user_id', $userId)
            ->where('status', 'approved')
            ->get();

        $result = [
            'izin' => 0,
            'cuti' => 0,
            'sakit' => 0,
        ];

        foreach ($leaves as $leave) {
            $start = Carbon::parse($leave->start_date);
            $end = Carbon::parse($leave->end_date);

            $holidays = Holiday::pluck('date')->toArray();
            $period = CarbonPeriod::create($start, $end);

            foreach ($period as $date) {
                if ($date->isWeekend()) continue;
                if (in_array($date->format('Y-m-d'), $holidays)) continue;

                $result[$leave->type]++;
            }
        }

        return response()->json($result);
    }
}
