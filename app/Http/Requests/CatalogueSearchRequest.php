<?php

namespace App\Http\Requests;

use App\Enums\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogueSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // public: traders have no accounts
    }

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'max:100'],
            'lang' => ['nullable', Rule::enum(Language::class)],
        ];
    }

    public function language(): ?Language
    {
        return Language::tryFrom((string) $this->validated('lang'));
    }
}
