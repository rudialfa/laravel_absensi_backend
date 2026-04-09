<?php

namespace App\Http\Requests\Api\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class SelectPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // sudah dijaga middleware auth
    }
 
    public function rules(): array
    {
        return [
            // Slug paket yang dipilih: monthly | biannual | yearly
            'plan_slug'     => ['required', 'string', 'in:monthly,biannual,yearly'],
 
            // Bank untuk VA: bca | mandiri
            'bank'          => ['required', 'string', 'in:bca,mandiri'],
 
            // Kode voucher diskon (opsional)
            'discount_code' => ['nullable', 'string', 'max:50'],
        ];
    }
 
    public function messages(): array
    {
        return [
            'plan_slug.required' => 'Pilih paket terlebih dahulu.',
            'plan_slug.in'       => 'Paket tidak valid. Pilih: monthly, biannual, atau yearly.',
            'bank.required'      => 'Pilih bank untuk pembayaran.',
            'bank.in'            => 'Bank tidak valid. Pilih: bca atau mandiri.',
        ];
    }
}
