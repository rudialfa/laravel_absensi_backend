<?php

namespace App\Http\Requests\Api\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class SelectPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_slug'     => 'required|string|exists:subscription_plans,slug',
            'bank'          => 'required|string|in:bca,mandiri',
            'discount_code' => 'nullable|string',
        ];
    }
}
