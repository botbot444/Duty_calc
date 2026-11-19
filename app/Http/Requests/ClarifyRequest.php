<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClarifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'analysis_id' => ['required', 'integer', 'min:1'],
            'answers' => ['required', 'array', 'min:1', 'max:3'],
            'answers.*.question' => ['required', 'string', 'max:300'],
            'answers.*.answer' => ['required', 'string', 'max:300'],
        ];
    }

    /** @return list<array{question: string, answer: string}> */
    public function answers(): array
    {
        return array_map(
            fn (array $a) => ['question' => $a['question'], 'answer' => $a['answer']],
            array_values($this->validated('answers'))
        );
    }
}
