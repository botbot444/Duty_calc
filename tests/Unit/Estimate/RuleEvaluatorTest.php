<?php

namespace Tests\Unit\Estimate;

use App\Models\TariffRule;
use App\Services\Estimate\RuleEvaluator;
use Brick\Math\BigDecimal;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * The evaluator returns the exact, unrounded amount. Rounding happens once, in EstimateService.
 */
class RuleEvaluatorTest extends TestCase
{
    private function rule(array $attributes): TariffRule
    {
        return new TariffRule($attributes + ['tax_type' => 'customs_duty', 'sequence' => 1, 'base_components' => ['customs_value']]);
    }

    private function evaluate(array $rule, string $base, ?string $quantity = null): string
    {
        return (string) (new RuleEvaluator)->evaluate(
            $this->rule($rule),
            BigDecimal::of($base),
            $quantity === null ? null : BigDecimal::of($quantity),
        )->strippedOfTrailingZeros();
    }

    public function test_ad_valorem_is_rate_times_base(): void
    {
        $this->assertSame('2650', $this->evaluate(['method' => 'ad_valorem', 'rate' => '0.25'], '10600.00'));
    }

    public function test_ad_valorem_is_not_rounded_here(): void
    {
        // 25% of 100.02 = 25.005 exactly; the service rounds it.
        $this->assertSame('25.005', $this->evaluate(['method' => 'ad_valorem', 'rate' => '0.25'], '100.02'));
    }

    public function test_specific_is_amount_times_quantity_and_ignores_the_base(): void
    {
        $this->assertSame('1500', $this->evaluate(
            ['method' => 'specific', 'specific_amount' => '1.50', 'specific_currency' => 'ZMW'], '999999.99', '1000'
        ));
    }

    public function test_compound_max_takes_the_ad_valorem_side_when_it_is_higher(): void
    {
        $this->assertSame('4000', $this->evaluate(
            ['method' => 'compound', 'rate' => '0.40', 'specific_amount' => '5', 'specific_currency' => 'ZMW', 'combine' => 'max'],
            '10000.00', '500'
        ));
    }

    public function test_compound_max_takes_the_specific_side_when_it_is_higher(): void
    {
        $this->assertSame('2500', $this->evaluate(
            ['method' => 'compound', 'rate' => '0.40', 'specific_amount' => '5', 'specific_currency' => 'ZMW', 'combine' => 'max'],
            '2000.00', '500'
        ));
    }

    public function test_compound_sum_adds_both_sides(): void
    {
        $this->assertSame('512.5', $this->evaluate(
            ['method' => 'compound', 'rate' => '0.05', 'specific_amount' => '0.50', 'specific_currency' => 'ZMW', 'combine' => 'sum'],
            '6250.00', '400'
        ));
    }

    public function test_a_rule_that_needs_quantity_refuses_to_run_without_it(): void
    {
        $this->expectException(LogicException::class);

        $this->evaluate(['method' => 'specific', 'specific_amount' => '1.50', 'specific_currency' => 'ZMW'], '100');
    }

    public function test_a_compound_rule_without_combine_refuses_to_guess(): void
    {
        $this->expectException(LogicException::class);

        $this->evaluate(['method' => 'compound', 'rate' => '0.40', 'specific_amount' => '5', 'specific_currency' => 'ZMW'], '100', '1');
    }

    public function test_an_ad_valorem_rule_without_a_rate_refuses_to_default(): void
    {
        $this->expectException(LogicException::class);

        $this->evaluate(['method' => 'ad_valorem'], '100');
    }
}
