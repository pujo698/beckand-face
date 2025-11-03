<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

/**
 * @property int $id
 * @property int $user_id
 * @property \Carbon\Carbon $check_in
 * @property \Carbon\Carbon|null $check_out
 * @property string $status
 * @property-read User $user
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceLog whereCheckIn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceLog whereCheckOut($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceLog whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceLog whereUserId($value)
 * @mixin \Eloquent
 */
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
    ];

        protected $casts = [
        'check_in'  => 'datetime:Y-m-d\TH:i:sP',
        'check_out' => 'datetime:Y-m-d\TH:i:sP',
        'latitude'  => 'float',
        'longitude' => 'float',
    ];

    // Relasi ke User
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
