<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    const ROLE_USER = 'user';
    const ROLE_ADMIN = 'admin';
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'first_name',
        'last_name',
        'name',
        'email',
        'phone',
        'national_id',
        'password',
        'role',
        'status',
        'is_verified',
        'is_first_login',
        'otp_code',
        'otp_expiry',
        'api_token',
        'father_name',
        'grandfather_name',
        'birth_date',
        'iban',
        'bank_name',
        'region',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp_code',
        'api_token',
        'father_name',
        'grandfather_name',
        'birth_date',
        'iban',
        'bank_name',
        'region',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_verified' => 'boolean',
            'is_first_login' => 'boolean',
            'otp_expiry' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * For API responses - match frontend expected shape (user or admin).
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'family_name' => $this->last_name,
            'name' => $this->name ?? trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? '')),
            'email' => $this->email,
            'phone' => $this->phone,
            'national_id' => $this->national_id,
            'role' => $this->role,
            'status' => $this->status,
            'is_verified' => $this->is_verified,
            'is_first_login' => $this->is_first_login,
            'father_name' => $this->father_name,
            'grandfather_name' => $this->grandfather_name,
            'birth_date' => $this->birth_date,
            'iban' => $this->iban,
            'bank_name' => $this->bank_name,
            'region' => $this->region,
        ];
    }
}
