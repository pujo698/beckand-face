<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use Illuminate\Http\Request;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function exportAttendance(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer|min:2020',
        ]);

        $month = $request->month;
        $year = $request->year;
        $fileName = "laporan-kehadiran-{$month}-{$year}.xlsx";

        // 1. Membuat file Excel baru untuk diunduh.
        $writer = SimpleExcelWriter::streamDownload($fileName);

        // 2. Mengambil data absensi dari database.
        $logs = AttendanceLog::with('user:id,name')
            ->whereMonth('check_in', $month)
            ->whereYear('check_in', $year)
            ->cursor(); // cursor() efisien untuk data dalam jumlah besar.

        // 3. Menambahkan baris judul (header) ke file Excel.
        $writer->addHeader([
            'Nama Karyawan',
            'Tanggal',
            'Jam Masuk',
            'Jam Keluar',
            'Status',
        ]);

        // 4. Menambahkan setiap baris data absensi ke file Excel.
        foreach ($logs as $log) {
            $writer->addRow([
                'nama'      => $log->user->name,
                'tanggal'   => Carbon::parse($log->check_in)->format('d-m-Y'),
                'jam_masuk' => Carbon::parse($log->check_in)->format('H:i:s'),
                'jam_keluar'=> $log->check_out ? Carbon::parse($log->check_out)->format('H:i:s') : 'N/A',
                'status'    => $log->status,
            ]);
        }

        // 5. Mengirim file ke browser untuk diunduh.
        return $writer->toBrowser();
    }
}