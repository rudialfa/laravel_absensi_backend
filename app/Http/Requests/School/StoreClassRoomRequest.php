<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClassRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                => [
                'required',
                'string',
                'max:50',
                Rule::unique('class_rooms', 'name')
                    ->where('company_id', $this->user()->company_id)
                    ->where('academic_year', $this->input('academic_year')),
            ],
            'grade_level'         => 'required|integer|min:1|max:6',
            'academic_year'       => 'required|string|max:20',
            'homeroom_teacher_id' => [
                'nullable',
                Rule::exists('users', 'id')
                    ->where('company_id', $this->user()->company_id)
                    ->where('role', 'guru'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Nama kelas ini sudah dipakai di tahun ajaran yang sama.',
            'homeroom_teacher_id.exists' => 'User yang dipilih bukan guru di sekolah ini.',
        ];
    }
}
