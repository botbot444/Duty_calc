<?php

namespace Tests\Unit;

use App\Support\Display;
use Tests\TestCase;

class DisplayTest extends TestCase
{
    public function test_money_adds_thousands_separators_and_the_currency_sign(): void
    {
        $this->assertSame('K10,000.00', Display::money('10000'));
        $this->assertSame('K1,234,567.50', Display::money('1234567.5'));
        $this->assertSame('K0.00', Display::money('0.00'));
        $this->assertSame('K100.00', Display::money('100.00'));
        $this->assertSame('US$18.87', Display::money('18.87', 'USD'));
        $this->assertSame('–', Display::money(null));
    }

    public function test_rates_and_codes(): void
    {
        $this->assertSame('5%', Display::percent('0.050000'));
        $this->assertSame('16.5%', Display::percent('0.165000'));
        $this->assertSame('0%', Display::percent('0.000000'));
        $this->assertSame('26.50', Display::rate('26.50000000'));
        $this->assertSame('0.0105', Display::rate('0.01050000'));
        $this->assertSame('1701.99.00', Display::hs('17019900'));
    }

    public function test_rule_text_for_each_method(): void
    {
        $base = ['rate' => null, 'specific_amount' => null, 'specific_currency' => null, 'combine' => null];

        $this->assertSame('10%', Display::rule(['method' => 'ad_valorem', 'rate' => '0.100000'] + $base, 'l'));
        $this->assertSame('K0.20 per kg', Display::rule(['method' => 'specific', 'specific_amount' => '0.2000', 'specific_currency' => 'ZMW'] + $base, 'kg'));
        $this->assertSame('5% or K0.10 per kg, whichever is higher', Display::rule(['method' => 'compound', 'rate' => '0.050000',
            'specific_amount' => '0.1000', 'specific_currency' => 'ZMW', 'combine' => 'max'], 'kg'));
        $this->assertSame('2% plus K0.05 per kg', Display::rule(['method' => 'compound', 'rate' => '0.020000',
            'specific_amount' => '0.0500', 'specific_currency' => 'ZMW', 'combine' => 'sum'], 'kg'));
    }
}
