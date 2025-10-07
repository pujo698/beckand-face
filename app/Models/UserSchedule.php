<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSchedule extends Model
{
    protected $fillable = [
        'user_id',
        'shift_id',
        'date',
    ];

    // Relasi ke User
    public function user(){
        return $this->belongsTo(User::class);
    }
    // Relasi ke Shift
    public function shift(){
        return $this->belongsTo(Shift::class);
    }
}
