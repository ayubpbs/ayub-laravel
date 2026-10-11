<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = ['type', 'name', 'opening_balance'];

    protected $casts = ['opening_balance' => 'decimal:2'];

    /** type => [label, icon] */
    public static function types(): array
    {
        return [
            'cash'  => ['Cash',  '💵'],
            'bank'  => ['Bank',  '🏦'],
            'bkash' => ['bKash', '📱'],
            'nagad' => ['Nagad', '📲'],
        ];
    }

    /** The account row for a type (created on the spot if it is missing) */
    public static function forType(string $type): self
    {
        return static::firstOrCreate(
            ['type' => $type],
            ['name' => self::types()[$type][0] ?? ucfirst($type), 'opening_balance' => 0]
        );
    }

    public function transactions()
    {
        return $this->hasMany(AccountTransaction::class);
    }

    /** All-time money in / out and the current balance */
    public function totals(): array
    {
        $in  = (float) $this->transactions()->where('direction', 'in')->sum('amount');
        $out = (float) $this->transactions()->where('direction', 'out')->sum('amount');

        return ['in' => $in, 'out' => $out, 'balance' => (float) $this->opening_balance + $in - $out];
    }
}
