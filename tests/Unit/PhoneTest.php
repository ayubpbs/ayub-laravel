<?php

namespace Tests\Unit;

use App\Support\Phone;
use PHPUnit\Framework\TestCase;

class PhoneTest extends TestCase
{
    /** @dataProvider validNumbers */
    public function test_valid_formats_become_one_canonical_number(string $input): void
    {
        $this->assertSame('01712345678', Phone::normalize($input));
    }

    public function validNumbers(): array
    {
        return [
            'plain'            => ['01712345678'],
            'spaces and dash'  => [' 01712-345 678 '],
            'plus 880'         => ['+8801712345678'],
            '880'              => ['8801712345678'],
            '00880'            => ['008801712345678'],
            '+880 with zero'   => ['+880 01712345678'],
            'no leading zero'  => ['1712345678'],
            'bangla digits'    => ['০১৭১২৩৪৫৬৭৮'],
            'bangla +880'      => ['+৮৮০১৭১২৩৪৫৬৭৮'],
            'brackets'         => ['(017) 1234-5678'],
        ];
    }

    /** @dataProvider invalidNumbers */
    public function test_invalid_numbers_return_null(?string $input): void
    {
        $this->assertNull(Phone::normalize($input));
    }

    public function invalidNumbers(): array
    {
        return [['0171234567'], ['017123456789'], ['01212345678'], ['abc'], [''], [null], ['+8801212345678'], ['0221234567']];
    }

    public function test_fragment_ignores_country_code_and_short_input(): void
    {
        $this->assertSame('1712', Phone::fragment('+8801712'));
        $this->assertSame('01712', Phone::fragment('০১৭১২'));
        $this->assertSame('', Phone::fragment('12'));
        $this->assertSame('', Phone::fragment('#123'));
    }

    public function test_variants_cover_old_stored_formats(): void
    {
        $v = Phone::variants('01712345678');
        $this->assertContains('01712345678', $v);
        $this->assertContains('8801712345678', $v);
        $this->assertContains('+8801712345678', $v);
        $this->assertContains('1712345678', $v);
    }

    public function test_international_format(): void
    {
        $this->assertSame('+8801712345678', Phone::international('01712345678'));
        $this->assertNull(Phone::international('nope'));
    }
}
