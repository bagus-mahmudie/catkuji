<?php

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TopupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $allowedAmounts = collect(config('gacha.topup_packages', []))
            ->pluck('amount')
            ->map(fn ($amount) => (string) $amount)
            ->all();

        return [
            'amount' => [
                'required',
                'integer',
                Rule::in($allowedAmounts),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Nominal top up wajib dipilih.',
            'amount.integer' => 'Nominal top up tidak valid.',
            'amount.in' => 'Paket top up tidak tersedia.',
        ];
    }
}