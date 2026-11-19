<?php

namespace App\Services\Estimate;

use Brick\Math\BigDecimal;
use InvalidArgumentException;

/**
 * What the trader gave us, for one confirmed catalogue item.
 *
 * Numbers arrive as decimal strings and stay strings (never floats). The Form Request
 * gives the trader friendly messages; these checks are the engine's own guard, so a
 * malformed number can never reach the money path.
 */
final class EstimateRequest
{
    private const MONEY = '/^\d{1,13}(\.\d{1,2})?$/';

    private const QUANTITY = '/^\d{1,11}(\.\d{1,4})?$/';

    public function __construct(
        public readonly string $catalogueItemId,
        public readonly ?int $tradeRouteId,
        public readonly string $declaredValue,
        public readonly string $currency,
        public readonly ?string $freight = null,
        public readonly ?string $insurance = null,
        public readonly ?string $quantity = null,
        public readonly ?int $aiProductAnalysisId = null,
    ) {
        self::check('declaredValue', $declaredValue, self::MONEY);
        if (BigDecimal::of($declaredValue)->isZero()) {
            throw new InvalidArgumentException('declaredValue must be more than 0');
        }
        self::check('freight', $freight, self::MONEY);
        self::check('insurance', $insurance, self::MONEY);
        self::check('quantity', $quantity, self::QUANTITY);

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('currency must be a 3-letter upper-case ISO code');
        }
    }

    private static function check(string $field, ?string $value, string $pattern): void
    {
        if ($value !== null && ! preg_match($pattern, $value)) {
            throw new InvalidArgumentException("{$field} must be a non-negative decimal string, got \"{$value}\"");
        }
    }

    /** The request exactly as given, for calculations.inputs. */
    public function toArray(): array
    {
        return [
            'catalogue_item_id' => $this->catalogueItemId,
            'trade_route_id' => $this->tradeRouteId,
            'declared_value' => $this->declaredValue,
            'currency' => $this->currency,
            'freight' => $this->freight,
            'insurance' => $this->insurance,
            'quantity' => $this->quantity,
            'ai_product_analysis_id' => $this->aiProductAnalysisId,
        ];
    }
}
