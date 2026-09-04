<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi role/context sudah ditangani ContextMiddleware (context:school,admin)
        return true;
    }

    public function rules(): array
    {
        return [
            'class_id'      => [
                'nullable',
                Rule::exists('class_rooms', 'id')->where('company_id', $this->user()->company_id),
            ],
            'nis'           => [
                'required',
                'string',
                'max:30',
                Rule::unique('students', 'nis')->where('company_id', $this->user()->company_id),
            ],
            'nisn'          => 'nullable|string|max:30',
            'name'          => 'required|string|max:150',
            'gender'        => 'required|in:L,P',
            'birth_place'   => 'nullable|string|max:100',
            'birth_date'    => 'nullable|date|before:today',
            'photo_url'     => 'nullable|string|max:255',
            'address'       => 'nullable|string|max:500',
            'is_boarding'   => 'boolean',
            'enrolled_at'   => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'nis.unique' => 'NIS ini sudah dipakai murid lain di sekolah yang sama.',
            'class_id.exists' => 'Kelas tidak ditemukan di sekolah Anda.',
        ];
    }
}
