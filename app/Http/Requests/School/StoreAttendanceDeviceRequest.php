<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'class_id' => [
                'nullable',
                Rule::exists('class_rooms', 'id')->where('company_id', $this->user()->company_id),
            ],
            'name'              => 'required|string|max:100',
            'device_identifier' => 'nullable|string|max:100',
        ];
    }
}
