<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === 'operator';
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'contact' => ['nullable', 'string', 'max:255'],
            'information' => ['nullable', 'string', 'max:10000'], 'progress' => ['nullable', 'string', 'max:10000']];
    }
}
