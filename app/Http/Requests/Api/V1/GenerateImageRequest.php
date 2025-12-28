<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class GenerateImageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'model_id' => [
                'required',
                'integer',
                'exists:models,id',
            ],
            'prompt' => [
                'required',
                'string',
                'min:1',
                'max:2000',
            ],
            'negative_prompt' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'size' => [
                'nullable',
                'string',
                'regex:/^\d+x\d+$/',
            ],
            'style' => [
                'nullable',
                'string',
                'max:100',
            ],
            'seed' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'steps' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
            'guidance_scale' => [
                'nullable',
                'numeric',
                'min:0',
                'max:20',
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
            'model_id.required' => 'Model ID is required.',
            'model_id.exists' => 'Selected model does not exist or is not available.',
            'prompt.required' => 'Prompt is required.',
            'prompt.max' => 'Prompt must not exceed 2000 characters.',
            'size.regex' => 'Size must be in format WIDTHxHEIGHT (e.g., 512x512).',
        ];
    }
}

