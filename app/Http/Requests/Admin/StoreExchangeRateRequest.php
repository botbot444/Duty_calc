<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currency' => ['required', 'regex:/^[A-Z]{3}$/', 'not_in:ZMW'],
            'rate_to_zmw' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,8})?$/'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'source' => ['required', 'string', 'max:255'],
        ];
    }
}
