<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ModelsUsageRequest extends FormRequest
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
                'in:day,week,month,year',
            ],
            'start_date' => [
                'nullable',
                'date',
                'required_with:end_date',
            ],
            'end_date' => [
                'nullable',
                'date',
                'required_with:start_date',
                'after_or_equal:start_date',
            ],
        ];
    }
}

