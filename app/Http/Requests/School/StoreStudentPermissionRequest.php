<?php

namespace App\Http\Requests\School;

use App\Enums\School\PermissionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StoreStudentPermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi "apakah wali ini benar wali dari murid ini" ditangani
        // oleh StudentPermissionPolicy di controller (lebih tepat di sana
        // karena butuh route-model {student}, bukan cuma cek input body).
        return true;
    }

    public function rules(): array
    {
        $maxDaysAhead = config('school.permission_max_days_ahead', 14);

        return [
            'date_permission' => "required|date|after_or_equal:today|before_or_equal:+{$maxDaysAhead} days",
            'type'            => ['required', Rule::enum(PermissionType::class)],
            'reason'          => 'required|string|max:500',
            'attachment'      => [
                'nullable',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (!$value) {
                        return;
                    }

                    if (!Storage::disk('public')->exists($value)) {
                        $fail('File lampiran tidak ditemukan, silakan upload ulang.');
                        return;
                    }

                    $expectedPrefix = 'permission-attachments/' . $this->user()->company_id . '/';
                    if (!str_starts_with($value, $expectedPrefix)) {
                        $fail('File lampiran tidak valid.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        $maxDaysAhead = config('school.permission_max_days_ahead', 14);

        return [
            'date_permission.after_or_equal'  => 'Tanggal izin tidak boleh tanggal yang sudah lewat.',
            'date_permission.before_or_equal' => "Pengajuan izin maksimal {$maxDaysAhead} hari ke depan.",
        ];
    }
}
