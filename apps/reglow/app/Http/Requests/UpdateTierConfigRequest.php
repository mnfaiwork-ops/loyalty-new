<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'produk' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /** Total bobot 0 membuat undian tidak bisa jalan untuk semua pelanggan. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $total = (int) $this->input('poin') + (int) $this->input('voucher') + (int) $this->input('merch') + (int) $this->input('produk');
                if ($total <= 0) {
                    $validator->errors()->add('poin', 'Total bobot tier harus lebih dari 0.');
                }
            },
        ];
    }
}
