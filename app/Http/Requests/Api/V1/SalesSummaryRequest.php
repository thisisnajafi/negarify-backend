<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SalesSummaryRequest extends FormRequest
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

    public function messages(): array
    {
        return [
            'range.in' => 'Range must be one of: day, week, month, year',
            'end_date.after_or_equal' => 'End date must be after or equal to start date',
        ];
    }
}



