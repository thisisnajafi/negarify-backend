<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CostProfitSummaryRequest extends FormRequest
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
                'in:day,week,month,year,all',
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
            'range.in' => 'Range must be one of: day, week, month, year, all',
            'start_date.required_with' => 'Start date is required when end date is provided',
            'end_date.required_with' => 'End date is required when start date is provided',
            'end_date.after_or_equal' => 'End date must be after or equal to start date',
        ];
    }
}
