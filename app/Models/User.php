<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * Hak akses / permission yang diekstrak langsung dari token JWT Kelompok 1.
     *
     * @var array<string>
     */
    public array $permissions = [];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Memeriksa apakah user memiliki permission tertentu dari JWT K1.
     */
    public function hasPermission(string $permission): bool
    {
        $role = strtoupper((string) ($this->role ?? ''));
        if ($role === 'ADMINISTRATOR' || $role === 'ADMIN') {
            return true;
        }

        return in_array('*', $this->permissions, true) || in_array($permission, $this->permissions, true);
    }
}
