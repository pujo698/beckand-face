<?php

namespace App\Http\Controllers;

use App\Models\OnDutyAuthorization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OnDutyAuthorizationController extends Controller
{
    /**
     * MENAMPILKAN DATA (GET)
     * Endpoint: /api/admin/on-duty-authorizations
     */
    public function index(Request $request)
    {
        // 1. Query Data
        $query = OnDutyAuthorization::with('user:id,name,position')->latest();

        // Filter Status (Pending, Approved, Rejected)
        if ($request->filled('status') && in_array($request->status, ['approved', 'rejected', 'pending'])) {
            $query->where('status', $request->status);
        }

        // Filter Search Nama
        if ($request->filled('search')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%');
            });
        }

        $data = $query->get();

        // 2. Hitung Statistik (Untuk Kartu Dashboard)
        $stats = [
            'total'    => OnDutyAuthorization::count(),
            'pending'  => OnDutyAuthorization::where('status', 'pending')->count(),
            'approved' => OnDutyAuthorization::where('status', 'approved')->count(),
            'rejected' => OnDutyAuthorization::where('status', 'rejected')->count(),
        ];

        // Return format JSON lengkap
        return response()->json([
            'stats' => $stats,
            'data'  => $data
        ]);
    }

    /**
     * MENYIMPAN DATA BARU (POST)
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id'    => 'required|exists:users,id',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'reason'     => 'required|string',
        ]);

        $exists = OnDutyAuthorization::where('user_id', $request->user_id)
                    ->whereDate('start_date', $request->start_date)
                    ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Gagal: Karyawan ini sudah memiliki Surat Tugas pada tanggal tersebut.'
            ], 422);
        }

        $auth = OnDutyAuthorization::create([
            'user_id'     => $request->user_id,
            'start_date'  => $request->start_date,
            'end_date'    => $request->end_date,
            'reason'      => $request->reason,
            'status'      => 'Approved', 
            'approved_by' => Auth::id(),
        ]);

        return response()->json($auth, 201);
    }

    /**
     * UPDATE STATUS / APPROVAL (PUT)
     * Endpoint: /api/admin/on-duty-authorizations/{id}
     */
    public function update(Request $request, OnDutyAuthorization $onDutyAuthorization)
    {
        // Validasi input dari Vue
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'reason'     => 'required|string',
        ]);

        // Update data
        $onDutyAuthorization->update([
            'start_date'  => $request->start_date,
            'end_date'    => $request->end_date,
            'reason'      => $request->reason,
            'description' => $request->description,
        ]);

        return response()->json(['message' => 'Data berhasil diperbarui.']);
    }

    /**
     * HAPUS DATA (DELETE)
     * Endpoint: /api/admin/on-duty-authorizations/{id}
     */
    public function destroy(OnDutyAuthorization $onDutyAuthorization)
    {
        $onDutyAuthorization->delete();
        return response()->json(['message' => 'Izin dinas luar berhasil dihapus.']);
    }
}