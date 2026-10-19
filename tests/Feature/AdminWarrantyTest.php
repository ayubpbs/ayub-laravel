<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceRequest;
use App\Models\Staff;
use App\Support\AdminAccess;
use App\Support\WarrantyPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 6: warranty claims. Runs only on a TEST database (name ends with _test) and rolls every test back.
 * Run "php artisan migrate" on the test database first.
 */
class AdminWarrantyTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (!str_ends_with((string) DB::connection()->getDatabaseName(), '_test')) {
            $this->markTestSkipped('Not a test database (name must end with _test).');
        }
        if (!Order::hasAdminColumns() || !ServiceRequest::tableReady() || !WarrantyPolicy::columnsReady()) {
            $this->markTestSkipped('Run "php artisan migrate" on the test database first.');
        }

        Storage::fake('local');
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

    private function product(string $type = 'any', ?int $days = 365, int $stock = 10): Product
    {
        return Product::create([
            'title' => 'Test Breaker ' . uniqid(), 'price' => 500, 'stock_quantity' => $stock, 'is_published' => true, 'is_upcoming' => false,
            'warranty_type' => $type, 'warranty_days' => $days,
        ]);
    }

    /** A delivered order with one line. $snapshot = copy the product's policy onto the line like AdminOrderBuilder does. */
    private function delivered(Product $p, int $qty = 1, $deliveredAt = 'now', bool $snapshot = true): Order
    {
        $o = Order::create([
            'name' => 'Rahim', 'phone' => '01712345678', 'district' => 'Dhaka', 'upazila' => 'Banani', 'city' => 'Dhaka',
            'address' => 'House 5, Road 2, Dhaka, Banani, Dhaka', 'subtotal' => 500 * $qty, 'discount' => 0, 'delivery_charge' => 0,
            'total' => 500 * $qty, 'status' => 'delivered', 'payment_method' => 'cod',
            'delivered_at' => $deliveredAt === null ? null : ($deliveredAt === 'now' ? now() : $deliveredAt),
        ]);
        $o->items()->create([
            'product_id' => $p->id, 'title' => $p->title, 'price' => 500, 'quantity' => $qty, 'line_total' => 500 * $qty,
        ] + ($snapshot ? WarrantyPolicy::snapshotFor($p) : []));

        return $o->fresh('items');
    }

    private function open(Order $o, array $over = [], int $qty = 1)
    {
        return $this->admin()->post(route('admin.claims.warranty.store'), $over + [
            'order_id' => $o->id, 'order_item_id' => $o->items->first()->id, 'quantity' => $qty, 'reason' => 'Does not switch on',
        ]);
    }

    private function openOk(Order $o, array $over = []): ServiceRequest
    {
        $this->open($o, $over)->assertSessionHasNoErrors()->assertSessionMissing('error');

        return ServiceRequest::where('type', 'warranty')->latest('id')->firstOrFail();
    }

    private function shipment(ServiceRequest $c, string $direction, string $status)
    {
        return $this->admin()->post(route('admin.claims.shipments.store', $c->id), ['direction' => $direction, 'status' => $status, 'courier' => 'Hand delivery'])->assertRedirect();
    }

    /** accept -> product received -> inspected as a covered defect */
    private function toInspected(ServiceRequest $c, string $result = 'defect_confirmed'): ServiceRequest
    {
        $this->admin()->post(route('admin.claims.warranty.accept', $c->id))->assertSessionHas('success');
        $this->shipment($c, 'inbound', 'received');
        $this->admin()->post(route('admin.claims.warranty.inspect', $c->id), ['received_condition' => 'Complete, no burn marks', 'inspection_result' => $result])->assertSessionHas('success');

        return $c->fresh();
    }

    // ----- policy snapshot -----

    public function test_the_policy_is_copied_onto_new_order_lines_and_later_product_changes_do_not_touch_it(): void
    {
        $p = $this->product('repair', 90, 10);

        [$order, $created] = app(\App\Services\AdminOrderBuilder::class)->create([
            'name' => 'Rahim', 'phone' => '01712345678', 'district' => 'Dhaka', 'upazila' => 'Banani', 'city' => 'Dhaka',
            'address' => 'House 5', 'client_token' => 'w-' . uniqid(),
        ], [$p->id => 2]);

        $line = $order->items()->first();
        $this->assertTrue($created);
        $this->assertSame('repair', $line->warranty_type);
        $this->assertSame(90, (int) $line->warranty_days);
        $this->assertSame('product', $line->warranty_source);

        $p->update(['warranty_type' => 'none', 'warranty_days' => null]);
        $this->assertSame('repair', $line->fresh()->warranty_type);
        $this->assertSame(90, (int) $line->fresh()->warranty_days);
    }

    public function test_the_product_form_saves_the_policy_and_asks_for_a_period(): void
    {
        $base = ['title' => 'Switch ' . uniqid(), 'price' => 100, 'stock_quantity' => 5];

        $this->admin()->post(route('admin.products.store'), $base + ['warranty_type' => 'replacement', 'warranty_days' => 180, 'warranty_terms' => 'No cover for burn.'])->assertSessionHasNoErrors();
        $p = Product::where('title', $base['title'])->firstOrFail();
        $this->assertSame('replacement', $p->warranty_type);
        $this->assertSame(180, (int) $p->warranty_days);

        // type without a period and no shop default period: refused
        DB::table('settings')->where('key', 'warranty_days')->delete();
        \Illuminate\Support\Facades\Cache::forget('shop_settings_v1');
        $this->admin()->post(route('admin.products.store'), ['title' => 'Other ' . uniqid()] + $base + ['warranty_type' => 'repair'])->assertSessionHasErrors('warranty_days');
    }

    // ----- opening a claim -----

    public function test_a_claim_inside_the_warranty_opens_with_policy_and_expiry(): void
    {
        $p = $this->product('any', 365);
        $c = $this->openOk($this->delivered($p), ['symptoms' => 'Clicks but no power', 'serial_number' => 'SN-001']);

        $this->assertSame('open', $c->status);
        $this->assertSame('in_warranty', $c->warranty_state);
        $this->assertSame('any', $c->warranty_type);
        $this->assertSame(365, (int) $c->warranty_days);
        $this->assertSame('delivered', $c->warranty_start_source);
        $this->assertSame(now()->addDays(365)->toDateString(), $c->warranty_expires_at->toDateString());
        $this->assertSame('SN-001', $c->serial_number);
        $this->assertTrue($c->return_required);
    }

    public function test_an_expired_warranty_is_refused_with_the_dates_and_can_be_recorded_as_rejected(): void
    {
        $p = $this->product('any', 30);
        $o = $this->delivered($p, 1, now()->subDays(40));

        $this->open($o)->assertSessionHas('error', fn ($m) => str_contains($m, 'ended on') && str_contains($m, 'Save it anyway'));
        $this->assertSame(0, ServiceRequest::where('type', 'warranty')->count());

        $this->open($o, ['record_ineligible' => 1])->assertRedirect();
        $c = ServiceRequest::where('type', 'warranty')->firstOrFail();
        $this->assertSame('rejected', $c->status);
        $this->assertSame('expired', $c->warranty_state);
        $this->assertSame('System (automatic)', $c->decided_by_name);
        $this->assertStringContainsString('ended on', $c->decision_note);
    }

    public function test_a_product_without_warranty_is_refused(): void
    {
        $o = $this->delivered($this->product('none', null));

        $this->open($o)->assertSessionHas('error', fn ($m) => str_contains($m, 'no warranty'));
        $this->assertSame(0, ServiceRequest::where('type', 'warranty')->count());
    }

    public function test_an_old_line_without_a_snapshot_uses_todays_product_policy_and_is_flagged(): void
    {
        $p = $this->product('repair', 120);
        $o = $this->delivered($p, 1, 'now', false);
        $this->assertNull($o->items->first()->warranty_source);

        $c = $this->openOk($o);

        $this->assertSame('backfilled', $c->warranty_source);
        $this->assertSame('backfilled', $o->items()->first()->warranty_source);   // kept, so a later product change cannot alter it
        $p->update(['warranty_days' => 5]);
        $this->assertSame(120, (int) $o->items()->first()->warranty_days);
    }

    public function test_the_delivery_date_is_needed_when_the_order_has_none(): void
    {
        $o = $this->delivered($this->product('any', 365), 1, null);

        $this->open($o)->assertSessionHas('error', fn ($m) => str_contains($m, 'delivery date'));

        $this->open($o, ['start_date' => now()->subDays(10)->toDateString()])->assertRedirect();
        $c = ServiceRequest::where('type', 'warranty')->firstOrFail();
        $this->assertSame('admin', $c->warranty_start_source);
        $this->assertSame(now()->subDays(10)->addDays(365)->toDateString(), $c->warranty_expires_at->toDateString());

        // a date in the future is refused
        $o2 = $this->delivered($this->product('any', 365), 1, null);
        $this->open($o2, ['start_date' => now()->addDays(3)->toDateString()])->assertSessionHas('error');
    }

    public function test_only_a_delivered_order_can_get_a_claim(): void
    {
        $o = $this->delivered($this->product());
        $o->update(['status' => 'confirmed']);

        $this->open($o->fresh('items'))->assertSessionHas('error', fn ($m) => str_contains($m, 'delivered'));
    }

    public function test_the_same_piece_cannot_have_two_active_claims_but_is_free_again_after_the_first_is_finished(): void
    {
        $p = $this->product('any', 365);
        $o = $this->delivered($p, 1);

        $c = $this->openOk($o);
        $this->open($o)->assertSessionHas('error', fn ($m) => str_contains($m, 'can still be claimed'));

        $c->update(['status' => 'resolved']);   // finished: the unit may fail again inside the warranty
        $this->openOk($o);
        $this->assertSame(2, ServiceRequest::where('type', 'warranty')->count());
    }

    // ----- permissions -----

    public function test_accepting_needs_the_aftersales_approve_permission(): void
    {
        $c = $this->openOk($this->delivered($this->product()));

        $this->staff(['claims_view', 'claims_manage'])->post(route('admin.claims.warranty.accept', $c->id))->assertForbidden();
        $this->staff(['claims_view', 'claims_manage'])->post(route('admin.claims.warranty.reject', $c->id), ['note' => 'x'])->assertForbidden();
        $this->assertSame('open', $c->fresh()->status);

        $this->staff(['claims_view', 'aftersales_approve'])->post(route('admin.claims.warranty.accept', $c->id))->assertSessionHas('success');
        $this->assertSame('approved', $c->fresh()->status);
    }

    public function test_inspection_needs_the_inspect_permission_and_staff_without_manage_cannot_open(): void
    {
        $o = $this->delivered($this->product());
        $c = $this->openOk($o);
        $this->admin()->post(route('admin.claims.warranty.accept', $c->id));
        $this->shipment($c, 'inbound', 'received');

        $this->staff(['claims_view', 'claims_manage', 'aftersales_approve'])
            ->post(route('admin.claims.warranty.inspect', $c->id), ['received_condition' => 'ok', 'inspection_result' => 'defect_confirmed'])->assertForbidden();
        $this->staff(['claims_view'])->post(route('admin.claims.warranty.store'), ['order_id' => $o->id])->assertForbidden();
    }

    // ----- the flow -----

    public function test_repair_flow_from_accept_to_finish_adds_no_stock(): void
    {
        $p = $this->product('repair', 365, 10);
        $c = $this->openOk($this->delivered($p));

        // the product has not arrived yet: no inspection
        $this->admin()->post(route('admin.claims.warranty.accept', $c->id))->assertSessionHas('success');
        $this->admin()->post(route('admin.claims.warranty.inspect', $c->id), ['received_condition' => 'x', 'inspection_result' => 'defect_confirmed'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Record the returned product'));

        $this->shipment($c, 'inbound', 'received');
        $this->admin()->post(route('admin.claims.warranty.inspect', $c->id), ['received_condition' => 'Complete, burnt relay', 'inspection_result' => 'defect_confirmed'])->assertSessionHas('success');
        // the result cannot be changed afterwards
        $this->admin()->post(route('admin.claims.warranty.inspect', $c->id), ['received_condition' => 'again', 'inspection_result' => 'no_fault'])->assertSessionHas('error');

        $this->admin()->post(route('admin.claims.warranty.resolve', $c->id), ['resolution' => 'repair'])->assertSessionHas('success');

        // cannot finish before the repaired product is sent back
        $this->admin()->post(route('admin.claims.close', $c->id))->assertSessionHas('error', fn ($m) => str_contains($m, 'sends the repaired product'));
        $this->shipment($c, 'outbound', 'in_transit');
        $this->admin()->post(route('admin.claims.close', $c->id))->assertSessionHas('success');

        $this->assertSame('resolved', $c->fresh()->status);
        $this->assertSame('repair', $c->fresh()->resolution);
        $this->assertSame(10, (int) $p->fresh()->stock_quantity);   // the defective unit never goes on the shelf
    }

    public function test_the_solution_must_be_allowed_by_the_policy_of_the_purchase(): void
    {
        $repairOnly = $this->toInspected($this->openOk($this->delivered($this->product('repair', 365))));
        $this->admin()->post(route('admin.claims.warranty.resolve', $repairOnly->id), ['resolution' => 'replacement'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'does not allow'));
        $this->assertNull($repairOnly->fresh()->resolution);

        $replaceOnly = $this->toInspected($this->openOk($this->delivered($this->product('replacement', 365))));
        $this->admin()->post(route('admin.claims.warranty.resolve', $replaceOnly->id), ['resolution' => 'repair'])->assertSessionHas('error');
        $this->admin()->post(route('admin.claims.warranty.resolve', $replaceOnly->id), ['resolution' => 'replacement'])->assertSessionHas('success');
    }

    public function test_a_fault_that_is_not_covered_gets_no_free_solution_and_can_be_rejected(): void
    {
        $c = $this->toInspected($this->openOk($this->delivered($this->product('any', 365))), 'physical_damage');

        $this->admin()->post(route('admin.claims.warranty.resolve', $c->id), ['resolution' => 'repair'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'did not confirm'));
        $this->admin()->post(route('admin.claims.close', $c->id))->assertSessionHas('error');

        $this->admin()->post(route('admin.claims.warranty.reject', $c->id), ['note' => 'Water damage found'])->assertSessionHas('success');
        $this->assertSame('rejected', $c->fresh()->status);

        // the unit still has to go back to the customer
        $this->shipment($c, 'outbound', 'in_transit');
        $this->assertSame(1, $c->fresh()->shipments()->where('direction', 'outbound')->count());
    }

    public function test_replacement_must_be_the_same_goods_and_continues_the_original_warranty(): void
    {
        $p     = $this->product('replacement', 365, 10);
        $other = $this->product('any', 365, 10);
        $o     = $this->delivered($p, 1);
        $c     = $this->toInspected($this->openOk($o));
        $this->admin()->post(route('admin.claims.warranty.resolve', $c->id), ['resolution' => 'replacement'])->assertSessionHas('success');

        // a different product is not what the warranty promises
        $this->admin()->post(route('admin.claims.order', $c->id), ['client_token' => 'w1', 'items' => [['product_id' => $other->id, 'quantity' => 1]]])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'same product'));
        // more pieces than claimed neither
        $this->admin()->post(route('admin.claims.order', $c->id), ['client_token' => 'w2', 'items' => [['product_id' => $p->id, 'quantity' => 2]]])->assertSessionHas('error');
        $this->assertNull($c->fresh()->replacement_order_id);

        $this->admin()->post(route('admin.claims.order', $c->id), ['client_token' => 'w3', 'items' => [['product_id' => $p->id, 'quantity' => 1]]])->assertRedirect();
        $new = Order::findOrFail($c->fresh()->replacement_order_id);
        $this->assertSame('replacement', $new->order_type);
        $this->assertSame(0, (int) $new->subtotal);
        $this->assertSame('continued', $new->items()->first()->warranty_source);
        $this->assertSame(9, (int) $p->fresh()->stock_quantity);          // the replacement left the shop

        $this->admin()->post(route('admin.claims.close', $c->id))->assertSessionHas('success');

        // the original order is untouched and a new claim on it is possible again inside the same warranty
        $this->assertSame(500, (int) $o->fresh()->total);
        $this->openOk($o->fresh('items'));
    }

    public function test_serial_numbers_are_saved_and_the_replacement_serial_needs_the_replacement_solution(): void
    {
        $c = $this->openOk($this->delivered($this->product('any', 365)));

        $this->admin()->post(route('admin.claims.warranty.serials', $c->id), ['serial_number' => 'SN-9'])->assertSessionHas('success');
        $this->assertSame('SN-9', $c->fresh()->serial_number);

        $this->admin()->post(route('admin.claims.warranty.serials', $c->id), ['serial_number' => 'SN-9', 'replacement_serial' => 'SN-10'])->assertSessionHas('error');
    }

    // ----- the exchange screens must not decide a warranty claim -----

    public function test_the_exchange_screens_cannot_decide_refund_or_inspect_a_warranty_claim(): void
    {
        $c = $this->openOk($this->delivered($this->product('any', 365)));

        $this->admin()->post(route('admin.claims.decide', $c->id), ['action' => 'approve', 'resolution' => 'replacement'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'warranty panel'));
        $this->assertSame('open', $c->fresh()->status);

        $this->admin()->post(route('admin.claims.warranty.accept', $c->id));
        $this->admin()->post(route('admin.claims.refund', $c->id), ['amount' => 100, 'method' => 'cash'])->assertSessionHas('error');
        $this->admin()->post(route('admin.claims.inspect', $c->id), ['rows' => [1 => ['restock' => 1]]])->assertSessionHas('error');
        $this->assertSame(0, $c->fresh()->refunds()->count());
    }

    public function test_the_standard_claim_form_cannot_open_a_warranty_claim(): void
    {
        $o = $this->delivered($this->product('any', 365));

        $this->admin()->post(route('admin.claims.store'), [
            'order_id' => $o->id, 'type' => 'warranty', 'qty' => [$o->items->first()->id => 1],
        ])->assertSessionHasErrors('type');
        $this->assertSame(0, ServiceRequest::where('type', 'warranty')->count());
    }

    public function test_the_claim_page_and_the_list_show_the_warranty(): void
    {
        $c = $this->openOk($this->delivered($this->product('repair', 365)));

        $this->admin()->get(route('admin.claims.show', $c->id))->assertOk()->assertSee('Warranty')->assertSee('Repair / service warranty')->assertSee('Accept for inspection');
        $this->admin()->get(route('admin.claims.index', ['kind' => 'warranty']))->assertOk()->assertSee('Warranty claim');
        $this->admin()->get(route('admin.claims.warranty.create', ['order' => $c->order_id]))->assertOk()->assertSee('In warranty');
    }
}
