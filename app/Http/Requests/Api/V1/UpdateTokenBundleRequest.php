<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTokenBundleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization handled by admin middleware
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $bundleId = $this->route('id');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('token_bundles', 'name')->ignore($bundleId),
            ],
            'token_amount' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
            ],
            'price_usd' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                'decimal:0,2',
            ],
            'bonus_tokens' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
            'display_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A bundle with this name already exists.',
            'token_amount.min' => 'Token amount must be at least 1.',
            'price_usd.min' => 'Price must be non-negative.',
        ];
    }
}

