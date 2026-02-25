<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class AttendanceLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'check_in',
        'check_out',
        'status',
        'latitude',
        'longitude',
        'risk_score',
        'risk_note',
        'device_info'
    ];

    protected $casts = [
        'check_in'  => 'datetime:Y-m-d\TH:i:sP',
        'check_out' => 'datetime:Y-m-d\TH:i:sP',
        'latitude'  => 'float',
        'longitude' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
