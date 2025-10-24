<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\HolidaysImport;

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

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv'
        ]);

        try {
            // Proses impor file
            Excel::import(new HolidaysImport, $request->file('file'));
            
            return response()->json(['message' => 'Data hari libur berhasil diimpor.'], 200);

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
             // Menangkap error validasi dari Excel 
             return response()->json(['errors' => $e->failures()], 422);
        } catch (\Exception $e) {
            // Tangani jika ada error 
            return response()->json(['message' => 'Gagal mengimpor file: ' . $e->getMessage()], 500);
        }
    }
}