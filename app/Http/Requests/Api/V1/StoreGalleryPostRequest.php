<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreGalleryPostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'generation_job_id' => [
                'required',
                'integer',
                'exists:generation_jobs,id',
            ],
            'title' => [
                'nullable',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'tags' => [
                'nullable',
                'array',
                'max:10',
            ],
            'tags.*' => [
                'required',
                'string',
                'max:50',
            ],
            'visibility' => [
                'nullable',
                'string',
                'in:public,private',
            ],
            'prompt_visible' => [
                'nullable',
                'boolean',
            ],
            'model_visible' => [
                'nullable',
                'boolean',
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
            'generation_job_id.required' => 'Generation job ID is required.',
            'generation_job_id.exists' => 'Generation job not found.',
            'tags.max' => 'Maximum 10 tags allowed.',
            'tags.*.max' => 'Each tag must not exceed 50 characters.',
            'visibility.in' => 'Visibility must be either public or private.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize tags: trim, remove duplicates, lowercase (optional)
        if ($this->has('tags') && is_array($this->tags)) {
            $tags = array_map('trim', $this->tags);
            $tags = array_filter($tags); // Remove empty tags
            $tags = array_unique($tags); // Remove duplicates
            $tags = array_values($tags); // Re-index array
            $this->merge(['tags' => $tags]);
        }
    }
}

