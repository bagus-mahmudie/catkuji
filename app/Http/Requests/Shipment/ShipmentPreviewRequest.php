<?php

namespace App\Http\Requests\Shipment;

use Illuminate\Foundation\Http\FormRequest;

class ShipmentPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.gacha_prize_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Pilih minimal 1 hadiah untuk dikirim.',
            'items.array' => 'Format hadiah tidak valid.',
            'items.min' => 'Pilih minimal 1 hadiah untuk dikirim.',
            'items.*.gacha_prize_id.required' => 'Hadiah tidak valid.',
            'items.*.quantity.required' => 'Jumlah hadiah wajib diisi.',
            'items.*.quantity.min' => 'Jumlah hadiah minimal 1.',
        ];
    }
}