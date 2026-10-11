<?php

namespace App\Http\Requests;

use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;

class StoreLeadRequest extends FormRequest
{
    /**
     * The lead form is public: any website or directory may post to it.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:254'],
            'message' => ['nullable', 'string', 'max:5000'],
            'source' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * Checks that run after the basic rules: a lead we cannot reply to is refused. A phone we
     * cannot read is fine when there is an email to reach the person by.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['phone', 'email'])) {
                    return;
                }

                $phone = trim((string) $this->input('phone'));
                $email = trim((string) $this->input('email'));

                if ($email !== '' || PhoneNumber::toE164($phone) !== null) {
                    return;
                }

                $validator->errors()->add('phone', $phone === ''
                    ? 'Give a phone number or an email address so we can reply.'
                    : 'We could not read that phone number. Check it, or give an email address.');
            },
        ];
    }

    /**
     * Answer a rejected lead with a 422 and the errors as JSON. A website form does not ask
     * for JSON, and the default would redirect it to a page that does not exist.
     */
    protected function failedValidation(ValidatorContract $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422));
    }
}
