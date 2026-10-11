<?php

namespace App\Http\Requests\Gacha;

use Illuminate\Foundation\Http\FormRequest;

class DrawRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'draw_count' => ['required', 'integer', 'min:1', 'max:10'],
            'request_idempotency_key' => ['required', 'string', 'max:100'],
        ];
    }
}