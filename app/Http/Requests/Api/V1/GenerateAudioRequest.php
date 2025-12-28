<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class GenerateAudioRequest extends FormRequest
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
            'duration' => ['nullable', 'integer', 'min:1', 'max:300'],
            'format' => ['nullable', 'string', 'in:mp3,wav,flac'],
            'sample_rate' => ['nullable', 'integer', 'in:22050,44100,48000'],
            'seed' => ['nullable', 'integer', 'min:0'],
        ];
    }
}

