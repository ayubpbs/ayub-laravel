<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sales reports.
 *
 * What counts as a sale: real customer orders (not replacement / exchange orders) that are not
 * Cancelled or Returned. Pending orders ARE included, and every report shows them separately
 * so you can see how much of the sale is still waiting for confirmation.
 *
 * Days follow the same time setting as the rest of the admin panel (config/app.php timezone).
 */
class ReportController extends Controller
{
    /** Statuses that never count as a sale */
    private const OUT = ['cancelled', 'returned'];

    // ---------- Daily Sales ----------

    public function daily(Request $request)
    {
        $date = $this->date($request->query('date')) ?? today()->toDateString();
        $day  = Carbon::parse($date);

        $orders = $this->sales()
            ->whereDate('orders.created_at', $date)
            ->orderByDesc('orders.id')
            ->get();

        return view('admin.reports.daily', [
            'date'    => $date,
            'day'     => $day,
            'prev'    => $day->copy()->subDay()->toDateString(),
            'next'    => $day->copy()->addDay()->toDateString(),
            'isToday' => $day->isToday(),
            'orders'  => $orders,
            'sum'     => $this->summary($orders),
        ]);
    }

    // ---------- Monthly Sales ----------

    public function monthly(Request $request)
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) === 1
            ? (string) $request->query('month')
            : now()->format('Y-m');
        $start = Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfDay();
        $end   = $start->copy()->endOfMonth();

        $rows = $this->sales()
            ->whereBetween('orders.created_at', [$start, $end])
            ->selectRaw('DATE(orders.created_at) as d, COUNT(*) as orders, '
                . 'COALESCE(SUM(orders.subtotal),0) as subtotal, COALESCE(SUM(orders.discount),0) as discount, '
                . 'COALESCE(SUM(orders.delivery_charge),0) as delivery, COALESCE(SUM(orders.total),0) as total, '
                . "SUM(CASE WHEN orders.status = 'pending' THEN 1 ELSE 0 END) as pending_orders, "
                . "COALESCE(SUM(CASE WHEN orders.status = 'pending' THEN orders.total ELSE 0 END),0) as pending_total")
            ->groupBy(DB::raw('DATE(orders.created_at)'))
            ->orderBy('d')
            ->get();

        $sum = [
            'orders'   => (int) $rows->sum('orders'),
            'subtotal' => (int) $rows->sum('subtotal'),
            'discount' => (int) $rows->sum('discount'),
            'delivery' => (int) $rows->sum('delivery'),
            'total'    => (int) $rows->sum('total'),
            'pending_orders' => (int) $rows->sum('pending_orders'),
            'pending_total'  => (int) $rows->sum('pending_total'),
        ];

        return view('admin.reports.monthly', [
            'month'   => $month,
            'start'   => $start,
            'prev'    => $start->copy()->subMonth()->format('Y-m'),
            'next'    => $start->copy()->addMonth()->format('Y-m'),
            'isNow'   => $start->isSameMonth(now()),
            'rows'    => $rows,
            'sum'     => $sum,
            'maxDay'  => max(1, (int) $rows->max('total')),
            'avgDay'  => $rows->count() ? (int) round($sum['total'] / $rows->count()) : 0,
            'best'    => $rows->sortByDesc('total')->first(),
        ]);
    }

    // ---------- Product-wise Sales ----------

    public function products(Request $request)
    {
        $from = $this->date($request->query('from')) ?? now()->startOfMonth()->toDateString();
        $to   = $this->date($request->query('to'))   ?? today()->toDateString();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $sort = $request->query('sort') === 'amount' ? 'amount' : 'qty';

        $rows = $this->sales()
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->whereDate('orders.created_at', '>=', $from)
            ->whereDate('orders.created_at', '<=', $to)
            ->selectRaw('order_items.product_id, order_items.title, '
                . 'SUM(order_items.quantity) as qty, COALESCE(SUM(order_items.line_total),0) as amount, '
                . 'COUNT(DISTINCT orders.id) as orders')
            ->groupBy('order_items.product_id', 'order_items.title')
            ->orderByDesc($sort)
            ->orderBy('order_items.title')
            ->get();

        return view('admin.reports.products', [
            'from'   => $from,
            'to'     => $to,
            'sort'   => $sort,
            'rows'   => $rows,
            'qty'    => (int) $rows->sum('qty'),
            'amount' => (int) $rows->sum('amount'),
            'maxAmt' => max(1, (int) $rows->max('amount')),
        ]);
    }

    // ---------- Profit / Loss ----------

    /**
     * Gross profit from orders = product sales - coupon discounts - what the goods cost you.
     * Buying price comes from the order line (saved at the time of sale). Older lines that have none
     * use the product's current buying price (an estimate); lines with no buying price anywhere count as 0.
     * Delivery charge is left out: what the courier really costs is not recorded per order.
     * Expenses (rent, salary, ...) are not here; they are in Accounting > Profit & Loss.
     */
    public function profit(Request $request)
    {
        $from  = $this->date($request->query('from')) ?? now()->startOfMonth()->toDateString();
        $to    = $this->date($request->query('to'))   ?? today()->toDateString();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $scope = $request->query('scope') === 'delivered' ? 'delivered' : 'all';

        $base = fn () => $this->sales()
            ->whereDate('orders.created_at', '>=', $from)
            ->whereDate('orders.created_at', '<=', $to)
            ->when($scope === 'delivered', fn ($q) => $q->where('orders.status', 'delivered'));

        // order level numbers
        $o = $base()->selectRaw('COUNT(*) as c, COALESCE(SUM(orders.discount),0) as discount, COALESCE(SUM(orders.delivery_charge),0) as delivery, '
            . "SUM(CASE WHEN orders.status = 'pending' THEN 1 ELSE 0 END) as pending_orders, "
            . "COALESCE(SUM(CASE WHEN orders.status = 'pending' THEN orders.total ELSE 0 END),0) as pending_total")->first();

        // line level numbers: cost = quantity x buying price
        $lineCost = [];
        if (OrderItem::hasCostColumn()) {
            $lineCost[] = 'order_items.cost_price';
        }
        $productCost = Schema::hasColumn('products', 'cost_price');
        if ($productCost) {
            $lineCost[] = 'products.cost_price';
        }
        $unit       = $lineCost ? 'COALESCE(' . implode(', ', $lineCost) . ', 0)' : '0';
        $noCostCond = $lineCost ? implode(' IS NULL AND ', $lineCost) . ' IS NULL' : '1 = 1';
        $estCond    = OrderItem::hasCostColumn() ? 'order_items.cost_price IS NULL' : '1 = 1';

        $rows = $base()
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->selectRaw('order_items.product_id, order_items.title, '
                . 'SUM(order_items.quantity) as qty, COALESCE(SUM(order_items.line_total),0) as amount, '
                . "COALESCE(SUM(order_items.quantity * {$unit}),0) as cost, "
                . "SUM(CASE WHEN {$estCond} THEN 1 ELSE 0 END) as est_lines, "
                . "SUM(CASE WHEN {$noCostCond} THEN 1 ELSE 0 END) as no_cost_lines")
            ->groupBy('order_items.product_id', 'order_items.title')
            ->get()
            ->each(function ($r) {
                $r->profit = (int) $r->amount - (int) $r->cost;
                $r->margin = (int) $r->amount > 0 ? round($r->profit / $r->amount * 100, 1) : null;
            })
            ->sortByDesc('profit')
            ->values();

        $goods    = (int) $rows->sum('amount');
        $discount = (int) $o->discount;
        $net      = $goods - $discount;
        $cost     = (int) $rows->sum('cost');
        $profit   = $net - $cost;

        return view('admin.reports.profit', [
            'from'      => $from,
            'to'        => $to,
            'scope'     => $scope,
            'orders'    => (int) $o->c,
            'pending'   => ['orders' => (int) $o->pending_orders, 'total' => (int) $o->pending_total],
            'delivery'  => (int) $o->delivery,
            'goods'     => $goods,
            'discount'  => $discount,
            'net'       => $net,
            'cost'      => $cost,
            'profit'    => $profit,
            'margin'    => $net > 0 ? round($profit / $net * 100, 1) : null,
            'rows'      => $rows,
            'noCost'    => $rows->where('no_cost_lines', '>', 0)->values(),
            'estimated' => (int) $rows->sum('est_lines'),
            'hasCostCol' => OrderItem::hasCostColumn(),
        ]);
    }

    // ---------- Delivered / Cancelled / Returned orders ----------

    /**
     * One list per final status. Dates: Delivered uses the delivery date; Cancelled and Returned have no
     * date of their own, so the last change of the order is used (shown as "Status changed", which is
     * the real date unless the order was edited afterwards, e.g. a refund was recorded).
     * Replacement / exchange orders are not listed.
     */
    public function orders(Request $request, string $status)
    {
        $from = $this->date($request->query('from')) ?? now()->startOfMonth()->toDateString();
        $to   = $this->date($request->query('to'))   ?? today()->toDateString();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $q = trim((string) $request->query('q', ''));

        $dateExpr = $status === 'delivered'
            ? (Order::hasAdminColumns() ? 'COALESCE(orders.delivered_at, orders.updated_at)' : 'orders.updated_at')
            : 'orders.updated_at';
        $netPaid = Order::netPaidSql();

        $base = fn () => Order::salesOrders()
            ->where('orders.status', $status)
            ->whereRaw("DATE({$dateExpr}) >= ?", [$from])
            ->whereRaw("DATE({$dateExpr}) <= ?", [$to])
            ->when($q !== '', function ($w) use ($q) {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
                $w->where(function ($x) use ($q, $like) {
                    $x->where('orders.name', 'like', $like)->orWhere('orders.phone', 'like', $like);
                    if (ctype_digit(ltrim($q, '#'))) {
                        $x->orWhere('orders.id', (int) ltrim($q, '#'));
                    }
                });
            });

        $t = $base()->selectRaw('COUNT(*) as c, COALESCE(SUM(orders.total),0) as total, COALESCE(SUM(orders.subtotal),0) as subtotal, '
            . 'COALESCE(SUM(orders.discount),0) as discount, COALESCE(SUM(orders.delivery_charge),0) as delivery, '
            . "COALESCE(SUM(GREATEST({$netPaid}, 0)),0) as held, SUM(CASE WHEN {$netPaid} > 0 THEN 1 ELSE 0 END) as held_orders, "
            . "COALESCE(SUM(GREATEST(orders.total - ({$netPaid}), 0)),0) as owed, SUM(CASE WHEN orders.total > ({$netPaid}) THEN 1 ELSE 0 END) as owed_orders")
            ->first();

        $list = $base()->withPaid()
            ->orderByRaw("{$dateExpr} DESC")->orderByDesc('orders.id')
            ->paginate(25)->withQueryString();

        // which products are in these orders (most pieces first)
        $products = $status === 'delivered' ? collect() : $base()
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->selectRaw('order_items.title, SUM(order_items.quantity) as qty, COALESCE(SUM(order_items.line_total),0) as amount')
            ->groupBy('order_items.product_id', 'order_items.title')
            ->orderByDesc('qty')->orderBy('order_items.title')
            ->limit(10)->get();

        return view('admin.reports.orders', [
            'status'   => $status,
            'label'    => Order::statuses()[$status] ?? ucfirst($status),
            'from'     => $from,
            'to'       => $to,
            'q'        => $q,
            'count'    => (int) $t->c,
            'total'    => (int) $t->total,
            'subtotal' => (int) $t->subtotal,
            'discount' => (int) $t->discount,
            'delivery' => (int) $t->delivery,
            'held'     => (int) $t->held,
            'heldOrders' => (int) $t->held_orders,
            'owed'     => (int) $t->owed,
            'owedOrders' => (int) $t->owed_orders,
            'orders'   => $list,
            'products' => $products,
            'dateExpr' => $dateExpr,
        ]);
    }

    // ---------- helpers ----------

    /** Orders that count as sales: real customer orders, not cancelled / returned */
    private function sales()
    {
        return Order::salesOrders()->whereNotIn('orders.status', self::OUT);
    }

    private function summary($orders): array
    {
        $pending = $orders->where('status', 'pending');

        return [
            'orders'   => $orders->count(),
            'subtotal' => (int) $orders->sum('subtotal'),
            'discount' => (int) $orders->sum('discount'),
            'delivery' => (int) $orders->sum('delivery_charge'),
            'total'    => (int) $orders->sum('total'),
            'pending_orders' => $pending->count(),
            'pending_total'  => (int) $pending->sum('total'),
        ];
    }

    private function date($value): ?string
    {
        $value = (string) $value;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }
        try {
            return Carbon::createFromFormat('Y-m-d', $value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
