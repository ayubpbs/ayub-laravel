<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountCategory extends Model
{
    protected $fillable = ['type', 'name'];

    /** type => [page title, short label, icon] */
    public static function types(): array
    {
        return [
            'income'  => ['Income Categories',  'Income',  '📥'],
            'expense' => ['Expense Categories', 'Expense', '📤'],
        ];
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
