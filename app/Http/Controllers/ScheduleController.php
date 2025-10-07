<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\UserSchedule;
use App\Models\User;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $schedules = Auth::user()->schedules()
            ->with('shift') // Ambil juga detail shift-nya
            ->whereMonth('date', $request->query('month', now()->month))
            ->whereYear('date', $request->query('year', now()->year))
            ->get();

        return response()->json($schedules);
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id'  => 'required|exists:users,id',
            'shift_id' => 'required|exists:shifts,id',
            'date'     => 'required|date',
        ]);

        $schedule = UserSchedule::updateOrCreate(
            ['user_id' => $request->user_id, 
             'date' => $request->date],

            [
             'shift_id' => $request->shift_id
            ]
        );
        return response()->json($schedule, 201);
    }

    // Hapus jadwal
    public function destroy(User $user, $date)
    {
        UserSchedule::where('user_id', $user->id)
            ->where('date', $date)
            ->delete();
        return response()->json(['message' => 'Jadwal berhasil dihapus.']);
    }
}
