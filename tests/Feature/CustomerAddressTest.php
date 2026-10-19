<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CustomerAddress;
use App\Models\CustomerProfile;
use App\Models\Order;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Runs only on a TEST database (name ends with _test) and rolls every test back. See CustomerSearchTest. */
class CustomerAddressTest extends TestCase
{
    use DatabaseTransactions;

    private const A = '01712345678';
    private const B = '01812345678';

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

    private function order(string $phone): Order
    {
        return Order::create([
            'name' => 'Rahim', 'phone' => $phone, 'district' => 'Dhaka', 'upazila' => 'Banani', 'city' => 'Dhaka',
            'address' => 'Old House, Dhaka, Banani, Dhaka', 'subtotal' => 500, 'total' => 500, 'status' => 'pending',
        ]);
    }

    private function payload(array $over = []): array
    {
        return $over + [
            'recipient_name' => 'Rahim', 'phone' => self::A, 'district' => 'Dhaka', 'upazila' => 'Banani',
            'city' => 'Dhaka', 'address' => 'House 5, Road 2', 'landmark' => 'Near mosque',
        ];
    }

    private function save(string $phone, array $over = [])
    {
        return $this->admin()->post("/admin/customers/{$phone}/addresses", $this->payload($over));
    }

    public function test_first_address_becomes_default_and_is_audited(): void
    {
        $this->order(self::A);
        $this->save(self::A)->assertRedirect(route('admin.customers.show', self::A));

        $address = CustomerAddress::firstOrFail();
        $this->assertTrue($address->is_default);
        $this->assertSame(1, AuditLog::where('action', 'address.created')->where('customer_phone', self::A)->count());
    }

    public function test_only_one_default_at_a_time(): void
    {
        $this->order(self::A);
        $this->save(self::A);
        $this->save(self::A, ['address' => 'House 9', 'is_default' => '1']);

        $this->assertSame(2, CustomerAddress::count());
        $this->assertSame(1, CustomerAddress::where('is_default', true)->count());
        $this->assertSame('House 9', CustomerAddress::where('is_default', true)->value('address'));

        $first = CustomerAddress::where('address', 'House 5, Road 2')->firstOrFail();
        $this->admin()->post("/admin/customers/" . self::A . "/addresses/{$first->id}/default")->assertSessionHas('success');
        $this->assertSame(1, CustomerAddress::where('is_default', true)->count());
        $this->assertTrue($first->fresh()->is_default);
    }

    public function test_duplicate_and_invalid_addresses_are_refused(): void
    {
        $this->order(self::A);
        $this->save(self::A);
        $this->save(self::A, ['address' => '  house 5,   road 2 '])->assertSessionHas('error');
        $this->save(self::A, ['upazila' => 'Not A Real Upazila'])->assertSessionHasErrors('upazila');
        $this->save(self::A, ['district' => 'Atlantis'])->assertSessionHasErrors('district');
        $this->save(self::A, ['phone' => '123'])->assertSessionHasErrors('phone');

        $this->assertSame(1, CustomerAddress::count());
    }

    public function test_one_customer_cannot_touch_another_customers_address(): void
    {
        $this->order(self::A);
        $this->order(self::B);
        $this->save(self::B, ['phone' => self::B]);
        $theirs = CustomerAddress::firstOrFail();

        // customer A's URL with customer B's address id
        $this->admin()->get('/admin/customers/' . self::A . "/addresses/{$theirs->id}/edit")->assertNotFound();
        $this->admin()->put('/admin/customers/' . self::A . "/addresses/{$theirs->id}", $this->payload(['address' => 'Hacked']))->assertNotFound();
        $this->admin()->post('/admin/customers/' . self::A . "/addresses/{$theirs->id}/default")->assertNotFound();
        $this->admin()->delete('/admin/customers/' . self::A . "/addresses/{$theirs->id}")->assertNotFound();

        $this->assertSame('House 5, Road 2', $theirs->fresh()->address);
    }

    public function test_unknown_customer_gets_404_and_no_profile(): void
    {
        $this->save('01999999999', ['phone' => '01999999999'])->assertNotFound();
        $this->assertSame(0, CustomerProfile::where('phone', '01999999999')->count());
    }

    public function test_editing_or_deleting_a_saved_address_never_changes_an_order(): void
    {
        $order = $this->order(self::A);
        $before = $order->only(['district', 'upazila', 'city', 'address']);

        $this->save(self::A);
        $address = CustomerAddress::firstOrFail();

        $this->admin()->put('/admin/customers/' . self::A . "/addresses/{$address->id}", $this->payload(['address' => 'Completely New Place']))
            ->assertRedirect();
        $this->assertSame('Completely New Place', $address->fresh()->address);
        $this->assertSame(1, AuditLog::where('action', 'address.updated')->count());

        $this->admin()->delete('/admin/customers/' . self::A . "/addresses/{$address->id}")->assertRedirect();
        $this->assertSame(0, CustomerAddress::count());

        $this->assertSame($before, $order->fresh()->only(['district', 'upazila', 'city', 'address']));
    }

    public function test_deleting_the_default_promotes_another_address(): void
    {
        $this->order(self::A);
        $this->save(self::A);
        $this->save(self::A, ['address' => 'House 9']);
        $default = CustomerAddress::where('is_default', true)->firstOrFail();

        $this->admin()->delete('/admin/customers/' . self::A . "/addresses/{$default->id}")->assertRedirect();

        $this->assertSame(1, CustomerAddress::count());
        $this->assertTrue(CustomerAddress::firstOrFail()->is_default);
    }

    public function test_limit_of_ten_addresses(): void
    {
        $this->order(self::A);
        for ($i = 1; $i <= CustomerAddress::MAX_PER_CUSTOMER; $i++) {
            $this->save(self::A, ['address' => "House {$i}"]);
        }
        $this->save(self::A, ['address' => 'One too many'])->assertSessionHas('error');

        $this->assertSame(CustomerAddress::MAX_PER_CUSTOMER, CustomerAddress::count());
    }

    public function test_address_pages_need_admin_login(): void
    {
        $this->get('/admin/customers/' . self::A . '/addresses/create')->assertRedirect(route('admin.login'));
        $this->post('/admin/customers/' . self::A . '/addresses', $this->payload())->assertRedirect(route('admin.login'));
    }
}
