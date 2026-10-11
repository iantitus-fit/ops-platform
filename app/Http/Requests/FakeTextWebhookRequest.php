<?php

namespace App\Http\Requests;

use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;

class FakeTextWebhookRequest extends FormRequest
{
    /**
     * The webhook is public; the route's middleware decides whether it is on at all.
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
            'MessageSid' => ['required', 'string', 'max:64'],
            'From' => ['required', 'string', 'max:32'],
            'To' => ['nullable', 'string', 'max:32'],
            'Body' => ['nullable', 'string', 'max:5000'],
            'NumMedia' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Checks that run after the basic rules: the sender must be a phone number we can read.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('From')) {
                    return;
                }

                if (PhoneNumber::toE164($this->input('From')) === null) {
                    $validator->errors()->add('From', 'From is not a phone number we can read.');
                }
            },
        ];
    }

    /**
     * Answer a rejected text with a 422 and the errors as JSON. Twilio does not ask for
     * JSON, and the default would redirect it to a page that does not exist.
     */
    protected function failedValidation(ValidatorContract $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422));
    }
}
