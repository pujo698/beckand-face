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

    // Tambahkan relasi ke User (opsional tapi bagus)
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
