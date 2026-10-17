<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() === null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_.-]+$/', Rule::unique('users')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')],
            'password' => ['required', 'string', 'min:12', 'max:200', 'confirmed'],
            'role' => ['prohibited'],
            'client_id' => ['prohibited'],
            'email_verified_at' => ['prohibited'],
        ];
    }
}
