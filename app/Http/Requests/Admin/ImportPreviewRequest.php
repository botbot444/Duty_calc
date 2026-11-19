<?php

namespace App\Http\Requests\Admin;

use App\Services\TariffData\TariffCsvImporter;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * One or more reference-data CSVs, each named as in database/data/sample/README.md
 * (tariff_rules.csv, exchange_rates.csv, ...).
 */
class ImportPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $known = array_keys(TariffCsvImporter::FILES);

        return [
            'files' => ['required', 'array', 'min:1', 'max:'.count($known)],
            'files.*' => [
                'file', 'max:2048', 'mimetypes:text/csv,text/plain,application/csv',
                function (string $attribute, mixed $file, Closure $fail) use ($known) {
                    if ($file instanceof UploadedFile && ! in_array($file->getClientOriginalName(), $known, true)) {
                        $fail('Unknown file "'.$file->getClientOriginalName().'". Use one of: '.implode(', ', $known).'.');
                    }
                },
            ],
        ];
    }
}
