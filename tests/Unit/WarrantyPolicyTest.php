<?php

namespace Tests\Unit;

use App\Support\WarrantyPolicy;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/** The warranty calculation has no database access, so it is tested on its own. */
class WarrantyPolicyTest extends TestCase
{
    public function test_policy_type_decides_what_may_be_promised(): void
    {
        $this->assertSame(['replacement'], WarrantyPolicy::allowedResolutions('replacement'));
        $this->assertSame(['repair'], WarrantyPolicy::allowedResolutions('repair'));
        $this->assertSame(['repair', 'replacement'], WarrantyPolicy::allowedResolutions('any'));
        $this->assertSame([], WarrantyPolicy::allowedResolutions('none'));
        $this->assertSame([], WarrantyPolicy::allowedResolutions(null));
    }

    public function test_the_last_day_is_delivery_date_plus_the_days(): void
    {
        $policy = ['type' => 'any', 'days' => 30];
        $start  = Carbon::parse('2026-01-01 15:00');

        $day0   = WarrantyPolicy::evaluate($policy, $start, Carbon::parse('2026-01-01 16:00'));
        $last   = WarrantyPolicy::evaluate($policy, $start, Carbon::parse('2026-01-31 23:00'));
        $late   = WarrantyPolicy::evaluate($policy, $start, Carbon::parse('2026-02-01 00:30'));

        $this->assertSame('in_warranty', $day0['state']);
        $this->assertSame(30, $day0['days_left']);
        $this->assertSame('in_warranty', $last['state']);
        $this->assertSame(0, $last['days_left']);
        $this->assertSame('expired', $late['state']);
        $this->assertSame('2026-01-31', $late['expires']->toDateString());
        $this->assertStringContainsString('ended on 31 Jan 2026', $late['message']);
    }

    public function test_no_policy_or_zero_days_is_not_covered(): void
    {
        $now = Carbon::parse('2026-01-10');

        $this->assertSame('not_covered', WarrantyPolicy::evaluate(['type' => 'none', 'days' => 0], Carbon::parse('2026-01-01'), $now)['state']);
        $this->assertSame('not_covered', WarrantyPolicy::evaluate(['type' => 'repair', 'days' => 0], Carbon::parse('2026-01-01'), $now)['state']);
    }

    public function test_unknown_delivery_date_asks_for_it_instead_of_guessing(): void
    {
        $r = WarrantyPolicy::evaluate(['type' => 'repair', 'days' => 90], null, Carbon::parse('2026-01-10'));

        $this->assertSame('unknown', $r['state']);
        $this->assertNull($r['expires']);
    }

    public function test_type_labels(): void
    {
        $this->assertSame('No warranty', WarrantyPolicy::typeLabel('none'));
        $this->assertSame('No warranty', WarrantyPolicy::typeLabel(null));
        $this->assertSame('Replacement warranty', WarrantyPolicy::typeLabel('replacement'));
    }
}
