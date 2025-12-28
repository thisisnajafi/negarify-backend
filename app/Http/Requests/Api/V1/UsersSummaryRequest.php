<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UsersSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by admin middleware
    }

    public function rules(): array
    {
        return [
            'range' => [
                'nullable',
                'string',
                'in:day,week,month',
            ],
        ];
    }
}

