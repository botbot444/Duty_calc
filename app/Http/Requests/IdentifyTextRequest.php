<?php

namespace App\Http\Requests;

use App\Enums\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IdentifyTextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:300'],
            'lang' => ['nullable', Rule::enum(Language::class)],
        ];
    }

    public function messages(): array
    {
        return ['description.required' => __('Type a word to search.')];
    }

    public function language(): Language
    {
        return Language::tryFrom((string) $this->validated('lang')) ?? Language::English;
    }
}
