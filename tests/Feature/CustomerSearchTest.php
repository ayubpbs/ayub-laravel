<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerProfile;
use App\Models\Order;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Needs a TEST database (name ending in _test, see .env.testing). Every test runs inside a transaction
 * that is rolled back, and the tests are skipped on any other database so real data is never touched.
 */
class CustomerSearchTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (!str_ends_with((string) DB::connection()->getDatabaseName(), '_test')) {
            $this->markTestSkipped('Not a test database (name must end with _test).');
        }
    }

    private function admin()
    {
        return $this->withSession(['admin_ok' => true]);
    }

    private function order(string $phone, string $name = 'Rahim', int $total = 500): Order
    {
        return Order::create([
            'name' => $name, 'phone' => $phone, 'address' => 'House 1, Dhaka', 'subtotal' => $total,
            'total' => $total, 'status' => 'pending',
        ]);
    }

    public function test_customer_pages_need_admin_login(): void
    {
        $this->get('/admin/customers')->assertRedirect(route('admin.login'));
        $this->post('/admin/customers', ['phone' => '01712345678', 'name' => 'X'])->assertRedirect(route('admin.login'));
    }

    public function test_full_phone_in_any_format_opens_the_customer(): void
    {
        $this->order('01712345678');

        $this->admin()->get('/admin/customers?q=' . urlencode('+88 01712-345678'))
            ->assertRedirect(route('admin.customers.show', '01712345678'));

        $this->admin()->get('/admin/customers?q=' . urlencode('০১৭১২৩৪৫৬৭৮'))
            ->assertRedirect(route('admin.customers.show', '01712345678'));
    }

    public function test_search_by_name_and_by_order_id(): void
    {
        $o = $this->order('01712345678', 'Karim Uddin');
        $this->order('01812345678', 'Someone Else');

        $this->admin()->get('/admin/customers?q=Karim')->assertOk()->assertSee('01712345678')->assertDontSee('01812345678');
        $this->admin()->get('/admin/customers?q=' . urlencode('#' . $o->id))->assertOk()->assertSee('01712345678')->assertDontSee('01812345678');
    }

    public function test_partial_phone_search(): void
    {
        $this->order('01712345678');
        $this->admin()->get('/admin/customers?q=2345')->assertOk()->assertSee('01712345678');
    }

    public function test_unknown_valid_number_offers_to_add_the_customer(): void
    {
        $this->admin()->get('/admin/customers?q=01999999999&list=1')->assertOk()->assertSee('Add this customer');
    }

    public function test_old_phone_format_opens_one_canonical_page(): void
    {
        $this->order('8801712345678', 'Old Format');

        $this->admin()->get('/admin/customers/8801712345678')->assertRedirect(route('admin.customers.show', '01712345678'));
        $this->admin()->get('/admin/customers/01712345678')->assertOk()->assertSee('Old Format');
    }

    public function test_adding_a_customer_never_creates_a_duplicate(): void
    {
        $this->admin()->post('/admin/customers', ['phone' => '+8801712345678', 'name' => 'Rahim'])
            ->assertRedirect(route('admin.customers.show', '01712345678'));
        $this->assertSame(1, CustomerProfile::where('phone', '01712345678')->count());

        // same person, other format: no second record
        $this->admin()->post('/admin/customers', ['phone' => '017-1234 5678', 'name' => 'Rahim Again'])
            ->assertRedirect(route('admin.customers.show', '01712345678'));
        $this->assertSame(1, CustomerProfile::where('phone', '01712345678')->count());

        // a number that already has orders is not duplicated either
        $this->order('01812345678');
        $this->admin()->post('/admin/customers', ['phone' => '01812345678', 'name' => 'Rahim'])->assertRedirect();
        $this->assertSame(0, CustomerProfile::where('phone', '01812345678')->count());
    }

    public function test_invalid_number_is_rejected(): void
    {
        $this->admin()->post('/admin/customers', ['phone' => '12345', 'name' => 'Rahim'])->assertSessionHas('error');
        $this->assertSame(0, CustomerProfile::where('name', 'Rahim')->count());
    }

    public function test_sync_command_is_insert_only_and_repeatable(): void
    {
        $this->order('01712345678', 'From Orders');
        Customer::create(['name' => 'Old One', 'phone' => '01912345678', 'total_orders' => 3]);
        CustomerProfile::create(['phone' => '01712345678', 'name' => 'Edited By Hand', 'source' => 'admin']);

        $ordersBefore = Order::count();

        $this->artisan('customers:sync-profiles')->assertExitCode(0);
        $this->artisan('customers:sync-profiles')->assertExitCode(0);

        $this->assertSame('Edited By Hand', CustomerProfile::where('phone', '01712345678')->value('name'));
        $this->assertSame(1, CustomerProfile::where('phone', '01912345678')->count());
        $this->assertSame($ordersBefore, Order::count());
    }
}
