<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class GenerateVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'model_id' => ['required', 'integer', 'exists:models,id'],
            'prompt' => ['required', 'string', 'min:1', 'max:2000'],
            'negative_prompt' => ['nullable', 'string', 'max:2000'],
            'duration' => ['nullable', 'integer', 'min:1', 'max:60'],
            'resolution' => ['nullable', 'string', 'regex:/^\d+x\d+$/'],
            'fps' => ['nullable', 'integer', 'min:1', 'max:60'],
            'seed' => ['nullable', 'integer', 'min:0'],
        ];
    }
}

