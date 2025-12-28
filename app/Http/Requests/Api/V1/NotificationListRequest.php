<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class NotificationListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    public function rules(): array
    {
        return [
            'read' => [
                'nullable',
                'boolean',
            ],
            'type' => [
                'nullable',
                'string',
                'in:generation_completed,post_liked,comment_added,post_curated,report_resolved',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:50',
            ],
        ];
    }
}

