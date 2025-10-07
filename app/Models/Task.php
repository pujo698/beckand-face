<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = [
        'title',
        'description',
        'due_date',
        'created_by',
    ];

    // Relasi ke User (Admin yang membuat tugas)
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Relasi many-to-many ke User (Karyawan yang ditugaskan)
    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('status')->withTimestamps();
    }
}
