<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function index() { return Holiday::all(); }

    public function store(Request $request)
    {
        $request->validate(['date' => 'required|date|unique:holidays', 'description' => 'required|string']);
        return Holiday::create($request->all());
    }

    public function destroy(Holiday $holiday)
    {
        $holiday->delete();
        return response()->json(['message' => 'Hari libur berhasil dihapus.']);
    }
}