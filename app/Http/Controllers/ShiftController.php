<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    // Menampilkan semua shift
    public function index()
    {
        return Shift::all();
    }

    // Menyimpan shift baru
    public function store(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|unique:shifts',
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i',
        ]);

        $shift = Shift::create($request->all());
        return response()->json($shift, 201);
    }

    // Mengupdate shift
    public function update(Request $request, Shift $shift)
    {
        $request->validate([
            'name'       => 'required|string|unique:shifts,name,' . $shift->id,
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i',
        ]);

        $shift->update($request->all());
        return response()->json($shift);
    }

    // Menghapus shift
    public function destroy(Shift $shift)
    {
        $shift->delete();
        return response()->json(['message' => 'Shift berhasil dihapus.']);
    }
}