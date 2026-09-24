<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateTierConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'poin' => ['required', 'integer', 'min:0'],
            'voucher' => ['required', 'integer', 'min:0'],
            'merch' => ['required', 'integer', 'min:0'],
        ];
    }
}
