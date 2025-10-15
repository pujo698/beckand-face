<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Collection;
use App\Models\AttendanceLog;
use App\Models\LeaveRequest;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $phone
 * @property string $role
 * @property string $photo
 * @property-read Collection|AttendanceLog[] $attendanceLogs
 * @property-read Collection|LeaveRequest[] $leaveRequests
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read int|null $attendance_logs_count
 * @property-read int|null $leave_requests_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhoto($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'position',
        'status',
        'photo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['photo_url'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Aksesor untuk mendapatkan URL foto
    public function getPhotoUrlAttribute()
    {
        if ($this->photo) {
            // Menggabungkan APP_URL dari config dengan path storage
            return config('app.url') . Storage::url($this->photo);
        }
        // Jika tidak ada foto, kembalikan URL ke gambar default
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=random';
    }

    // Relasi ke AttendanceLog
    public function attendanceLogs()
    {
        return $this->hasMany(AttendanceLog::class);
    }

    // Relasi ke LeaveRequest
    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    // Relasi ke Overtime
    public function overtimes()
    {
        return $this->hasMany(Overtime::class);
    }
    // Relasi ke UserSchedule
    public function userSchedules()
    {
        return $this->hasMany(UserSchedule::class);
    }

    // Relasi ke Task (many-to-many)
    public function tasks()
    {
        return $this->belongsToMany(Task::class)->withPivot('status')->withTimestamps();
    }
    // Aksesor untuk mendapatkan URL foto

}
