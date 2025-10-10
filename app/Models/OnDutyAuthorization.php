<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnDutyAuthorization extends Model
{
    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'reason',
        'approved_by',
    ];
}
