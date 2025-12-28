<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class BulkCurationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by admin middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'post_ids' => [
                'required',
                'array',
                'min:1',
                'max:100', // Limit bulk operations
            ],
            'post_ids.*' => [
                'required',
                'integer',
                'exists:gallery_posts,id',
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
            'post_ids.required' => 'Post IDs are required.',
            'post_ids.array' => 'Post IDs must be an array.',
            'post_ids.min' => 'At least one post ID is required.',
            'post_ids.max' => 'Maximum 100 posts can be processed at once.',
            'post_ids.*.exists' => 'One or more post IDs are invalid.',
        ];
    }
}

