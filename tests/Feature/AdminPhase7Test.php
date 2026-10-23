<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceRequest;
use App\Models\Staff;
use App\Support\AdminAccess;
use App\Support\WarrantyPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 7: sales are kept apart from replacement / exchange orders, customer claim history, filters,
 * the owner-only audit log and the read-only health check. Runs only on a TEST database (name ends with _test)
 * and rolls every test back.
 */
class AdminPhase7Test extends TestCase
{
    use DatabaseTransactions;

    private const PHONE = '01755500000';

    protected function setUp(): void
    {
        parent::setUp();

        if (!str_ends_with((string) DB::connection()->getDatabaseName(), '_test')) {
            $this->markTestSkipped('Not a test database (name must end with _test).');
        }
        if (!Order::hasAdminColumns() || !ServiceRequest::tableReady() || !WarrantyPolicy::columnsReady() || !AuditLog::tableReady()) {
            $this->markTestSkipped('Run "php artisan migrate" on the test database first.');
        }
    }

    // ----- helpers -----

    private function signOut(): void
    {
        $this->flushSession();
        $cache = new \ReflectionProperty(AdminAccess::class, 'cache');
        $cache->setAccessible(true);
        $cache->setValue(null, []);
    }

    private function admin()
    {
        $this->signOut();

        return $this->withSession(['admin_ok' => true]);
    }

    private function staff(array $permissions)
    {
        $this->signOut();
        $s = Staff::create(['name' => 'Karim', 'username' => 'karim' . uniqid(), 'password' => bcrypt('x'), 'permissions' => $permissions, 'is_active' => true]);

        return $this->withSession(['admin_ok' => true, 'admin_staff_id' => $s->id]);
    }

    private function order(string $title, int $total, string $type = 'normal', ?int $source = null, string $status = 'delivered'): Order
    {
        $o = Order::create([
            'name' => 'Rahim', 'phone' => self::PHONE, 'district' => 'Dhaka', 'upazila' => 'Banani', 'city' => 'Dhaka',
            'address' => 'House 5, Dhaka', 'subtotal' => $total, 'discount' => 0, 'delivery_charge' => 0, 'total' => $total,
            'status' => $status, 'payment_method' => 'cod', 'delivered_at' => $status === 'delivered' ? now() : null,
            'order_type' => $type, 'source_order_id' => $source,
        ]);
        $o->items()->create(['product_id' => null, 'title' => $title, 'price' => $total, 'quantity' => 1, 'line_total' => $total]);

        return $o;
    }

    private function claim(Order $o, string $type = 'defective', string $status = 'open'): ServiceRequest
    {
        $c = ServiceRequest::create([
            'order_id' => $o->id, 'customer_name' => $o->name, 'customer_phone' => $o->phone, 'type' => $type, 'status' => $status,
            'reason' => 'Problem', 'return_required' => true,
        ]);
        $c->items()->create(['order_item_id' => $o->items()->first()->id, 'product_id' => null, 'title' => $o->items()->first()->title, 'price' => $o->total, 'quantity' => 1]);

        return $c;
    }

    // ----- sales apart from claim orders -----

    public function test_delivered_sales_do_not_include_replacement_or_exchange_orders_which_are_shown_apart(): void
    {
        $baseSales  = (int) Order::salesOrders()->where('status', 'delivered')->sum('total');
        $baseClaims = Order::whereIn('order_type', ['replacement', 'exchange'])->count();

        $normal = $this->order('Breaker P7', 1000);
        $this->order('Breaker P7', 300, 'exchange', $normal->id);
        $this->order('Breaker P7', 0, 'replacement', $normal->id);

        $this->admin()->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('stats', fn ($s) => $s['delivered'] === $baseSales + 1000 && $s['claim_orders'] === $baseClaims + 2);
    }

