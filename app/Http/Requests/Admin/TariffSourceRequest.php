<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TariffSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'document_url' => ['nullable', 'url', 'max:500'],
            'edition' => ['nullable', 'string', 'max:100'],
            'published_on' => ['nullable', 'date_format:Y-m-d'],
            'retrieved_on' => ['nullable', 'date_format:Y-m-d'],
            'verified_by' => ['nullable', 'string', 'max:255'],
        ];
    }
}
