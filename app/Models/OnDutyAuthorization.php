<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnDutyAuthorization extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'reason',
        'description',
        'status',
        'approved_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}