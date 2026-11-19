<?php

namespace App\Http\Requests;

use App\Models\CatalogueItem;
use App\Services\Identification\ProductIdentificationService;
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
            // Set when the suggestion came from /api/identify/*; must be an analysis from this session.
            'ai_product_analysis_id' => [
                'nullable', 'integer',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! app(ProductIdentificationService::class)->belongsToSession((int) $value)) {
                        $fail(__('Please choose and confirm a product first.'));
                    }
                },
            ],
        ];
    }

    public function analysisId(): ?int
    {
        $id = $this->validated('ai_product_analysis_id');

        return $id === null ? null : (int) $id;
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
