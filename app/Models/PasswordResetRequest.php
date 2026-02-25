<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetRequest extends Model
{
    protected $fillable = [
        'user_id',
        'email',
        'verification_details',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
