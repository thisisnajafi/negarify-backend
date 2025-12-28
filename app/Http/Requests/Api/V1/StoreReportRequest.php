<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'in:spam,inappropriate,copyright,harassment,other',
            ],
            'description' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }
}

