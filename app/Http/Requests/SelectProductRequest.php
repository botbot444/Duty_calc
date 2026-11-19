<?php

namespace App\Http\Requests;

use App\Models\CatalogueItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SelectProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'catalogue_item_id' => [
                'required', 'string', 'max:20',
                Rule::exists('catalogue_items', 'id')->where('active', true),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'catalogue_item_id.required' => __('Enter the :attribute.'),
            'catalogue_item_id.exists' => __('Choose a :attribute from the list.'),
        ];
    }

    public function attributes(): array
    {
        return ['catalogue_item_id' => __('product')];
    }

    public function item(): CatalogueItem
    {
        return CatalogueItem::with('tariffLine')->findOrFail($this->validated('catalogue_item_id'));
    }
}
