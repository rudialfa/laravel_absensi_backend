<?php

namespace App\Http\Requests\School;

use App\Models\ClassTeacher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                Rule::exists('users', 'id')
                    ->where('company_id', $this->route('class')->company_id)
                    ->where('role', 'guru'),
            ],
            'role_in_class' => 'required|in:wali_kelas,guru_mapel',
            'subject'       => 'required_if:role_in_class,guru_mapel|nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.exists' => 'User yang dipilih bukan guru di sekolah ini.',
        ];
    }

    /**
     * Cegah duplikasi assignment guru ke kelas yang sama dengan peran yang sama.
     * Perlu dicek manual di sini karena unique constraint DB (class_id, user_id, subject)
     * tidak efektif untuk role wali_kelas — MySQL menganggap NULL != NULL pada subject,
     * jadi 2 baris wali_kelas dengan subject NULL bisa lolos unique constraint.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $class = $this->route('class');

            $exists = ClassTeacher::where('class_id', $class->id)
                ->where('user_id', $this->input('user_id'))
                ->where('role_in_class', $this->input('role_in_class'))
                ->when(
                    $this->input('role_in_class') === 'guru_mapel',
                    fn($q) => $q->where('subject', $this->input('subject')),
                    fn($q) => $q->whereNull('subject')
                )
                ->exists();

            if ($exists) {
                $validator->errors()->add('user_id', 'Guru ini sudah ditugaskan dengan peran yang sama di kelas ini.');
            }
        });
    }
}
