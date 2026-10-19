<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use App\Support\Phone;

/** The customer record of one phone number. Orders, addresses and claims hang off the phone. */
class CustomerProfile extends Model
{
    protected $fillable = ['phone', 'name', 'notes', 'source', 'created_by_id', 'created_by_name'];

    private static ?bool $ready = null;

    /** False until "php artisan migrate" has been run, so the customer pages keep working before that. */
    public static function tableReady(): bool
    {
        if (self::$ready === null) {
            try {
                self::$ready = Schema::hasTable('customer_profiles');
            } catch (\Throwable $e) {
                self::$ready = false;
            }
        }

        return self::$ready;
    }

    /**
     * Returns [profile, created]. The unique phone index makes a duplicate impossible:
     * if two requests race, the loser reads the winner's row.
     */
    public static function findOrCreateByPhone(string $phone, ?string $name, string $source, ?int $byId, ?string $byName): array
    {
        $existing = self::where('phone', $phone)->first();
        if ($existing) {
            return [$existing, false];
        }

        try {
            $profile = self::create([
                'phone'           => $phone,
                'name'            => $name,
                'source'          => $source,
                'created_by_id'   => $byId,
                'created_by_name' => $byName,
            ]);

            return [$profile, true];
        } catch (QueryException $e) {
            return [self::where('phone', $phone)->firstOrFail(), false];
        }
    }

    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class, 'customer_profile_id');
    }

    /** True when the number has orders, a profile or an imported old-customer record */
    public static function phoneKnown(string $norm): bool
    {
        $variants = Phone::variants($norm);

        return Order::whereIn('phone', $variants)->exists()
            || (self::tableReady() && self::where('phone', $norm)->exists())
            || Customer::whereIn('phone', $variants)->exists();
    }
}
