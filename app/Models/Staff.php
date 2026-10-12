<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/** A person with their own admin login and a limited set of tasks (the owner is not stored here) */
class Staff extends Model
{
    protected $table = 'staff_members';

    protected $fillable = ['name', 'username', 'password', 'permissions', 'is_active', 'last_login_at'];

    protected $hidden = ['password'];

    protected $casts = [
        'permissions'   => 'array',
        'is_active'     => 'boolean',
        'last_login_at' => 'datetime',
    ];

    private static ?bool $ready = null;

    /** False until "php artisan migrate" has been run. The owner can still sign in before that. */
    public static function tableReady(): bool
    {
        if (self::$ready === null) {
            try {
                self::$ready = Schema::hasTable('staff_members');
            } catch (\Throwable $e) {
                self::$ready = false;
            }
        }

        return self::$ready;
    }

    public function can(string $permission): bool
    {
        return in_array($permission, (array) $this->permissions, true);
    }
}
