<?php

namespace Tests\Unit;

use App\Models\CustomerAddress;
use PHPUnit\Framework\TestCase;

class CustomerAddressTest extends TestCase
{
    public function test_full_line_order_matches_orders_address(): void
    {
        $a = new CustomerAddress(['address' => 'House 5, Road 2', 'city' => 'Dhaka', 'upazila' => 'Banani', 'district' => 'Dhaka']);

        $this->assertSame('House 5, Road 2, Dhaka, Banani, Dhaka', $a->fullLine());
    }

    public function test_same_place_typed_differently_is_a_duplicate(): void
    {
        $one = ['phone' => '01712345678', 'district' => 'Dhaka', 'upazila' => 'Banani', 'city' => 'Dhaka', 'address' => 'House 5,  Road 2'];
        $two = ['phone' => '01712345678', 'district' => 'Dhaka', 'upazila' => 'Banani', 'city' => 'DHAKA', 'address' => ' house 5, road 2 '];
        $other = $one;
        $other['address'] = 'House 6, Road 2';

        $this->assertSame(CustomerAddress::duplicateKey($one), CustomerAddress::duplicateKey($two));
        $this->assertNotSame(CustomerAddress::duplicateKey($one), CustomerAddress::duplicateKey($other));
    }
}
