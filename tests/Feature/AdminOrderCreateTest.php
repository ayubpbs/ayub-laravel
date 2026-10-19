<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Coupon;
use App\Models\CustomerAddress;
use App\Models\CustomerProfile;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 4: admin order creation and reorder.
 * Runs only on a TEST database (name ends with _test) and rolls every test back. See CustomerSearchTest.
 * Run "php artisan migrate" on the test database first.
 */
class AdminOrderCreateTest extends TestCase
{
    use DatabaseTransactions;

    private const PHONE = '01712345678';

    protected function setUp(): void
    {
        parent::setUp();

        if (!str_ends_with((string) DB::connection()->getDatabaseName(), '_test')) {
            $this->markTestSkipped('Not a test database (name must end with _test).');
        }
        if (!Order::hasAdminColumns()) {
            $this->markTestSkipped('Run "php artisan migrate" on the test database first.');
        }
    }

    private function admin()
    {
        return $this->withSession(['admin_ok' => true]);
    }

    private function product(int $price = 500, int $stock = 10, array $over = []): Product
    {
        return Product::create($over + [
            'title' => 'Test Switch ' . uniqid(), 'price' => $price, 'stock_quantity' => $stock,
            'is_published' => true, 'is_upcoming' => false,
        ]);
    }

    private function payload(array $items, array $over = []): array
    {
        $rows = [];
        foreach ($items as $productId => $qty) {
            $rows[] = ['product_id' => $productId, 'quantity' => $qty];
        }

        return $over + [
            'name' => 'Rahim', 'phone' => '+8801712-345678', 'district' => 'Dhaka', 'upazila' => 'Banani', 'city' => 'Dhaka',
            'address' => 'House 5, Road 2', 'client_token' => 'tok-' . uniqid(), 'items' => $rows,
        ];
    }

    public function test_creates_order_with_totals_stock_attribution_profile_and_audit(): void
    {
        $p = $this->product(500, 10);

        $this->admin()->post(route('admin.orders.store'), $this->payload([$p->id => 3]))->assertRedirect();

        $order = Order::latest('id')->first();
        $this->assertSame(self::PHONE, $order->phone);               // phone was normalised
        $this->assertSame(1500, (int) $order->subtotal);
        $this->assertSame('Owner', $order->created_by_name);
        $this->assertSame('pending', $order->status);
        $this->assertSame('normal', $order->order_type);
        $this->assertSame('House 5, Road 2, Dhaka, Banani, Dhaka', $order->address);
        $this->assertSame(1, $order->items()->count());
        $this->assertSame(7, (int) $p->fresh()->stock_quantity);     // stock went down
        $this->assertTrue(CustomerProfile::where('phone', self::PHONE)->exists());
        $this->assertTrue(AuditLog::where('action', 'order.created')->where('subject_id', $order->id)->exists());
    }

    public function test_same_form_sent_twice_makes_one_order(): void
    {
        $p    = $this->product(500, 10);
        $body = $this->payload([$p->id => 2], ['client_token' => 'same-token-1']);

        $this->admin()->post(route('admin.orders.store'), $body);
        $this->admin()->post(route('admin.orders.store'), $body);

        $this->assertSame(1, Order::where('client_token', 'same-token-1')->count());
        $this->assertSame(8, (int) $p->fresh()->stock_quantity);     // stock reduced only once
    }

    public function test_not_enough_stock_is_refused_and_nothing_changes(): void
    {
        $p      = $this->product(500, 2);
        $before = Order::count();

        $this->admin()->post(route('admin.orders.store'), $this->payload([$p->id => 5]))->assertSessionHas('error');

        $this->assertSame($before, Order::count());
        $this->assertSame(2, (int) $p->fresh()->stock_quantity);
    }

    public function test_unpublished_and_upcoming_products_are_refused(): void
    {
        $hidden   = $this->product(500, 5, ['is_published' => false]);
        $upcoming = $this->product(500, 5, ['is_upcoming' => true]);
        $before   = Order::count();

        $this->admin()->post(route('admin.orders.store'), $this->payload([$hidden->id => 1]))->assertSessionHas('error');
        $this->admin()->post(route('admin.orders.store'), $this->payload([$upcoming->id => 1]))->assertSessionHas('error');

        $this->assertSame($before, Order::count());
    }

    public function test_invalid_phone_and_empty_items_are_refused(): void
    {
        $p = $this->product();

        $this->admin()->post(route('admin.orders.store'), $this->payload([$p->id => 1], ['phone' => '12345']))->assertSessionHasErrors('phone');
        $this->admin()->post(route('admin.orders.store'), $this->payload([]))->assertSessionHasErrors('items');
    }

    public function test_coupon_is_applied_and_counted(): void
    {
        $p = $this->product(1000, 10);
        Coupon::create(['code' => 'SAVE100', 'type' => 'fixed', 'value' => 100, 'is_active' => true]);

        $this->admin()->post(route('admin.orders.store'), $this->payload([$p->id => 1], ['coupon_code' => 'save 100']))->assertRedirect();

        $order = Order::latest('id')->first();
        $this->assertSame('SAVE100', $order->coupon_code);
        $this->assertSame(100, (int) $order->discount);
        $this->assertSame(1, (int) Coupon::where('code', 'SAVE100')->value('used_count'));
    }

