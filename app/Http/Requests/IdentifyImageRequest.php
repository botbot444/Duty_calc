<?php

namespace App\Http\Requests;

use App\Enums\Language;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Photo upload. The type is checked from the file's content (finfo), never its name or the
 * browser's Content-Type, and the image must actually be readable as an image, so a
 * renamed .exe or a text file called photo.jpg is refused.
 */
class IdentifyImageRequest extends FormRequest
{
    public const MAX_KB = 5120;   // 5 MB

    public const MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo' => [
                'required', 'file', 'max:'.self::MAX_KB, 'mimetypes:'.implode(',', self::MIME_TYPES),
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! $value instanceof UploadedFile || ! $value->isValid() || @getimagesize($value->getRealPath()) === false) {
                        $fail(__('The :attribute must be a photo (JPEG, PNG or WebP).'));
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:300'],
            'lang' => ['nullable', Rule::enum(Language::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required' => __('Enter the :attribute.'),
            'photo.file' => __('The :attribute must be a photo (JPEG, PNG or WebP).'),
            'photo.uploaded' => __('The :attribute must not be larger than :size MB.', ['size' => self::MAX_KB / 1024]),
            'photo.max' => __('The :attribute must not be larger than :size MB.', ['size' => self::MAX_KB / 1024]),
            'photo.mimetypes' => __('The :attribute must be a photo (JPEG, PNG or WebP).'),
        ];
    }

    public function attributes(): array
    {
        return ['photo' => __('photo')];
    }

    public function language(): Language
    {
        return Language::tryFrom((string) $this->validated('lang')) ?? Language::English;
    }
}
