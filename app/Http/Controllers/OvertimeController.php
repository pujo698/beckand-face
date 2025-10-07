<?php

namespace App\Http\Controllers;

use App\Models\Overtime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OvertimeController extends Controller
{
    /**
     * Karyawan mengajukan lembur baru.
     */
    public function store(Request $request)
    {
        $request->validate([
            'date'       => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i|after:start_time',
            'reason'     => 'required|string',
        ]);

        $overtime = Auth::user()->overtimes()->create($request->all());

        return response()->json(['message' => 'Pengajuan lembur berhasil dikirim.', 'data' => $overtime], 201);
    }

    /**
     * Admin melihat semua pengajuan lembur.
     */
    public function index()
    {
        return Overtime::with('user:id,name')->latest()->get();
    }

    /**
     * Admin menyetujui pengajuan lembur.
     */
    public function approve(Overtime $overtime)
    {
        $overtime->update([
            'status'      => 'approved',
            'approved_by' => Auth::id(),
        ]);

        return response()->json(['message' => 'Pengajuan lembur disetujui.', 'data' => $overtime]);
    }

    /**
     * Admin menolak pengajuan lembur.
     */
    public function reject(Overtime $overtime)
    {
        $overtime->update([
            'status'      => 'rejected',
            'approved_by' => Auth::id(),
        ]);

        return response()->json(['message' => 'Pengajuan lembur ditolak.', 'data' => $overtime]);
    }
}