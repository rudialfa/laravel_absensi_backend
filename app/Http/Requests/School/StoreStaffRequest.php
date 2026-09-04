<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => 'required|string|max:150',
            'email'    => 'required|email|unique:users,email',
            'phone'    => 'nullable|string|max:20',
            'role'     => 'required|in:guru,wali',
            'password' => 'required|string|min:6',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'    => 'Email ini sudah terdaftar di sistem.',
            'role.in'         => 'Role harus guru atau wali.',
            'password.required' => 'Password wajib diisi saat membuat akun guru/wali.',
        ];
    }
}