    public function test_customer_numbers_count_real_purchases_only_and_the_history_lists_everything(): void
    {
        $normal = $this->order('Breaker P7', 1000);
        $this->order('Breaker P7', 300, 'exchange', $normal->id);
        $this->claim($normal);

        $this->admin()->get(route('admin.customers.show', self::PHONE))->assertOk()
            ->assertViewHas('stats', fn ($s) => $s['orders'] === 1 && $s['delivered'] === 1 && $s['spent'] === 1000 && $s['claim_orders'] === 1)
            ->assertViewHas('orders', fn ($o) => $o->count() === 2)
            ->assertViewHas('claims', fn ($c) => $c->count() === 1)
            ->assertSee('Exchange');

        // the customer list counts one order for this phone, not two
        $this->admin()->get(route('admin.customers.index', ['q' => self::PHONE, 'list' => 1]))->assertOk()
            ->assertViewHas('customers', fn ($p) => $p->count() === 1 && (int) $p->first()->orders_count === 1 && (int) $p->first()->delivered_total === 1000);
    }

    public function test_claim_history_on_the_customer_page_needs_the_claims_view_permission(): void
    {
        $this->claim($this->order('Breaker P7', 1000));

        $this->staff(['customers'])->get(route('admin.customers.show', self::PHONE))->assertOk()
            ->assertViewHas('claims', fn ($c) => $c->isEmpty());
        $this->staff(['customers', 'claims_view'])->get(route('admin.customers.show', self::PHONE))->assertOk()
            ->assertViewHas('claims', fn ($c) => $c->count() === 1);
    }

    // ----- filters -----

    public function test_orders_can_be_filtered_by_type_product_and_date(): void
    {
        $normal = $this->order('Alpha Relay P7', 1000);
        $this->order('Beta Switch P7', 300, 'exchange', $normal->id);
        $q = ['q' => self::PHONE];

        $this->admin()->get(route('admin.orders.index', $q + ['type' => 'claim']))
            ->assertViewHas('orders', fn ($p) => $p->total() === 1 && $p->first()->order_type === 'exchange');
        $this->admin()->get(route('admin.orders.index', $q + ['type' => 'normal']))
            ->assertViewHas('orders', fn ($p) => $p->total() === 1 && $p->first()->order_type === 'normal');
        $this->admin()->get(route('admin.orders.index', $q + ['product' => 'Beta Switch']))
            ->assertViewHas('orders', fn ($p) => $p->total() === 1 && $p->first()->order_type === 'exchange');
        $this->admin()->get(route('admin.orders.index', $q + ['from' => now()->addDay()->toDateString()]))
            ->assertViewHas('orders', fn ($p) => $p->total() === 0);
        $this->admin()->get(route('admin.orders.index', $q + ['from' => now()->toDateString(), 'to' => now()->toDateString()]))
            ->assertViewHas('orders', fn ($p) => $p->total() === 2);
    }

    public function test_claims_can_be_filtered_by_type_product_date_status_and_phone(): void
    {
        $a = $this->order('Alpha Relay P7', 1000);
        $b = $this->order('Beta Switch P7', 500);
        $this->claim($a, 'wrong_product', 'open');
        $this->claim($b, 'warranty', 'approved');
        $base = ['q' => self::PHONE];

        $this->admin()->get(route('admin.claims.index', $base))->assertViewHas('claims', fn ($p) => $p->total() === 2);
        $this->admin()->get(route('admin.claims.index', $base + ['type' => 'warranty']))->assertViewHas('claims', fn ($p) => $p->total() === 1);
        $this->admin()->get(route('admin.claims.index', $base + ['kind' => 'exchange']))->assertViewHas('claims', fn ($p) => $p->total() === 1 && $p->first()->type === 'wrong_product');
        $this->admin()->get(route('admin.claims.index', $base + ['product' => 'Beta Switch']))->assertViewHas('claims', fn ($p) => $p->total() === 1 && $p->first()->type === 'warranty');
        $this->admin()->get(route('admin.claims.index', $base + ['status' => 'approved']))->assertViewHas('claims', fn ($p) => $p->total() === 1);
        $this->admin()->get(route('admin.claims.index', $base + ['from' => now()->addDay()->toDateString()]))->assertViewHas('claims', fn ($p) => $p->total() === 0);
    }

