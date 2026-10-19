<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class CustomerAddress extends Model
{
    public const MAX_PER_CUSTOMER = 10;

    protected $fillable = [
        'customer_profile_id', 'recipient_name', 'phone', 'district', 'upazila', 'city',
        'address', 'landmark', 'is_default',
    ];

    protected $casts = ['is_default' => 'boolean'];

    private static ?bool $ready = null;

    public static function tableReady(): bool
    {
        if (self::$ready === null) {
            try {
                self::$ready = Schema::hasTable('customer_addresses');
            } catch (\Throwable $e) {
                self::$ready = false;
            }
        }

        return self::$ready;
    }

    public function profile()
    {
        return $this->belongsTo(CustomerProfile::class, 'customer_profile_id');
    }

    /** One line: street, city, upazila, district (the same order checkout uses for orders.address) */
    public function fullLine(): string
    {
        return implode(', ', array_filter([$this->address, $this->city, $this->upazila, $this->district]));
    }

    /** Same place typed twice (case and spacing ignored) gives the same key: used to refuse duplicates */
    public static function duplicateKey(array $a): string
    {
        $clean = fn ($v) => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $v)));

        return implode('|', array_map($clean, [$a['phone'] ?? '', $a['district'] ?? '', $a['upazila'] ?? '', $a['city'] ?? '', $a['address'] ?? '']));
    }

    /** The values worth keeping in the audit log */
    public function snapshot(): array
    {
        return $this->only(['recipient_name', 'phone', 'district', 'upazila', 'city', 'address', 'landmark', 'is_default']);
    }
}
