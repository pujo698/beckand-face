<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Models\UserSchedule;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $schedules = $user->schedules()
            ->whereMonth('date', $request->query('month', now()->month))
            ->whereYear('date', $request->query('year', now()->year))
            ->get();

        return response()->json($schedules);
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id'  => 'required|exists:users,id',
            'date'     => 'required|date',
        ]);

        $schedule = UserSchedule::updateOrCreate(
            ['user_id' => $request->user_id, 
             'date' => $request->date],
            []
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