    // ----- audit log -----

    public function test_the_audit_log_is_owner_only_and_filterable(): void
    {
        AuditLog::create(['actor_name' => 'Owner', 'action' => 'warranty.accepted', 'subject_type' => 'claim', 'subject_id' => 7, 'customer_phone' => self::PHONE, 'summary' => 'Warranty claim #7 accepted for inspection']);
        AuditLog::create(['actor_name' => 'Karim', 'action' => 'address.created', 'subject_type' => 'customer_address', 'subject_id' => 3, 'customer_phone' => '01766600000', 'summary' => 'Address saved: House 1']);

        $this->admin()->get(route('admin.audit.index', ['phone' => self::PHONE]))->assertOk()
            ->assertViewHas('logs', fn ($p) => $p->total() === 1 && $p->first()->action === 'warranty.accepted');
        $this->admin()->get(route('admin.audit.index', ['action' => 'address.', 'actor' => 'Karim']))->assertOk()
            ->assertViewHas('logs', fn ($p) => $p->total() >= 1 && $p->pluck('action')->every(fn ($a) => str_starts_with($a, 'address.')));

        // even a staff member who may do everything else cannot read the audit log
        $all = array_keys(AdminAccess::permissions());
        $this->staff($all)->get(route('admin.audit.index'))->assertForbidden();
    }

    // ----- health check -----

    public function test_the_check_reports_a_broken_link_and_changes_nothing(): void
    {
        // a replacement order that is linked to nothing
        $bad = $this->order('Breaker P7', 0, 'replacement', null);
        $ordersBefore = Order::count();
        $claimsBefore = ServiceRequest::count();

        $code = Artisan::call('aftersales:check');
        $out  = Artisan::output();

        $this->assertSame(1, $code);
        $this->assertStringContainsString('no link to the original order or the claim', $out);
        $this->assertSame($ordersBefore, Order::count());
        $this->assertSame($claimsBefore, ServiceRequest::count());
        $this->assertSame('replacement', $bad->fresh()->order_type);
    }

    // ----- reorder with an extra product (acceptance test 2) -----

    public function test_a_reorder_can_add_a_new_product_and_uses_current_prices(): void
    {
        $a = Product::create(['title' => 'Old Item ' . uniqid(), 'price' => 500, 'stock_quantity' => 10, 'is_published' => true, 'is_upcoming' => false]);
        $b = Product::create(['title' => 'New Item ' . uniqid(), 'price' => 250, 'stock_quantity' => 10, 'is_published' => true, 'is_upcoming' => false]);

        $old = Order::create([
            'name' => 'Rahim', 'phone' => '01712345678', 'district' => 'Dhaka', 'upazila' => 'Banani', 'city' => 'Dhaka',
            'address' => 'House 5', 'subtotal' => 400, 'total' => 400, 'status' => 'delivered',
        ]);
        $old->items()->create(['product_id' => $a->id, 'title' => $a->title, 'price' => 400, 'quantity' => 1, 'line_total' => 400]);   // the price was 400 back then

        $this->admin()->post(route('admin.orders.store'), [
            'name' => 'Rahim', 'phone' => '01712345678', 'district' => 'Dhaka', 'upazila' => 'Banani', 'city' => 'Dhaka',
            'address' => 'House 5', 'client_token' => 'p7-' . uniqid(), 'source_order_id' => $old->id,
            'items' => [['product_id' => $a->id, 'quantity' => 1], ['product_id' => $b->id, 'quantity' => 2]],
        ])->assertRedirect();

        $new = Order::latest('id')->first();
        $this->assertSame($old->id, (int) $new->source_order_id);
        $this->assertSame(2, $new->items()->count());
        $this->assertSame(500, (int) $new->items()->where('product_id', $a->id)->value('price'));   // today's price, not the old 400
        $this->assertSame(400, (int) $old->items()->first()->price);                                   // the old order is untouched
        $this->assertSame(500 + 250 * 2, (int) $new->subtotal);
    }
}
