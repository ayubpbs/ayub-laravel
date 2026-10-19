<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AuditLog extends Model
{
    const UPDATED_AT = null; // rows are written once and never edited

    protected $fillable = [
        'actor_id', 'actor_name', 'action', 'subject_type', 'subject_id', 'customer_phone',
        'summary', 'old_values', 'new_values', 'ip',
    ];

    protected $casts = ['old_values' => 'array', 'new_values' => 'array'];

    private static ?bool $ready = null;

    public static function tableReady(): bool
    {
        if (self::$ready === null) {
            try {
                self::$ready = Schema::hasTable('audit_logs');
            } catch (\Throwable $e) {
                self::$ready = false;
            }
        }

        return self::$ready;
    }
}
