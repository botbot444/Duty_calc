<?php

namespace App\Http\Requests\Admin;

use App\Enums\CombineMode;
use App\Enums\RuleMethod;
use App\Enums\TaxType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Add a rate" on a tariff line with no rule of that charge for the period. Field-level checks
 * here; the full rule checks (base components, overlaps, line validity) run in TariffDataValidator.
 */
class AddRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // the route is behind `auth`; every user is an admin
    }

    public function rules(): array
    {
        return [
            'tax_type' => ['required', Rule::enum(TaxType::class)],
            'method' => ['required', Rule::enum(RuleMethod::class)],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'rate' => ['nullable', 'string', 'regex:/^\d{1,3}(\.\d{1,6})?$/'],
            'specific_amount' => ['nullable', 'string', 'regex:/^\d{1,11}(\.\d{1,4})?$/'],
            'specific_currency' => ['nullable', 'regex:/^[A-Z]{3}$/'],
            'combine' => ['nullable', Rule::enum(CombineMode::class)],
            'sequence' => ['sometimes', 'integer', 'min:1', 'max:255'],
            'base_components' => ['required', 'array', 'min:1'],
            'base_components.*' => ['string', 'max:30'],
            'tariff_source_id' => ['required', 'integer', 'exists:tariff_sources,id'],
            'source_page' => ['required', 'integer', 'min:1', 'max:65535'],
        ];
    }
}
