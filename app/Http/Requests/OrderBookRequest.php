<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderBookRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'pair' => [
                'string',
                'required',
                'between:6,20',
                'regex:/^\w+_\w+$/i'
            ],
            'amount' => [
                'numeric',
                'required',
                'gt:0',
                'max:1000000',
            ],
        ];
    }
}
