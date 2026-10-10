<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'identity_number',
        'email',
        'phone',
        'password',
        'role',
        'status',
        'rejection_reason',
    ];

    protected $hidden = [
        'password',
    ];

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function reports()
    {
        return $this->hasMany(DamageReport::class);
    }

    public function damageReports()
    {
        return $this->hasMany(DamageReport::class);
    }

    public function isAdmin(): bool
    {
        return strtolower($this->role ?? '') === 'admin';
    }

    public function isPetugas(): bool
    {
        return in_array(strtolower($this->role ?? ''), ['petugas', 'admin']);
    }

    public function isPengguna(): bool
    {
        return in_array(strtolower($this->role ?? ''), ['pengguna', 'user']);
    }

    public function isActive(): bool
    {
        return in_array(strtolower($this->status ?? ''), ['aktif', 'active']);
    }
}