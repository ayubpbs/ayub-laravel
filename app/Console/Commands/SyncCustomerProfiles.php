<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\CustomerProfile;
use App\Support\Phone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fills customer_profiles from the orders table and the imported customers table.
 * Insert-only and safe to run again: existing profiles and all other tables are never changed.
 */
class SyncCustomerProfiles extends Command
{
    protected $signature   = 'customers:sync-profiles {--dry-run : Only show what would be added}';
    protected $description = 'Create a customer profile for every phone number found in orders and imported customers';

    public function handle(): int
    {
        if (!CustomerProfile::tableReady()) {
            $this->error('The customer_profiles table does not exist. Run: php artisan migrate');

            return self::FAILURE;
        }

        $found   = []; // normalized phone => [name, source]
        $invalid = [];

        // 1) Orders (later orders win for the name)
        $rows  = DB::table('orders')->select('phone', DB::raw('MAX(id) as last_id'))->groupBy('phone')->orderBy('last_id')->get();
        $names = DB::table('orders')->whereIn('id', $rows->pluck('last_id'))->pluck('name', 'id');
        foreach ($rows as $r) {
            $n = Phone::normalize($r->phone);
            if ($n === null) {
                $invalid[] = 'orders: ' . $r->phone;
                continue;
            }
            $found[$n] = [$names[$r->last_id] ?? null, 'orders'];
        }

        // 2) Imported old customers (only numbers not already found in orders)
        foreach (Customer::select('phone', 'name')->cursor() as $c) {
            $n = Phone::normalize($c->phone);
            if ($n === null) {
                $invalid[] = 'customers: ' . $c->phone;
                continue;
            }
            if (!isset($found[$n])) {
                $found[$n] = [$c->name, 'import'];
            }
        }

        $existing = CustomerProfile::pluck('phone')->flip();
        $new      = array_diff_key($found, $existing->all());

        $this->info(count($found) . ' phone numbers found, ' . $existing->count() . ' profiles already exist, ' . count($new) . ' to add.');
        if ($invalid) {
            $this->warn(count($invalid) . ' numbers could not be read as a Bangladeshi mobile number and were skipped (first 20):');
            foreach (array_slice($invalid, 0, 20) as $line) {
                $this->line('  ' . $line);
            }
        }

        if ($this->option('dry-run') || !$new) {
            $this->info($this->option('dry-run') ? 'Dry run: nothing was written.' : 'Nothing to add.');

            return self::SUCCESS;
        }

        $now   = now();
        $batch = [];
        foreach ($new as $phone => [$name, $source]) {
            $batch[] = [
                'phone' => $phone, 'name' => $name ? mb_substr($name, 0, 100) : null, 'source' => $source,
                'created_by_name' => 'sync', 'created_at' => $now, 'updated_at' => $now,
            ];
            if (count($batch) === 200) {
                DB::table('customer_profiles')->insertOrIgnore($batch);
                $batch = [];
            }
        }
        if ($batch) {
            DB::table('customer_profiles')->insertOrIgnore($batch);
        }

        $this->info('Done. Profiles now: ' . CustomerProfile::count());

        return self::SUCCESS;
    }
}
