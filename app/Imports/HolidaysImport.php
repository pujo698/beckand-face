<?php

namespace App\Imports;

use App\Models\Holiday;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow; 
use Maatwebsite\Excel\Concerns\WithUpserts;    

class HolidaysImport implements ToModel, WithHeadingRow, WithUpserts
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        return new Holiday([
            'date'        => $row['tanggal'], // Format: YYYY-MM-DD
            'description' => $row['deskripsi'],
        ]);
    }

    /**
     * Ini adalah kunci agar data tidak duplikat.
     * Kita menggunakan kolom 'date' sebagai key unik.
     */
    public function uniqueBy()
    {
        return 'date';
    }
}