<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */

    const STATUS_PENDING = 'Pending';
    const STATUS_ACTIVE = 'Active';
    const STATUS_NONACTIVE = 'Non-Active';

    protected $table = 'users';

    protected $fillable = [
        'role_id',
        'full_name',
        'username',
        'email',
        'email_verified_at',
        'email_verification_token',
        'password',
        'is_default_password',
        'user_status'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'email_verification_token'  
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public static function generateDefaultPassword()
    {
        return '123456';
    }

    public static function generateSetupToken()
    {
        return Str::random(60);
    }

    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isActive()
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function markEmailAsVerified()
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
            'status' => self::STATUS_ACTIVE,
            'email_verification_token' => null,
        ])->save();
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }

    public function order()
    {
        return $this->hasMany(Order::class, 'created_by', 'id');
    }

    public function design()
    {
        return $this->hasMany(Design::class, 'assigned_to', 'id');
    }

    public function production()
    {
        return $this->hasMany(Production::class, 'assigned_to', 'id');
    }

    public function spk()
    {
        return $this->hasMany(Spk::class, 'assigned_to', 'id');
    }

    public function notification()
    {
        return $this->hasMany(Notification::class, 'user_id', 'id');
    }

    public function statusHistory()
    {
        return $this->hasMany(StatusHistory::class, 'updated_by', 'id');
    }
}
