<?php

namespace App\Http\Controllers;

use App\Models\OnDutyAuthorization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OnDutyAuthorizationController extends Controller
{
    public function index()
    {
        return OnDutyAuthorization::with('user:id,name')->latest()->get();
    }

// app/Http/Controllers/OnDutyAuthorizationController.php
    public function store(Request $request)
    {
        $request->validate([
            'user_id'    => 'required|exists:users,id',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date', // Memastikan tanggal selesai tidak sebelum tanggal mulai
            'reason'     => 'required|string',
        ]);

        $auth = OnDutyAuthorization::create([
            'user_id'     => $request->user_id,
            'start_date'  => $request->start_date,
            'end_date'    => $request->end_date,
            'reason'      => $request->reason,
            'approved_by' => Auth::id(),
        ]);

        return response()->json($auth, 201);
    }
    public function destroy(OnDutyAuthorization $onDutyAuthorization)
    {
        $onDutyAuthorization->delete();
        return response()->json(['message' => 'Izin dinas luar berhasil dibatalkan.']);
    }
}