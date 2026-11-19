<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** POST creates an item (id, hs_code, display_name); PATCH renames it or turns it on/off. */
class CatalogueItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->isMethod('post')) {
            return [
                'id' => ['required', 'string', 'max:20'],
                'hs_code' => ['required', 'string'],
                'display_name' => ['required', 'string', 'max:255'],
                'active' => ['sometimes', 'boolean'],
            ];
        }

        return [
            'display_name' => ['sometimes', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