    public function test_bad_coupon_stops_the_order_and_puts_stock_back(): void
    {
        $p      = $this->product(1000, 10);
        $before = Order::count();

        $this->admin()->post(route('admin.orders.store'), $this->payload([$p->id => 1], ['coupon_code' => 'NOPE']))->assertSessionHas('error');

        $this->assertSame($before, Order::count());
        $this->assertSame(10, (int) $p->fresh()->stock_quantity);    // the transaction rolled back
    }

    public function test_reorder_form_uses_current_price_and_flags_unavailable_items(): void
    {
        $a = $this->product(500, 10);
        $b = $this->product(300, 0);
        $old = Order::create([
            'name' => 'Rahim', 'phone' => self::PHONE, 'district' => 'Dhaka', 'upazila' => 'Banani', 'city' => 'Dhaka',
            'address' => 'House 5, Dhaka, Banani, Dhaka', 'subtotal' => 0, 'total' => 0, 'status' => 'delivered',
        ]);
        $old->items()->create(['product_id' => $a->id, 'title' => $a->title, 'price' => 400, 'quantity' => 2, 'line_total' => 800]);
        $old->items()->create(['product_id' => $b->id, 'title' => $b->title, 'price' => 300, 'quantity' => 1, 'line_total' => 300]);

        $res = $this->admin()->get(route('admin.orders.create', ['reorder' => $old->id]));

        $res->assertOk()->assertSee('is out of stock', false)->assertSee('Price changed: was', false);
    }

    public function test_reorder_creates_a_new_order_and_leaves_the_old_one_alone(): void
    {
        $a = $this->product(500, 10);
        $old = Order::create([
            'name' => 'Rahim', 'phone' => self::PHONE, 'district' => 'Dhaka', 'upazila' => 'Banani', 'city' => 'Dhaka',
            'address' => 'House 5, Dhaka, Banani, Dhaka', 'subtotal' => 400, 'total' => 400, 'status' => 'delivered',
        ]);

        $this->admin()->post(route('admin.orders.store'), $this->payload([$a->id => 1], ['source_order_id' => $old->id]))->assertRedirect();

        $new = Order::latest('id')->first();
        $this->assertNotSame($old->id, $new->id);
        $this->assertSame($old->id, (int) $new->source_order_id);
        $this->assertSame(400, (int) $old->fresh()->total);
        $this->assertSame('delivered', $old->fresh()->status);
    }

    public function test_address_is_saved_once_and_not_duplicated(): void
    {
        $p = $this->product(500, 10);

        $this->admin()->post(route('admin.orders.store'), $this->payload([$p->id => 1], ['save_address' => '1']));
        $this->admin()->post(route('admin.orders.store'), $this->payload([$p->id => 1], ['save_address' => '1']));

        $profile = CustomerProfile::where('phone', self::PHONE)->first();
        $this->assertSame(1, CustomerAddress::where('customer_profile_id', $profile->id)->count());
    }

    public function test_foreign_address_id_is_ignored(): void
    {
        $p = $this->product(500, 10);

        $this->admin()->post(route('admin.orders.store'), $this->payload([$p->id => 1], ['address_id' => 999999]));

        $this->assertNull(Order::latest('id')->first()->address_id);
    }

    public function test_delivered_at_is_set_once_and_status_change_is_audited(): void
    {
        $o = Order::create([
            'name' => 'Rahim', 'phone' => self::PHONE, 'district' => 'Dhaka', 'upazila' => 'Banani', 'city' => 'Dhaka',
            'address' => 'x', 'subtotal' => 100, 'total' => 100, 'status' => 'confirmed',
        ]);

        $this->admin()->post(route('admin.orders.status', $o->id), ['status' => 'delivered']);

        $this->assertNotNull($o->fresh()->delivered_at);
        $this->assertTrue(AuditLog::where('action', 'order.status')->where('subject_id', $o->id)->exists());
    }

    public function test_staff_needs_the_orders_create_permission(): void
    {
        $this->assertSame('orders_create', \App\Support\AdminAccess::routePermission('admin.orders.create'));
        $this->assertSame('orders_create', \App\Support\AdminAccess::routePermission('admin.orders.store'));
        $this->assertSame('orders_create', \App\Support\AdminAccess::routePermission('admin.orders.create.products'));
        $this->assertSame('orders_view', \App\Support\AdminAccess::routePermission('admin.orders.show'));
        $this->assertArrayHasKey('orders_create', \App\Support\AdminAccess::permissions());
    }

    public function test_product_search_returns_only_sellable_products(): void
    {
        $ok = $this->product(500, 5, ['title' => 'Zzqx Plug']);
        $this->product(500, 5, ['title' => 'Zzqx Hidden', 'is_published' => false]);

        $res = $this->admin()->getJson(route('admin.orders.create.products', ['q' => 'Zzqx']));

        $res->assertOk()->assertJsonCount(1)->assertJsonFragment(['id' => $ok->id]);
    }
}
