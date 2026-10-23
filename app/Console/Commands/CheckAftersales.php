<?php

namespace App\Console\Commands;

use App\Models\CustomerProfile;
use App\Models\Order;
use App\Models\ServiceRequest;
use App\Support\Phone;
use App\Support\WarrantyPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only health check of the after-sales data. It never changes anything: run it after installing a phase
 * and from time to time. ERROR = something is inconsistent and needs a look, WARN = worth knowing, OK = fine.
 */
class CheckAftersales extends Command
{
    protected $signature   = 'aftersales:check';
    protected $description = 'Read-only check of customers, orders, claims, warranty and stock for inconsistencies';

    private array $rows   = [];
    private int $errors   = 0;

    public function handle(): int
    {
        // 1) Is everything installed?
        $missing = [];
        foreach (['customer_profiles', 'customer_addresses', 'audit_logs', 'service_requests', 'service_request_items', 'service_request_files', 'service_request_shipments'] as $t) {
            if (!Schema::hasTable($t)) {
                $missing[] = $t;
            }
        }
        if (!Order::hasAdminColumns()) {
            $missing[] = 'orders: admin columns';
        }
        if (!WarrantyPolicy::columnsReady()) {
            $missing[] = 'warranty columns';
        }
        $this->add('Tables and columns installed', $missing ? 'ERROR' : 'OK', $missing ? 'missing: ' . implode(', ', $missing) . ' (run php artisan migrate)' : '');
        if ($missing) {
            return $this->finish();
        }

        $this->check('Phone numbers in canonical form (01XXXXXXXXX)', function () {
            $bad = DB::table('orders')->select('phone')->distinct()->pluck('phone')->filter(fn ($p) => Phone::normalize($p) !== $p)->values();

            return $bad->isEmpty() ? ['OK', ''] : ['WARN', $bad->count() . ' number(s) are stored in another format, e.g. ' . $bad->take(5)->implode(', ') . ' (they still open the right customer)'];
        });

        $this->check('Every ordering phone number has a customer profile', function () {
            $phones   = DB::table('orders')->select('phone')->distinct()->pluck('phone')->map(fn ($p) => Phone::normalize($p))->filter()->unique();
            $profiles = CustomerProfile::pluck('phone')->flip();
            $without  = $phones->reject(fn ($p) => $profiles->has($p));

            return $without->isEmpty() ? ['OK', ''] : ['WARN', $without->count() . ' number(s) have no profile yet (php artisan customers:sync-profiles creates them)'];
        });

        $this->check('Replacement / exchange orders are linked to their claim', function () {
            $n = DB::table('orders')->whereIn('order_type', ['replacement', 'exchange'])
                ->where(fn ($w) => $w->whereNull('source_order_id')->orWhereNull('service_request_id'))->count();

            return $n === 0 ? ['OK', ''] : ['ERROR', $n . ' replacement/exchange order(s) have no link to the original order or the claim'];
        });

        $this->check('Claims point to existing orders', function () {
            $a = DB::table('service_requests as r')->leftJoin('orders as o', 'o.id', '=', 'r.order_id')->whereNull('o.id')->count();
            $b = DB::table('service_requests as r')->whereNotNull('r.replacement_order_id')
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('orders as o')->whereColumn('o.id', 'r.replacement_order_id'))->count();

            return ($a + $b) === 0 ? ['OK', ''] : ['ERROR', "{$a} claim(s) without an original order, {$b} claim(s) with a missing replacement order"];
        });

        $this->check('No order line is claimed more often than it was bought', function () {
            $rows = DB::table('service_request_items as i')
                ->join('service_requests as r', 'r.id', '=', 'i.service_request_id')
                ->join('order_items as oi', 'oi.id', '=', 'i.order_item_id')
                ->where(function ($w) {
                    $w->where(fn ($x) => $x->where('r.type', '!=', 'warranty')->whereIn('r.status', ServiceRequest::activeStatuses()))
                      ->orWhere(fn ($x) => $x->where('r.type', 'warranty')->whereIn('r.status', ['open', 'approved']));
                })
                ->groupBy('i.order_item_id', 'oi.quantity')
                ->havingRaw('SUM(i.quantity) > oi.quantity')
                ->select('i.order_item_id')->get();

            return $rows->isEmpty() ? ['OK', ''] : ['ERROR', $rows->count() . ' order line(s) are in claims for more pieces than were bought (line ids: ' . $rows->pluck('order_item_id')->take(5)->implode(', ') . ')'];
        });

        $this->check('Finished replacement / exchange claims have their order', function () {
            $n = DB::table('service_requests')->where('status', 'resolved')->whereIn('resolution', ['replacement', 'exchange'])->whereNull('replacement_order_id')->count();

            return $n === 0 ? ['OK', ''] : ['ERROR', $n . ' finished claim(s) have no replacement/exchange order'];
        });

        $this->check('Open warranty claims are inside the warranty', function () {
            $n = DB::table('service_requests')->where('type', 'warranty')->whereIn('status', ['open', 'approved'])
                ->where(fn ($w) => $w->whereNull('warranty_state')->orWhere('warranty_state', '!=', 'in_warranty'))->count();

            return $n === 0 ? ['OK', ''] : ['ERROR', $n . ' open warranty claim(s) are outside the warranty'];
        });

        $this->check('Delivered orders have a delivery date (warranty start)', function () {
            $n = DB::table('orders')->where('status', 'delivered')->whereNull('delivered_at')->count();

            return $n === 0 ? ['OK', ''] : ['WARN', $n . ' delivered order(s) have no delivery date: for a warranty claim on them the date is typed in by hand'];
        });

        $this->check('No negative stock', function () {
            $n = DB::table('products')->where('stock_quantity', '<', 0)->count();

            return $n === 0 ? ['OK', ''] : ['ERROR', $n . ' product(s) have a negative stock'];
        });

        $this->check('No order has been refunded more than it received', function () {
            $n = Order::whereRaw(Order::netPaidSql() . ' < 0')->count();

            return $n === 0 ? ['OK', ''] : ['ERROR', $n . ' order(s) have more money paid back than received'];
        });

        $this->check('No parcel was booked twice', function () {
            $n = DB::table('orders')->whereNotNull('consignment_id')->where('consignment_id', '!=', '')->groupBy('consignment_id')->havingRaw('COUNT(*) > 1')->get()->count();

            return $n === 0 ? ['OK', ''] : ['ERROR', $n . ' courier consignment id(s) are used by more than one order'];
        });

        return $this->finish();
    }

    private function check(string $label, callable $run): void
    {
        try {
            [$level, $note] = $run();
        } catch (\Throwable $e) {
            [$level, $note] = ['WARN', 'could not be checked: ' . $e->getMessage()];
        }
        $this->add($label, $level, $note);
    }

    private function add(string $label, string $level, string $note): void
    {
        if ($level === 'ERROR') {
            $this->errors++;
        }
        $this->rows[] = [$level, $label, $note];
    }

    private function finish(): int
    {
        $this->table(['', 'Check', 'Result'], $this->rows);
        $this->line($this->errors === 0 ? 'No errors found.' : $this->errors . ' error(s) found. Nothing was changed by this command.');

        return $this->errors === 0 ? self::SUCCESS : self::FAILURE;
    }
}
