<?php

namespace App\Icm;

use App\Enums\IcmFileKind;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator as LaravelValidator;
use Illuminate\Validation\Rule;

/**
 * Checks every typed file of a loaded folder and describes each problem in plain words.
 */
class Validator
{
    /**
     * Every problem in the folder. A folder with no problems may go live.
     */
    public function validate(LoadedFolder $folder): ValidationReport
    {
        $errors = [];

        foreach ($folder->files as $file) {
            if ($file->kind !== IcmFileKind::Typed) {
                continue;
            }

            $rules = $this->rulesFor((string) $file->type);

            if ($rules === []) {
                continue;
            }

            $validator = LaravelValidator::make($file->frontMatter, $rules);

            if ($validator->passes()) {
                continue;
            }

            foreach ($validator->failed() as $field => $failures) {
                foreach ($failures as $rule => $parameters) {
                    $errors[] = [
                        'file' => $file->path,
                        'field' => $field,
                        'message' => PlainWords::render($rule, $parameters, $field, Arr::get($file->frontMatter, $field)),
                    ];
                }
            }
        }

        return new ValidationReport($errors);
    }

    /**
     * The rules for one file type. `bail` keeps a single problem from being reported twice.
     *
     * @return array<string, mixed>
     */
    private function rulesFor(string $type): array
    {
        return match ($type) {
            'service' => [
                'name' => 'bail|required|string',
                'slug' => 'bail|required|string',
                'duration_minutes' => 'bail|required|integer:strict|min:1',
                'book_online' => 'bail|required|boolean:strict',
                'price_policy' => ['bail', 'required', Rule::in(['none', 'starting_at', 'fixed'])],
                'price_cents' => 'bail|required_unless:price_policy,none|integer:strict|min:0',
                'intake' => 'bail|required|array|min:1',
                'intake.*.key' => 'bail|required|string',
                'intake.*.ask' => 'bail|required|string',
                'intake.*.options' => 'bail|sometimes|array',
            ],
            default => [],
        };
    }
}
