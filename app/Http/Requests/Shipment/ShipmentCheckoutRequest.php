<?php

namespace App\Http\Requests\Shipment;

use Illuminate\Foundation\Http\FormRequest;

class ShipmentCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'address_id' => ['required', 'integer'],
            'courier_code' => ['required', 'string', 'max:100'],
            'courier_name' => ['required', 'string', 'max:100'],
            'service_code' => ['required', 'string', 'max:100'],
            'service_name' => ['required', 'string', 'max:100'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'address_id.required' => 'Pilih alamat pengiriman.',
            'courier_code.required' => 'Pilih kurir pengiriman.',
            'courier_name.required' => 'Pilih kurir pengiriman.',
            'service_code.required' => 'Pilih layanan pengiriman.',
            'service_name.required' => 'Pilih layanan pengiriman.',
            'shipping_cost.required' => 'Biaya pengiriman wajib ada.',
        ];
    }
}