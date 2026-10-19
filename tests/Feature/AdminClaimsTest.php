<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestFile;
use App\Models\Staff;
use App\Support\AdminAccess;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 5: claims (exchange / wrong product / defective), replacement and exchange orders, refunds,
 * inspection (only "restock" adds stock), shipments and private evidence.
 * Runs only on a TEST database (name ends with _test) and rolls every test back. See CustomerSearchTest.
 * Run "php artisan migrate" on the test database first.
 */
class AdminClaimsTest extends TestCase
{
    use DatabaseTransactions;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        if (!str_ends_with((string) DB::connection()->getDatabaseName(), '_test')) {
            $this->markTestSkipped('Not a test database (name must end with _test).');
        }
        if (!Order::hasAdminColumns() || !ServiceRequest::tableReady()) {
            $this->markTestSkipped('Run "php artisan migrate" on the test database first.');
        }

        Storage::fake('local');
    }

    // ----- helpers -----

    /** Forget who is signed in (a test keeps its session between requests) and AdminAccess' per-request staff cache */
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

    private function product(int $price = 500, int $stock = 10, array $over = []): Product
    {
        return Product::create($over + [
            'title' => 'Test Switch ' . uniqid(), 'price' => $price, 'stock_quantity' => $stock,
            'is_published' => true, 'is_upcoming' => false,
        ]);
    }

    /** A delivered order with one line. Stock is NOT touched here (the goods already left the shop). */
    private function delivered(Product $p, int $qty = 2, array $over = []): Order
    {
        $o = Order::create($over + [
            'name' => 'Rahim', 'phone' => '01712345678', 'district' => 'Dhaka', 'upazila' => 'Banani', 'city' => 'Dhaka',
            'address' => 'House 5, Road 2, Dhaka, Banani, Dhaka', 'subtotal' => $p->price * $qty, 'discount' => 0, 'delivery_charge' => 0,
            'total' => $p->price * $qty, 'status' => 'delivered', 'payment_method' => 'cod', 'delivered_at' => now(),
        ]);
        $o->items()->create(['product_id' => $p->id, 'title' => $p->title, 'price' => $p->price, 'quantity' => $qty, 'line_total' => $p->price * $qty]);

        return $o->fresh('items');
    }

    private function openClaim(Order $o, int $qty = 1, array $over = []): ServiceRequest
    {
        $line = $o->items->first();
        $this->admin()->post(route('admin.claims.store'), $over + [
            'order_id' => $o->id, 'type' => 'defective', 'reason' => 'Does not turn on', 'return_required' => 1,
            'qty' => [$line->id => $qty],
        ])->assertSessionHasNoErrors();

        return ServiceRequest::latest('id')->firstOrFail();
    }

    private function approve(ServiceRequest $c, string $resolution)
    {
        $this->admin()->post(route('admin.claims.decide', $c->id), ['action' => 'approve', 'resolution' => $resolution])->assertRedirect();

        return $c->fresh();
    }

    // ----- opening a claim -----

    public function test_claim_can_only_be_opened_for_a_delivered_order_and_never_edits_the_order(): void
    {
        $p = $this->product();
        $o = $this->delivered($p, 2);
        $snap   = fn (Order $x) => [(int) $x->total, $x->status, (int) $x->subtotal, $x->updated_at->toDateTimeString()];
        $before = $snap($o);

        $c = $this->openClaim($o, 1);

        $this->assertSame('open', $c->status);
        $this->assertSame($o->id, (int) $c->order_id);
        $this->assertSame(1, (int) $c->items->first()->quantity);
        $this->assertSame((int) $p->price, (int) $c->items->first()->price);
        $this->assertSame($before, $snap($o->fresh()), 'the original order must not change');
        $this->assertNotNull(AuditLog::where('action', 'claim.opened')->where('subject_id', $c->id)->first());

        $pending = Order::create(['name' => 'A', 'phone' => '01712345679', 'address' => 'x', 'subtotal' => 1, 'total' => 1, 'status' => 'pending']);
        $pending->items()->create(['product_id' => $p->id, 'title' => 't', 'price' => 1, 'quantity' => 1, 'line_total' => 1]);
        $this->admin()->post(route('admin.claims.store'), [
            'order_id' => $pending->id, 'type' => 'defective', 'qty' => [$pending->items()->first()->id => 1],
        ])->assertSessionHas('error');
        $this->assertSame(1, ServiceRequest::count());
    }

    public function test_cannot_claim_more_pieces_than_were_delivered_across_all_claims(): void
    {
        $o = $this->delivered($this->product(), 2);
        $line = $o->items->first();

        $this->openClaim($o, 2);
        $this->admin()->post(route('admin.claims.store'), ['order_id' => $o->id, 'type' => 'defective', 'qty' => [$line->id => 1]])
            ->assertSessionHas('error');
        $this->assertSame(1, ServiceRequest::count());

        // a rejected claim frees the pieces again
        $this->admin()->post(route('admin.claims.decide', ServiceRequest::first()->id), ['action' => 'reject', 'note' => 'Not covered'])->assertRedirect();
        $this->admin()->post(route('admin.claims.store'), ['order_id' => $o->id, 'type' => 'defective', 'qty' => [$line->id => 2]])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, ServiceRequest::count());
    }

    public function test_reject_needs_a_reason_and_a_decided_claim_cannot_be_decided_twice(): void
    {
        $c = $this->openClaim($this->delivered($this->product()));

        $this->admin()->post(route('admin.claims.decide', $c->id), ['action' => 'reject'])->assertSessionHas('error');
        $this->assertSame('open', $c->fresh()->status);

        $this->approve($c, 'replacement');
        $this->admin()->post(route('admin.claims.decide', $c->id), ['action' => 'reject', 'note' => 'x'])->assertSessionHas('error');
        $this->assertSame('approved', $c->fresh()->status);
    }

    // ----- replacement -----

    public function test_replacement_creates_a_linked_free_order_through_the_builder_and_takes_stock(): void
    {
        $p = $this->product(500, 10);
        $o = $this->delivered($p, 2, ['total' => 1000]);
        $c = $this->approve($this->openClaim($o, 1), 'replacement');

        $this->admin()->post(route('admin.claims.order', $c->id), [
            'client_token' => 'tok-1', 'items' => [['product_id' => $p->id, 'quantity' => 1]], 'delivery' => 0,
        ])->assertRedirect();

        $new = Order::latest('id')->first();
        $this->assertNotSame($o->id, $new->id);
        $this->assertSame('replacement', $new->order_type);
        $this->assertSame($o->id, (int) $new->source_order_id);
        $this->assertSame($c->id, (int) $new->service_request_id);
        $this->assertSame(0, (int) $new->total);
        $this->assertSame('pending', $new->status);
        $this->assertSame(0, (int) $new->items->first()->price);
        $this->assertSame($o->address, $new->address);
        $this->assertSame(9, (int) $p->fresh()->stock_quantity, 'stock goes down for the replacement like any order');
        $this->assertSame($new->id, (int) $c->fresh()->replacement_order_id);
        $this->assertNotNull($new->created_by_name);
        $this->assertSame(1000, (int) $o->fresh()->total, 'original order untouched');
    }

    public function test_replacement_form_sent_twice_makes_only_one_order_and_a_second_order_is_refused(): void
    {
        $p = $this->product(500, 10);
        $c = $this->approve($this->openClaim($this->delivered($p, 2)), 'replacement');
        $payload = ['client_token' => 'tok-dup', 'items' => [['product_id' => $p->id, 'quantity' => 1]]];

        $this->admin()->post(route('admin.claims.order', $c->id), $payload)->assertRedirect();
        $count = Order::count();
        $this->admin()->post(route('admin.claims.order', $c->id), $payload);
        $this->admin()->post(route('admin.claims.order', $c->id), ['client_token' => 'tok-other'] + $payload)->assertSessionHas('error');

        $this->assertSame($count, Order::count());
        $this->assertSame(9, (int) $p->fresh()->stock_quantity);
    }

    public function test_replacement_with_not_enough_stock_is_refused_and_nothing_is_left_behind(): void
    {
        $p = $this->product(500, 0);
        $c = $this->approve($this->openClaim($this->delivered($p, 2)), 'replacement');
        $orders = Order::count();

        $this->admin()->post(route('admin.claims.order', $c->id), ['client_token' => 't', 'items' => [['product_id' => $p->id, 'quantity' => 1]]])
            ->assertSessionHas('error');

        $this->assertSame($orders, Order::count());
        $this->assertNull($c->fresh()->replacement_order_id);
        $this->assertSame(0, (int) $p->fresh()->stock_quantity);
    }

    // ----- exchange -----

    public function test_exchange_total_is_the_price_difference_and_the_excess_is_reported(): void
    {
        $old = $this->product(500, 10);
        $new = $this->product(800, 10);
        $o   = $this->delivered($old, 1);
        $c   = $this->approve($this->openClaim($o, 1, ['type' => 'exchange']), 'exchange');

        $this->admin()->post(route('admin.claims.order', $c->id), [
            'client_token' => 'ex-1', 'items' => [['product_id' => $new->id, 'quantity' => 1]], 'credit' => 500, 'delivery' => 60,
        ])->assertRedirect();

        $order = Order::latest('id')->first();
        $this->assertSame('exchange', $order->order_type);
        $this->assertSame(800, (int) $order->subtotal);
        $this->assertSame(500, (int) $order->discount);
        $this->assertSame(60, (int) $order->delivery_charge);
        $this->assertSame(360, (int) $order->total, '800 - 500 + 60');
        $this->assertSame(9, (int) $new->fresh()->stock_quantity);

        // cheaper goods: the returned goods are worth more than the new goods
        $cheap = $this->product(300, 10);
        $o2    = $this->delivered($old, 1);
        $c2    = $this->approve($this->openClaim($o2, 1, ['type' => 'exchange']), 'exchange');
        $this->admin()->post(route('admin.claims.order', $c2->id), ['client_token' => 'ex-2', 'items' => [['product_id' => $cheap->id, 'quantity' => 1]], 'credit' => 500, 'delivery' => 0])->assertRedirect();

        $order2 = Order::latest('id')->first();
        $this->assertSame(0, (int) $order2->total);
        $this->assertSame(300, (int) $order2->discount, 'the credit can never make the order total negative');
        $this->assertSame(200, app(\App\Services\ClaimService::class)->exchangeExcess($c2->fresh()));
    }

    public function test_exchange_credit_is_capped_at_the_value_of_the_claimed_goods(): void
    {
        $old = $this->product(500, 10);
        $new = $this->product(800, 10);
        $c   = $this->approve($this->openClaim($this->delivered($old, 1), 1, ['type' => 'exchange']), 'exchange');

        $this->admin()->post(route('admin.claims.order', $c->id), ['client_token' => 'cap', 'items' => [['product_id' => $new->id, 'quantity' => 1]], 'credit' => 5000])
            ->assertRedirect();

        $order = Order::where('order_type', 'exchange')->firstOrFail();
        $this->assertSame(500, (int) $order->discount, 'asking for more credit than the claimed goods are worth is cut down');
        $this->assertSame(300, (int) $order->total);
    }

    // ----- refund -----

    public function test_refund_is_a_payment_row_linked_to_the_claim_and_does_not_make_the_order_due(): void
    {
        $p = $this->product(500, 10);
        $o = $this->delivered($p, 2, ['total' => 1000]);
        Payment::create(['order_id' => $o->id, 'type' => 'payment', 'method' => 'cod', 'amount' => 1000, 'paid_at' => now()]);
        $c = $this->approve($this->openClaim($o, 1), 'refund');

        $this->admin()->post(route('admin.claims.refund', $c->id), ['amount' => 400, 'method' => 'bkash', 'trx_id' => 'TRX1'])->assertSessionHasNoErrors();

        $pay = Payment::where('type', 'refund')->first();
        $this->assertSame($o->id, (int) $pay->order_id);
        $this->assertSame($c->id, (int) $pay->service_request_id);
        $this->assertSame(400, (int) $pay->amount);

        $fresh = Order::withPaid()->find($o->id);
        $this->assertSame(1000, $fresh->netPaid(), 'received money is unchanged');
        $this->assertSame(400, $fresh->claimRefunded());
        $this->assertSame(0, $fresh->dueAmount(), 'a claim refund must not make a delivered order show as owing');
        $this->assertSame('paid', $fresh->paymentStatus());
        $this->assertFalse(Order::owing()->whereKey($o->id)->exists());
        $this->assertSame(1000, (int) $fresh->total, 'order total / invoice stay as they were');
        $this->assertSame(600, $fresh->moneyHeld());
    }

    public function test_refund_cannot_be_more_than_the_money_held_and_a_trx_id_cannot_be_reused(): void
    {
        $o = $this->delivered($this->product(), 2, ['total' => 1000]);
        Payment::create(['order_id' => $o->id, 'type' => 'payment', 'method' => 'cash', 'amount' => 300, 'paid_at' => now()]);
        $c = $this->approve($this->openClaim($o, 1), 'refund');

        $this->admin()->post(route('admin.claims.refund', $c->id), ['amount' => 301, 'method' => 'cash'])->assertSessionHas('error');
        $this->assertSame(0, Payment::where('type', 'refund')->count());

        $this->admin()->post(route('admin.claims.refund', $c->id), ['amount' => 300, 'method' => 'bkash', 'trx_id' => 'DUP'])->assertSessionHasNoErrors();
        $this->admin()->post(route('admin.claims.refund', $c->id), ['amount' => 1, 'method' => 'bkash', 'trx_id' => 'DUP'])->assertSessionHas('error');
        $this->assertSame(1, Payment::where('type', 'refund')->count());
    }

    public function test_a_refund_needs_an_approved_claim(): void
    {
        $o = $this->delivered($this->product(), 1, ['total' => 500]);
        Payment::create(['order_id' => $o->id, 'type' => 'payment', 'method' => 'cash', 'amount' => 500, 'paid_at' => now()]);
        $c = $this->openClaim($o, 1);

        $this->admin()->post(route('admin.claims.refund', $c->id), ['amount' => 100, 'method' => 'cash'])->assertSessionHas('error');
        $this->assertSame(0, Payment::where('type', 'refund')->count());
    }

    // ----- inspection: nothing goes back on the shelf by itself -----

    public function test_returned_defective_goods_do_not_go_back_to_stock_unless_inspection_says_restock(): void
    {
        $p = $this->product(500, 10);
        $o = $this->delivered($p, 4);
        $c = $this->approve($this->openClaim($o, 4), 'refund');
        $item = $c->items->first();

        // marking things received / approving / closing never touches stock
        $this->admin()->post(route('admin.claims.shipments.store', $c->id), ['direction' => 'inbound', 'status' => 'received', 'courier' => 'Steadfast'])->assertSessionHasNoErrors();
        $this->assertSame(10, (int) $p->fresh()->stock_quantity);

        // numbers must add up to the claimed quantity
        $this->admin()->post(route('admin.claims.inspect', $c->id), ['rows' => [$item->id => ['restock' => 1, 'supplier' => 1, 'scrap' => 1]]])->assertSessionHas('error');
        $this->assertSame(10, (int) $p->fresh()->stock_quantity);

        $this->admin()->post(route('admin.claims.inspect', $c->id), ['rows' => [$item->id => ['restock' => 1, 'supplier' => 1, 'scrap' => 2, 'note' => 'one is fine']]])->assertSessionHasNoErrors();

        $this->assertSame(11, (int) $p->fresh()->stock_quantity, 'only the restock piece is added');
        $i = $item->fresh();
        $this->assertSame([1, 1, 2], [(int) $i->qty_restock, (int) $i->qty_supplier, (int) $i->qty_scrap]);
        $this->assertTrue($i->isInspected());

        // cannot be inspected twice (no double stock)
        $this->admin()->post(route('admin.claims.inspect', $c->id), ['rows' => [$item->id => ['restock' => 4, 'supplier' => 0, 'scrap' => 0]]])->assertSessionHas('error');
        $this->assertSame(11, (int) $p->fresh()->stock_quantity);
    }

    public function test_wrong_product_restock_goes_to_the_product_that_was_really_received(): void
    {
        $ordered  = $this->product(500, 10);
        $received = $this->product(500, 3);
        $o   = $this->delivered($ordered, 1);
        $line = $o->items->first();

        $this->admin()->post(route('admin.claims.store'), [
            'order_id' => $o->id, 'type' => 'wrong_product', 'return_required' => 1, 'qty' => [$line->id => 1], 'received' => [$line->id => $received->id],
        ])->assertSessionHasNoErrors();
        $c = $this->approve(ServiceRequest::latest('id')->first(), 'replacement');

        $this->admin()->post(route('admin.claims.inspect', $c->id), ['rows' => [$c->items->first()->id => ['restock' => 1, 'supplier' => 0, 'scrap' => 0]]])->assertSessionHasNoErrors();

        $this->assertSame(10, (int) $ordered->fresh()->stock_quantity);
        $this->assertSame(4, (int) $received->fresh()->stock_quantity);
    }

    public function test_cancelling_or_returning_the_original_order_is_blocked_while_it_has_a_claim(): void
    {
        $p = $this->product(500, 10);
        $o = $this->delivered($p, 1);
        $this->openClaim($o, 1);

        $this->admin()->post(route('admin.orders.status', $o->id), ['status' => 'returned'])->assertSessionHas('error');
        $this->assertSame('delivered', $o->fresh()->status);
        $this->assertSame(10, (int) $p->fresh()->stock_quantity);
    }

    public function test_cancelling_a_replacement_order_puts_its_stock_back_once(): void
    {
        $p = $this->product(500, 10);
        $c = $this->approve($this->openClaim($this->delivered($p, 2)), 'replacement');
        $this->admin()->post(route('admin.claims.order', $c->id), ['client_token' => 'c1', 'items' => [['product_id' => $p->id, 'quantity' => 2]]])->assertRedirect();
        $new = Order::latest('id')->first();
        $this->assertSame(8, (int) $p->fresh()->stock_quantity);

        $this->admin()->post(route('admin.orders.status', $new->id), ['status' => 'cancelled'])->assertRedirect();
        $this->assertSame(10, (int) $p->fresh()->stock_quantity);
    }

    // ----- closing -----

    public function test_a_claim_can_only_be_closed_when_its_solution_and_inspection_are_done(): void
    {
        $p = $this->product(500, 10);
        $o = $this->delivered($p, 1, ['total' => 500]);
        Payment::create(['order_id' => $o->id, 'type' => 'payment', 'method' => 'cash', 'amount' => 500, 'paid_at' => now()]);
        $c = $this->approve($this->openClaim($o, 1), 'refund');

        $this->admin()->post(route('admin.claims.close', $c->id))->assertSessionHas('error');
        $this->admin()->post(route('admin.claims.refund', $c->id), ['amount' => 500, 'method' => 'cash'])->assertSessionHasNoErrors();
        $this->admin()->post(route('admin.claims.close', $c->id))->assertSessionHas('error'); // still not inspected
        $this->admin()->post(route('admin.claims.inspect', $c->id), ['rows' => [$c->items->first()->id => ['restock' => 0, 'supplier' => 0, 'scrap' => 1]]])->assertSessionHasNoErrors();
        $this->admin()->post(route('admin.claims.close', $c->id))->assertSessionHas('success');

        $this->assertSame('resolved', $c->fresh()->status);
        $this->assertSame(10, (int) $p->fresh()->stock_quantity, 'scrapped goods never reach the shelf');
    }

    // ----- evidence is private -----

    public function test_evidence_is_stored_outside_public_with_a_random_name_and_only_served_to_admins_with_permission(): void
    {
        $c = $this->openClaim($this->delivered($this->product()));
        $png = UploadedFile::fake()->createWithContent('customer-photo.png', base64_decode(self::PNG));

        $this->admin()->post(route('admin.claims.evidence.store', $c->id), ['files' => [$png], 'note' => 'box'])->assertSessionHasNoErrors();

        $f = ServiceRequestFile::first();
        $this->assertSame('image', $f->kind);
        $this->assertStringStartsWith('claims/' . $c->id . '/', $f->path);
        $this->assertStringNotContainsString('customer-photo', $f->path, 'the original name is never used on disk');
        Storage::disk('local')->assertExists($f->path);
        $this->assertFalse(file_exists(public_path($f->path)), 'nothing may be reachable from public/');

        $url = route('admin.claims.evidence.show', [$c->id, $f->id]);
        $this->admin()->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        // not signed in: sent to the login, the file is never shown
        $this->signOut();
        $this->get($url)->assertRedirect(route('admin.login'));
        // staff without the claims permission
        $this->staff(['orders_view'])->get($url)->assertStatus(403);
        // staff with it
        $this->staff(['claims_view'])->get($url)->assertOk();
        // a file id of another claim cannot be read through this claim
        $other = $this->openClaim($this->delivered($this->product()));
        $this->admin()->get(route('admin.claims.evidence.show', [$other->id, $f->id]))->assertNotFound();
    }

    public function test_only_real_photos_and_videos_are_accepted(): void
    {
        $c = $this->openClaim($this->delivered($this->product()));

        $fakePhp = UploadedFile::fake()->createWithContent('shell.png', '<?php echo "hi"; ?>');
        $this->admin()->post(route('admin.claims.evidence.store', $c->id), ['files' => [$fakePhp]])->assertSessionHas('error');

        $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->admin()->post(route('admin.claims.evidence.store', $c->id), ['files' => [$svg]])->assertSessionHas('error');

        $this->assertSame(0, ServiceRequestFile::count());

        $mp4 = UploadedFile::fake()->createWithContent('clip.mp4', "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom\x00\x00\x00\x08free");
        $this->admin()->post(route('admin.claims.evidence.store', $c->id), ['files' => [$mp4]])->assertSessionHasNoErrors();
        $this->assertSame('video', ServiceRequestFile::first()->kind);
    }

    public function test_files_can_be_added_when_the_claim_is_opened(): void
    {
        $o = $this->delivered($this->product());
        $line = $o->items->first();
        $png = UploadedFile::fake()->createWithContent('a.png', base64_decode(self::PNG));

        $this->admin()->post(route('admin.claims.store'), [
            'order_id' => $o->id, 'type' => 'damaged', 'qty' => [$line->id => 1], 'files' => [$png],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, ServiceRequestFile::count());
    }

    // ----- shipments -----

    public function test_shipments_can_be_recorded_and_marked_received_without_touching_stock(): void
    {
        $p = $this->product(500, 10);
        $c = $this->openClaim($this->delivered($p));

        $this->admin()->post(route('admin.claims.shipments.store', $c->id), ['direction' => 'inbound', 'status' => 'in_transit', 'courier' => 'Sundarban', 'tracking_code' => 'SB123'])->assertSessionHasNoErrors();
        $ship = $c->shipments()->first();
        $this->assertSame('in_transit', $ship->status);

        $this->admin()->post(route('admin.claims.shipments.update', [$c->id, $ship->id]), ['status' => 'received'])->assertSessionHasNoErrors();
        $this->assertSame('received', $ship->fresh()->status);
        $this->assertNotNull($ship->fresh()->received_at);
        $this->assertSame(10, (int) $p->fresh()->stock_quantity);
    }

    // ----- permissions -----

    public function test_permissions_are_enforced_per_action(): void
    {
        $c = $this->approve($this->openClaim($this->delivered($this->product())), 'refund');

        $this->assertSame('claims_view', AdminAccess::routePermission('admin.claims.show'));
        $this->assertSame('claims_view', AdminAccess::routePermission('admin.claims.evidence.show'));
        $this->assertSame('claims_manage', AdminAccess::routePermission('admin.claims.decide'));
        $this->assertSame('claims_manage', AdminAccess::routePermission('admin.claims.evidence.destroy'));
        $this->assertSame('claims_inspect', AdminAccess::routePermission('admin.claims.inspect'));
        $this->assertSame('payments', AdminAccess::routePermission('admin.claims.refund'));
        $this->assertSame('orders_create', AdminAccess::routePermission('admin.claims.order'));

        // view only: can look, cannot act
        $this->staff(['claims_view'])->get(route('admin.claims.index'))->assertOk();
        $this->staff(['claims_view'])->get(route('admin.claims.show', $c->id))->assertOk();
        $this->staff(['claims_view'])->post(route('admin.claims.close', $c->id))->assertStatus(403);
        $this->staff(['claims_view'])->post(route('admin.claims.inspect', $c->id), ['rows' => [1 => ['restock' => 1]]])->assertStatus(403);
        // manage without payments: no refund
        $this->staff(['claims_view', 'claims_manage'])->post(route('admin.claims.refund', $c->id), ['amount' => 1, 'method' => 'cash'])->assertStatus(403);
        // nothing at all
        $this->staff(['orders_view'])->get(route('admin.claims.index'))->assertStatus(403);
        $this->signOut();
        $this->get(route('admin.claims.index'))->assertRedirect(route('admin.login'));
    }

    // ----- screens -----

    public function test_screens_render(): void
    {
        $p = $this->product(500, 10);
        $o = $this->delivered($p, 2);
        $c = $this->approve($this->openClaim($o, 1), 'exchange');

        $this->admin()->get(route('admin.claims.index'))->assertOk()->assertSee('Claims');
        $this->admin()->get(route('admin.claims.create'))->assertOk();
        $this->admin()->get(route('admin.claims.create', ['order' => $o->id]))->assertOk()->assertSee($p->title);
        $this->admin()->get(route('admin.claims.show', $c->id))->assertOk()->assertSee('Exchange order');
        $this->admin()->get(route('admin.orders.show', $o->id))->assertOk()->assertSee('Open claim')->assertSee('Claims on this order');
        $this->admin()->get(route('admin.claims.products', ['q' => 'Test']))->assertOk();
    }

    public function test_claim_screen_renders_in_every_state(): void
    {
        $p = $this->product(500, 10);
        $o = $this->delivered($p, 2, ['total' => 1000]);
        Payment::create(['order_id' => $o->id, 'type' => 'payment', 'method' => 'cash', 'amount' => 1000, 'paid_at' => now()]);

        $rejected = $this->openClaim($o, 1);
        $this->admin()->get(route('admin.claims.show', $rejected->id))->assertOk()->assertSee('Approve');
        $this->admin()->post(route('admin.claims.decide', $rejected->id), ['action' => 'reject', 'note' => 'No'])->assertRedirect();
        $this->admin()->get(route('admin.claims.show', $rejected->id))->assertOk()->assertSee('Rejected');

        $c = $this->approve($this->openClaim($o, 2), 'refund');
        $this->admin()->get(route('admin.claims.show', $c->id))->assertOk()->assertSee('Save inspection')->assertSee('Record refund');
        $this->admin()->post(route('admin.claims.refund', $c->id), ['amount' => 100, 'method' => 'cash'])->assertSessionHasNoErrors();
        $this->admin()->post(route('admin.claims.inspect', $c->id), ['rows' => [$c->items->first()->id => ['restock' => 1, 'supplier' => 0, 'scrap' => 1]]])->assertSessionHasNoErrors();
        $this->admin()->get(route('admin.claims.show', $c->id))->assertOk()->assertSee('Mark claim as resolved');
        $this->admin()->post(route('admin.claims.close', $c->id))->assertSessionHas('success');
        $this->admin()->get(route('admin.claims.show', $c->id))->assertOk()->assertSee('Resolved');
        $this->admin()->get(route('admin.orders.show', $o->id))->assertOk()->assertSee('Refunded for claims');
        $this->admin()->get(route('admin.payments.index'))->assertOk();
        $this->admin()->get(route('admin.accounting.dashboard'))->assertOk();

        $x = $this->approve($this->openClaim($this->delivered($p, 1), 1, ['type' => 'exchange']), 'exchange');
        $this->admin()->get(route('admin.claims.show', $x->id))->assertOk()->assertSee('Make exchange order');
        $cheap = $this->product(100, 5);
        $this->admin()->post(route('admin.claims.order', $x->id), ['client_token' => 'walk', 'items' => [['product_id' => $cheap->id, 'quantity' => 1]], 'credit' => 500])->assertRedirect();
        $this->admin()->get(route('admin.claims.show', $x->id))->assertOk()->assertSee('Refund due');
        $this->admin()->get(route('admin.orders.show', Order::latest('id')->first()->id))->assertOk()->assertSee('Exchange');
    }

    // ----- other screens must not count claim orders as new orders -----

    public function test_dashboard_does_not_count_replacement_and_exchange_orders_as_new_orders(): void
    {
        $p = $this->product(500, 10);
        $c = $this->approve($this->openClaim($this->delivered($p, 2)), 'replacement');
        $before = Order::salesOrders()->count();

        $this->admin()->post(route('admin.claims.order', $c->id), ['client_token' => 'dash', 'items' => [['product_id' => $p->id, 'quantity' => 1]]])->assertRedirect();

        $this->assertSame($before, Order::salesOrders()->count());
        $this->assertSame($before + 1, Order::count());
        $this->admin()->get(route('admin.dashboard'))->assertOk();
    }

    public function test_normal_admin_orders_still_work_and_are_marked_normal(): void
    {
        $p = $this->product(500, 10);
        $this->admin()->post(route('admin.orders.store'), [
            'name' => 'Rahim', 'phone' => '01712345678', 'district' => 'Dhaka', 'upazila' => 'Banani', 'city' => 'Dhaka',
            'address' => 'House 5', 'client_token' => 'n-' . uniqid(), 'items' => [['product_id' => $p->id, 'quantity' => 2]],
        ])->assertSessionHasNoErrors();

        $o = Order::latest('id')->first();
        $this->assertSame('normal', $o->order_type);
        $this->assertNull($o->service_request_id);
        $this->assertSame(1000, (int) $o->subtotal);
        $this->assertSame(8, (int) $p->fresh()->stock_quantity);
    }
}
