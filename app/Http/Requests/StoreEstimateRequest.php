<?php

namespace App\Http\Requests;

use App\Services\Catalogue\ProductSelection;
use App\Services\Estimate\EstimateRequest;
use App\Services\Estimate\SupportedCurrencies;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Server-side checks for POST /api/estimates. Numbers must be plain decimal strings
 * ("10000.50", not "10,000.50" or "1e4"), so nothing reaches the engine as a float.
 *
 * The item must be the one the trader confirmed in this session (POST /api/selection).
 * An unknown or unsupported trade route is not rejected here: the engine answers
 * `unsupported` and the request is saved, so the trader sees why.
 */
class StoreEstimateRequest extends FormRequest
{
    private const MONEY = 'regex:/^\d{1,13}(\.\d{1,2})?$/';

    private const QUANTITY = 'regex:/^\d{1,11}(\.\d{1,4})?$/';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'catalogue_item_id' => ['required', 'string', 'max:20'],
            'trade_route_id' => ['required', 'integer', 'min:1'],
            'declared_value' => ['required', 'string', self::MONEY, 'numeric', 'gt:0'],
            'currency' => ['required', 'string', Rule::in(app(SupportedCurrencies::class)->codes())],
            'freight' => ['nullable', 'string', self::MONEY],
            'insurance' => ['nullable', 'string', self::MONEY],
            'quantity' => ['nullable', 'string', self::QUANTITY, 'numeric', 'gt:0'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $id = $this->input('catalogue_item_id');
                if (is_string($id) && ! app(ProductSelection::class)->isConfirmed($id)) {
                    $validator->errors()->add('catalogue_item_id', __('Please choose and confirm a product first.'));
                }
            },
        ];
    }

    public function messages(): array
    {
        $decimals = fn (int $count) => __('The :attribute must be a number with at most :count decimal places.', ['count' => $count]);

        return [
            'required' => __('Enter the :attribute.'),
            'declared_value.regex' => $decimals(2),
            'freight.regex' => $decimals(2),
            'insurance.regex' => $decimals(2),
            'quantity.regex' => $decimals(4),
            'numeric' => __('The :attribute must be a number.'),
            'gt' => __('The :attribute must be more than :value.'),
            'currency.in' => __('Choose a :attribute from the list.'),
            'trade_route_id.*' => __('Choose a :attribute from the list.'),
        ];
    }

    public function attributes(): array
    {
        return [
            'catalogue_item_id' => __('product'),
            'trade_route_id' => __('border post'),
            'declared_value' => __('value of the goods'),
            'currency' => __('currency'),
            'freight' => __('freight'),
            'insurance' => __('insurance'),
            'quantity' => __('quantity'),
        ];
    }

    public function toEstimateRequest(): EstimateRequest
    {
        $v = $this->validated();

        return new EstimateRequest(
            catalogueItemId: $v['catalogue_item_id'],
            tradeRouteId: (int) $v['trade_route_id'],
            declaredValue: $v['declared_value'],
            currency: $v['currency'],
            freight: $v['freight'] ?? null,
            insurance: $v['insurance'] ?? null,
            quantity: $v['quantity'] ?? null,
            aiProductAnalysisId: app(ProductSelection::class)->aiProductAnalysisId(),
        );
    }
}
