<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $student = $this->route('student');

        return [
            'class_id'      => [
                'nullable',
                Rule::exists('class_rooms', 'id')->where('company_id', $this->user()->company_id),
            ],
            'nis'           => [
                'sometimes',
                'string',
                'max:30',
                Rule::unique('students', 'nis')
                    ->where('company_id', $this->user()->company_id)
                    ->ignore($student?->id),
            ],
            'nisn'          => 'nullable|string|max:30',
            'name'          => 'sometimes|string|max:150',
            'gender'        => 'sometimes|in:L,P',
            'birth_place'   => 'nullable|string|max:100',
            'birth_date'    => 'nullable|date|before:today',
            'photo_url'     => 'nullable|string|max:255',
            'address'       => 'nullable|string|max:500',
            'is_boarding'   => 'boolean',
            'is_active'     => 'boolean',
        ];
    }
}
