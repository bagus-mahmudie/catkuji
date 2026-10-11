<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpsertAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_phone' => ['required', 'string', 'max:30'],
            'province' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'district' => ['required', 'string', 'max:100'],
            'subdistrict' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:20'],
            'address_line' => ['required', 'string', 'max:500'],
            'address_details' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'recipient_name' => 'nama lengkap',
            'recipient_phone' => 'nomor WhatsApp',
            'province' => 'provinsi',
            'city' => 'kota',
            'district' => 'kecamatan',
            'subdistrict' => 'kelurahan',
            'postal_code' => 'kode pos',
            'address_line' => 'alamat utama',
            'address_details' => 'detail alamat',
            'notes' => 'catatan',
        ];
    }
}