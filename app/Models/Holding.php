<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Savings certificate, FDR, loan given, cash receivable (assets)
 * and loan / borrowed money taken (liability).
 * Counted at the amount still outstanding: principal minus what has been received back / repaid.
 */
class Holding extends Model
{
    protected $table = 'account_holdings';

    protected $fillable = [
        'kind', 'title', 'party', 'reference_no', 'principal', 'interest_rate',
        'start_date', 'maturity_date', 'note', 'account_transaction_id',
    ];

    protected $casts = [
        'principal'     => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'start_date'    => 'date',
        'maturity_date' => 'date',
    ];

    /** SQL for "still outstanding" of one row */
    const OUT_SQL = '(account_holdings.principal - COALESCE((SELECT SUM(p.amount) FROM account_holding_payments p WHERE p.holding_id = account_holdings.id), 0))';

    /**
     * kind => settings
     * side: asset (money we will get / have invested) or liability (money we must pay back)
     * moves_at_start: creating it can also move money in an account (optional)
     */
    public static function kinds(): array
    {
        return [
            'sanchaypatra' => ['label' => 'Savings Certificate', 'bn' => 'সঞ্চয়পত্র', 'icon' => '🏅', 'side' => 'asset', 'moves_at_start' => true,
                'title_hint' => 'e.g. Pensioner Sanchayapatra', 'party_label' => 'Bank / Post office', 'settle' => 'Encashed (money received)'],
            'fdr'          => ['label' => 'FDR', 'bn' => 'এফডিআর', 'icon' => '🏛️', 'side' => 'asset', 'moves_at_start' => true,
                'title_hint' => 'e.g. FDR – 1 year', 'party_label' => 'Bank', 'settle' => 'Encashed (money received)'],
            'loan_given'   => ['label' => 'Loan Given', 'bn' => 'লোন দিলাম', 'icon' => '🤝', 'side' => 'asset', 'moves_at_start' => true,
                'title_hint' => 'e.g. Loan to Rahim', 'party_label' => 'Given to (person)', 'settle' => 'Received back'],
            'receivable'   => ['label' => 'Cash Receivable', 'bn' => 'পাওনা', 'icon' => '🧾', 'side' => 'asset', 'moves_at_start' => false,
                'title_hint' => 'e.g. Due from supplier / party', 'party_label' => 'Will receive from', 'settle' => 'Received'],
            'loan_taken'   => ['label' => 'Loan / Borrowed', 'bn' => 'লোন / ধার নিলাম', 'icon' => '💳', 'side' => 'liability', 'moves_at_start' => true,
                'title_hint' => 'e.g. Loan from Karim', 'party_label' => 'Taken from (bank / person)', 'settle' => 'Repaid (money paid)'],
        ];
    }

    public function meta(): array
    {
        return self::kinds()[$this->kind] ?? self::kinds()['receivable'];
    }

    public function isAsset(): bool
    {
        return $this->meta()['side'] === 'asset';
    }

    public function payments()
    {
        return $this->hasMany(HoldingPayment::class, 'holding_id');
    }

    public function settled(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function outstanding(): float
    {
        return max(0.0, round((float) $this->principal - $this->settled(), 2));
    }

    public function scopeOpen($q)
    {
        return $q->whereRaw(self::OUT_SQL . ' > 0');
    }

    public function scopeClosed($q)
    {
        return $q->whereRaw(self::OUT_SQL . ' <= 0');
    }

    /** Outstanding amount of every kind, plus asset / liability / net totals */
    public static function summary(): array
    {
        $principal = static::query()->selectRaw('kind, SUM(principal) as s')->groupBy('kind')->pluck('s', 'kind');
        $paid = HoldingPayment::query()
            ->join('account_holdings', 'account_holdings.id', '=', 'account_holding_payments.holding_id')
            ->selectRaw('account_holdings.kind as kind, SUM(account_holding_payments.amount) as s')
            ->groupBy('account_holdings.kind')->pluck('s', 'kind');

        $by = [];
        $assets = 0.0;
        $liabilities = 0.0;

        foreach (self::kinds() as $key => $k) {
            $o = max(0.0, (float) ($principal[$key] ?? 0) - (float) ($paid[$key] ?? 0));
            $by[$key] = $o;
            if ($k['side'] === 'asset') {
                $assets += $o;
            } else {
                $liabilities += $o;
            }
        }

        return ['by' => $by, 'assets' => $assets, 'liabilities' => $liabilities, 'net' => $assets - $liabilities];
    }
}
